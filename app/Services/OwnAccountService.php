<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OwnAccountService
{
    public function __construct(private OperationsAccessService $access, private FileAssetService $files) {}

    public function actor(User $user, ?string $role = null, bool $lock = false): User
    {
        $actor = $lock ? User::whereKey($user->id)->lockForUpdate()->firstOrFail() : $this->access->actor($user);
        abort_unless($actor->is_active && in_array($actor->role_code, ['teacher', 'student'], true)
            && ($role === null || $actor->role_code === $role), 403);

        return $actor;
    }

    public function teacher(User $actor, bool $lock = false, bool $active = false): ?Teacher
    {
        $query = Teacher::where('user_id', $actor->id);
        if ($lock) { $query->lockForUpdate(); }
        $teacher = $query->first();
        if ($active) { abort_unless($teacher?->is_active, 403, 'Profil Teacher aktif belum tersedia. Hubungi Admin.'); }

        return $teacher;
    }

    public function version(User $user, Teacher $teacher): string
    {
        $snapshot = [
            'user' => array_intersect_key($user->getRawOriginal(), array_flip(['id', 'username', 'full_name', 'email', 'role_code', 'is_active'])),
            'teacher' => array_intersect_key($teacher->getRawOriginal(), array_flip(['id', 'user_id', 'whatsapp_number', 'photo_file_id', 'is_active'])),
        ];
        foreach ($snapshot as &$values) { ksort($values); }

        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function saveProfile(User $user, array $data, ?UploadedFile $photo = null): Teacher
    {
        $removePhoto = filter_var($data['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($photo && $removePhoto) {
            throw ValidationException::withMessages(['photo' => 'Pilih unggah foto baru atau hapus foto profil, salah satu saja.']);
        }
        $newPhoto = null;
        $oldPhoto = null;
        try {
            $updated = DB::transaction(function () use ($user, $data, $photo, $removePhoto, &$newPhoto, &$oldPhoto) {
                $actor = $this->actor($user, 'teacher', true);
                $teacher = $this->teacher($actor, true, true);
                if (! hash_equals($this->version($actor, $teacher), $data['record_version'])) {
                    throw ValidationException::withMessages(['record_version' => 'Profil sudah berubah sejak halaman dibuka. Muat ulang dan periksa data terbaru sebelum menyimpan.']);
                }
                $email = $data['email'] ?? null;
                if ($email && User::where('email', $email)->where('id', '!=', $actor->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'Email tersebut sudah dipakai akun lain.']);
                }
                $oldPhoto = $teacher->photoFile;
                if ($photo) { $newPhoto = $this->files->store($photo, 'teacher-photos'); }
                // Never accept account/Teacher ids, role, username, active flags, or a referenced asset from input.
                $actor->update(['full_name' => $data['full_name'], 'email' => $email]);
                $teacher->whatsapp_number = $data['whatsapp_number'];
                if ($newPhoto) { $teacher->photo_file_id = $newPhoto->id; }
                elseif ($removePhoto) { $teacher->photo_file_id = null; }
                $teacher->save();

                return $teacher->fresh(['user', 'photoFile']);
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                try { $this->files->delete($newPhoto); } catch (Throwable $cleanup) { report($cleanup); }
            }
            throw $exception;
        }
        // Cleanup happens after the outer transaction commits. Shared/retained references are protected by FK checks.
        if ($oldPhoto && $updated->photo_file_id !== $oldPhoto->id) {
            try { $this->files->delete($oldPhoto); } catch (Throwable $cleanup) { report($cleanup); }
        }

        return $updated;
    }

    public function changePassword(User $user, string $role, array $data): void
    {
        DB::transaction(function () use ($user, $role, $data) {
            $actor = $this->actor($user, $role, true);
            try { $matches = Hash::check($data['current_password'], $actor->password_hash); }
            catch (RuntimeException) { $matches = false; }
            if (! $matches) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi lama tidak sesuai.']);
            }
            if ($data['current_password'] === $data['password']) {
                throw ValidationException::withMessages(['password' => 'Gunakan kata sandi baru yang berbeda dari kata sandi lama.']);
            }
            $actor->update(['password_hash' => Hash::make($data['password'])]);
        });
    }
}
