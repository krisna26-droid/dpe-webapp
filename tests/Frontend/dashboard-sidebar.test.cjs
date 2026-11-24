const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const assets = path.join(__dirname, '../../public/assets/dpe');
const source = fs.readFileSync(path.join(assets, 'dashboard-sidebar.js'), 'utf8');
const baseCss = fs.readFileSync(path.join(assets, 'dashboard.css'), 'utf8');
const responsiveCss = fs.readFileSync(path.join(assets, 'dashboard-responsive.css'), 'utf8');
const teacherCss = fs.readFileSync(path.join(assets, 'teacher-workspace.css'), 'utf8');

function element() {
    const listeners = new Map(), attrs = new Map(), classes = new Set();
    return {
        hidden: false, inert: false, focusCount: 0,
        classList: { contains: c => classes.has(c), toggle(c, on) { if (on) classes.add(c); else classes.delete(c); } },
        addEventListener(name, fn) { listeners.set(name, [...(listeners.get(name) || []), fn]); },
        emit(name, event = {}) { for (const fn of listeners.get(name) || []) fn(event); },
        dispatchEvent(event) { this.emit(event.type, event); },
        setAttribute(name, value) { attrs.set(name, String(value)); },
        getAttribute(name) { return attrs.get(name) ?? null; },
        contains(node) { return node === this; },
        focus() { this.focusCount++; },
    };
}

function page({ mode = 'tablet', identity = 'teacher:session-a', storage = new Map(), blocked = false,
    width = 1180, compact = false, loading = false } = {}) {
    const body = element(), sidebar = element(), toggle = element(), close = element(), overlay = element(), link = element();
    sidebar.setAttribute('data-sidebar-layout-key', identity);
    body.classList.toggle('dpe-teacher-students-page', compact);
    const nodes = { '[data-sidebar]': sidebar, '[data-sidebar-toggle]': toggle, '[data-sidebar-close]': close, '[data-sidebar-overlay]': overlay };
    const document = Object.assign(element(), {
        body, activeElement: null, readyState: loading ? 'loading' : 'complete',
        querySelector: selector => nodes[selector] || null, querySelectorAll: () => [link],
    });
    const window = Object.assign(element(), {
        innerWidth: width, mode,
        getComputedStyle: () => ({ getPropertyValue: name => name === '--dpe-sidebar-mode' ? window.mode : '' }),
    });
    Object.defineProperty(window, 'sessionStorage', {
        get() {
            if (blocked) throw new Error('storage unavailable');
            return { getItem: k => storage.get(k) ?? null, setItem: (k, v) => storage.set(k, v), removeItem: k => storage.delete(k) };
        },
    });
    vm.runInNewContext(source, { document, window, Event: class { constructor(type) { this.type = type; } } });
    function changeMode(value) { window.mode = value; window.emit('resize'); }
    return { body, sidebar, toggle, close, overlay, link, document, window, storage, changeMode,
        collapsed: () => body.classList.contains('is-tablet-sidebar-collapsed'),
        visible: () => sidebar.getAttribute('aria-hidden') === 'false',
    };
}

for (const role of ['superadmin', 'admin', 'teacher', 'student']) {
    test(`${role}: tablet opens by default and keeps the preference on another menu`, () => {
        const identity = `${role}:session-one`;
        const first = page({ identity });
        assert.equal(first.visible(), true);
        assert.equal(first.toggle.getAttribute('aria-expanded'), 'true');
        assert.equal(first.overlay.hidden, true);
        first.link.emit('click');
        assert.equal(first.visible(), true, 'navigation on a tablet must not close its sidebar');
        first.close.emit('click');
        assert.equal(first.collapsed(), true);
        assert.equal(first.visible(), false);
        assert.equal(first.sidebar.inert, true);
        const next = page({ identity, storage: first.storage, compact: role === 'teacher' });
        assert.equal(next.collapsed(), true);
        next.toggle.emit('click');
        assert.equal(next.visible(), true);
        assert.equal(page({ identity, storage: first.storage }).visible(), true);
    });
}

test('a new login and a different account start open, even if the last one was collapsed', () => {
    const first = page({ identity: 'teacher:session-old' });
    first.toggle.emit('click');
    assert.equal(first.collapsed(), true);
    assert.equal(page({ identity: 'teacher:session-new', storage: first.storage }).visible(), true);
    assert.equal(page({ identity: 'student:session-other', storage: first.storage }).visible(), true);
});

test('tablet rotation and crossing desktop / mobile modes do not lose the tablet choice', () => {
    const p = page();
    p.toggle.emit('click');
    p.window.innerWidth = 834;
    p.window.emit('resize');
    assert.equal(p.collapsed(), true);
    p.changeMode('desktop');
    assert.equal(p.visible(), true);
    assert.equal(p.collapsed(), false);
    p.changeMode('drawer');
    assert.equal(p.visible(), false);
    p.toggle.emit('click');
    assert.equal(p.overlay.hidden, false);
    p.changeMode('tablet');
    assert.equal(p.collapsed(), true);
    assert.equal(p.overlay.hidden, true);
});

test('mobile stays a closed overlay drawer, closes on menu selection and Escape', () => {
    const p = page({ mode: 'drawer', width: 390 });
    assert.equal(p.visible(), false);
    p.toggle.emit('click');
    assert.equal(p.visible(), true);
    assert.equal(p.overlay.hidden, false);
    p.link.emit('click');
    assert.equal(p.visible(), false);
    p.toggle.emit('click');
    p.document.emit('keydown', { key: 'Escape' });
    assert.equal(p.overlay.hidden, true);
    assert.equal(p.storage.size, 0);
});

test('desktop does not collapse; the old compact non-touch roster stays a drawer', () => {
    const p = page({ mode: 'desktop', width: 1366 });
    p.toggle.emit('click');
    p.document.emit('keydown', { key: 'Escape' });
    assert.equal(p.visible(), true);
    assert.equal(page({ mode: '', width: 1180, compact: true }).visible(), false);
    assert.equal(page({ mode: '', width: 1180 }).visible(), true);
});

test('tablet preference applies before main content and works without storage', () => {
    const first = page({ loading: true, storage: new Map([['dpe.sidebar-layout.v1.teacher:session-a', 'collapsed']]) });
    assert.equal(first.collapsed(), true);
    first.document.emit('DOMContentLoaded');
    assert.equal(first.toggle.getAttribute('aria-expanded'), 'false');
    const blocked = page({ blocked: true });
    blocked.toggle.emit('click');
    assert.equal(blocked.collapsed(), true);
    blocked.toggle.emit('click');
    assert.equal(blocked.visible(), true);
});

test('the shared stylesheet connects the CSS mode, collapsed state and table scroll safeguards', () => {
    assert.match(responsiveCss, /@media[^\{]*min-width:\s*600px[^\{]*max-width:\s*1440px[^\{]*any-pointer:\s*coarse/);
    assert.match(responsiveCss, /--dpe-sidebar-mode:\s*tablet/);
    assert.match(responsiveCss, /\.is-tablet-sidebar-collapsed\s+\.dpe-dashboard-sidebar\s*\{\s*display:\s*none/);
    assert.match(baseCss, /\.dpe-dashboard-table-wrap\s*\{[^}]*max-width:\s*100%[^}]*overflow-x:\s*auto/);
    assert.match(responsiveCss, /\.dpe-dashboard-table[^\{]*\.dpe-dashboard-button\s*\{[^}]*min-width:\s*max-content[^}]*white-space:\s*nowrap[^}]*overflow-wrap:\s*normal/);
    assert.match(teacherCss, /\.dpe-teacher-students-table\s*\{[^}]*min-width:\s*1080px/);
});
