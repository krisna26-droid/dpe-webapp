(() => {
    'use strict';
    document.querySelectorAll('[data-wa-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const field = document.getElementById(button.dataset.waCopy);
            const status = document.querySelector('[data-wa-copy-status]');
            if (!field || button.disabled) return;
            try {
                if (!navigator.clipboard || !window.isSecureContext) throw new Error('manual');
                await navigator.clipboard.writeText(field.value);
                if (status) status.textContent = 'Pesan berhasil disalin.';
            } catch (_) {
                field.focus();
                field.select();
                if (status) status.textContent = 'Pesan sudah dipilih. Gunakan Salin / Ctrl+C pada perangkat Anda.';
            }
        });
    });
})();
