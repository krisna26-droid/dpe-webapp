<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AutomaticMonthlyReportService;
use Illuminate\Console\Command;

class PrepareMonthlyReports extends Command
{
    protected $signature = 'dpe:prepare-monthly-reports';

    protected $description = 'Menyiapkan draf laporan bulan berjalan berdasarkan penugasan siswa yang aktif';

    public function handle(AutomaticMonthlyReportService $reports): int
    {
        $actor = User::where('role_code', 'superadmin')->where('is_active', true)->orderBy('id')->first();
        if (! $actor) {
            $this->error('Tidak ada akun Super Admin aktif untuk menyiapkan periode otomatis.');

            return self::FAILURE;
        }
        $result = $reports->ensureFor($actor);
        $this->info("Draf baru: {$result['created']}. Perlu pengaturan Admin: {$result['needs_setup']}.");

        return self::SUCCESS;
    }
}
