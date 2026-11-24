@php
    $exportRoute = auth()->user()->role_code.'.users.export';
    $exportFilters = array_filter($filters, fn ($value) => $value !== '');
    $studentExportAvailable = in_array($filters['role_code'], ['', 'student'], true);
@endphp
@foreach (['pdf' => 'PDF', 'xlsx' => 'Excel'] as $format => $label)
    @if ($studentExportAvailable)
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($exportRoute, ['format' => $format] + $exportFilters) }}" aria-label="Ekspor akun siswa {{ $label }}" title="Ekspor seluruh akun siswa sesuai filter ({{ $label }})">
            <x-dashboard.icon name="report" /><span>Ekspor {{ $label }}</span>
        </a>
    @else
        <button type="button" class="dpe-dashboard-button dpe-dashboard-button--outline" disabled title="Pilih Semua role atau Siswa / Parent untuk ekspor akun siswa">
            <x-dashboard.icon name="report" /><span>Ekspor {{ $label }}</span>
        </button>
    @endif
@endforeach
