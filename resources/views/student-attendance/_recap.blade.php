<td class="dpe-student-attendance-recap" data-attendance-row-summary data-attendance-summary="{{ json_encode($row['summary']) }}">
    <strong><span data-attendance-count="attended_count">{{ $row['summary']['attended_count'] }}</span>/<span data-attendance-count="meeting_count">{{ $row['summary']['meeting_count'] }}</span> hadir</strong>
    <small><span data-attendance-count="present">{{ $row['summary']['present'] }}</span> tepat waktu · <span data-attendance-count="late">{{ $row['summary']['late'] }}</span> terlambat</small>
    <small><span data-attendance-count="absent">{{ $row['summary']['absent'] }}</span> tidak hadir · <span data-attendance-count="sick">{{ $row['summary']['sick'] }}</span> sakit · <span data-attendance-count="permission">{{ $row['summary']['permission'] }}</span> izin</small>
    <small><span data-attendance-count="unrecorded">{{ $row['summary']['unrecorded'] }}</span> belum dicatat · <span data-attendance-count="rescheduled">{{ $row['summary']['rescheduled'] }}</span> jadwal ulang</small>
    <small data-attendance-unknown-note @if (! $row['summary']['unknown']) hidden @endif><span data-attendance-count="unknown">{{ $row['summary']['unknown'] }}</span> status lama</small>
</td>
