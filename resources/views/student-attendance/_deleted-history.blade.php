@if ($deletedRecords->isNotEmpty())
    <article class="dpe-dashboard-card dpe-student-attendance-history">
        <header class="dpe-dashboard-card__head"><h2>Riwayat kehadiran siswa terhapus</h2></header>
        <div class="dpe-dashboard-table-wrap">
            <table class="dpe-dashboard-table">
                <thead><tr><th>Siswa</th><th>Kehadiran tercatat</th><th>Dicatat pertama</th><th>Riwayat</th></tr></thead>
                <tbody>
                    @foreach ($deletedRecords as $record)
                        <tr>
                            <td><strong>{{ $record->student->full_name }}</strong> <small class="dpe-dashboard-badge">Siswa dihapus</small></td>
                            <td>{{ $statuses[$record->attendance_status] ?? ($record->attendance_status ?: 'Belum dicatat') }}</td>
                            <td>{{ $record->recorded_at?->format('d M Y H:i') ?: '—' }}</td>
                            <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.edit', [$lessonSession, $record->student_id]) }}">Lihat riwayat</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>
@endif
