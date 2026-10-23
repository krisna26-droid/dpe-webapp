@props([
    'title' => 'Belum ada data',
    'message' => 'Data akan muncul setelah aktivitas tersedia.',
    'actionUrl' => null,
    'actionLabel' => null,
    'icon' => 'book',
])

<div {{ $attributes->class(['dpe-dashboard-state', 'dpe-dashboard-state--empty']) }}>
    <div class="dpe-dashboard-state__icon">
        <x-dashboard.icon :name="$icon" />
    </div>
    <div class="dpe-dashboard-state__body">
        <h3>{{ $title }}</h3>
        <p>{{ $message }}</p>
        @if ($actionUrl && $actionLabel)
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $actionUrl }}">
                <span>{{ $actionLabel }}</span>
                <x-dashboard.icon name="arrow" />
            </a>
        @endif
    </div>
</div>
