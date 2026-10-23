(() => {
    let activeCamera = null;
    const stopCamera = () => {
        if (!activeCamera) return;
        activeCamera.stream.getTracks().forEach((track) => track.stop());
        activeCamera.video.srcObject = null;
        activeCamera.video.hidden = true;
        activeCamera.capture.disabled = true;
        activeCamera = null;
    };

    document.querySelectorAll('[data-attendance-form]').forEach((form) => {
        const get = (key) => form.querySelector('[data-' + key + ']');
        const video = get('camera-video'), preview = get('camera-preview'), canvas = get('camera-canvas');
        const file = get('camera-file'), open = get('camera-open'), capture = get('camera-capture');
        const cameraMessage = get('camera-message'), submit = get('attendance-submit');
        let previewUrl = null, photoAt = null, locationAt = null;

        open.addEventListener('click', async () => {
            stopCamera();
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                cameraMessage.textContent = 'Kamera memerlukan HTTPS atau localhost serta browser yang mendukung kamera.';
                return;
            }
            open.disabled = true;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                stopCamera();
                activeCamera = { stream, video, capture };
                video.srcObject = stream;
                video.hidden = false;
                await video.play();
                capture.disabled = false;
                cameraMessage.textContent = 'Kamera siap. Pastikan wajah terlihat, lalu tekan Ambil foto.';
            } catch (error) {
                stopCamera();
                cameraMessage.textContent = 'Kamera tidak tersedia. Izinkan akses kamera lalu coba kembali.';
            } finally {
                open.disabled = false;
            }
        });

        capture.addEventListener('click', () => {
            if (!video.videoWidth || !video.videoHeight) return;
            capture.disabled = true;
            const ratio = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.round(video.videoWidth * ratio);
            canvas.height = Math.round(video.videoHeight * ratio);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                if (!blob) { capture.disabled = false; return; }
                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(new File([blob], 'absensi-' + Date.now() + '.jpg', { type: 'image/jpeg' }));
                    file.files = transfer.files;
                    if (previewUrl) URL.revokeObjectURL(previewUrl);
                    previewUrl = URL.createObjectURL(blob);
                    preview.src = previewUrl;
                    preview.hidden = false;
                    photoAt = Date.now();
                    submit.disabled = false;
                    open.textContent = 'Ambil ulang foto';
                    cameraMessage.textContent = 'Foto siap disimpan bersama absensi.';
                    stopCamera();
                } catch (error) {
                    cameraMessage.textContent = 'Browser ini belum mendukung pengiriman foto kamera. Gunakan browser terbaru.';
                    stopCamera();
                }
            }, 'image/jpeg', 0.85);
        });

        const status = get('location-status'), latitude = get('location-latitude'), longitude = get('location-longitude');
        const accuracy = get('location-accuracy'), note = get('location-note');
        const locationMessage = get('location-message'), locationButton = get('location-button');
        const unavailable = (message) => {
            status.value = 'unavailable';
            latitude.value = longitude.value = accuracy.value = '';
            locationAt = null;
            note.required = true;
            locationMessage.textContent = message;
            get('location-box').classList.remove('is-captured');
        };
        locationButton.addEventListener('click', () => {
            if (!navigator.geolocation) { unavailable('Lokasi tidak tersedia. Isi alasan sebelum menyimpan.'); return; }
            locationButton.disabled = true;
            locationMessage.textContent = 'Mengambil lokasi perangkat…';
            navigator.geolocation.getCurrentPosition((position) => {
                status.value = 'captured';
                latitude.value = position.coords.latitude;
                longitude.value = position.coords.longitude;
                accuracy.value = position.coords.accuracy;
                locationAt = Date.now();
                note.required = false;
                locationMessage.textContent = 'Lokasi terbaca · akurasi sekitar ' + Math.round(position.coords.accuracy) + ' meter.';
                get('location-box').classList.add('is-captured');
                locationButton.disabled = false;
            }, () => {
                unavailable('Lokasi gagal terbaca. Isi alasan sebelum menyimpan.');
                locationButton.disabled = false;
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        });

        form.addEventListener('submit', (event) => {
            if (!file.files.length || !photoAt || Date.now() - photoAt > 5 * 60 * 1000) {
                event.preventDefault();
                cameraMessage.textContent = 'Ambil foto baru sebelum menyimpan absensi.';
                submit.disabled = true;
                return;
            }
            if (status.value === 'captured' && (!locationAt || Date.now() - locationAt > 5 * 60 * 1000)) {
                event.preventDefault();
                unavailable('Lokasi sudah terlalu lama. Ambil lokasi kembali atau isi alasan.');
                return;
            }
            if (status.value === 'unavailable' && !note.value.trim()) { event.preventDefault(); note.focus(); return; }
            submit.disabled = true;
            submit.textContent = 'Menyimpan…';
            stopCamera();
        });
    });
    window.addEventListener('pagehide', stopCamera);
})();
