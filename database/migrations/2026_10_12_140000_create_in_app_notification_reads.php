<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notification_reads', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('user_id', 36);
            $table->char('notification_key', 64);
            $table->dateTime('read_at');
            $table->primary(['user_id', 'notification_key']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('in_app_notification_reads')->exists()) {
            throw new RuntimeException('Rollback ditolak: status baca notifikasi perlu dipertahankan.');
        }
        Schema::dropIfExists('in_app_notification_reads');
    }
};
