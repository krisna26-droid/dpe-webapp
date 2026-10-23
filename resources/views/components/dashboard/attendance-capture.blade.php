@props(['action', 'label', 'uid'])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="dpe-dashboard-attendance-form" data-attendance-form>
    @csrf
    <div class="dpe-camera">
        <label>Foto langsung</label>
        <video data-camera-video autoplay playsinline muted hidden aria-label="Pratinjau kamera"></video>
        <img data-camera-preview hidden alt="Foto absensi yang baru diambil">
        <canvas data-camera-canvas hidden></canvas>
        <input type="file" name="photo" data-camera-file hidden accept="image/jpeg">
        <p data-camera-message role="status">Buka kamera, lalu ambil foto sebelum menyimpan absensi.</p>
        <div class="dpe-camera__actions">
            <button class="dpe-dashboard-button dpe-dashboard-button--outline" type="button" data-camera-open><x-dashboard.icon name="camera" /> Buka kamera</button>
            <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="button" data-camera-capture disabled>Ambil foto</button>
        </div>
        @error('photo')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
    </div>
    <div class="dpe-dashboard-location-box" data-location-box>
        <div class="dpe-dashboard-location-box__head">
            <div><strong>Lokasi saat ini</strong><span data-location-message>Lokasi belum diambil.</span></div>
            <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="button" data-location-button>Ambil lokasi</button>
        </div>
        <input type="hidden" name="location_status" value="unavailable" data-location-status>
        <input type="hidden" name="latitude" data-location-latitude>
        <input type="hidden" name="longitude" data-location-longitude>
        <input type="hidden" name="accuracy" data-location-accuracy>
        <div class="dpe-dashboard-field">
            <label for="location-note-{{ $uid }}">Alasan jika lokasi tidak tersedia</label>
            <textarea id="location-note-{{ $uid }}" name="location_note" rows="2" maxlength="500" data-location-note required></textarea>
            <p class="dpe-dashboard-field__hint">Wajib jika lokasi gagal terbaca. Admin akan memeriksa foto dan alasan.</p>
            @error('location_note')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>
    @error('attendance')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
    <div class="dpe-dashboard-form-actions">
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" data-attendance-submit disabled><x-dashboard.icon name="check" /> {{ $label }}</button>
    </div>
</form>
