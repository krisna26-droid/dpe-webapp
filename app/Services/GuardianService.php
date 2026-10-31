<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\ReportWhatsappShare;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardianService
{
    public function create(array $data): Guardian
    {
        return DB::transaction(function () use ($data) {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            return Guardian::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'full_name' => $data['full_name'],
                'relationship_name' => $data['relationship_name'] ?? null,
                'whatsapp_number' => $data['whatsapp_number'],
                'is_primary' => $data['is_primary'] ?? false,
            ]);
        });
    }

    public function update(
        Guardian $guardian,
        array $data
    ): Guardian {
        return DB::transaction(function () use (
            $guardian,
            $data
        ) {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($data['student_id']);

            $guardian = Guardian::whereKey($guardian->id)->lockForUpdate()->firstOrFail();
            if ($guardian->student_id !== $student->id && $this->hasReportHistory($guardian)) {
                throw ValidationException::withMessages([
                    'student_id' => 'Wali dengan riwayat pembagian rapor tidak dapat dipindahkan ke siswa lain. Buat kontak wali baru untuk siswa tersebut.',
                ]);
            }

            if ($data['is_primary'] ?? false) {
                Guardian::query()
                    ->where('student_id', $student->id)
                    ->where('id', '!=', $guardian->id)
                    ->update(['is_primary' => false]);
            }

            $guardian->update([
                'student_id' => $student->id,
                'full_name' => $data['full_name'],
                'relationship_name' => $data['relationship_name'] ?? null,
                'whatsapp_number' => $data['whatsapp_number'],
                'is_primary' => $data['is_primary'] ?? false,
            ]);

            return $guardian->refresh();
        });
    }

    /**
     * Update only the contact number when an Admin Cabang maintains a
     * guardian record for a student in their branch.
     */
    public function updatePhone(Guardian $guardian, string $phone): Guardian
    {
        return DB::transaction(function () use ($guardian, $phone) {
            $guardian = Guardian::query()
                ->whereKey($guardian->id)
                ->lockForUpdate()
                ->firstOrFail();

            $guardian->update(['whatsapp_number' => trim($phone)]);

            return $guardian->refresh();
        });
    }

    public function delete(Guardian $guardian): void
    {
        DB::transaction(function () use ($guardian) {
            $guardian = Guardian::whereKey($guardian->id)->lockForUpdate()->firstOrFail();
            if ($this->hasReportHistory($guardian)) {
                throw ValidationException::withMessages([
                    'guardian' => 'Wali memiliki riwayat pembagian rapor dan tidak dapat dihapus. Data kontak masih dapat diperbarui.',
                ]);
            }
            $guardian->delete();
        });
    }

    private function hasReportHistory(Guardian $guardian): bool
    {
        return $guardian->reportShareAttempts()->exists()
            || (Schema::hasTable('report_whatsapp_shares')
                && ReportWhatsappShare::where('guardian_id', $guardian->id)->exists());
    }
}
