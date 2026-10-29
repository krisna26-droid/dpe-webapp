<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_learning_session_enrollments', function (Blueprint $table) {
            // NULL retains the previous selection: this session on every scheduled day.
            $table->unsignedTinyInteger('weekday')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('class_learning_session_enrollments')->whereNotNull('weekday')->exists()) {
            throw new RuntimeException('Pilihan sesi per hari sudah digunakan. Pulihkan backup sebelum membatalkan migrasi ini.');
        }
        Schema::table('class_learning_session_enrollments', fn (Blueprint $table) => $table->dropColumn('weekday'));
    }
};
