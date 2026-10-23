<div class="dpe-student-attendance-modal" data-attendance-modal hidden>
    <div class="dpe-student-attendance-modal-backdrop" data-attendance-cancel></div>
    <section class="dpe-student-attendance-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="attendance-confirm-title" aria-describedby="attendance-confirm-description">
        <h2 id="attendance-confirm-title">Konfirmasi absensi siswa</h2>
        <p id="attendance-confirm-description" data-attendance-confirm-description></p>
        <p class="dpe-student-attendance-modal-current">Status saat ini: <strong data-attendance-current-label></strong></p>
        <form data-attendance-confirm-form>
            <div class="dpe-dashboard-field">
                <label for="attendance-confirm-status">Status yang akan disimpan</label>
                <select id="attendance-confirm-status" data-attendance-confirm-status>
                    <option value="">Belum dicatat / kosongkan</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field" data-attendance-reason-field hidden>
                <label for="attendance-confirm-reason">Alasan koreksi *</label>
                <textarea id="attendance-confirm-reason" data-attendance-confirm-reason maxlength="1000" rows="3" placeholder="Jelaskan alasan perubahan kehadiran."></textarea>
            </div>
            <p class="dpe-student-attendance-modal-error" data-attendance-confirm-error role="alert" hidden></p>
            <p class="dpe-student-attendance-modal-help">Perubahan disimpan setelah kamu menekan Ya, simpan. Status kosong berarti belum dicatat.</p>
            <div class="dpe-student-attendance-modal-actions">
                <button type="button" class="dpe-dashboard-button dpe-dashboard-button--outline" data-attendance-cancel>Batal</button>
                <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary" data-attendance-confirm-save>Ya, simpan</button>
            </div>
        </form>
    </section>
</div>
