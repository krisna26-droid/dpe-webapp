const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/dpe/report-whatsapp.js'), 'utf8');

function page({ secure = true, clipboard, disabled = false } = {}) {
    const field = { value: 'Pesan untuk Dina\n\nRapor: https://dpe.test/rapor/é', focus() { this.focused = true; }, select() { this.selected = true; } };
    const status = { textContent: '' };
    const button = { disabled, dataset: { waCopy: 'wa-message' }, addEventListener(type, handler) { this.handler = handler; } };
    vm.runInNewContext(source, { window: { isSecureContext: secure }, navigator: { clipboard },
        document: { querySelectorAll() { return [button]; }, getElementById() { return field; }, querySelector() { return status; } } });
    return { field, status, click: () => button.handler() };
}

test('copy preserves the exact multi-paragraph template and report URL', async () => {
    let copied;
    const p = page({ clipboard: { async writeText(text) { copied = text; } } });
    await p.click();
    assert.equal(copied, p.field.value);
    assert.match(p.status.textContent, /berhasil disalin/);
});
test('clipboard rejection selects the original message for manual copy', async () => {
    const p = page({ clipboard: { async writeText() { throw new Error('denied'); } } });
    await p.click();
    assert.equal(p.field.focused, true);
    assert.equal(p.field.selected, true);
    assert.match(p.status.textContent, /Ctrl\+C/);
});
test('HTTP without clipboard support still lets the teacher select and copy', async () => {
    const p = page({ secure: false });
    await p.click();
    assert.equal(p.field.selected, true);
});
test('disabled sharing never writes to the clipboard', async () => {
    let calls = 0;
    const p = page({ disabled: true, clipboard: { async writeText() { calls++; } } });
    await p.click();
    assert.equal(calls, 0);
    assert.equal(p.status.textContent, '');
});
