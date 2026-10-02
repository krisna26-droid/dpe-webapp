<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Log;

class StudentService
{
    public function __construct(
        private readonly FileAssetService $fileAssetService
    ) {
    }

    public function create(
        array $data,
        ?UploadedFile $photo = null
    ): Student {
        $newPhoto = null;

        try {
            return DB::transaction(function () use (
                $data,
                $photo,
                &$newPhoto
            ) {
                $photoFileId = null;

                if ($photo) {
                    $newPhoto = $this->fileAssetService->store(
                        $photo,
                        'student-photos'
                    );

                    $photoFileId = $newPhoto->id;
                }

                return Student::create([
                    'id' => (string) Str::uuid(),
                    'portal_user_id' => $data['portal_user_id'],
                    'full_name' => $data['full_name'],
                    'school_name' => $data['school_name'] ?? null,
                    'grade_name' => $data['grade_name'] ?? null,
                    'began_on' => $data['began_on'],
                    'status' => 'active',
                    'special_notes_internal' =>
                        $data['special_notes_internal'] ?? null,
                    'photo_file_id' => $photoFileId,
                ]);
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                try {
                    $this->fileAssetService->delete($newPhoto);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }

    public function update(
        Student $student,
        array $data,
        ?UploadedFile $photo = null
    ): Student {
        $oldPhoto = $student->photoFile;
        $newPhoto = null;

        try {
            $updatedStudent = DB::transaction(function () use (
                $student,
                $data,
                $photo,
                &$newPhoto
            ) {
                $student->portal_user_id = $data['portal_user_id'];
                $student->full_name = $data['full_name'];
                $student->school_name = $data['school_name'] ?? null;
                $student->grade_name = $data['grade_name'] ?? null;
                $student->began_on = $data['began_on'];
                $student->special_notes_internal =
                    $data['special_notes_internal'] ?? null;

                if ($photo) {
                    $newPhoto = $this->fileAssetService->store(
                        $photo,
                        'student-photos'
                    );

                    $student->photo_file_id = $newPhoto->id;
                }

                $student->save();

                return $student->fresh([
                    'portalUser',
                    'photoFile',
                ]);
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                try {
                    $this->fileAssetService->delete($newPhoto);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        // Foto lama dibersihkan setelah transaksi berhasil.
        if (
            $newPhoto
            && $oldPhoto
            && $oldPhoto->id !== $updatedStudent->photo_file_id
        ) {
            try {
                $this->fileAssetService->delete($oldPhoto);
            } catch (Throwable $exception) {
                Log::error(
                    'Gagal menghapus foto lama siswa setelah profil diperbarui.',
                    [
                        'student_id' => $updatedStudent->id,
                        'file_asset_id' => $oldPhoto->id,
                        'storage_key' => $oldPhoto->storage_key,
                        'exception' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $updatedStudent;
    }
}
