const { test } = require('node:test');
const assert = require('node:assert/strict');
const { fixture, response, contextResponse, current, snapshot, origin, validFile } = require('./helpers/payment-dom.cjs');

test('Cancel preserves image and never requests context or saves a proof', async () => {
    const f = fixture(); f.form.emit('submit');
    assert.equal(f.nodes['[data-dashboard-confirm]'].hidden, false);
    f.nodes['[data-confirm-cancel]'].emit('click'); await f.settle();
    assert.equal(f.calls.length, 0); assert.equal(f.form.nativeSubmissions, undefined);
    assert.equal(f.image.files[0].name, 'bukti.png');
});
test('Confirmation posts original image with fresh CSRF and the reviewed snapshot, then returns to payment', async () => {
    const f = fixture(); const file = f.image.files[0]; await f.confirm();
    assert.equal(f.calls.length, 2);
    const posted = f.calls[1].options;
    assert.equal(posted.body.get('image').name, file.name);
    assert.deepEqual(await posted.body.get('image').arrayBuffer(), await file.arrayBuffer());
    assert.equal(posted.body.get('_token'), 'session-current');
    assert.equal(posted.headers['X-CSRF-TOKEN'], 'session-current');
    assert.equal(posted.body.get('expected_snapshot'), snapshot);
    assert.equal(posted.credentials, 'same-origin');
    assert.equal(posted.headers['Content-Type'], undefined, 'Browser must set multipart boundary');
    assert.deepEqual(f.redirects, [origin + '/student/payments/charge-1']);
    assert.equal(f.form.nativeSubmissions, undefined);
});
test('Rapid confirmations issue one upload while the context request is pending', async () => {
    let resolve; const pending = new Promise(done => { resolve = done; });
    const f = fixture([() => pending, response(201)]);
    f.form.emit('submit'); f.nodes['[data-confirm-submit]'].emit('click');
    f.nodes['[data-confirm-submit]'].emit('click'); f.form.emit('dpe:confirmed-submit');
    assert.equal(f.calls.length, 1); resolve(contextResponse()); await f.settle();
    assert.equal(f.calls.length, 2);
});
test('Changed version does not replace the reviewed snapshot or post the selected image', async () => {
    const f = fixture([response(200, { ...current, expected_snapshot: 'b'.repeat(64) })]); await f.confirm();
    assert.equal(f.calls.length, 1); assert.equal(f.version.value, snapshot);
    assert.match(f.nodes['[data-payment-message]'].textContent, /Data pembayaran berubah/);
    assert.equal(f.image.disabled, false); assert.equal(f.button.disabled, false); assert.equal(f.image.files.length, 1);
});
test('Pending payment context prevents a new upload', async () => {
    const f = fixture([response(200, { ...current, can_upload: false })]); await f.confirm();
    assert.equal(f.calls.length, 1); assert.equal(f.redirects.length, 0);
});
for (const [status, text] of [[401, /Sesi sudah berakhir/], [403, /Akun tidak dapat mengakses/], [404, /Akun tidak dapat mengakses/]]) {
    test(`Context ${status} keeps the form and does not post to another account`, async () => {
        const f = fixture([response(status)]); await f.confirm();
        assert.equal(f.calls.length, 1); assert.match(f.nodes['[data-payment-message]'].textContent, text);
        assert.equal(f.redirects.length, 0); assert.equal(f.image.files.length, 1);
    });
}
for (const [status, data, text] of [[419, {}, /Sesi sudah berakhir/], [413, {}, /batas unggah server/], [422, { errors: { image: ['Gambar tidak valid <script>'] } }, /Gambar tidak valid <script>/], [500, {}, /Periksa riwayat pembayaran/]]) {
    test(`Upload ${status} displays a safe message, retains the image and never navigates to an error page`, async () => {
        const f = fixture([contextResponse(), response(status, data)]); await f.confirm();
        assert.match(f.nodes['[data-payment-message]'].textContent, text);
        assert.equal(f.redirects.length, 0); assert.equal(f.image.files[0].name, 'bukti.png');
        assert.equal(f.button.disabled, false); assert.equal(f.nodes['[data-payment-feedback]'].hidden, false);
    });
}
test('Network failure does not automatically resend an upload with uncertain outcome', async () => {
    const f = fixture([contextResponse(), new TypeError('Failed to fetch')]); await f.confirm();
    assert.equal(f.calls.length, 2); assert.equal(f.redirects.length, 0);
    assert.match(f.nodes['[data-payment-message]'].textContent, /Periksa riwayat pembayaran/);
});
test('Unexpected HTML success is not treated as a saved proof', async () => {
    const f = fixture([contextResponse(), response(200, {}, false)]); await f.confirm();
    assert.equal(f.redirects.length, 0); assert.match(f.nodes['[data-payment-message]'].textContent, /belum dapat dipastikan/);
});
test('Files larger than 5 MB cannot open the confirmation or issue a request', async () => {
    const f = fixture(); f.image.files = [new File([Buffer.alloc(5 * 1024 * 1024 + 1)], 'large.png', { type: 'image/png' })];
    f.form.emit('submit'); await f.settle();
    assert.equal(f.calls.length, 0); assert.equal(f.nodes['[data-dashboard-confirm]'].hidden, true);
    assert.match(f.image.validation, /maksimal 5 MB/);
});
test('Unsupported type is rejected before confirmation; selecting a valid image clears the error', () => {
    const f = fixture(); f.image.files = [new File(['pdf'], 'bukti.pdf', { type: 'application/pdf' })]; f.form.emit('submit');
    assert.match(f.image.validation, /JPG, PNG, atau WEBP/);
    f.image.files = [validFile()]; f.image.emit('change'); assert.equal(f.image.validation, '');
});
test('An explicit retry requests a new token and preserves the existing image', async () => {
    const f = fixture([contextResponse(), response(419), response(200, { ...current, csrf_token: 'session-new' }), response(201)]);
    const file = f.image.files[0]; await f.confirm(); assert.equal(f.calls.length, 2);
    await f.confirm(); assert.equal(f.calls.length, 4);
    assert.equal(f.calls[3].options.body.get('_token'), 'session-new'); assert.equal(f.calls[3].options.body.get('image').name, file.name);
});
test('Other dashboard confirmation forms still use their own native form after confirmation', () => {
    const f = fixture(); f.ordinary.emit('submit'); f.nodes['[data-confirm-submit]'].emit('click');
    assert.equal(f.ordinary.nativeSubmissions, 1); assert.equal(f.calls.length, 0);
    assert.equal(f.ordinary.nativePayload.get('_token'), 'ordinary-token');
    assert.equal(f.ordinary.nativePayload.get('_method'), 'DELETE');
    assert.equal(f.form.querySelector('input[name="image"]'), f.image);
    f.ordinary.emit('submit'); assert.equal(f.nodes['[data-confirm-submit]'].disabled, false);
});
test('A context URL on another origin is rejected without transmitting the image', async () => {
    const f = fixture(); f.form.dataset.paymentContextUrl = 'https://example.com/context'; await f.confirm();
    assert.equal(f.calls.length, 0); assert.equal(f.redirects.length, 0);
    assert.match(f.nodes['[data-payment-message]'].textContent, /Alamat pembayaran tidak sesuai/);
});

test('Real Blade attribute collisions never replace the form when the popup is opened', () => {
    const f = fixture();
    assert.equal(f.document.querySelector('[data-confirm-title]'), f.form);
    assert.equal(f.document.querySelector('[data-confirm-message]'), f.form);
    const fields = f.form.querySelectorAll('input');
    f.form.emit('submit');
    assert.deepEqual(f.form.querySelectorAll('input'), fields);
    assert.equal(f.form.querySelector('input[name="image"]'), f.image);
    assert.equal(f.form.querySelector('input[name="_token"]'), f.token);
    assert.equal(f.form.querySelector('input[name="expected_snapshot"]'), f.version);
    assert.equal(f.nodes['[data-confirm-title]'].textContent, f.form.dataset.confirmTitle);
    assert.equal(f.nodes['[data-confirm-message]'].textContent, f.form.dataset.confirmMessage);
});

test('Cancel and reopen preserve the same file input and selected image', () => {
    const f = fixture(); const file = f.image.files[0];
    f.form.emit('submit'); f.nodes['[data-confirm-cancel]'].emit('click'); f.form.emit('submit');
    assert.equal(f.form.querySelector('input[name="image"]'), f.image);
    assert.equal(f.image.files[0], file);
    assert.equal(f.form.querySelectorAll('input').length, 3);
});

test('Native multipart serialization contains the image bytes, filename, token and reviewed version', async () => {
    const f = fixture(); const file = f.image.files[0]; await f.confirm();
    const request = new Request(f.calls[1].url, f.calls[1].options);
    assert.match(request.headers.get('Content-Type'), /^multipart\/form-data; boundary=/);
    const bytes = Buffer.from(await request.arrayBuffer());
    for (const expected of ['name="image"; filename="bukti.png"', 'Content-Type: image/png', 'name="_token"', 'session-current', 'name="expected_snapshot"', snapshot]) {
        assert.ok(bytes.includes(Buffer.from(expected)), expected);
    }
    assert.ok(bytes.includes(Buffer.from(await file.arrayBuffer())));
});
