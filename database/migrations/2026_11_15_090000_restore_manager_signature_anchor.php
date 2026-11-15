<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The owner/revision unique key makes this safe to apply to both a
        // complete local database and an import missing the permanent lock.
        DB::table('report_signatures')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'owner_key' => 'manager',
            'revision' => 0,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // This is a data repair, not a schema change. The shared lock must stay
        // available to uploads and approvals, including after a code rollback.
    }
};
