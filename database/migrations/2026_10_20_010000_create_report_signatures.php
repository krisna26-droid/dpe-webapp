<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_signatures', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->string('owner_key', 60);
            $table->char('teacher_id', 36)->nullable();
            $table->unsignedInteger('revision');
            $table->char('file_id', 36)->nullable();
            $table->string('signer_name')->nullable();
            $table->char('uploaded_by_user_id', 36)->nullable();
            $table->timestamp('created_at');
            $table->unique(['owner_key', 'revision']);
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('file_id')->references('id')->on('file_assets')->restrictOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        // A permanent row serializes the first Manager upload and report approval too.
        DB::table('report_signatures')->insert(['id' => (string) Str::uuid(), 'owner_key' => 'manager',
            'revision' => 0, 'created_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('report_signatures')->whereNotNull('file_id')->exists()) {
            throw new RuntimeException('Rollback ditolak: tanda tangan sudah diunggah. Riwayat tidak dihapus otomatis.');
        }
        Schema::dropIfExists('report_signatures');
    }
};
