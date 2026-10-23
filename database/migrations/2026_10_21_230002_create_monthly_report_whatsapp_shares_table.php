<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_report_whatsapp_shares', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('report_id', 36)->index('monthly_wa_report_index');
            $table->char('file_id', 36)->nullable();
            $table->char('teacher_id', 36);
            $table->char('actor_user_id', 36);
            $table->char('guardian_id', 36);
            $table->char('request_key', 36)->unique('monthly_wa_request_unique');
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
            $table->foreign('report_id', 'monthly_wa_report_fk')->references('id')->on('monthly_reports')->restrictOnDelete();
            $table->foreign('file_id', 'monthly_wa_pdf_fk')->references('id')->on('monthly_report_files')->restrictOnDelete();
            $table->foreign('teacher_id', 'monthly_wa_teacher_fk')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('actor_user_id', 'monthly_wa_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('guardian_id', 'monthly_wa_guardian_fk')->references('id')->on('guardians')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('monthly_report_whatsapp_shares')->exists()) {
            throw new RuntimeException('Rollback ditolak: riwayat WhatsApp bulanan harus dipertahankan.');
        }
        Schema::dropIfExists('monthly_report_whatsapp_shares');
    }
};
