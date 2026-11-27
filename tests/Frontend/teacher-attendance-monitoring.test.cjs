const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');

const source = readFileSync(join(__dirname, '../../public/assets/dpe/teacher-attendance-monitoring.js'), 'utf8');

function monitoringPage(href, scrollY = 0) {
    const handlers = {};
    const scrolls = [];
    const replacements = [];
    const document = {
        addEventListener(name, handler) { handlers[name] = handler; },
    };
    const window = {
        location: new URL(href),
        scrollY,
        history: {
            state: { monitoring: true },
            replaceState(state, title, url) { replacements.push({ state, url }); },
        },
        addEventListener(name, handler) { handlers[name] = handler; },
        requestAnimationFrame(callback) { callback(); },
        scrollTo(options) { scrolls.push({ ...options }); },
    };
    runInNewContext(source, { document, window, URL });

    return { handlers, scrolls, replacements };
}

test('opening an attendance detail saves the list position alongside its filters', () => {
    const { handlers } = monitoringPage('https://example.test/admin/teacher-attendance?page=2', 1820.3);
    const link = { href: 'https://example.test/admin/teacher-attendance/record?monitoring%5Bpage%5D=2' };
    handlers.click({ target: { closest: () => link }, button: 0 });

    const detail = new URL(link.href);
    assert.equal(detail.searchParams.get('monitoring[page]'), '2');
    assert.equal(detail.searchParams.get('monitoring[scroll_y]'), '1820');
});

test('returning restores the position and consumes the fragment while keeping the query', () => {
    const href = 'https://example.test/admin/teacher-attendance?page=2&status=approved';
    const { handlers, scrolls, replacements } = monitoringPage(href + '#attendance-scroll-1820');
    handlers.pageshow();

    assert.deepEqual(scrolls, [{ top: 1820, behavior: 'instant' }]);
    assert.deepEqual(replacements, [{ state: { monitoring: true }, url: href }]);
});

test('normal monitoring visits keep the browser scroll position', () => {
    const { handlers, scrolls, replacements } = monitoringPage('https://example.test/admin/teacher-attendance?page=2');
    handlers.pageshow();

    assert.deepEqual(scrolls, []);
    assert.deepEqual(replacements, []);
});
