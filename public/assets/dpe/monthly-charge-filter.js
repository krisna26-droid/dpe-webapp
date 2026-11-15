(() => {
    'use strict';

    document.querySelectorAll('[data-monthly-charge-filter]').forEach((form) => {
        const teacher = form.querySelector('[name="teacher_filter_id"]');
        const student = form.querySelector('[name="student_id"]');
        const month = form.querySelector('[name="charge_month"]');
        const hint = form.querySelector('#charge-student-help');
        const retry = form.querySelector('[data-charge-retry]');
        const submit = form.querySelector('[data-charge-submit]');
        if (!teacher || !student || !month || !hint || !retry || !submit) return;

        let requestNumber = 0;
        let controller;

        const replaceOptions = (select, rows, placeholder) => {
            select.replaceChildren(new Option(placeholder, ''));
            rows.forEach((row) => select.add(new Option(row.label, row.id)));
        };

        const refresh = async () => {
            const currentRequest = ++requestNumber;
            if (controller) controller.abort();
            controller = new AbortController();
            const teacherId = teacher.value;
            // A previous selection must never survive a teacher/month change.
            student.value = '';
            student.disabled = true;
            student.setAttribute('aria-busy', 'true');
            submit.disabled = true;
            retry.hidden = true;
            replaceOptions(student, [], 'Memuat siswa…');
            if (!month.value || !month.validity.valid) {
                student.setAttribute('aria-busy', 'false');
                replaceOptions(student, [], 'Pilih bulan tagihan terlebih dahulu');
                hint.textContent = 'Isi bulan tagihan yang valid untuk menampilkan siswa.';
                return;
            }
            hint.textContent = 'Memuat daftar siswa…';
            const url = new URL(form.dataset.optionsUrl, window.location.href);
            url.searchParams.set('charge_month', month.value);
            if (teacherId) url.searchParams.set('teacher_filter_id', teacherId);
            try {
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store',
                    headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok || response.redirected) throw new Error('Pilihan siswa belum dapat dimuat.');
                const data = await response.json();
                if (currentRequest !== requestNumber) return;
                if (!Array.isArray(data.students) || !Array.isArray(data.teachers)) throw new Error('Pilihan siswa belum dapat dimuat.');
                replaceOptions(teacher, data.teachers, 'Semua guru / tampilkan semua siswa');
                teacher.value = teacherId;
                replaceOptions(student, data.students, data.students.length ? 'Pilih siswa' : 'Tidak ada siswa yang sesuai');
                student.disabled = data.students.length === 0;
                student.setAttribute('aria-busy', 'false');
                submit.disabled = data.students.length === 0 || form.dataset.hasBranches !== '1';
                hint.textContent = data.students.length ? `${data.students.length} siswa dapat dipilih.`
                    : 'Tidak ada siswa untuk guru dan bulan tagihan ini. Pilih guru atau bulan lain.';
            } catch (error) {
                if (currentRequest !== requestNumber || error.name === 'AbortError') return;
                student.setAttribute('aria-busy', 'false');
                replaceOptions(student, [], 'Daftar siswa belum dapat dimuat');
                hint.textContent = 'Daftar siswa belum dapat dimuat. Coba lagi atau muat ulang halaman.';
                retry.hidden = false;
            }
        };

        teacher.addEventListener('change', refresh);
        month.addEventListener('change', () => {
            teacher.value = '';
            refresh();
        });
        retry.addEventListener('click', refresh);
    });
})();
