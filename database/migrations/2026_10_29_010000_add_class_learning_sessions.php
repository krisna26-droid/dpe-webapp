<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_learning_sessions', function (Blueprint $t) {
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_0900_ai_ci';
            $t->char('id', 36)->primary();
            $t->char('class_group_id', 36);
            $t->string('name', 100);
            $t->timestamps();
            $t->foreign('class_group_id')->references('id')->on('class_groups')->restrictOnDelete();
            $t->unique(['class_group_id', 'name'], 'uq_class_learning_session_name');
        });
        foreach (['lesson_schedule_series', 'lesson_sessions'] as $name) {
            Schema::table($name, function (Blueprint $t) {
                $t->char('class_learning_session_id', 36)->nullable();
                $t->foreign('class_learning_session_id')->references('id')->on('class_learning_sessions')->restrictOnDelete();
            });
        }
        Schema::table('class_group_memberships', function (Blueprint $t) {
            $t->date('session_selection_from')->nullable();
            $t->unsignedBigInteger('session_selection_revision')->default(0);
        });
        Schema::create('class_learning_session_enrollments', function (Blueprint $t) {
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_0900_ai_ci';
            $t->char('id', 36)->primary();
            $t->char('class_group_membership_id', 36);
            $t->char('class_learning_session_id', 36);
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->foreign('class_group_membership_id', 'fk_learning_enrollment_membership')->references('id')->on('class_group_memberships')->restrictOnDelete();
            $t->foreign('class_learning_session_id', 'fk_learning_enrollment_session')->references('id')->on('class_learning_sessions')->restrictOnDelete();
            $t->index(['class_group_membership_id', 'starts_on'], 'ix_learning_enrollment_period');
        });
        // Keep existing participation unchanged until an operator selects sessions.
        $keys = [];
        $numbers = [];
        $alias = function ($group, $key) use (&$keys, &$numbers) {
            if (isset($keys[$group][$key])) {
                return $keys[$group][$key];
            }
            $id = (string) Str::uuid();
            $numbers[$group] = ($numbers[$group] ?? 0) + 1;
            DB::table('class_learning_sessions')->insert(['id' => $id, 'class_group_id' => $group, 'name' => 'Sesi '.$numbers[$group], 'created_at' => now(), 'updated_at' => now()]);

            return $keys[$group][$key] = $id;
        };
        foreach (DB::table('lesson_schedule_series')->orderBy('created_at')->orderBy('id')->get() as $series) {
            $d = json_decode($series->definition, true) ?? [];
            $days = $d['weekdays'] ?? [];
            sort($days);
            $key = ($d['teacher_id'] ?? '').'|'.implode(',', $days).'|'.($d['starts_at'] ?? '').'|'.($d['ends_at'] ?? '');
            $id = $alias($series->class_group_id, $key);
            DB::table('lesson_schedule_series')->where('id', $series->id)->update(['class_learning_session_id' => $id]);
            DB::table('lesson_sessions')->where('schedule_series_id', $series->id)->update(['class_learning_session_id' => $id]);
        }
        foreach (DB::table('lesson_sessions')->whereNotNull('class_group_id')->whereNull('class_learning_session_id')->orderBy('planned_start_at')->orderBy('id')->get() as $s) {
            $start = CarbonImmutable::parse($s->planned_start_at);
            $end = CarbonImmutable::parse($s->planned_end_at);
            $id = $alias($s->class_group_id, $s->teacher_id.'|'.$start->isoWeekday().'|'.$start->format('H:i').'|'.$end->format('H:i'));
            DB::table('lesson_sessions')->where('id', $s->id)->update(['class_learning_session_id' => $id]);
        }
        foreach (DB::table('lesson_sessions')->whereNotNull('rescheduled_from_session_id')->whereNotNull('class_group_id')->get() as $s) {
            $source = DB::table('lesson_sessions')->where('id', $s->rescheduled_from_session_id)->first();
            if ($source && $source->class_group_id === $s->class_group_id) {
                DB::table('lesson_sessions')->where('id', $s->id)->update(['class_learning_session_id' => $source->class_learning_session_id]);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('class_group_memberships')->whereNotNull('session_selection_from')->exists()) {
            throw new RuntimeException('Pilihan sesi sudah digunakan. Pulihkan backup sebelum membatalkan migrasi ini.');
        }
        Schema::dropIfExists('class_learning_session_enrollments');
        Schema::table('class_group_memberships', fn (Blueprint $t) => $t->dropColumn(['session_selection_from', 'session_selection_revision']));
        foreach (['lesson_sessions', 'lesson_schedule_series'] as $name) {
            Schema::table($name, function (Blueprint $t) {
                $t->dropForeign(['class_learning_session_id']);
                $t->dropColumn('class_learning_session_id');
            });
        }
        Schema::dropIfExists('class_learning_sessions');
    }
};
