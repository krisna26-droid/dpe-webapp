const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/dpe/student-attendance.js'), 'utf8');
const countKeys = ['present', 'late', 'absent', 'sick', 'permission', 'rescheduled', 'unrecorded', 'unknown', 'meeting_count', 'attended_count'];
const labels = { present: 'Hadir', late: 'Terlambat', absent: 'Tidak Hadir', sick: 'Sakit', permission: 'Izin', rescheduled: 'Dijadwalkan Ulang' };
const attrKey = (name) => name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());

// Minimal DOM covers the APIs used by this module; rendered Blade attributes
// and the JSON contract are verified separately by Laravel feature tests.
class Node {
    constructor(tag = 'div', attrs = {}) {
        this.tag = tag; this.attrs = attrs; this.dataset = {}; this.children = []; this.listeners = {};
        this.hidden = Boolean(attrs.hidden); this.disabled = false; this.checked = false; this.required = false; this.value = '';
        this.classList = { add() {}, remove() {} };
        for (const [key, value] of Object.entries(attrs)) if (key.startsWith('data-')) this.dataset[attrKey(key)] = value;
    }
    append(...nodes) { for (const node of nodes) { node.parent = this; this.children.push(node); } return this; }
    setAttribute(key, value) { this.attrs[key] = value; }
    removeAttribute(key) { delete this.attrs[key]; }
    matches(selector) {
        const m = selector.trim().match(/^([a-z]+)?(?:\[([^=\]]+)(?:="([^"]*)")?\])?$/);
        if (!m) throw new Error('Unsupported selector: ' + selector);
        const [, tag, attr, value] = m;
        if (tag && this.tag !== tag) return false;
        if (!attr) return true;
        const actual = attr === 'hidden' ? (this.hidden ? true : undefined)
            : attr.startsWith('data-') ? this.dataset[attrKey(attr)] : this.attrs[attr];
        return actual !== undefined && (value === undefined || String(actual) === value);
    }
    querySelectorAll(selector) {
        const result = []; const selectors = selector.includes('[') ? [selector] : selector.split(',');
        for (const child of this.children) {
            if (selectors.some((s) => child.matches(s))) result.push(child);
            result.push(...child.querySelectorAll(selector));
        }
        return result;
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    closest(selector) { for (let node = this; node; node = node.parent) if (node.matches(selector)) return node; return null; }
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
    emit(type, props = {}) {
        const event = { currentTarget: this, target: this, prevented: false, preventDefault() { this.prevented = true; }, ...props };
        this.lastEvent = event;
        return Promise.all((this.listeners[type] || []).map((fn) => fn(event)));
    }
    focus() { this.doc.activeElement = this; }
    setCustomValidity(value) { this.invalid = value; }
    reportValidity() { return !this.querySelectorAll('textarea').some((n) => n.invalid || (n.required && !n.value) || n.value.length > 1000); }
    set innerHTML(_) { throw new Error('Unsafe HTML assignment'); }
    requestSubmit() { this.submitted = (this.submitted || 0) + 1; }
}
function cell(status = null, version = 0, extra = {}) {
    const counts = Object.fromEntries(countKeys.map((key) => [key, 0]));
    counts.meeting_count = status === 'rescheduled' ? 0 : 1;
    counts[status || 'unrecorded'] = 1;
    counts.attended_count = ['present', 'late'].includes(status) ? 1 : 0;
    return { session_id: 'session-1', student_id: 'student-1', status, version, label: labels[status] || 'Belum dicatat',
        is_attending: counts.attended_count === 1, can_record: true, requires_reason: version > 0,
        recorded_at_label: version ? '10 Oct 2026 18:00' : '—', has_recorded_status: status !== null, contribution: counts, ...extra };
}
function fixture(initial = cell(), { sessionView = false } = {}) {
    const data = (name, tag = 'div', extra = {}) => new Node(tag, { ['data-' + name]: '', ...extra });
    const doc = new Node('document'); doc.body = new Node('body'); doc.append(doc.body);
    doc.getElementById = (id) => doc.querySelector('[id="' + id + '"]');
    const modal = data('attendance-modal'); modal.hidden = true;
    const backdrop = data('attendance-cancel');
    const dialog = new Node('section');
    const description = data('attendance-confirm-description', 'p');
    const current = data('attendance-current-label', 'strong');
    const confirm = data('attendance-confirm-form', 'form');
    const select = data('attendance-confirm-status', 'select');
    const field = data('attendance-reason-field'); field.hidden = true;
    const reason = data('attendance-confirm-reason', 'textarea');
    field.append(reason);
    const error = data('attendance-confirm-error', 'p'); error.hidden = true;
    const cancel = data('attendance-cancel', 'button');
    const save = data('attendance-confirm-save', 'button');
    confirm.append(select, field, error, cancel, save);
    dialog.append(description, current, confirm); modal.append(backdrop, dialog);
    const toast = data('dashboard-toast');
    const form = data('attendance-quick', 'form');
    Object.assign(form.dataset, { studentId: 'student-1', sessionId: 'session-1', studentName: 'Ayu <script> & "Putri"',
        meetingLabel: '10 Oktober 2026, 17:28', requiresReason: initial.requires_reason ? '1' : '0', attendanceCell: JSON.stringify(initial) });
    form.action = '/teacher/student-attendance/session-1/student-1';
    const checkbox = data('attendance-check', 'input'); checkbox.checked = initial.is_attending; checkbox.disabled = !initial.can_record;
    const token = new Node('input', { name: '_token' }); token.value = 'csrf-fixture';
    const version = new Node('input', { name: 'expected_version' }); version.value = initial.version;
    const fallback = data('attendance-open', 'button');
    form.append(checkbox, token, version, fallback);
    const row = new Node('tr');
    const view = sessionView ? row : new Node('td'); view.dataset.attendanceCellView = '';
    const status = data('attendance-status', 'span'); status.textContent = initial.label;
    const recorded = data('attendance-recorded-at', 'td'); recorded.textContent = initial.recorded_at_label;
    const detail = data('attendance-detail', 'a');
    if (!sessionView) row.append(view);
    view.append(form, status, recorded, detail);
    function summary(name) {
        const root = data(name); root.dataset.attendanceSummary = JSON.stringify(initial.contribution);
        for (const key of [...countKeys, 'absent,sick,permission']) root.append(data('attendance-count', 'span', { 'data-attendance-count': key }));
        root.append(data('attendance-unknown-note'));
        return root;
    }
    const recap = summary('attendance-row-summary'); row.append(recap);
    const overall = summary('attendance-overall-summary');
    const notice = summary('attendance-completeness'); notice.hidden = !initial.contribution.unrecorded;
    const filters = new Node('form');
    const branch = data('attendance-branch', 'select'); branch.form = filters;
    const group = new Node('select', { id: 'attendance-group', name: 'class_group_id' }); group.form = filters;
    const student = new Node('select', { id: 'attendance-student', name: 'student_id' });
    const period = new Node('input', { id: 'attendance-period' }); period.value = '2026-11';
    const month = data('attendance-month', 'input');
    filters.append(branch, group, student, period, month);
    doc.body.append(row, overall, notice, modal, toast, filters);
    const attachDoc = (n) => { n.doc = doc; n.children.forEach(attachDoc); }; attachDoc(doc);
    const calls = []; const queue = [];
    const fetch = async (url, options) => {
        calls.push({ url, options, body: JSON.parse(options.body) });
        const answer = queue.shift();
        if (answer instanceof Error) throw answer;
        return typeof answer === 'function' ? answer() : answer;
    };
    const response = (nextCell, code = 200, errors) => ({
        ok: code === 200, status: code, json: async () => ({ cell: nextCell, errors }),
    });
    const context = { document: doc, fetch, window: { clearTimeout() {}, setTimeout() { return 1; },
        location: { assign() { throw new Error('Unexpected navigation'); }, reload() { throw new Error('Unexpected refresh'); } } } };
    vm.runInNewContext(source, context);
    doc.emit('DOMContentLoaded');
    const click = (checked = !checkbox.checked) => { checkbox.checked = checked; return checkbox.emit('change'); };
    const count = (root, key) => Number(root.querySelector('[data-attendance-count="' + key + '"]').textContent);
    return { doc, modal, backdrop, description, current, confirm, select, reason, field, error, cancel, save, checkbox, form, fallback,
        version, status, recorded, detail, recap, overall, notice, toast, calls, queue, response, click, count, branch, group, student, filters, period, month };
}

test('checkbox opens popup without saving; cancel and Escape preserve state', async () => {
    const f = fixture(); await f.click(true);
    assert.equal(f.modal.hidden, false); assert.equal(f.checkbox.checked, false); assert.equal(f.calls.length, 0);
    assert.equal(f.select.value, 'present'); assert.equal(f.doc.activeElement, f.cancel);
    assert.equal(f.description.textContent, 'Ayu <script> & "Putri" · 10 Oktober 2026, 17:28');
    await f.cancel.emit('click'); assert.equal(f.modal.hidden, true); assert.equal(f.calls.length, 0);
    assert.equal(f.doc.activeElement, f.checkbox);
    await f.click(true); await f.doc.emit('keydown', { key: 'Escape' });
    assert.equal(f.modal.hidden, true); assert.equal(f.checkbox.checked, false); assert.equal(f.calls.length, 0);
});

test('confirm sends CSRF and version once, then updates checkbox, status and both recaps', async () => {
    const f = fixture(); await f.click(true); f.queue.push(f.response(cell('present', 1))); await f.confirm.emit('submit');
    assert.equal(f.calls.length, 1); assert.equal(f.calls[0].body._token, 'csrf-fixture');
    assert.equal(f.calls[0].body.expected_version, 0); assert.equal(f.calls[0].body.attendance_status, 'present');
    assert.equal(f.calls[0].options.headers.Accept, 'application/json'); assert.equal(f.calls[0].options.credentials, 'same-origin');
    assert.equal('individual_learning_note' in f.calls[0].body, false);
    assert.equal(f.checkbox.checked, true); assert.equal(f.checkbox.disabled, false); assert.equal(f.version.value, 1);
    assert.equal(f.status.textContent, 'Hadir'); assert.equal(f.modal.hidden, true); assert.equal(f.form.submitted, undefined);
    for (const root of [f.recap, f.overall]) { assert.equal(f.count(root, 'attended_count'), 1); assert.equal(f.count(root, 'unrecorded'), 0); }
    assert.equal(f.notice.hidden, true); assert.equal(f.toast.hidden, false);
});

test('changing saved attendance requires a nonblank reason and clearing is unrecorded', async () => {
    const f = fixture(cell('present', 1)); await f.click(false);
    assert.equal(f.checkbox.checked, true); assert.equal(f.select.value, ''); assert.equal(f.field.hidden, false);
    f.reason.value = '   '; await f.confirm.emit('submit'); assert.equal(f.calls.length, 0);
    f.reason.value = 'Periksa ulang'; await f.reason.emit('input');
    f.queue.push(f.response(cell(null, 2))); await f.confirm.emit('submit');
    assert.equal(f.calls[0].body.attendance_status, null); assert.equal(f.calls[0].body.correction_reason, 'Periksa ulang');
    assert.equal(f.checkbox.checked, false); assert.equal(f.count(f.recap, 'unrecorded'), 1); assert.equal(f.count(f.recap, 'absent'), 0);
    assert.equal(f.notice.hidden, false);
});

test('late counts as attended and can be changed to explicit absent without refresh', async () => {
    const f = fixture(cell('late', 1)); await f.click(false);
    f.select.value = 'absent'; f.reason.value = 'Koreksi kehadiran';
    f.queue.push(f.response(cell('absent', 2))); await f.confirm.emit('submit');
    assert.equal(f.status.textContent, 'Tidak Hadir'); assert.equal(f.count(f.overall, 'absent,sick,permission'), 1);
    assert.equal(f.count(f.overall, 'late'), 0); assert.equal(f.count(f.overall, 'attended_count'), 0);
});

test('validation error stays in popup and an explicit retry updates the row once', async () => {
    const f = fixture(); await f.click(true);
    f.queue.push(f.response(null, 422, { attendance_status: ['Periksa jadwal sesi.'] }));
    await f.confirm.emit('submit');
    assert.equal(f.modal.hidden, false); assert.equal(f.error.textContent, 'Periksa jadwal sesi.'); assert.equal(f.save.disabled, false);
    assert.equal(f.checkbox.checked, false); assert.equal(f.version.value, 0);
    f.queue.push(f.response(cell('sick', 1))); f.select.value = 'sick'; await f.confirm.emit('submit');
    assert.equal(f.count(f.recap, 'sick'), 1); assert.equal(f.count(f.recap, 'unrecorded'), 0); assert.equal(f.calls.length, 2);
});

test('double submit and closing during save do not send a second request', async () => {
    const f = fixture(); await f.click(true);
    let resolve; f.queue.push(() => new Promise((r) => { resolve = r; }));
    const saving = f.confirm.emit('submit');
    assert.equal(f.save.disabled, true); assert.equal(f.checkbox.disabled, true);
    await f.confirm.emit('submit'); await f.cancel.emit('click'); await f.doc.emit('keydown', { key: 'Escape' });
    assert.equal(f.calls.length, 1); assert.equal(f.modal.hidden, false);
    resolve(f.response(cell('present', 1))); await saving;
    assert.equal(f.modal.hidden, true); assert.equal(f.checkbox.disabled, false);
});

test('stale version adopts server state and blocks overwrite until popup reopened', async () => {
    const f = fixture(); await f.click(true);
    f.queue.push(f.response(cell('late', 2), 422, { expected_version: ['Catatan sudah berubah.'] }));
    await f.confirm.emit('submit');
    assert.equal(f.status.textContent, 'Terlambat'); assert.equal(f.version.value, 2); assert.equal(f.checkbox.checked, true);
    assert.equal(f.count(f.overall, 'late'), 1); assert.equal(f.modal.hidden, false); assert.equal(f.save.disabled, true);
    await f.confirm.emit('submit'); assert.equal(f.calls.length, 1);
    await f.cancel.emit('click'); await f.click(false);
    assert.equal(f.save.disabled, false); assert.equal(f.reason.required, true);
    f.reason.value = 'Koreksi setelah pemeriksaan'; f.queue.push(f.response(cell(null, 3))); await f.confirm.emit('submit');
    assert.equal(f.calls[1].body.expected_version, 2); assert.equal(f.count(f.overall, 'late'), 0);
});

test('network failure leaves checkbox and recap unchanged and popup retryable', async () => {
    const f = fixture(); await f.click(true); f.queue.push(new Error('network unavailable')); await f.confirm.emit('submit');
    assert.equal(f.checkbox.checked, false); assert.equal(f.version.value, 0); assert.equal(f.modal.hidden, false);
    assert.equal(f.save.disabled, false); assert.match(f.error.textContent, /Periksa koneksi/);
    assert.equal(JSON.parse(f.overall.dataset.attendanceSummary).unrecorded, 1);
});

test('mismatched response identity cannot update another student', async () => {
    const f = fixture(); await f.click(true);
    f.queue.push(f.response(cell('present', 1, { student_id: 'other-student' }))); await f.confirm.emit('submit');
    assert.equal(f.checkbox.checked, false); assert.equal(f.version.value, 0); assert.equal(f.error.hidden, false);
});

test('session detail updates first recording time and action label', async () => {
    const f = fixture(cell(), { sessionView: true }); await f.click(true);
    f.queue.push(f.response(cell('present', 1))); await f.confirm.emit('submit');
    assert.equal(f.recorded.textContent, '10 Oct 2026 18:00'); assert.equal(f.detail.textContent, 'Ubah / riwayat');
});

test('disabled future checkbox cannot open popup or save', async () => {
    const f = fixture(cell(null, 0, { can_record: false })); await f.click(true);
    assert.equal(f.modal.hidden, true); assert.equal(f.checkbox.checked, false); assert.equal(f.calls.length, 0);
});

test('fallback button and native form submit open confirmation without posting', async () => {
    const f = fixture(); await f.fallback.emit('click');
    assert.equal(f.modal.hidden, false); assert.equal(f.calls.length, 0); await f.backdrop.emit('click');
    await f.form.emit('submit');
    assert.equal(f.form.lastEvent.prevented, true); assert.equal(f.modal.hidden, false); assert.equal(f.calls.length, 0);
});

test('keyboard focus remains inside popup', async () => {
    const f = fixture(); await f.click(true); f.save.focus();
    await f.doc.emit('keydown', { key: 'Tab' }); assert.equal(f.doc.activeElement, f.select);
    await f.doc.emit('keydown', { key: 'Tab', shiftKey: true }); assert.equal(f.doc.activeElement, f.save);
});

test('branch, class and period filters retain their behavior', async () => {
    const f = fixture(); await f.branch.emit('change'); assert.equal(f.filters.submitted, 1);
    assert.equal('name' in f.group.attrs, false); assert.equal('name' in f.student.attrs, false);
    await f.group.emit('change'); assert.equal(f.filters.submitted, 2);
    f.student.value = 'student-1'; await f.period.emit('change');
    assert.equal(f.month.value, '2026-11'); assert.equal(f.student.value, '');
});
