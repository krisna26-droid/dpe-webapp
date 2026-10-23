@props([
    'label',
    'value' => '—',
    'meta' => 'Belum tersedia',
    'tone' => 'blue',
    'icon' => 'dashboard',
    'status' => null,
])

<article {{ $attributes->class(['dpe-dashboard-metric', 'is-'.$tone]) }}>
    <div class="dpe-dashboard-metric__icon">
        <x-dashboard.icon :name="$icon" />
    </div>
    <div class="dpe-dashboard-metric__body">
        <p class="dpe-dashboard-metric__label">{{ $label }}</p>
        <p class="dpe-dashboard-metric__value">@if ($status !== null)<x-dashboard.status :status="$status" :label="$value" />@else{{ $value }}@endif</p>
        <p class="dpe-dashboard-metric__meta">{{ $meta }}</p>
    </div>
</article>
