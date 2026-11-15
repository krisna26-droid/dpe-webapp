const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Exercise the shipped browser script with asynchronous HTTP responses, without dependencies.
class Control extends EventTarget {
    constructor() { super(); this.disabled = false; this.hidden = false; this.attributes = {}; this.textContent = ''; }
    setAttribute(name, value) { this.attributes[name] = value; }
}
class Select extends Control {
    constructor(rows) { super(); this.options = rows; this._value = rows[0]?.value ?? ''; }
    get value() { return this._value; }
    set value(value) { this._value = this.options.some((o) => o.value === value) ? value : ''; }
    replaceChildren(...options) { this.options = options; this._value = options[0]?.value ?? ''; }
    add(option) { this.options.push(option); }
}
const option = (label, value) => ({ label, value });
const tick = () => new Promise((resolve) => setImmediate(resolve));
function setup() {
    const teacher = new Select([option('Semua', ''), option('Guru A', 'teacher-a'), option('Guru B', 'teacher-b')]);
    const student = new Select([option('Pilih siswa', ''), option('Siswa A', 'student-a')]); student.value = 'student-a';
    const month = new Control(); month.value = '2026-11-01'; month.validity = { valid: true };
    const hint = new Control(); const retry = new Control(); retry.hidden = true; const submit = new Control();
    const fields = { '[name="teacher_filter_id"]': teacher, '[name="student_id"]': student,
        '[name="charge_month"]': month, '#charge-student-help': hint, '[data-charge-retry]': retry, '[data-charge-submit]': submit };
    const form = { dataset: { optionsUrl: '/admin/monthly-charge-student-options', hasBranches: '1' }, querySelector: (s) => fields[s] };
    const requests = [];
    const context = { document: { querySelectorAll: () => [form] }, window: { location: { href: 'https://example.test/admin/monthly-charges/create' } },
        URL, AbortController, Option: function (label, value) { return option(label, value); },
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })) };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/dpe/monthly-charge-filter.js'), 'utf8'), context);
    const respond = (index, students, teachers = [{ id: 'teacher-a', label: 'Guru A' }, { id: 'teacher-b', label: 'Guru B' }]) => {
        requests[index].resolve({ ok: true, redirected: false, json: async () => ({ students, teachers }) });
    };
    return { teacher, student, month, hint, retry, submit, requests, respond };
}

test('changing teacher clears the selected student and loads the chosen teacher only', async () => {
    const ui = setup(); ui.teacher.value = 'teacher-b'; ui.teacher.dispatchEvent(new Event('change'));
    assert.equal(ui.student.value, ''); assert.equal(ui.student.disabled, true); assert.equal(ui.submit.disabled, true);
    assert.equal(ui.requests[0].url.searchParams.get('teacher_filter_id'), 'teacher-b');
    assert.equal(ui.requests[0].url.searchParams.get('charge_month'), '2026-11-01');
    ui.respond(0, [{ id: 'student-b', label: 'Siswa B' }]); await tick();
    assert.deepEqual(ui.student.options.map((o) => o.value), ['', 'student-b']);
    assert.equal(ui.student.value, ''); assert.equal(ui.student.disabled, false); assert.equal(ui.submit.disabled, false);
    assert.equal(ui.teacher.value, 'teacher-b');
});

test('a slow response for the previous teacher cannot replace the latest results', async () => {
    const ui = setup(); ui.teacher.value = 'teacher-a'; ui.teacher.dispatchEvent(new Event('change'));
    ui.teacher.value = 'teacher-b'; ui.teacher.dispatchEvent(new Event('change'));
    assert.equal(ui.requests[0].options.signal.aborted, true);
    ui.respond(1, [{ id: 'student-b', label: 'Siswa B' }]); await tick();
    ui.respond(0, [{ id: 'student-a', label: 'Siswa A' }]); await tick();
    assert.deepEqual(ui.student.options.map((o) => o.value), ['', 'student-b']);
    assert.equal(ui.teacher.value, 'teacher-b');
});

test('loading errors keep submission blocked and a retry can recover', async () => {
    const ui = setup(); ui.teacher.value = 'teacher-b'; ui.teacher.dispatchEvent(new Event('change'));
    ui.requests[0].reject(new Error('Network unavailable')); await tick();
    assert.equal(ui.student.value, ''); assert.equal(ui.student.disabled, true); assert.equal(ui.submit.disabled, true);
    assert.equal(ui.retry.hidden, false); assert.match(ui.hint.textContent, /Coba lagi/);
    ui.retry.dispatchEvent(new Event('click')); ui.respond(1, [{ id: 'student-b', label: 'Siswa B' }]); await tick();
    assert.equal(ui.retry.hidden, true); assert.equal(ui.student.disabled, false); assert.equal(ui.submit.disabled, false);
});

test('changing billing month resets both choices and refreshes available teachers', async () => {
    const ui = setup(); ui.teacher.value = 'teacher-a'; ui.month.value = '2026-12-01'; ui.month.dispatchEvent(new Event('change'));
    assert.equal(ui.teacher.value, ''); assert.equal(ui.student.value, '');
    assert.equal(ui.requests[0].url.searchParams.get('teacher_filter_id'), null);
    assert.equal(ui.requests[0].url.searchParams.get('charge_month'), '2026-12-01');
    ui.respond(0, [{ id: 'student-c', label: 'Siswa C' }], [{ id: 'teacher-c', label: 'Guru C' }]); await tick();
    assert.deepEqual(ui.teacher.options.map((o) => o.value), ['', 'teacher-c']);
});

test('an empty roster is explicit and selecting all teachers can restore the student list', async () => {
    const ui = setup(); ui.teacher.value = 'teacher-b'; ui.teacher.dispatchEvent(new Event('change'));
    ui.respond(0, []); await tick();
    assert.equal(ui.student.disabled, true); assert.equal(ui.submit.disabled, true); assert.match(ui.hint.textContent, /Tidak ada siswa/);
    ui.teacher.value = ''; ui.teacher.dispatchEvent(new Event('change'));
    ui.respond(1, [{ id: 'student-a', label: 'Siswa A' }]); await tick();
    assert.equal(ui.student.disabled, false); assert.equal(ui.student.value, '');
});

test('invalid month input clears stale choices without requesting an invalid roster', async () => {
    const ui = setup(); ui.month.value = ''; ui.month.validity.valid = false; ui.month.dispatchEvent(new Event('change'));
    assert.equal(ui.requests.length, 0); assert.equal(ui.student.value, ''); assert.equal(ui.submit.disabled, true);
    assert.equal(ui.student.attributes['aria-busy'], 'false');
});
