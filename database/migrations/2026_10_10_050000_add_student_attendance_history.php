<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_students', function (Blueprint $table) {
            $table->dateTime('attendance_updated_at')->nullable();
            $table->char('attendance_recorded_by_user_id', 36)->nullable();
            $table->unsignedInteger('attendance_version')->default(0);
            $table->foreign('attendance_recorded_by_user_id', 'fk_student_attendance_recorder')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('student_attendance_audits', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->bigIncrements('id');
            $table->char('session_id', 36);
            $table->char('student_id', 36);
            $table->char('actor_user_id', 36)->nullable();
            $table->string('event_type', 30);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('happened_at');
            $table->index(['session_id', 'student_id', 'id'], 'idx_student_attendance_history');
            $table->foreign('session_id')->references('id')->on('lesson_sessions')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('student_attendance_audits')->exists()
            || DB::table('session_students')->whereNotNull('attendance_updated_at')->exists()) {
            throw new RuntimeException('Rollback ditolak karena sudah ada catatan atau riwayat absensi siswa.');
        }
        Schema::dropIfExists('student_attendance_audits');
        Schema::table('session_students', function (Blueprint $table) {
            $table->dropForeign('fk_student_attendance_recorder');
            $table->dropColumn(['attendance_updated_at', 'attendance_recorded_by_user_id', 'attendance_version']);
        });
    }
};
