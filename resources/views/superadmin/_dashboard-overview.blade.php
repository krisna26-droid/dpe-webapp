<section class="dpe-dashboard-overview" aria-label="Monitoring rapor dan ringkasan cabang">
    <article class="dpe-dashboard-card dpe-dashboard-overview__card">
        <header class="dpe-dashboard-overview__head">
            <div>
                <h2>Monitoring rapor bulanan</h2>
                <p>{{ $overview['month_label'] }} · Completed = Approved; Overdue bagian dari Pending.</p>
            </div>
            <form class="dpe-dashboard-overview__filter" method="GET" action="{{ route('superadmin.dashboard') }}">
                <label for="dashboard-report-month"><span>Periode rapor</span><input id="dashboard-report-month" type="month" name="month" value="{{ $overview['month'] }}" min="2000-01" max="2100-12" required></label>
                <button class="dpe-dashboard-button dpe-dashboard-button--outline" type="submit">Tampilkan</button>
            </form>
        </header>
        <div class="dpe-dashboard-overview__body">
            <div class="dpe-dashboard-overview__statuses" aria-label="Status rapor bulanan">
                <div class="dpe-dashboard-overview__status is-completed"><span>Completed</span><div><strong>{{ number_format($overview['summary']['approved'], 0, ',', '.') }}</strong><small>Sudah Approved</small></div></div>
                <div class="dpe-dashboard-overview__status is-pending"><span>Pending</span><div><strong>{{ number_format($overview['summary']['pending'], 0, ',', '.') }}</strong><small>Belum Approved</small></div></div>
                <div class="dpe-dashboard-overview__status is-overdue"><span>Overdue</span><div><strong>{{ number_format($overview['summary']['overdue'], 0, ',', '.') }}</strong><small>{{ number_format($overview['summary']['overdue'], 0, ',', '.') }} dari {{ number_format($overview['summary']['pending'], 0, ',', '.') }} Pending</small></div></div>
            </div>
            <div class="dpe-dashboard-overview__table-wrap">
                <table class="dpe-dashboard-table dpe-dashboard-overview__table">
                    <caption class="sr-only">Teacher perlu perhatian pada {{ $overview['month_label'] }}</caption>
                    <colgroup><col style="width:48%"><col style="width:28%"><col style="width:24%"></colgroup>
                    <thead><tr><th scope="col">Teacher perlu perhatian</th><th scope="col">Cabang</th><th scope="col">Pending</th></tr></thead>
                    <tbody>
                        @forelse ($overview['teacher_attention'] as $teacher)
                            <tr><td><strong>{{ $teacher['teacher_name'] }}</strong></td><td>{{ $teacher['branch_name'] }}</td><td class="dpe-dashboard-overview__pending">{{ number_format($teacher['pending'], 0, ',', '.') }} rapor</td></tr>
                        @empty
                            <tr><td colspan="3">{{ $overview['summary']['total'] === 0 ? 'Belum ada periode rapor pada bulan ini.' : 'Tidak ada rapor Pending pada periode ini.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="dpe-dashboard-overview__footer">
                @if ($overview['teacher_attention_total'] > 3)<p>Menampilkan 3 dari {{ number_format($overview['teacher_attention_total'], 0, ',', '.') }} penugasan Teacher/cabang yang perlu perhatian.</p>@endif
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('monitoring.index', ['month' => $overview['month']]) }}">Lihat monitoring</a>
            </footer>
        </div>
    </article>
    <aside class="dpe-dashboard-card dpe-dashboard-overview__card" aria-labelledby="dashboard-branch-summary-title">
        <header class="dpe-dashboard-overview__head"><div><h2 id="dashboard-branch-summary-title">Ringkasan cabang</h2><p>Siswa aktif per cabang · {{ $overview['branch_as_of'] }}</p></div></header>
        <div class="dpe-dashboard-overview__branches">
            @forelse ($overview['branches'] as $branch)
                <div class="dpe-dashboard-overview__branch"><span>{{ $branch['name'] }}</span><progress value="{{ $branch['students'] }}" max="{{ $overview['branch_max'] }}" aria-label="{{ $branch['name'] }}: {{ $branch['students'] }} siswa aktif">{{ $branch['students'] }}</progress><strong>{{ number_format($branch['students'], 0, ',', '.') }}</strong></div>
            @empty
                <x-dashboard.empty title="Belum ada cabang aktif" message="Ringkasan siswa akan tampil setelah cabang aktif tersedia." icon="building" />
            @endforelse
        </div>
    </aside>
</section>
