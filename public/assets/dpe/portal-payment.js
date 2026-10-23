/* Confirmed payment uploads keep the chosen image and show errors on the form. */
(function () {
    'use strict';

    function init() {
        var form = document.querySelector('[data-payment-upload]');
        if (!form || !window.fetch || !window.FormData) {
            return;
        }
        var image = form.querySelector('input[name="image"]');
        var snapshot = form.querySelector('input[name="expected_snapshot"]');
        var token = form.querySelector('input[name="_token"]');
        var submit = form.querySelector('button[type="submit"]');
        var feedback = document.querySelector('[data-payment-feedback]');
        var message = document.querySelector('[data-payment-message]');
        var busy = false;

        function show(text) {
            if (feedback && message) {
                message.textContent = text;
                feedback.hidden = false;
            }
        }

        function validImage() {
            var file = image && image.files && image.files[0];
            var error = !file ? 'Pilih gambar bukti pembayaran terlebih dahulu.'
                : file.size > 5 * 1024 * 1024 ? 'Ukuran gambar maksimal 5 MB. Pilih gambar yang lebih kecil.'
                : file.type && ['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1 ? 'Gunakan gambar JPG, PNG, atau WEBP.' : '';
            if (image) {
                image.setCustomValidity(error);
            }
            if (error) {
                show(error);
                form.reportValidity();
                return false;
            }
            return true;
        }

        function sameOrigin(value) {
            var url = new URL(value, window.location.href);
            if (url.origin !== window.location.origin) {
                throw new Error('Alamat pembayaran tidak sesuai. Muat halaman terbaru.');
            }
            return url.href;
        }

        async function readResponse(response) {
            if (response.status === 419 || response.status === 401) {
                throw new Error('Sesi sudah berakhir atau berubah. Masuk kembali lalu muat halaman pembayaran terbaru.');
            }
            if (response.status === 413) {
                throw new Error('Gambar melebihi batas unggah server. Pilih gambar yang lebih kecil, maksimal 5 MB.');
            }
            if (response.status === 403 || response.status === 404) {
                throw new Error('Akun tidak dapat mengakses pembayaran ini. Muat halaman terbaru dan periksa akun yang digunakan.');
            }
            var isJson = (response.headers.get('Content-Type') || '').indexOf('application/json') !== -1;
            var data = isJson ? await response.json() : {};
            if (response.status === 422) {
                var errors = data.errors || {};
                var first = Object.keys(errors).map(function (key) { return errors[key]; })[0];
                throw new Error(Array.isArray(first) && typeof first[0] === 'string' ? first[0] : 'Bukti belum dapat disimpan. Periksa gambar dan halaman pembayaran terbaru.');
            }
            if (!response.ok || !isJson) {
                throw new Error('Pengiriman belum dapat dipastikan. Periksa riwayat pembayaran sebelum mencoba lagi.');
            }
            return data;
        }

        async function upload() {
            if (busy || !validImage()) {
                return;
            }
            if (!snapshot || !token || !submit) {
                show('Formulir belum lengkap. Muat halaman terbaru sebelum mengirim bukti.');
                return;
            }
            busy = true;
            var originalLabel = submit.textContent;
            var completed = false;
            try {
                // Capture the file before disabling inputs; retain the reviewed version.
                var data = new FormData(form);
                var reviewed = snapshot.value;
                var contextUrl = sameOrigin(form.dataset.paymentContextUrl);
                var uploadUrl = sameOrigin(form.action);
                var returnUrl = sameOrigin(form.dataset.paymentReturnUrl);
                image.disabled = true;
                submit.disabled = true;
                submit.textContent = 'Mengirim bukti...';
                form.setAttribute('aria-busy', 'true');
                var options = { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
                var current = await readResponse(await window.fetch(contextUrl, options));
                if (!current.can_upload || current.expected_snapshot !== reviewed) {
                    throw new Error('Data pembayaran berubah sejak halaman dibuka. Muat halaman terbaru dan periksa status sebelum mengunggah.');
                }
                if (typeof current.csrf_token !== 'string' || !current.csrf_token) {
                    throw new Error('Sesi belum tersedia. Masuk kembali dan muat halaman pembayaran terbaru.');
                }
                token.value = current.csrf_token;
                data.set('_token', current.csrf_token);
                var result = await readResponse(await window.fetch(uploadUrl, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', body: data,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': current.csrf_token }
                }));
                completed = true;
                show(result.message || 'Bukti berhasil diunggah dan menunggu validasi Super Admin.');
                window.location.assign(returnUrl);
            } catch (error) {
                show(error instanceof TypeError ? 'Koneksi terputus. Periksa riwayat pembayaran sebelum mencoba lagi.' : error.message);
            } finally {
                if (!completed) {
                    busy = false;
                    image.disabled = false;
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                    form.removeAttribute('aria-busy');
                }
            }
        }

        image.addEventListener('change', validImage);
        form.addEventListener('submit', function (event) {
            if (busy || !validImage()) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
        form.addEventListener('dpe:confirmed-submit', function (event) {
            event.preventDefault();
            upload();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
