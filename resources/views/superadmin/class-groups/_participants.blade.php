<div class="dpe-dashboard-table-wrap">
    <table class="dpe-dashboard-table">
        <thead>
            <tr>
                <th>Siswa</th>
                <th>Mulai</th>
                <th>Berakhir</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memberships as $membership)
                @php
                    $membershipActive = $membership->isActiveParticipant($membershipDate);
                    $studentDeleted = $membership->student?->trashed() ?? false;
                    $membershipStatus = $membershipActive ? 'Aktif' : 'Selesai';
                    if ($studentDeleted) {
                        $membershipStatus = 'Siswa dihapus';
                    } elseif ($membership->student === null) {
                        $membershipStatus = 'Siswa tidak ditemukan';
                    } elseif ($membership->starts_on?->gt($membershipDate)) {
                        $membershipStatus = 'Belum mulai';
                    }
                @endphp
                <tr>
                    <td>
                        <a href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">
                            <strong>{{ $membership->student?->full_name ?: 'Siswa tidak ditemukan' }}</strong>
                        </a>
                        @if ($membership->student?->portalUser?->username)<br><span>{{ $membership->student->portalUser->username }}</span>@endif
                    </td>
                    <td>{{ $membership->starts_on?->format('d M Y') ?: '—' }}</td>
                    <td>{{ $membership->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                    <td>
                        <span class="dpe-dashboard-badge {{ $membershipActive ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                            {{ $membershipStatus }}
                        </span>
                    </td>
                    <td>
                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">Detail</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
