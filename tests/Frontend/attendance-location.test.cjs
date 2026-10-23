const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/dpe/attendance-location.js'), 'utf8');
const fallback = 'Alamat belum tersedia. Titik lokasi dapat dilihat di peta.';
const flush = () => new Promise(resolve => setImmediate(resolve));

function location(url = '/admin/teacher-attendance/attendance-a/location/check-in') {
    const address = { textContent: fallback };
    Object.defineProperty(address, 'innerHTML', { set() { throw new Error('Unsafe HTML write'); } });
    const credit = { hidden: true };
    const map = { href: 'https://www.google.com/maps/search/?api=1&query=-8.65%2C115.21' };
    return {
        dataset: { locationUrl: url }, isConnected: true, address, credit, map,
        querySelector: selector => selector === '[data-location-address]' ? address : selector === '[data-location-credit]' ? credit : null,
    };
}

function page({ locations = [location()], fetcher, loading = false, unsupported = '' } = {}) {
    const calls = [];
    const timers = new Map();
    const events = new Map();
    let nextTimer = 1;
    class AbortController {
        constructor() { this.signal = { aborted: false }; }
        abort() { this.signal.aborted = true; this.signal.onabort?.(); }
    }
    const window = {
        location: { href: 'https://dpe.test/admin/teacher-attendance/attendance-a', origin: 'https://dpe.test' },
        AbortController,
        fetch: async (url, options) => {
            calls.push({ url, options });
            return fetcher ? fetcher(url, options, calls.length) : { ok: true, json: async () => ({ status: 'resolved', address: 'Jalan Contoh, Denpasar' }) };
        },
        setTimeout(callback) { const id = nextTimer++; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    if (unsupported === 'fetch') delete window.fetch;
    if (unsupported === 'abort') delete window.AbortController;
    const document = {
        readyState: loading ? 'loading' : 'complete',
        querySelectorAll: () => locations,
        addEventListener: (event, callback) => events.set(event, callback),
    };
    const context = { document, window, URL };
    vm.runInNewContext(source, context);
    return { calls, timers, events, locations, window, rerun: () => vm.runInNewContext(source, context) };
}

test('two locations resolve sequentially, retain their own address, and keep map links', async () => {
    let release;
    const locations = [location(), location('/admin/teacher-attendance/attendance-a/location/check-out')];
    const h = page({ locations, fetcher: (_url, _options, count) => count === 1
        ? new Promise(resolve => { release = resolve; })
        : { ok: true, json: async () => ({ status: 'resolved', address: 'Jalan Selesai' }) } });
    assert.equal(h.calls.length, 1);
    assert.equal(locations[0].address.textContent, 'Memuat alamat…');
    assert.equal(locations[1].address.textContent, fallback);
    release({ ok: true, json: async () => ({ status: 'resolved', address: 'Jalan Masuk' }) });
    await flush();
    assert.equal(h.calls.length, 2);
    assert.equal(locations[0].address.textContent, 'Alamat perkiraan: Jalan Masuk');
    assert.equal(locations[1].address.textContent, 'Alamat perkiraan: Jalan Selesai');
    assert.ok(locations.every(item => !item.credit.hidden));
    assert.ok(locations.every(item => item.map.href.endsWith('query=-8.65%2C115.21')));
    assert.equal(h.calls[0].options.credentials, 'same-origin');
    assert.equal(h.calls[0].options.cache, 'no-store');
    assert.equal(h.calls[0].options.redirect, 'error');
    assert.equal(h.timers.size, 0);
});

test('provider text is displayed as text even when it contains markup', async () => {
    const h = page({ fetcher: () => ({ ok: true, json: async () => ({ status: 'resolved', address: '<img src=x onerror=alert(1)>' }) }) });
    await flush();
    assert.equal(h.locations[0].address.textContent, 'Alamat perkiraan: <img src=x onerror=alert(1)>');
    assert.ok(h.locations[0].map.href.startsWith('https://www.google.com/maps/'));
});

for (const result of [null, {}, { status: 'unavailable', address: null }, { status: 'resolved', address: '' },
    { status: 'resolved', address: '   ' }, { status: 'resolved', address: {} }, { status: 'resolved', address: 'a'.repeat(601) }]) {
    test(`invalid or unavailable address keeps fallback and map: ${JSON.stringify(result).slice(0, 80)}`, async () => {
        const h = page({ fetcher: () => ({ ok: true, json: async () => result }) });
        await flush();
        assert.equal(h.locations[0].address.textContent, fallback);
        assert.equal(h.locations[0].credit.hidden, true);
        assert.ok(h.locations[0].map.href);
        assert.equal(h.timers.size, 0);
    });
}

for (const status of [401, 403, 429, 500]) {
    test(`HTTP ${status} leaves the map usable and does not attempt JSON parsing`, async () => {
        const h = page({ fetcher: () => ({ ok: false, status, json() { assert.fail('Must not parse this response'); } }) });
        await flush();
        assert.equal(h.locations[0].address.textContent, fallback);
        assert.ok(h.locations[0].map.href);
        assert.equal(h.calls.length, 1);
        assert.equal(h.timers.size, 0);
    });
}

for (const failure of ['network', 'json']) {
    test(`${failure} error falls back without retrying`, async () => {
        const h = page({ fetcher: () => {
            if (failure === 'network') throw new Error('Offline');
            return { ok: true, json: async () => { throw new Error('Invalid JSON'); } };
        } });
        await flush();
        assert.equal(h.locations[0].address.textContent, fallback);
        assert.equal(h.calls.length, 1);
        assert.equal(h.timers.size, 0);
    });
}

test('a hung request is aborted and restores the fallback', async () => {
    const h = page({ fetcher: (_url, options) => new Promise((_resolve, reject) => {
        options.signal.onabort = () => reject(new Error('Aborted'));
    }) });
    h.timers.values().next().value();
    await flush();
    assert.equal(h.calls[0].options.signal.aborted, true);
    assert.equal(h.locations[0].address.textContent, fallback);
    assert.equal(h.timers.size, 0);
});

for (const url of ['', 'https://untrusted.test/location', 'http://[bad-url']) {
    test(`invalid or cross-origin lookup URL is not fetched: ${url}`, async () => {
        const h = page({ locations: [location(url)] });
        await flush();
        assert.equal(h.calls.length, 0);
        assert.equal(h.locations[0].address.textContent, fallback);
    });
}

for (const unsupported of ['fetch', 'abort']) {
    test(`browser without ${unsupported} keeps the server-rendered coordinates and map`, async () => {
        const h = page({ unsupported });
        await flush();
        assert.equal(h.calls.length, 0);
        assert.equal(h.locations[0].address.textContent, fallback);
        assert.ok(h.locations[0].map.href);
    });
}

test('loading DOM initializes once and repeated scripts do not duplicate requests', async () => {
    const h = page({ loading: true });
    assert.equal(h.calls.length, 0);
    h.events.get('DOMContentLoaded')();
    await flush();
    h.rerun();
    h.events.get('DOMContentLoaded')();
    await flush();
    assert.equal(h.calls.length, 1);
});

for (const change of ['disconnected', 'url changed']) {
    test(`late response does not overwrite a different location: ${change}`, async () => {
        let release;
        const h = page({ fetcher: () => new Promise(resolve => { release = resolve; }) });
        if (change === 'disconnected') h.locations[0].isConnected = false;
        else h.locations[0].dataset.locationUrl += '?changed=1';
        h.locations[0].address.textContent = 'Alamat lokasi lain';
        release({ ok: true, json: async () => ({ status: 'resolved', address: 'Alamat lama' }) });
        await flush();
        assert.equal(h.locations[0].address.textContent, 'Alamat lokasi lain');
        assert.equal(h.locations[0].credit.hidden, true);
        assert.equal(h.timers.size, 0);
    });
}

test('a manual or unfinished attendance page without captured points does not fetch', async () => {
    const h = page({ locations: [] });
    await flush();
    assert.equal(h.calls.length, 0);
});
