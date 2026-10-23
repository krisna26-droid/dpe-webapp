const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/dpe/student-portal-attendance.js'), 'utf8');

function setup(values = ['2026-08', '2026-09', '2026-10']) {
    const inputs = values.map(value => ({ value, message: '', setCustomValidity(message) { this.message = message; } }));
    const listeners = {}, submissions = [];
    const form = {
        querySelectorAll() { return inputs; },
        addEventListener(type, handler) { listeners[type] = handler; },
        reportValidity() { return inputs.every(input => input.value && !input.message); },
        requestSubmit() { const event = this.submit(); if (!event.prevented) submissions.push(inputs.map(input => input.value)); },
        submit() { const event = { prevented: false, preventDefault() { this.prevented = true; } }; listeners.submit(event); return event; },
    };
    vm.runInNewContext(source, { document: { querySelector() { return form; } } });
    return { inputs, form, submissions, change(index, value) { inputs[index].value = value; listeners.change({ target: inputs[index] }); } };
}

test('changing any month immediately submits all three choices, including nonconsecutive months', () => {
    const page = setup();
    page.change(0, '2026-07');
    page.change(1, '2026-05');
    page.change(2, '2027-01');
    assert.deepEqual(page.submissions, [
        ['2026-07', '2026-09', '2026-10'],
        ['2026-07', '2026-05', '2026-10'],
        ['2026-07', '2026-05', '2027-01'],
    ]);
});

test('duplicate months stay on the page until corrected and validity clears afterwards', () => {
    const page = setup();
    page.change(0, '2026-09');
    assert.equal(page.submissions.length, 0);
    assert.ok(page.inputs[0].message);
    assert.ok(page.inputs[1].message);
    assert.equal(page.form.submit().prevented, true);
    page.change(0, '2026-07');
    assert.equal(page.inputs[0].message, '');
    assert.equal(page.inputs[1].message, '');
    assert.deepEqual(page.submissions, [['2026-07', '2026-09', '2026-10']]);
});

test('clearing a month never navigates with an incomplete selection', () => {
    const page = setup();
    page.change(1, '');
    assert.equal(page.submissions.length, 0);
    assert.equal(page.form.submit().prevented, true);
    page.change(1, '2026-06');
    assert.deepEqual(page.submissions, [['2026-08', '2026-06', '2026-10']]);
});

test('loading the script on another page has no effect', () => {
    assert.doesNotThrow(() => vm.runInNewContext(source, { document: { querySelector() { return null; } } }));
});
