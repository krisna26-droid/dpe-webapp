<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Siswa - DPE</title>
</head>
<body>
    <h1>Manajemen Siswa</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <p>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a> |
        <a href="{{ route('admin.students.create') }}">Tambah Siswa</a>
    </p>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Username Portal</th>
                <th>Sekolah</th>
                <th>Kelas</th>
                <th>Mulai Belajar</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td>{{ $student->full_name }}</td>
                    <td>{{ $student->portalUser?->username ?? '-' }}</td>
                    <td>{{ $student->school_name ?? '-' }}</td>
                    <td>{{ $student->grade_name ?? '-' }}</td>
                    <td>{{ $student->began_on?->format('d-m-Y') }}</td>
                    <td>{{ $student->status }}</td>
                    <td>
                        <a href="{{ route('admin.students.show', $student) }}">
                            Detail
                        </a>
                        |
                        <a href="{{ route('admin.students.edit', $student) }}">
                            Edit
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Belum ada data siswa.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $students->links() }}

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</body>
</html>