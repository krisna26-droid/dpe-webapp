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
    document.querySelectorAll('[data-account-avatar-image]').forEach(function (image) {
        image.addEventListener('error', function () { image.hidden = true; });
    });
    var upload = document.querySelector('[data-account-photo]');
    var preview = document.querySelector('[data-account-photo-preview]');
    var hint = document.querySelector('[data-account-photo-hint]');
    if (!upload || !preview || !hint) return;
    var original = preview.getAttribute('src') || '';
    var objectUrl = null;
    function clear() { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; }
    upload.addEventListener('change', function () {
        clear(); upload.setCustomValidity('');
        var file = upload.files && upload.files[0];
        if (!file) { preview.src = original; preview.hidden = !original; hint.textContent = 'Foto JPG, PNG, atau WebP. Maksimal 2 MB.'; return; }
        var supported = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && /\.(jpe?g|png|webp)$/i.test(file.name);
        var error = !supported ? 'Gunakan foto JPG, PNG, atau WebP.' : file.size > 2 * 1024 * 1024 ? 'Ukuran foto maksimal 2 MB.' : '';
        if (error) { upload.setCustomValidity(error); hint.textContent = error; preview.src = original; preview.hidden = !original; return; }
        objectUrl = URL.createObjectURL(file); preview.src = objectUrl; preview.hidden = false; hint.textContent = file.name + ' · foto baru siap disimpan';
    });
    window.addEventListener('pagehide', clear);
}());
