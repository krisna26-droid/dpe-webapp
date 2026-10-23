<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_content_drafts', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36)->primary();
            $table->string('section_key')->unique();
            $table->json('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->char('base_live_hash', 64);
            $table->char('saved_by_user_id', 36);
            $table->string('saved_by_name');
            $table->dateTime('saved_at');
        });
        // Deliberately retain actor IDs and snapshots even if a legacy API removes a source row.
        Schema::create('website_content_changes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36)->primary();
            $table->string('section_key')->index();
            $table->string('action', 32);
            $table->char('actor_user_id', 36);
            $table->string('actor_name');
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('website_content_drafts')->exists() || DB::table('website_content_changes')->exists()) {
            throw new RuntimeException('Rollback ditolak: draft dan riwayat konten website perlu dipertahankan.');
        }
        Schema::dropIfExists('website_content_changes');
        Schema::dropIfExists('website_content_drafts');
    }
};
