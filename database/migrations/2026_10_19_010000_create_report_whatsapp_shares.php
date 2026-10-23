<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_whatsapp_shares', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('report_id', 36)->index();
            $table->char('file_id', 36);
            $table->char('teacher_id', 36);
            $table->char('actor_user_id', 36);
            $table->char('guardian_id', 36);
            $table->char('request_key', 36)->unique();
            $table->string('method', 8);
            $table->string('salutation', 8);
            $table->string('status', 24)->default('prepared');
            $table->char('source_signature', 64);
            $table->char('preview_signature', 64);
            $table->json('contact_snapshot');
            $table->text('portal_url');
            $table->text('message');
            $table->dateTime('prepared_at');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('downloaded_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreign('report_id')->references('id')->on('quarterly_reports')->restrictOnDelete();
            $table->foreign('file_id')->references('id')->on('quarterly_report_files')->restrictOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('guardian_id')->references('id')->on('guardians')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('report_whatsapp_shares')->exists()) {
            throw new RuntimeException('Rollback ditolak: riwayat berbagi WhatsApp harus dipertahankan.');
        }
        Schema::dropIfExists('report_whatsapp_shares');
    }
};
