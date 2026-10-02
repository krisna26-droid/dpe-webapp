<?php

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Log;

class TeacherService
{
    public function __construct(
        private readonly FileAssetService $fileAssetService
    ) {
    }

    public function create(
        array $data,
        ?UploadedFile $photo = null
    ): Teacher {
        $newPhoto = null;

        try {
            return DB::transaction(function () use (
                $data,
                $photo,
                &$newPhoto
            ) {
                $photoFileId = $data['photo_file_id'] ?? null;

                if ($photo) {
                    $newPhoto = $this->fileAssetService->store(
                        $photo,
                        'teacher-photos'
                    );

                    $photoFileId = $newPhoto->id;
                }

                return Teacher::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $data['user_id'],
                    'whatsapp_number' => trim(
                        $data['whatsapp_number']
                    ),
                    'photo_file_id' => $photoFileId,
                    'is_active' => true,
                ]);
            });
        } catch (Throwable $exception) {
            $this->cleanupNewPhoto($newPhoto);

            throw $exception;
        }
    }

    public function update(
        Teacher $teacher,
        array $data,
        ?UploadedFile $photo = null
    ): Teacher {
        $oldPhoto = $teacher->photoFile;
        $newPhoto = null;

        try {
            $updatedTeacher = DB::transaction(function () use (
                $teacher,
                $data,
                $photo,
                &$newPhoto
            ) {
                $teacher->user_id = $data['user_id'];
                $teacher->whatsapp_number = trim(
                    $data['whatsapp_number']
                );

                if ($photo) {
                    $newPhoto = $this->fileAssetService->store(
                        $photo,
                        'teacher-photos'
                    );

                    $teacher->photo_file_id = $newPhoto->id;
                } elseif (array_key_exists('photo_file_id', $data)) {
                    $teacher->photo_file_id = $data['photo_file_id'];
                }

                $teacher->save();

                return $teacher->fresh([
                    'user',
                    'photoFile',
                ]);
            });
        } catch (Throwable $exception) {
            $this->cleanupNewPhoto($newPhoto);

            throw $exception;
        }

        // Foto lama dibersihkan setelah transaksi berhasil.
        if (
            $newPhoto
            && $oldPhoto
            && $oldPhoto->id !== $updatedTeacher->photo_file_id
        ) {
            try {
                $this->fileAssetService->delete($oldPhoto);
            } catch (Throwable $exception) {
                Log::error(
                    'Gagal menghapus foto lama guru setelah profil diperbarui.',
                    [
                        'teacher_id' => $updatedTeacher->id,
                        'file_asset_id' => $oldPhoto->id,
                        'storage_key' => $oldPhoto->storage_key,
                        'exception' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $updatedTeacher;
    }

    private function cleanupNewPhoto(
        mixed $newPhoto
    ): void {
        if (! $newPhoto) {
            return;
        }

        try {
            $this->fileAssetService->delete($newPhoto);
        } catch (Throwable $cleanupException) {
            // Catat kegagalan pembersihan tanpa menutupi
            // exception utama dari transaksi.
            report($cleanupException);
        }
    }
}
