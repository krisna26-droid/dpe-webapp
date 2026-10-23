<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->string('attendance_type')
                ->default('present')
                ->after('status');
            $table->text('manual_note')->nullable()->after('review_note');
            $table->char('recorded_by_user_id', 36)
                ->nullable()
                ->index('idx_teacher_attendances_recorded_by');
            $table->dateTime('recorded_at')->nullable();

            $table->index(
                ['attendance_type', 'status'],
                'idx_teacher_attendances_type_status'
            );
        });

        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->foreign('recorded_by_user_id', 'fk_teacher_attendances_recorded_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropForeign('fk_teacher_attendances_recorded_by');
            $table->dropIndex('idx_teacher_attendances_type_status');
            $table->dropIndex('idx_teacher_attendances_recorded_by');
            $table->dropColumn([
                'attendance_type',
                'manual_note',
                'recorded_by_user_id',
                'recorded_at',
            ]);
        });
    }
};
