const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/dpe/sidebar-scroll.js'), 'utf8');
const prefix = 'dpe.sidebar-scroll.v1.';

function emitter() {
    const listeners = new Map();
    return {
        addEventListener(name, callback, options) {
            const entries = listeners.get(name) || [];
            entries.push({ callback, options });
            listeners.set(name, entries);
        },
        emit(name, event = {}) {
            for (const { callback } of listeners.get(name) || []) callback(event);
            return event;
        },
        listeners,
    };
}

function page({ owner = 'superadmin:user-a', storage = new Map(), width = 1440,
    top = 0, height = 400, contentHeight = 1600, noSidebar = false,
    blocked = '', loading = false } = {}) {
    const writes = [];
    const sidebar = Object.assign(emitter(), {
        scrollTop: top, clientHeight: height, scrollHeight: contentHeight,
        getAttribute: name => name === 'data-sidebar-scroll-key' ? owner : null,
    });
    const document = Object.assign(emitter(), {
        readyState: loading ? 'loading' : 'complete', visibilityState: 'visible',
        querySelector: () => noSidebar ? null : sidebar,
    });
    const window = Object.assign(emitter(), { innerWidth: width, scrollY: 470 });
    const sessionStorage = {
        getItem(key) {
            if (blocked === 'read') throw new Error('Read blocked');
            return storage.has(key) ? storage.get(key) : null;
        },
        setItem(key, value) {
            if (blocked === 'write') throw new Error('Write blocked');
            storage.set(key, value);
            writes.push([key, value]);
        },
    };
    Object.defineProperty(window, 'sessionStorage', {
        get() {
            if (blocked === 'access') throw new Error('Storage blocked');
            return sessionStorage;
        },
    });
    vm.runInNewContext(source, { document, window });
    return { sidebar, document, window, storage, writes, key: prefix + owner };
}

for (const role of ['superadmin', 'admin', 'teacher', 'student']) {
    for (const width of [390, 1440]) {
        test(`${role} preserves the scrolled menu across navigation at width ${width}`, () => {
            const storage = new Map();
            const first = page({ owner: `${role}:user-a`, storage, width });
            first.sidebar.scrollTop = 620;
            first.sidebar.emit('scroll');
            // A final movement may happen before a scroll event has been delivered.
            first.sidebar.scrollTop = 710;
            const click = { defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
            first.sidebar.emit('click', click);
            assert.equal(click.defaultPrevented, false);
            const next = page({ owner: `${role}:user-a`, storage, width });
            assert.equal(next.sidebar.scrollTop, 710);
            assert.equal(next.window.scrollY, 470);
            assert.equal(first.sidebar.listeners.get('click')[0].options, true);
            assert.equal(first.sidebar.listeners.get('scroll')[0].options.passive, true);
        });
    }
}

test('returning to the top replaces a previously saved lower position', () => {
    const storage = new Map([[prefix + 'superadmin:user-a', '640']]);
    const first = page({ storage });
    assert.equal(first.sidebar.scrollTop, 640);
    first.sidebar.scrollTop = 0;
    first.sidebar.emit('scroll');
    assert.equal(page({ storage }).sidebar.scrollTop, 0);
});

test('accounts and role identities do not read or overwrite each other', () => {
    const storage = new Map([[prefix + 'superadmin:user-a', '500'], [prefix + 'admin:user-a', '200']]);
    assert.equal(page({ owner: 'superadmin:user-a', storage }).sidebar.scrollTop, 500);
    assert.equal(page({ owner: 'admin:user-a', storage }).sidebar.scrollTop, 200);
    const other = page({ owner: 'superadmin:user-b', storage });
    assert.equal(other.sidebar.scrollTop, 0);
    other.sidebar.scrollTop = 370;
    other.sidebar.emit('scroll');
    assert.equal(storage.get(prefix + 'superadmin:user-a'), '500');
    assert.equal(storage.get(prefix + 'admin:user-a'), '200');
});

test('a separate tab storage keeps an independent sidebar position', () => {
    const first = page();
    first.sidebar.scrollTop = 530;
    first.sidebar.emit('scroll');
    assert.equal(page().sidebar.scrollTop, 0);
    assert.equal(page({ storage: first.storage }).sidebar.scrollTop, 530);
});

test('pagehide saves the latest position for navigation outside the sidebar', () => {
    const first = page();
    first.sidebar.scrollTop = 475;
    first.window.emit('pagehide');
    assert.equal(page({ storage: first.storage }).sidebar.scrollTop, 475);
});

test('pageshow restores the latest saved position after back or forward navigation', () => {
    const first = page();
    first.storage.set(first.key, '585');
    first.sidebar.scrollTop = 0;
    first.window.emit('pageshow', { persisted: true });
    assert.equal(first.sidebar.scrollTop, 585);
});

test('hiding the tab saves while becoming visible does not overwrite state', () => {
    const first = page();
    first.sidebar.scrollTop = 315;
    first.document.emit('visibilitychange');
    assert.equal(first.writes.length, 0);
    first.document.visibilityState = 'hidden';
    first.document.emit('visibilitychange');
    assert.equal(first.storage.get(first.key), '315');
});

test('a shorter sidebar viewport clamps the saved position to its available scroll range', () => {
    const storage = new Map([[prefix + 'superadmin:user-a', '10000']]);
    assert.equal(page({ storage, height: 500, contentHeight: 900 }).sidebar.scrollTop, 400);
    assert.equal(page({ storage, height: 900, contentHeight: 500 }).sidebar.scrollTop, 0);
});

test('fractional positions remain valid when the browser uses fractional pixels', () => {
    const storage = new Map([[prefix + 'superadmin:user-a', '501.5']]);
    assert.equal(page({ storage }).sidebar.scrollTop, 501.5);
});

test('missing or invalid saved values do not move the sidebar or interrupt navigation', () => {
    for (const value of [null, '', '   ', 'bad', '-10', 'NaN', 'Infinity', '-Infinity', '{"top":5}']) {
        const storage = new Map();
        if (value !== null) storage.set(prefix + 'superadmin:user-a', value);
        assert.equal(page({ storage, top: 25 }).sidebar.scrollTop, 25);
    }
});

for (const blocked of ['access', 'read', 'write']) {
    test(`storage ${blocked} errors do not break sidebar navigation`, () => {
        const first = page({ blocked });
        assert.doesNotThrow(() => {
            first.sidebar.emit('scroll');
            first.sidebar.emit('click');
            first.window.emit('pagehide');
            first.window.emit('pageshow');
        });
    });
}

test('pages without a sidebar or its account identity are left alone', () => {
    for (const options of [{ noSidebar: true }, { owner: '' }]) {
        const first = page(options);
        assert.equal(first.window.listeners.size, 0);
        assert.equal(first.sidebar.listeners.size, 0);
        assert.equal(first.writes.length, 0);
    }
});

test('a fully hidden sidebar does not replace its saved position with zero', () => {
    const storage = new Map([[prefix + 'superadmin:user-a', '450']]);
    const first = page({ storage, height: 0, contentHeight: 0 });
    first.sidebar.emit('scroll');
    first.window.emit('pagehide');
    assert.equal(storage.get(first.key), '450');
});

test('the position restores immediately while the rest of the document is still loading', () => {
    const storage = new Map([[prefix + 'superadmin:user-a', '650']]);
    assert.equal(page({ storage, loading: true }).sidebar.scrollTop, 650);
});
