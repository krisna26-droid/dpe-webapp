<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('portal_attendance_visible')->default(true);
        });
        Schema::table('monthly_charges', function (Blueprint $table) {
            $table->decimal('amount_idr', 14, 2)->nullable()->change();
        });
        Schema::create('student_portal_events', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->char('id', 36)->primary();
            $table->char('actor_user_id', 36);
            $table->char('student_id', 36)->nullable();
            $table->char('branch_id', 36)->nullable();
            $table->char('charge_id', 36)->nullable();
            $table->char('payment_proof_id', 36)->nullable();
            $table->string('event_type', 40);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at');
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('charge_id')->references('id')->on('monthly_charges')->restrictOnDelete();
            $table->foreign('payment_proof_id')->references('id')->on('payment_proofs')->restrictOnDelete();
            $table->index(['payment_proof_id', 'id']);
            $table->index(['branch_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('student_portal_events')->exists()
            || DB::table('branches')->where('portal_attendance_visible', false)->exists()
            || DB::table('monthly_charges')->whereNull('amount_idr')->exists()) {
            throw new RuntimeException('Rollback ditolak: riwayat portal, pengaturan cabang, atau nominal belum ditetapkan perlu dipertahankan.');
        }
        Schema::dropIfExists('student_portal_events');
        Schema::table('monthly_charges', function (Blueprint $table) {
            $table->decimal('amount_idr', 14, 2)->nullable(false)->change();
        });
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('portal_attendance_visible'));
    }
};
