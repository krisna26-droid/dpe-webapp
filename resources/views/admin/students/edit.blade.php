<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Siswa - DPE</title>
</head>
<body>
    <h1>Edit Profil Siswa</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.students.update', $student) }}">
        @csrf
        @method('PUT')

        <p>
            <label for="portal_user_id">Akun Portal Siswa</label>
            <select id="portal_user_id" name="portal_user_id" required>
                <option value="">-- Pilih akun --</option>
                @foreach ($studentUsers as $user)
                    <option value="{{ $user->id }}"
                        @selected(old('portal_user_id', $student->portal_user_id) === $user->id)>
                        {{ $user->full_name }} ({{ $user->username }})
                    </option>
                @endforeach
            </select>
        </p>

        <p>
            <label for="full_name">Nama Lengkap</label>
            <input id="full_name" name="full_name" maxlength="255"
                value="{{ old('full_name', $student->full_name) }}" required>
        </p>

        <p>
            <label for="school_name">Nama Sekolah</label>
            <input id="school_name" name="school_name" maxlength="255"
                value="{{ old('school_name', $student->school_name) }}">
        </p>

        <p>
            <label for="grade_name">Kelas</label>
            <input id="grade_name" name="grade_name" maxlength="255"
                value="{{ old('grade_name', $student->grade_name) }}">
        </p>

        <p>
            <label for="began_on">Tanggal Mulai Belajar</label>
            <input id="began_on" type="date" name="began_on"
                value="{{ old('began_on', $student->began_on?->format('Y-m-d')) }}"
                required>
        </p>

        <p>
            <label for="special_notes_internal">Catatan Internal</label>
            <textarea id="special_notes_internal" name="special_notes_internal"
                rows="4">{{ old('special_notes_internal', $student->special_notes_internal) }}</textarea>
        </p>

        <button type="submit">Simpan Perubahan</button>
        <a href="{{ route('admin.students.show', $student) }}">Batal</a>
    </form>
</body>
</html>