<?php

namespace App\Services;

use App\Models\FileAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FileAssetService
{
    public function store(
        UploadedFile $file,
        string $directory
    ): FileAsset {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();

        $storageKey = $directory
            . '/'
            . $uuid
            . '.'
            . $extension;

        $disk = Storage::disk('local');

        $storedPath = $disk->putFileAs(
            $directory,
            $file,
            basename($storageKey)
        );

        if ($storedPath === false) {
            throw new RuntimeException(
                'File gagal disimpan ke storage.'
            );
        }

        try {
            return FileAsset::create([
                'id' => $uuid,
                'storage_key' => $storageKey,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sha256_hex' => hash_file(
                    'sha256',
                    $file->getRealPath()
                ),
            ]);
        } catch (Throwable $exception) {
            // Jika metadata gagal dibuat, bersihkan file fisik.
            try {
                $disk->delete($storageKey);
            } catch (Throwable $cleanupException) {
                // Jangan menutupi error asli.
                report($cleanupException);
            }

            throw $exception;
        }
    }

    public function delete(FileAsset $fileAsset): void
    {
        $disk = Storage::disk('local');
        $storageKey = $fileAsset->storage_key;

        // Jika file masih ada, pastikan penghapusannya berhasil.
        if ($disk->exists($storageKey)) {
            $deleted = $disk->delete($storageKey);

            if (! $deleted && $disk->exists($storageKey)) {
                throw new RuntimeException(
                    'File fisik gagal dihapus: ' . $storageKey
                );
            }
        }

        // Hapus metadata setelah file fisik tidak lagi tersedia.
        $fileAsset->delete();
    }
}
