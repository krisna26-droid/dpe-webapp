<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_schedule_series', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('request_key', 36)->unique();
            $table->char('creation_hash', 64);
            $table->char('created_by_user_id', 36);
            $table->char('branch_id', 36);
            $table->char('class_group_id', 36);
            $table->json('definition');
            $table->timestamps();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('class_group_id')->references('id')->on('class_groups')->restrictOnDelete();
        });
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->char('schedule_series_id', 36)->nullable();
            $table->date('series_occurrence_on')->nullable();
            $table->foreign('schedule_series_id')->references('id')->on('lesson_schedule_series')->restrictOnDelete();
            $table->unique(['schedule_series_id', 'series_occurrence_on'], 'uq_lesson_series_occurrence');
        });
        Schema::create('lesson_schedule_series_changes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('request_key', 36)->unique();
            $table->char('series_id', 36);
            $table->char('actor_user_id', 36);
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->timestamp('created_at');
            $table->foreign('series_id')->references('id')->on('lesson_schedule_series')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('lesson_schedule_series')->exists() || DB::table('lesson_schedule_series_changes')->exists()) {
            throw new RuntimeException('Rollback ditolak karena sudah ada seri jadwal atau riwayat perubahan.');
        }
        Schema::dropIfExists('lesson_schedule_series_changes');
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign(['schedule_series_id']);
            $table->dropUnique('uq_lesson_series_occurrence');
            $table->dropColumn(['schedule_series_id', 'series_occurrence_on']);
        });
        Schema::dropIfExists('lesson_schedule_series');
    }
};
