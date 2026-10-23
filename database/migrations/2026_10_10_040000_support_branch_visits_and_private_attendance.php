<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->string('teaching_location')->default('branch');
        });

        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->char('session_id', 36)->nullable()->change();
            $table->string('attendance_mode')->default('legacy_session');
            $table->date('attendance_date')->nullable();
            $table->char('active_visit_teacher_id', 36)->nullable();
            $table->string('exception_day_key', 100)->nullable();
            $table->unique('active_visit_teacher_id', 'uq_teacher_attendances_active_visit');
            $table->unique('exception_day_key', 'uq_teacher_attendances_exception_day');
            $table->index(['branch_id', 'attendance_date', 'attendance_mode'], 'idx_teacher_attendances_day_mode');
        });

        // Preserve all existing records as session history; never guess visits
        // or merge photos/timestamps from different old sessions.
        DB::table('teacher_attendances')->orderBy('id')->chunkById(200, function ($rows) {
            $dates = DB::table('lesson_sessions')
                ->whereIn('id', $rows->pluck('session_id')->filter())
                ->pluck('planned_start_at', 'id');

            foreach ($rows as $row) {
                $date = $dates[$row->session_id] ?? $row->check_in_at ?? $row->created_at;
                DB::table('teacher_attendances')->where('id', $row->id)->update([
                    'attendance_date' => substr((string) $date, 0, 10),
                ]);
            }
        });

        Schema::create('teacher_attendance_audit_logs', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('teacher_attendance_id', 36)->index();
            $table->char('actor_user_id', 36)->nullable();
            $table->string('event_type', 50);
            $table->text('snapshot_json');
            $table->text('reason')->nullable();
            $table->dateTime('happened_at');
            $table->foreign('teacher_attendance_id')->references('id')->on('teacher_attendances')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // A rollback must never delete branch visits or truncate their history.
        if (DB::table('teacher_attendances')->whereNull('session_id')->exists()
            || DB::table('teacher_attendance_audit_logs')->exists()) {
            throw new RuntimeException('Rollback v27 ditolak karena sudah ada data baru. Gunakan backup yang telah dibuat sebelum pembaruan.');
        }

        Schema::dropIfExists('teacher_attendance_audit_logs');
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropUnique('uq_teacher_attendances_active_visit');
            $table->dropUnique('uq_teacher_attendances_exception_day');
            $table->dropIndex('idx_teacher_attendances_day_mode');
            $table->dropColumn(['attendance_mode', 'attendance_date', 'active_visit_teacher_id', 'exception_day_key']);
            $table->char('session_id', 36)->nullable(false)->change();
        });
        Schema::table('lesson_sessions', fn (Blueprint $table) => $table->dropColumn('teaching_location'));
    }
};
