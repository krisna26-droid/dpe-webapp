<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indices = [
        'users' => ['uq_users_username' => ['username'], 'uq_users_email' => ['email']],
        'branches' => ['uq_branches_code' => ['code']], 'programs' => ['uq_programs_code' => ['code']],
        'class_groups' => ['uq_class_groups_branch_code' => ['branch_id', 'code']],
    ];

    public function up(): void
    {
        foreach (['users', 'students', 'teachers', 'branches', 'programs', 'class_groups'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->softDeletes());
        }
        foreach ($this->indices as $name => $indices) {
            Schema::table($name, fn (Blueprint $t) => $t->tinyInteger('live_identity_key')->nullable()->virtualAs('case when deleted_at is null then 1 else null end'));
            // Create replacement first: branch/code index also supports a foreign key on MySQL.
            foreach ($indices as $old => $columns) {
                Schema::table($name, fn (Blueprint $t) => $t->unique([...$columns, 'live_identity_key'], 'live_'.$old));
                Schema::table($name, fn (Blueprint $t) => $t->dropUnique($old));
            }
        }
        Schema::create('operational_deletion_events', function (Blueprint $t) {
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_0900_ai_ci';
            $t->char('id', 36)->primary();
            $t->char('actor_user_id', 36);
            $t->string('record_table', 64);
            $t->char('record_id', 36);
            $t->json('before_snapshot');
            $t->json('cancelled_session_ids');
            $t->timestamp('created_at');
            $t->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('operational_deletion_events')->exists()) {
            throw new RuntimeException('Data sudah dihapus melalui fitur operasional. Pulihkan backup untuk membatalkan migrasi.');
        }
        foreach (['users', 'students', 'teachers', 'branches', 'programs', 'class_groups'] as $name) {
            if (DB::table($name)->whereNotNull('deleted_at')->exists()) {
                throw new RuntimeException('Riwayat penghapusan harus dipulihkan dari backup.');
            }
        }
        Schema::dropIfExists('operational_deletion_events');
        foreach ($this->indices as $name => $indices) {
            foreach ($indices as $old => $columns) {
                Schema::table($name, fn (Blueprint $t) => $t->unique($columns, $old));
                Schema::table($name, fn (Blueprint $t) => $t->dropUnique('live_'.$old));
            }
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn('live_identity_key'));
        }
        foreach (['users', 'students', 'teachers', 'branches', 'programs', 'class_groups'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
