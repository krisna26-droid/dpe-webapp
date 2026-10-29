<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class StudentAccountInput
{
    public static function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'full_name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'max:255'],
            'grade_name' => ['required', 'string', 'max:255'], 'school_name' => ['required', 'string', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,28}[0-9]$/'],
            'branch_id' => ['required', 'string', 'max:36'], 'teacher_id' => ['required', 'string', 'max:36'],
            'learning_session_ids' => ['required', 'array', 'min:1', 'max:100'], 'learning_session_ids.*' => ['required', 'string', 'max:40', 'distinct'],
            'id' => ['prohibited'], 'portal_user_id' => ['prohibited'], 'student_id' => ['prohibited'],
        ];
    }

    public static function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan. Pilih username lain.',
            'guardian_phone.regex' => 'Masukkan nomor telepon wali yang valid, minimal 8 angka.',
            'learning_session_ids.required' => 'Pilih minimal satu sesi belajar.', 'learning_session_ids.min' => 'Pilih minimal satu sesi belajar.',
            'password.min' => 'Password minimal 8 karakter.', 'grade_name.required' => 'Isi kelas siswa di sekolah.',
            'school_name.required' => 'Isi asal sekolah siswa.', 'teacher_id.required' => 'Pilih nama guru.',
            'guardian_phone.required' => 'Isi nomor telepon wali siswa.', 'branch_id.required' => 'Pilih cabang.',
        ];
    }
}
