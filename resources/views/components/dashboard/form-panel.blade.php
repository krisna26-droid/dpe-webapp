@props(['title', 'description' => null, 'closeUrl'])

<section class="dpe-dashboard-form-page">
    <article {{ $attributes->class(['dpe-dashboard-form-panel']) }}>
        <header class="dpe-dashboard-form-panel__head">
            <div>
                <h2>{{ $title }}</h2>
                @if ($description)<p>{{ $description }}</p>@endif
            </div>
            <a class="dpe-dashboard-form-panel__close" href="{{ $closeUrl }}" aria-label="Tutup formulir dan kembali">
                <x-dashboard.icon name="close" />
            </a>
        </header>
        <div class="dpe-dashboard-form-panel__body">
            {{ $slot }}
        </div>
    </article>
</section>
