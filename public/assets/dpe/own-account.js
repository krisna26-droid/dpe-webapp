(function () {
    'use strict';
    document.querySelectorAll('[data-account-toggle]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-account-toggle'));
        if (!input) return;
        button.hidden = false;
        button.addEventListener('click', function () {
            var visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', (visible ? 'Sembunyikan ' : 'Tampilkan ') + (button.getAttribute('data-account-label') || 'kata sandi'));
            button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        });
    });
    var upload = document.querySelector('[data-account-photo]');
    var preview = document.querySelector('[data-account-photo-preview]');
    var hint = document.querySelector('[data-account-photo-hint]');
    var removeInput = document.querySelector('[data-account-photo-remove-input]');
    var removeButton = document.querySelector('[data-account-photo-remove]');
    var removeFallback = document.querySelector('[data-account-photo-remove-fallback]');
    if (!upload || !preview || !hint || !removeInput || !removeButton) return;
    var images = Array.from(document.querySelectorAll('[data-account-avatar-image]')).map(function (image) {
        return { element: image, original: image.getAttribute('src') || '' };
    });
    var original = preview.getAttribute('src') || '';
    var defaultHint = 'Foto JPG, PNG, atau WebP. Maksimal 2 MB dan 4096 × 4096 piksel.';
    var objectUrl = null;
    function clear() { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; }

    function restore() {
        images.forEach(function (image) {
            if (image.original) image.element.setAttribute('src', image.original);
            else image.element.removeAttribute('src');
            image.element.hidden = removeInput.checked || !image.original;
        });
        hint.textContent = removeInput.checked ? 'Foto profil akan dihapus setelah memilih Simpan profil.' : defaultHint;
    }

    function controls(hasSelection) {
        removeButton.hidden = !original && !hasSelection;
        removeButton.disabled = !original && !hasSelection;
        removeButton.setAttribute('aria-pressed', removeInput.checked ? 'true' : 'false');
        removeButton.textContent = removeInput.checked ? 'Batalkan hapus foto' : !original && hasSelection ? 'Batalkan foto baru' : 'Hapus foto profil';
    }

    function refresh() {
        clear(); upload.setCustomValidity('');
        var file = upload.files && upload.files[0];
        if (!file) { restore(); controls(false); return; }
        var supported = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && /\.(jpe?g|png|webp)$/i.test(file.name);
        var error = !supported ? 'Gunakan foto JPG, PNG, atau WebP.' : file.size > 2 * 1024 * 1024 ? 'Ukuran foto maksimal 2 MB.' : '';
        if (error) { restore(); upload.setCustomValidity(error); hint.textContent = error; controls(true); return; }
        removeInput.checked = false;
        objectUrl = URL.createObjectURL(file);
        images.forEach(function (image) { image.element.src = objectUrl; image.element.hidden = false; });
        hint.textContent = file.name + ' · foto baru siap disimpan';
        controls(true);
    }

    if (removeFallback) removeFallback.hidden = true;
    removeButton.addEventListener('click', function () {
        clear(); upload.value = ''; upload.setCustomValidity('');
        removeInput.checked = original ? !removeInput.checked : false;
        restore(); controls(false);
    });
    upload.addEventListener('change', refresh);
    if (upload.form) upload.form.addEventListener('reset', function () { window.setTimeout(refresh, 0); });
    window.addEventListener('pagehide', clear);
    window.addEventListener('pageshow', function (event) { if (event.persisted) refresh(); });
    refresh();
}());
