@props(['confirmUrl', 'approveUrl', 'expectedSnapshot', 'label', 'confirmLabel', 'disabled' => false])

@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('assets/dpe/report-approval.css') }}?v={{ filemtime(public_path('assets/dpe/report-approval.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/dpe/report-approval.js') }}?v={{ filemtime(public_path('assets/dpe/report-approval.js')) }}" defer></script>
    @endpush
@endonce

<div class="dpe-report-approval" data-report-approval>
    <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ $confirmUrl }}" data-report-approval-open>{{ $label }}</a>
    <form method="POST" action="{{ $approveUrl }}" class="dpe-report-approval__form" aria-label="{{ $confirmLabel }}" data-report-approval-form hidden>
        @csrf
        <input type="hidden" name="expected_snapshot" value="{{ old('expected_snapshot', $expectedSnapshot) }}">
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" data-report-approval-submit @disabled($disabled)>{{ $confirmLabel }}</button>
        <button class="dpe-dashboard-button dpe-dashboard-button--outline" type="button" data-report-approval-cancel>Batal</button>
    </form>
</div>
