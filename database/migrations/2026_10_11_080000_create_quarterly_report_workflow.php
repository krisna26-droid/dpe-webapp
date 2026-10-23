<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarterly_reports', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('cycle_id', 36)->unique();
            $table->char('student_id', 36)->index();
            $table->char('branch_id', 36)->index();
            $table->char('teacher_id', 36)->index();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision_number')->default(1);
            $table->json('skills')->nullable();
            $table->text('parent_note')->nullable();
            $table->json('approved_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->char('approved_by_user_id', 36)->nullable();
            $table->timestamps();
            $table->foreign('cycle_id')->references('id')->on('report_cycles')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('quarterly_report_events', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('report_id', 36)->index();
            $table->char('actor_user_id', 36);
            $table->string('event_type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('comment', 255)->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamp('occurred_at');
            $table->foreign('report_id')->references('id')->on('quarterly_reports')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('report_template_settings', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->unsignedTinyInteger('id')->primary();
            $table->json('settings');
            $table->unsignedInteger('revision')->default(1);
            $table->char('updated_by_user_id', 36);
            $table->timestamp('updated_at');
            $table->foreign('updated_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::table('quarterly_report_files', function (Blueprint $table) {
            $table->char('quarterly_report_id', 36)->nullable()->index();
            $table->unsignedInteger('approval_revision')->nullable();
            $table->json('public_snapshot')->nullable();
            $table->foreign('quarterly_report_id')->references('id')->on('quarterly_reports')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('quarterly_reports')->exists() || DB::table('report_template_settings')->exists()
            || DB::table('quarterly_report_files')->whereNotNull('quarterly_report_id')->exists()) {
            throw new RuntimeException('Rollback ditolak: rapor/template final sudah berisi data. Cadangkan dan tinjau data sebelum perubahan skema.');
        }
        Schema::table('quarterly_report_files', function (Blueprint $table) {
            $table->dropForeign(['quarterly_report_id']);
            $table->dropIndex(['quarterly_report_id']);
            $table->dropColumn(['quarterly_report_id', 'approval_revision', 'public_snapshot']);
        });
        Schema::dropIfExists('quarterly_report_events');
        Schema::dropIfExists('quarterly_reports');
        Schema::dropIfExists('report_template_settings');
    }
};
