@once
@push('head')<link rel="stylesheet" href="{{ asset('assets/dpe/report-signatures.css') }}?v={{ filemtime(public_path('assets/dpe/report-signatures.css')) }}">@endpush
@endonce
<article class="dpe-dashboard-card dpe-report-signature-card">
    <header class="dpe-dashboard-card__head"><div><h2>Tanda tangan {{ $signatureScope === 'teacher' ? 'Teacher' : 'Manager' }}</h2><p>Disertakan dalam PDF rapor sesuai penandatangan yang tersimpan saat persetujuan.</p></div></header>
    <div class="dpe-dashboard-card__body">
        <p>Penandatangan: <strong>{{ $signatureState['signer_name'] }}</strong></p>
        @if($signatureState['record'] && $signatureState['name_matches'] && $signatureState['file_valid'])
            <div class="dpe-report-signature-preview"><img src="{{ route($signatureRouteBase.'.image', ['v' => $signatureState['record']->id]) }}" alt="Tanda tangan {{ $signatureState['signer_name'] }}"></div>
            <p class="dpe-dashboard-field__hint">Tersimpan · versi {{ $signatureState['record']->revision }}. Pilih berkas baru untuk menggantinya pada persetujuan berikutnya.</p>
        @elseif($signatureState['record'] && !$signatureState['name_matches'])
            <p class="dpe-report-signature-notice">Nama penandatangan telah berubah. Unggah ulang untuk nama di atas; tanda tangan atas nama {{ $signatureState['record']->signer_name }} tidak digunakan pada rapor baru.</p>
        @elseif($signatureState['record'])
            <p class="dpe-report-signature-notice">Berkas tanda tangan tidak tersedia atau berubah. Unggah ulang berkas yang benar sebelum melanjutkan rapor.</p>
        @else
            <p class="dpe-dashboard-field__hint">Tanda tangan belum diunggah. PDF sementara menampilkan area tanda tangan.</p>
        @endif
        <form method="POST" action="{{ route($signatureRouteBase.'.store') }}" enctype="multipart/form-data" class="dpe-report-signature-form">
            @csrf
            <input type="hidden" name="signature_version" value="{{ $signatureState['version'] }}">
            <div class="dpe-dashboard-field">
                <label for="signature-{{ $signatureScope }}">Berkas tanda tangan</label>
                <input id="signature-{{ $signatureScope }}" type="file" name="signature_file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" required aria-describedby="signature-hint-{{ $signatureScope }}">
                <p id="signature-hint-{{ $signatureScope }}" class="dpe-dashboard-field__hint">PNG atau JPG, maksimal 512 KB dan 2000 x 2000 piksel. Gunakan PNG dengan latar benar-benar transparan, tanpa pola kotak-kotak yang menyatu pada gambar. Kurangi ruang kosong berlebih di sekeliling tanda tangan agar goresannya terlihat jelas. Jika mengubah nama, simpan {{ $signatureScope === 'teacher' ? 'profil' : 'template' }} terlebih dahulu sebelum mengunggah tanda tangan.</p>
                @error('signature_file')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                @error('signature_version')<p class="dpe-dashboard-field__error">{{ $message }} <a href="{{ url()->current() }}">Muat halaman terbaru</a>.</p>@enderror
            </div>
            <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Simpan tanda tangan</button>
        </form>
        <p class="dpe-dashboard-field__hint">Unggah sebelum Admin menyetujui rapor, lalu periksa Preview PDF. Rapor yang sudah disetujui tetap memakai salinan sebelumnya.</p>
    </div>
</article>
