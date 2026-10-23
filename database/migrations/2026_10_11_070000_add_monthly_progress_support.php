<?php

use App\Services\StudentProgressService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dateTime('video_target_confirmed_at')->nullable();
            $table->char('video_target_confirmed_by_user_id', 36)->nullable();
            $table->foreign('video_target_confirmed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('monthly_progress_audits', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->bigIncrements('id');
            $table->char('report_id', 36);
            $table->char('actor_user_id', 36)->nullable();
            $table->string('event_type', 30);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot');
            $table->dateTime('happened_at');
            $table->foreign('report_id')->references('id')->on('monthly_reports')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['report_id', 'id']);
        });

        // Preserve existing skill IDs, labels, and inactive flags. Add only missing categories.
        $skills = DB::table('learning_skills')->get();
        foreach (StudentProgressService::CATEGORIES as $code => $name) {
            $found = $skills->contains(fn ($skill) => strtolower($skill->code) === $code
                || StudentProgressService::categoryKey($skill->name) === StudentProgressService::categoryKey($name));
            if (! $found) {
                DB::table('learning_skills')->insert(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name,
                    'display_order' => array_search($code, array_keys(StudentProgressService::CATEGORIES), true) + 1, 'is_active' => true]);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('monthly_progress_audits')->exists()
            || DB::table('monthly_reports')->whereNotNull('video_target_confirmed_at')->exists()) {
            throw new RuntimeException('Migrasi progres sudah digunakan. Pulihkan melalui cadangan terverifikasi; riwayat tidak dihapus otomatis.');
        }
        Schema::dropIfExists('monthly_progress_audits');
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropForeign(['video_target_confirmed_by_user_id']);
            $table->dropColumn(['video_target_confirmed_at', 'video_target_confirmed_by_user_id']);
        });
        // Skill master records remain available to other learning features.
    }
};
