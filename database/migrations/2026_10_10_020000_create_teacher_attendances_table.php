<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('session_id', 36);
            $table->char('teacher_id', 36)->index('idx_teacher_attendances_teacher_id');
            $table->char('branch_id', 36)->index('idx_teacher_attendances_branch_id');

            $table->dateTime('check_in_at')->nullable();
            $table->char('check_in_photo_file_id', 36)->nullable()->index('idx_teacher_attendances_check_in_photo');
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_in_accuracy_m', 10, 2)->nullable();
            $table->string('check_in_location_status')->nullable();
            $table->text('check_in_location_note')->nullable();

            $table->dateTime('check_out_at')->nullable();
            $table->char('check_out_photo_file_id', 36)->nullable()->index('idx_teacher_attendances_check_out_photo');
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->decimal('check_out_accuracy_m', 10, 2)->nullable();
            $table->string('check_out_location_status')->nullable();
            $table->text('check_out_location_note')->nullable();

            $table->string('status')->default('in_progress');
            $table->char('reviewed_by_user_id', 36)->nullable()->index('idx_teacher_attendances_reviewer');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique('session_id', 'uq_teacher_attendances_session');
        });

        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->foreign('session_id', 'fk_teacher_attendances_session')
                ->references('id')
                ->on('lesson_sessions')
                ->restrictOnDelete();
            $table->foreign('teacher_id', 'fk_teacher_attendances_teacher')
                ->references('id')
                ->on('teachers')
                ->restrictOnDelete();
            $table->foreign('branch_id', 'fk_teacher_attendances_branch')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign('check_in_photo_file_id', 'fk_teacher_attendances_check_in_photo')
                ->references('id')
                ->on('file_assets')
                ->restrictOnDelete();
            $table->foreign('check_out_photo_file_id', 'fk_teacher_attendances_check_out_photo')
                ->references('id')
                ->on('file_assets')
                ->restrictOnDelete();
            $table->foreign('reviewed_by_user_id', 'fk_teacher_attendances_reviewer')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
