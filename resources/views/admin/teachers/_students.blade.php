<article class="dpe-dashboard-card dpe-dashboard-card--spaced dpe-teaching-students-card" id="teacher-students">
    <header class="dpe-dashboard-card__head">
        <div>
            <h2>Siswa yang diajar</h2>
            <p>{{ $teachingStudents->total() }} siswa aktif pada cabang yang Anda kelola.</p>
        </div>
    </header>
    @if ($teachingStudents->isEmpty())
        <x-dashboard.empty title="Belum ada siswa aktif" message="Siswa tampil setelah memiliki penugasan mengajar atau keanggotaan kelompok kelas aktif dengan Teacher ini." icon="student" />
    @else
        <div class="dpe-dashboard-table-wrap">
            <table class="dpe-dashboard-table">
                <thead><tr><th>Siswa</th><th>Akun portal</th><th>Program / cabang</th><th>Aksi</th></tr></thead>
                <tbody>
                    @foreach ($teachingStudents as $teachingStudent)
                        <tr>
                            <td><strong>{{ $teachingStudent->full_name }}</strong></td>
                            <td>{{ $teachingStudent->portalUser?->username ?: 'Belum memiliki akun' }}</td>
                            <td>
                                <ul class="dpe-teaching-names">
                                    @foreach ($teachingStudent->enrollments as $enrollment)
                                        <li>{{ $enrollment->program?->name ?: 'Program' }} · {{ $enrollment->branch?->name ?: 'Cabang' }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.students.show', $teachingStudent) }}">Detail siswa</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-dashboard.pagination :paginator="$teachingStudents" />
    @endif
</article>
