document.addEventListener('DOMContentLoaded', () => {
    const keys = ['present', 'late', 'absent', 'sick', 'permission', 'rescheduled', 'unrecorded', 'unknown', 'meeting_count', 'attended_count'];
    const statuses = ['present', 'late', 'absent', 'sick', 'permission', 'rescheduled'];
    const modal = document.querySelector('[data-attendance-modal]');
    const overall = document.querySelector('[data-attendance-overall-summary]');
    const notice = document.querySelector('[data-attendance-completeness]');
    let pending = null;
    let saving = false;
    let conflicted = false;
    let toastTimer;

    const parse = (value) => {
        try { return JSON.parse(value); } catch (_) { return null; }
    };
    const validCounts = (counts) => counts && keys.every((key) => Number.isSafeInteger(counts[key]) && counts[key] >= 0);
    const validCell = (cell) => cell && validCounts(cell.contribution)
        && Number.isSafeInteger(cell.version) && cell.version >= 0
        && (cell.status === null || typeof cell.status === 'string') && typeof cell.label === 'string'
        && ['is_attending', 'can_record', 'requires_reason'].every((key) => typeof cell[key] === 'boolean');
    const updateCounts = (root, counts) => {
        if (!root) return;
        root.querySelectorAll('[data-attendance-count]').forEach((node) => {
            node.textContent = node.dataset.attendanceCount.split(',').reduce((total, key) => total + (counts[key] || 0), 0);
        });
        root.querySelectorAll('[data-attendance-unknown-note]').forEach((node) => { node.hidden = counts.unknown === 0; });
    };
    const updateSummary = (root, before, after) => {
        if (!root) return null;
        const counts = parse(root.dataset.attendanceSummary);
        if (!validCounts(counts)) return null;
        keys.forEach((key) => { counts[key] = Math.max(0, counts[key] + after[key] - before[key]); });
        root.dataset.attendanceSummary = JSON.stringify(counts);
        updateCounts(root, counts);
        return counts;
    };
    const applyCell = (entry, cell) => {
        const before = entry.state.contribution;
        updateSummary(entry.form.closest('tr')?.querySelector('[data-attendance-row-summary]'), before, cell.contribution);
        const totals = updateSummary(overall, before, cell.contribution);
        if (notice && totals) {
            updateCounts(notice, totals);
            notice.hidden = totals.unrecorded === 0 && totals.unknown === 0;
        }
        entry.state = cell;
        entry.form.dataset.attendanceCell = JSON.stringify(cell);
        entry.form.dataset.requiresReason = cell.requires_reason ? '1' : '0';
        entry.form.querySelector('[name="expected_version"]').value = cell.version;
        entry.checkbox.checked = cell.is_attending;
        entry.checkbox.disabled = !cell.can_record;
        const view = entry.form.closest('[data-attendance-cell-view]');
        const label = view?.querySelector('[data-attendance-status]');
        if (label) {
            label.textContent = cell.label;
            label.className = 'dpe-student-attendance-status dpe-student-attendance-status--' + (statuses.includes(cell.status) ? cell.status : 'empty');
        }
        const recorded = view?.querySelector('[data-attendance-recorded-at]');
        if (recorded) recorded.textContent = cell.recorded_at_label;
        const detail = view?.querySelector('[data-attendance-detail]');
        if (detail) detail.textContent = cell.has_recorded_status ? 'Ubah / riwayat' : 'Catat / detail';
    };

    if (modal) {
        const confirmForm = modal.querySelector('[data-attendance-confirm-form]');
        const select = modal.querySelector('[data-attendance-confirm-status]');
        const reason = modal.querySelector('[data-attendance-confirm-reason]');
        const reasonField = modal.querySelector('[data-attendance-reason-field]');
        const error = modal.querySelector('[data-attendance-confirm-error]');
        const save = modal.querySelector('[data-attendance-confirm-save]');
        const cancel = modal.querySelector('button[data-attendance-cancel]');
        const setError = (message = '') => { error.textContent = message; error.hidden = !message; };
        const setBusy = (busy) => {
            saving = busy;
            confirmForm.setAttribute('aria-busy', busy ? 'true' : 'false');
            select.disabled = busy;
            reason.disabled = busy;
            cancel.disabled = busy;
            save.disabled = busy || conflicted;
            save.textContent = busy ? 'Menyimpan…' : 'Ya, simpan';
            if (pending) {
                pending.form.setAttribute('aria-busy', busy ? 'true' : 'false');
                pending.checkbox.disabled = busy || !pending.state.can_record;
            }
        };
        const close = () => {
            if (saving) return;
            const trigger = pending?.trigger;
            modal.hidden = true;
            document.body.classList.remove('is-student-attendance-confirm-open');
            pending = null;
            conflicted = false;
            trigger?.focus();
        };
        const open = (entry, status, trigger) => {
            entry.checkbox.checked = entry.state.is_attending;
            if (saving || pending || !entry.state.can_record) return;
            pending = entry;
            pending.trigger = trigger;
            conflicted = false;
            modal.querySelector('[data-attendance-confirm-description]').textContent = entry.form.dataset.studentName + ' · ' + entry.form.dataset.meetingLabel;
            modal.querySelector('[data-attendance-current-label]').textContent = entry.state.label;
            select.value = status;
            reason.value = '';
            reason.required = entry.state.requires_reason;
            reasonField.hidden = !entry.state.requires_reason;
            reason.setCustomValidity('');
            setError();
            setBusy(false);
            modal.hidden = false;
            document.body.classList.add('is-student-attendance-confirm-open');
            cancel.focus();
        };
        modal.querySelectorAll('[data-attendance-cancel]').forEach((node) => node.addEventListener('click', close));
        reason.addEventListener('input', () => reason.setCustomValidity(''));
        document.addEventListener('keydown', (event) => {
            if (modal.hidden) return;
            if (event.key === 'Escape') { event.preventDefault(); close(); }
            if (event.key !== 'Tab') return;
            const controls = [...modal.querySelectorAll('button, select, textarea')].filter((node) => !node.disabled && !node.closest('[hidden]'));
            if (!controls.length) { event.preventDefault(); return; }
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && (document.activeElement === first || !controls.includes(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !controls.includes(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        });
        confirmForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!pending || saving || conflicted) return;
            reason.setCustomValidity(reason.required && !reason.value.trim() ? 'Alasan koreksi wajib diisi.' : '');
            if (!confirmForm.reportValidity()) return;
            const entry = pending;
            const body = {
                _token: entry.form.querySelector('[name="_token"]').value,
                expected_version: entry.state.version,
                attendance_status: select.value || null,
                correction_reason: reason.value.trim(),
            };
            setError();
            setBusy(true);
            try {
                const response = await fetch(entry.form.action, {
                    method: 'POST', credentials: 'same-origin', redirect: 'error',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body),
                });
                const payload = await response.json().catch(() => null);
                const cell = payload?.cell;
                const matches = validCell(cell) && cell.session_id === entry.form.dataset.sessionId && cell.student_id === entry.form.dataset.studentId
                    && typeof cell.recorded_at_label === 'string' && typeof cell.has_recorded_status === 'boolean';
                if (response.ok) {
                    if (!matches) throw new Error('invalid-response');
                    applyCell(entry, cell);
                    setBusy(false);
                    close();
                    const toast = document.querySelector('[data-dashboard-toast]');
                    if (toast) {
                        window.clearTimeout(toastTimer);
                        toast.textContent = 'Kehadiran siswa berhasil disimpan.';
                        toast.hidden = false;
                        toastTimer = window.setTimeout(() => { toast.hidden = true; }, 4500);
                    }
                    return;
                }
                if (response.status === 422 && payload?.errors?.expected_version && matches) {
                    applyCell(entry, cell);
                    conflicted = true;
                    modal.querySelector('[data-attendance-current-label]').textContent = cell.label;
                    setError('Catatan sudah berubah. Status terbaru sudah ditampilkan. Tekan Batal, lalu buka konfirmasi kembali sebelum mengubah data.');
                } else if (response.status === 422 && payload?.errors) {
                    setError(Object.values(payload.errors).flat().join('\n') || 'Periksa kembali data kehadiran.');
                } else if (response.status === 419 || response.status === 401) {
                    setError('Sesi login sudah berakhir. Buka kembali halaman dan masuk untuk menyimpan absensi.');
                } else if (response.status === 403) {
                    setError('Kamu tidak memiliki akses untuk mencatat kehadiran pada sesi ini.');
                } else {
                    setError('Penyimpanan belum dapat dikonfirmasi. Coba kembali atau periksa status terbaru melalui Catat / detail.');
                }
            } catch (_) {
                setError('Penyimpanan belum dapat dikonfirmasi. Periksa koneksi, lalu coba kembali atau periksa status terbaru melalui Catat / detail.');
            } finally {
                setBusy(false);
            }
        });
        document.querySelectorAll('[data-attendance-quick]').forEach((form) => {
            const checkbox = form.querySelector('[data-attendance-check]');
            const state = parse(form.dataset.attendanceCell);
            if (!checkbox || !validCell(state)) return;
            const entry = { form, checkbox, state };
            form.classList.add('is-enhanced');
            checkbox.addEventListener('change', () => open(entry, checkbox.checked ? 'present' : '', checkbox));
            form.querySelector('[data-attendance-open]')?.addEventListener('click', (event) => open(entry, 'present', event.currentTarget));
            form.addEventListener('submit', (event) => { event.preventDefault(); open(entry, checkbox.checked ? 'present' : '', checkbox); });
        });
    }

    const branch = document.querySelector('[data-attendance-branch]');
    if (branch) branch.addEventListener('change', () => {
        const group = document.getElementById('attendance-group');
        const student = document.getElementById('attendance-student');
        if (group) group.removeAttribute('name');
        if (student) student.removeAttribute('name');
        branch.form.requestSubmit();
    });
    const group = document.getElementById('attendance-group');
    if (group) group.addEventListener('change', () => {
        const student = document.getElementById('attendance-student');
        if (student) student.removeAttribute('name');
        group.form.requestSubmit();
    });
    const period = document.getElementById('attendance-period');
    if (period) period.addEventListener('change', () => {
        const month = document.querySelector('[data-attendance-month]');
        const student = document.getElementById('attendance-student');
        if (month) month.value = period.value;
        if (student) student.value = '';
    });
});
