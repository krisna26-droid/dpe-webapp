@extends('layouts.dashboard')
@section('title', 'Edit Konten Website')
@section('heading', 'Edit Konten Website')
@section('description', 'Simpan dan periksa draft sebelum mengubah informasi publik.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/website-content.css') }}?v={{ filemtime(public_path('assets/dpe/website-content.css')) }}">
@endpush
@section('content')
    @php
        $definition = \App\Services\WebsiteContentDefinition::class;
        $imageAllowed = $definition::imageAllowed($key);
        $visibilityAllowed = $definition::visibilityAllowed($key);
        $liveFields = $definition::body($live?->body);
        $isMaster = $master !== null;
    @endphp
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">{{ $definition::TABS[$tab] }}</p><h2>{{ $master?->name ?? $label }}</h2><p>Pratinjau dan Tayangkan menggunakan draft yang sudah tersimpan.</p></div><div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.website.index', ['tab' => $tab]) }}"><x-dashboard.icon name="back" />Kembali ke Konten Publik</a></div></section>
    @if ($conflict)<div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">Versi website berubah setelah draft ini disimpan. Bandingkan isinya, lalu Simpan Draft kembali sebelum menayangkan.</div>@endif
    <div class="dpe-website-editor-grid">
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Isi draft</h2><p>Perubahan pada form ini berlaku setelah disimpan dan ditayangkan.</p></div></header><div class="dpe-dashboard-card__body">
            <form method="POST" action="{{ route('superadmin.website.save', $key) }}" enctype="multipart/form-data" class="dpe-website-form">@csrf @method('PUT')
                <input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}">
                <div class="dpe-dashboard-field"><label for="website-title">{{ $isMaster ? 'Nama '.$definition::TABS[$tab] : ($tab === 'identity' ? 'Nama lembaga' : 'Judul') }}</label><input id="website-title" name="title" required maxlength="255" value="{{ $isMaster ? $master->name : old('title', $payload['title']) }}" @readonly($isMaster)>@if ($isMaster)<small class="dpe-dashboard-field__hint">Nama mengikuti menu {{ $definition::TABS[$tab] }}.</small>@endif @error('title')<small class="dpe-website-field-error">{{ $message }}</small>@enderror</div>
                @foreach ($definition::fields($key) as $field => $fieldLabel)
                    <div class="dpe-dashboard-field"><label for="website-{{ $field }}">{{ $fieldLabel }}</label>
                        @if (in_array($field, ['description', 'detail', 'features', 'service_hours']))<textarea id="website-{{ $field }}" name="{{ $field }}" rows="{{ $field === 'features' ? 5 : 4 }}" maxlength="5000">{{ old($field, $fields[$field]) }}</textarea>
                        @else<input id="website-{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $fields[$field]) }}">@endif
                        @if (str_ends_with($field, '_url'))<small class="dpe-dashboard-field__hint">Contoh: #program, #kontak, /kontak, atau https://alamat-website.</small>@endif
                        @error($field)<small class="dpe-website-field-error">{{ $message }}</small>@enderror
                    </div>
                @endforeach
                @if ($imageAllowed)<section class="dpe-website-photo-panel"><h3>{{ $tab === 'identity' ? 'Logo website' : 'Foto' }}</h3>
                    @if ($payload['image_file_id'])<img class="dpe-website-photo" src="{{ route('superadmin.website.image', $payload['image_file_id']) }}" alt="Foto yang tersimpan" width="420" height="250"><label class="dpe-dashboard-checkbox"><input type="checkbox" name="remove_image" value="1" @checked(old('remove_image', false))>Hapus foto dari draft ini</label>@endif
                    <div class="dpe-dashboard-field"><label for="website-image">{{ $payload['image_file_id'] ? 'Ganti foto' : 'Unggah foto' }}</label><input id="website-image" name="image" type="file" accept="image/jpeg,image/png,image/webp"><small class="dpe-dashboard-field__hint">JPG, PNG, atau WebP. Maksimal {{ rtrim(rtrim(number_format($definition::uploadLimitKb() / 1024, 2, ',', '.'), '0'), ',') }} MB dan 4.096 × 4.096 piksel. Foto baru tampil publik setelah Tayangkan.</small>@error('image')<small class="dpe-website-field-error">{{ $message }}</small>@enderror</div>
                </section>@endif
                <div class="dpe-dashboard-field"><label for="website-order">Urutan tampilan</label><input id="website-order" name="display_order" type="number" min="0" max="32767" required value="{{ old('display_order', $payload['display_order']) }}"><small class="dpe-dashboard-field__hint">Angka lebih kecil tampil lebih dahulu dalam kelompoknya.</small>@error('display_order')<small class="dpe-website-field-error">{{ $message }}</small>@enderror</div>
                @if ($visibilityAllowed)<input type="hidden" name="is_published" value="0"><label class="dpe-dashboard-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $payload['is_published']))>Tampilkan di website saat draft ditayangkan</label>@else<input type="hidden" name="is_published" value="1">@endif
                <div class="dpe-website-actions dpe-website-save"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Simpan Draft</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.website.index', ['tab' => $tab]) }}">Kembali</a></div>
            </form>
        </div></article>
        <div class="dpe-website-review-stack">
            <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Versi website saat ini</h2><p>{{ $live?->is_published && (! $master || $master->is_active) ? 'Sedang ditayangkan' : 'Belum tampil di website' }}</p></div></header><div class="dpe-dashboard-card__body">@if ($live)<strong>{{ $live->title }}</strong><p class="dpe-website-readonly">{{ is_string($liveFields['description'] ?? null) ? $liveFields['description'] : '' }}</p><details class="dpe-website-details"><summary>Lihat isi versi website</summary>@foreach ($definition::fields($key) as $field => $fieldLabel)<div><strong>{{ $fieldLabel }}</strong><p class="dpe-website-readonly">{{ is_array($liveFields[$field] ?? null) ? implode("\n", array_filter($liveFields[$field], 'is_string')) : (is_string($liveFields[$field] ?? null) ? $liveFields[$field] : '—') }}</p></div>@endforeach</details>@else<p>Bagian ini belum memiliki konten yang ditayangkan.</p>@endif</div></article>
            <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Draft tersimpan</h2><p>{{ $draft ? 'Siap diperiksa' : 'Simpan draft untuk membuka pratinjau' }}</p></div></header><div class="dpe-dashboard-card__body dpe-website-publication">
                @if ($draft)<strong>{{ $draft->payload['title'] }}</strong><p>{{ $draft->saved_by_name }} · {{ $draft->saved_at->format('d/m/Y H:i') }}</p><p class="dpe-website-readonly">{{ is_string(($definition::body($draft->payload['body'])['description'] ?? null)) ? $definition::body($draft->payload['body'])['description'] : '' }}</p>
                    @if (! $draft->payload['is_published'])<p class="dpe-website-hint">Draft ini akan menyembunyikan bagian tersebut dari website.</p>@endif
                    @if ($master && ! $master->is_active)<p class="dpe-website-hint">{{ $definition::TABS[$tab] }} sedang nonaktif. Konten tidak dapat ditampilkan sebelum data operasionalnya aktif.</p>@endif
                    <div class="dpe-website-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.website.preview', $key) }}" target="_blank" rel="noopener noreferrer">Pratinjau Draft</a>
                        <form method="POST" action="{{ route('superadmin.website.publish', $key) }}" data-confirm="{{ $draft->payload['is_published'] ? 'Tayangkan draft tersimpan untuk bagian ini?' : 'Terapkan draft dan sembunyikan bagian ini dari website?' }}" data-confirm-title="Tayangkan perubahan?" data-confirm-submit="Ya, terapkan">@csrf<input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary" @disabled($conflict || ($master && ! $master->is_active && $draft->payload['is_published']))>Tayangkan</button></form>
                    </div>
                    <form method="POST" action="{{ route('superadmin.website.discard', $key) }}" data-confirm="Buang draft ini? Konten website yang sudah tayang tetap menggunakan versi sebelumnya." data-confirm-title="Buang draft?" data-confirm-submit="Ya, buang draft">@csrf @method('DELETE')<input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--danger">Buang Draft</button></form>
                @else<p>Isi form dan klik Simpan Draft. Pratinjau selalu memakai versi draft yang terakhir disimpan.</p>@endif
                @if ($visibilityAllowed && $live?->is_published)<form method="POST" action="{{ route('superadmin.website.hide', $key) }}" data-confirm="Sembunyikan bagian ini dari website? Data operasional dan riwayatnya tetap tersimpan." data-confirm-title="Sembunyikan dari website?" data-confirm-submit="Ya, sembunyikan">@csrf<input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--outline">Sembunyikan dari Website</button></form>@endif
                @if (isset($definition::REPEATED[$definition::kind($key)]) && ($live || $draft))<form method="POST" action="{{ route('superadmin.website.remove', $key) }}" data-confirm="Hapus bagian tambahan ini beserta draftnya? Riwayat perubahan tetap tersimpan." data-confirm-title="Hapus bagian tambahan?" data-confirm-submit="Ya, hapus bagian">@csrf @method('DELETE')<input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--danger">Hapus Bagian Tambahan</button></form>@endif
            </div></article>
        </div>
    </div>
    @if ($history->isNotEmpty())<article class="dpe-dashboard-card dpe-website-history"><header class="dpe-dashboard-card__head"><div><h2>Riwayat bagian ini</h2><p>15 aktivitas terakhir pada konten yang dipilih.</p></div></header><div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Waktu</th><th>Operator</th><th>Tindakan</th></tr></thead><tbody>@foreach ($history as $change)<tr><td>{{ $change->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $change->actor_name }}</td><td>{{ ['save_draft' => 'Simpan draft', 'publish' => 'Tayangkan', 'hide' => 'Sembunyikan', 'discard_draft' => 'Buang draft', 'remove_content' => 'Hapus bagian tambahan'][$change->action] ?? 'Perubahan konten' }}</td></tr>@endforeach</tbody></table></div></article>@endif
@endsection
