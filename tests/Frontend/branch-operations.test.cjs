const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/dpe/branch-operations.js'), 'utf8');

class Option {
    constructor(value, data = {}) { this.value = value; this.dataset = data; this.disabled = false; this.hidden = false; }
    getAttribute(name) { return this.dataset[name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] || null; }
}
class Select {
    constructor(options, value = '') { this.options = [new Option(''), ...options]; this.value = value; this.events = {}; }
    set value(value) { this.selected = this.options.some(o => o.value === value) ? value : ''; }
    get value() { return this.selected; }
    get selectedIndex() { return this.options.findIndex(o => o.value === this.selected); }
    addEventListener(name, callback) { this.events[name] = callback; }
    change(value) { this.value = value; this.events.change?.(); }
    visible() { return this.options.filter(o => o.value && !o.disabled).map(o => o.value); }
}
function init(fields, loading = false) {
    const form = { querySelector(selector) { return selector.includes('default_teacher_id') ? fields.teacher : fields[selector.match(/name="([^"]+)"/)[1]]; } };
    let callback;
    const document = { readyState: loading ? 'loading' : 'complete', querySelectorAll: () => [form], addEventListener: (_name, cb) => { callback = cb; } };
    vm.runInNewContext(source, { document });
    if (loading) { callback(); }
    return fields;
}
function sessionFixture() {
    return init({
        branch_id: new Select([new Option('A'), new Option('B')], 'A'),
        program_id: new Select([new Option('regular'), new Option('private')]),
        teacher: new Select([new Option('Dipta', { branches: 'A' }), new Option('GuruB', { branches: 'B' })], 'Dipta'),
        class_group_id: new Select([new Option('KA', { branch: 'A', program: 'regular', teacher: 'Dipta' }), new Option('KB', { branch: 'B', program: 'private', teacher: 'GuruB' })], 'KA'),
        rescheduled_from_session_id: new Select([new Option('SA', { branch: 'A', program: 'regular', group: 'KA' }), new Option('PA', { branch: 'A', program: 'private', group: '' }), new Option('SB', { branch: 'B', program: 'private', group: 'KB' })], 'SA'),
    });
}

test('changing branch clears mismatched teacher, class and source while showing that branch choices', () => {
    const f = sessionFixture();
    f.branch_id.change('B');
    assert.deepEqual(f.teacher.visible(), ['GuruB']);
    assert.equal(f.teacher.value, '');
    assert.equal(f.class_group_id.value, '');
    assert.equal(f.rescheduled_from_session_id.value, '');
});
test('changing program clears a class and source from another program', () => {
    const f = sessionFixture();
    f.program_id.change('private');
    assert.equal(f.class_group_id.value, '');
    assert.deepEqual(f.rescheduled_from_session_id.visible(), ['PA']);
});
test('selecting a class fills its program and an available default Teacher', () => {
    const f = sessionFixture();
    f.teacher.value = '';
    f.class_group_id.change('KA');
    assert.equal(f.program_id.value, 'regular');
    assert.equal(f.teacher.value, 'Dipta');
    assert.deepEqual(f.rescheduled_from_session_id.visible(), ['SA']);
});
test('switching to a private session offers private replacement sources', () => {
    const f = sessionFixture();
    f.class_group_id.change('');
    f.program_id.change('private');
    assert.deepEqual(f.rescheduled_from_session_id.visible(), ['PA']);
    f.rescheduled_from_session_id.change('PA');
    assert.equal(f.rescheduled_from_session_id.value, 'PA');
});
test('membership students follow the selected class and an incompatible student selection is cleared', () => {
    const f = init({ class_group_id: new Select([new Option('KA'), new Option('KB')], 'KA'), student_id: new Select([
        new Option('Ayu', { classGroups: 'KA' }), new Option('Wayan', { classGroups: 'KB' }), new Option('Ketut', { classGroups: 'KA KB' })], 'Ayu') });
    assert.deepEqual(f.student_id.visible(), ['Ayu', 'Ketut']);
    f.class_group_id.change('KB');
    assert.equal(f.student_id.value, '');
    assert.deepEqual(f.student_id.visible(), ['Wayan', 'Ketut']);
});
test('going back to the original branch restores its options without retaining an invalid selection', () => {
    const f = sessionFixture();
    f.branch_id.change('B');
    f.teacher.change('GuruB');
    f.branch_id.change('A');
    assert.deepEqual(f.teacher.visible(), ['Dipta']);
    assert.equal(f.teacher.value, '');
});
test('class form works without session fields and initializes after DOMContentLoaded', () => {
    const f = init({ branch_id: new Select([new Option('A')], 'A'), teacher: new Select([new Option('Dipta', { branches: 'A' })], 'Dipta') }, true);
    assert.equal(f.teacher.value, 'Dipta');
    assert.deepEqual(f.teacher.visible(), ['Dipta']);
});
test('a page without branch operations forms initializes without affecting other menus', () => {
    assert.doesNotThrow(() => vm.runInNewContext(source, { document: { readyState: 'complete', querySelectorAll: () => [] } }));
});
