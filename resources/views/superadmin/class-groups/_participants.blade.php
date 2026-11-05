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
                <tr>
                    <td>
                        <a href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">
                            <strong>{{ $membership->student?->full_name ?: 'Siswa tidak ditemukan' }}</strong>
                        </a>
                        @if ($membership->student?->portalUser?->username)<br><span>{{ $membership->student->portalUser->username }}</span>@endif
                    </td>
                    <td>{{ $membership->starts_on?->format('d M Y') ?: '—' }}</td>
                    <td>{{ $membership->ends_on?->format('d M Y') ?? ($membership->student?->trashed() ? '—' : 'Berjalan') }}</td>
                    <td>
                        <x-dashboard.class-membership-status :membership="$membership" :date="$membershipDate" />
                    </td>
                    <td>
                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">Detail</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
