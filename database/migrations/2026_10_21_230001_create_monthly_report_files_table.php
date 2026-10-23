<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_report_files', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('report_id', 36);
            $table->char('file_id', 36);
            $table->unsignedInteger('version_number');
            $table->char('generated_by_user_id', 36);
            $table->dateTime('generated_at');
            $table->char('source_snapshot_hash', 64);
            $table->json('public_snapshot');
            $table->unique(['report_id', 'version_number'], 'monthly_pdf_version_unique');
            $table->index(['report_id', 'source_snapshot_hash'], 'monthly_pdf_source_index');
            $table->foreign('report_id', 'monthly_pdf_report_fk')->references('id')->on('monthly_reports')->restrictOnDelete();
            $table->foreign('file_id', 'monthly_pdf_file_fk')->references('id')->on('file_assets')->restrictOnDelete();
            $table->foreign('generated_by_user_id', 'monthly_pdf_actor_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('monthly_report_files')->exists()) {
            throw new RuntimeException('Rollback ditolak: arsip PDF bulanan harus dipertahankan.');
        }
        Schema::dropIfExists('monthly_report_files');
    }
};
