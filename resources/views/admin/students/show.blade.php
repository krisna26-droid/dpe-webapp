<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Siswa - DPE</title>
</head>
<body>
    <h1>Detail Siswa</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <p><strong>Nama:</strong> {{ $student->full_name }}</p>
    <p><strong>Username portal:</strong> {{ $student->portalUser?->username ?? '-' }}</p>
    <p><strong>Email portal:</strong> {{ $student->portalUser?->email ?? '-' }}</p>
    <p><strong>Sekolah:</strong> {{ $student->school_name ?? '-' }}</p>
    <p><strong>Kelas:</strong> {{ $student->grade_name ?? '-' }}</p>
    <p><strong>Mulai belajar:</strong> {{ $student->began_on?->format('d-m-Y') }}</p>
    <p><strong>Status:</strong> {{ $student->status }}</p>
    <p>
        <strong>Catatan internal:</strong><br>
        {{ $student->special_notes_internal ?? '-' }}
    </p>

    <a href="{{ route('admin.students.edit', $student) }}">Edit</a>
    |
    <a href="{{ route('admin.students.index') }}">Kembali ke Daftar</a>
</body>
</html>