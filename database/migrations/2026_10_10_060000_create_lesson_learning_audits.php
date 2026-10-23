<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_learning_audits', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->bigIncrements('id');
            $table->char('session_id', 36);
            $table->char('actor_user_id', 36)->nullable();
            $table->string('event_type', 30);
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->text('reason')->nullable();
            $table->dateTime('happened_at');
            $table->index(['session_id', 'id'], 'idx_lesson_learning_history');
            $table->foreign('session_id')->references('id')->on('lesson_sessions')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('lesson_learning_audits')->exists()) {
            throw new RuntimeException('Rollback ditolak karena sudah ada riwayat catatan belajar.');
        }
        Schema::dropIfExists('lesson_learning_audits');
    }
};
