@props(['title', 'description' => 'Informasi sedang disiapkan. Silakan kembali lagi atau lihat halaman kontak.', 'icon' => 'book'])
<div {{ $attributes->class(['empty-state']) }}>
    <span class="icon-tile"><x-dpe.icon :name="$icon" /></span>
    <h2>{{ $title }}</h2>
    <p>{{ $description }}</p>
</div>
