@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="dpe-dashboard-pagination" aria-label="Navigasi halaman">
        @if ($paginator->onFirstPage())
            <span class="dpe-dashboard-pagination__button is-disabled">Sebelumnya</span>
        @else
            <a class="dpe-dashboard-pagination__button" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
        @endif

        <span class="dpe-dashboard-pagination__summary">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="dpe-dashboard-pagination__button" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
        @else
            <span class="dpe-dashboard-pagination__button is-disabled">Berikutnya</span>
        @endif
    </nav>
@endif
