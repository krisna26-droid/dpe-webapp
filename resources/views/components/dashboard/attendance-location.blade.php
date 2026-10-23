@props(['point' => null, 'lookupUrl' => null, 'emptyMessage' => 'Lokasi tidak tersedia'])

@if ($point)
    <div class="dpe-attendance-location" data-attendance-location data-location-url="{{ $lookupUrl }}">
        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal" data-location-address role="status" aria-live="polite">Alamat belum tersedia. Titik lokasi dapat dilihat di peta.</p>
        <p class="dpe-attendance-location__coordinates">{{ $point['coordinates'] }}</p>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline dpe-attendance-location__map" href="{{ $point['map_url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">
            <x-dashboard.icon name="globe" />
            <span>Lihat Peta</span>
        </a>
        <small class="dpe-attendance-location__credit" data-location-credit hidden>Sumber alamat: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a></small>
    </div>
@else
    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $emptyMessage }}</p>
@endif
