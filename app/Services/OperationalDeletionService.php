<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationalDeletionService
{
    public function delete(User $actor, Model $record): void
    {
        DB::transaction(function () use ($actor, $record) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && in_array($actor->role_code, ['admin', 'superadmin'], true), 403);
            $record = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $student = null;
            $teacher = null;
            if ($record instanceof User) {
                $student = Student::where('portal_user_id', $record->id)->lockForUpdate()->first();
                $teacher = Teacher::where('user_id', $record->id)->lockForUpdate()->first();
                if ($record->id === $actor->id) {
                    throw ValidationException::withMessages(['delete' => 'Akun sendiri tidak dapat dihapus.']);
                }
                if ($record->role_code === 'superadmin') {
                    $admins = User::where('role_code', 'superadmin')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
                    if ($record->is_active && $admins->count() <= 1) {
                        throw ValidationException::withMessages(['delete' => 'Super Admin aktif terakhir tidak dapat dihapus.']);
                    }
                }
                if ($actor->role_code === 'admin') {
                    abort_unless($record->role_code === 'student' && $student && app(StudentAccountProvisioningService::class)->users($actor)->whereKey($record->id)->exists(), 403);
                    $ids = app(StudentAccountProvisioningService::class)->branches($actor)->pluck('id');
                    abort_if($student->enrollments()->whereNotIn('branch_id', $ids)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->exists(), 403);
                }
            } elseif ($record instanceof ClassGroup) {
                app(BranchOperationsService::class)->authorize($actor, $record);
            } else {
                abort_unless($actor->role_code === 'superadmin' && ($record instanceof Branch || $record instanceof Program), 403);
            }
            $snapshot = ['record' => $record->getRawOriginal(), 'periods' => [], 'linked_profiles' => []];
            unset($snapshot['record']['password_hash'],$snapshot['record']['remember_token']);
            $day = today()->toDateString();
            $cancelled = [];
            $close = function (string $table, $query) use (&$snapshot, $day) {
                foreach ($query->lockForUpdate()->get() as $row) {
                    $snapshot['periods'][] = ['table' => $table, 'row' => (array) $row];
                    if (CarbonImmutable::parse($row->starts_on)->toDateString() > $day) {
                        DB::table($table)->where('id', $row->id)->delete();
                    } elseif (! $row->ends_on || CarbonImmutable::parse($row->ends_on)->toDateString() > $day) {
                        DB::table($table)->where('id', $row->id)->update(['ends_on' => $day]);
                    }
                }
            };
            $closeMembers = function ($query) use ($close) {
                $ids = (clone $query)->pluck('id');
                $close('class_learning_session_enrollments', DB::table('class_learning_session_enrollments')->whereIn('class_group_membership_id', $ids));
                $close('class_group_memberships', $query);
            };
            $cancel = function ($query) use (&$cancelled) {
                foreach ($query->where('planned_start_at', '>', now())->whereNotIn('status', ['cancelled', 'canceled', 'dibatalkan'])->orderBy('id')->lockForUpdate()->get() as $lesson) {
                    if ($lesson->actual_start_at || $lesson->actual_end_at || $lesson->teacherAttendance()->exists() || $lesson->learningAudits()->exists() || $lesson->studentChallenges()->exists() || $lesson->teacherMessages()->exists() || $lesson->sessionStudents()->where(fn ($q) => $q->whereNotNull('attendance_status')->orWhereNotNull('recorded_at')->orWhereNotNull('individual_learning_note'))->exists()) {
                        throw ValidationException::withMessages(['delete' => 'Jadwal mendatang masih memiliki absensi atau catatan. Selesaikan pemeriksaannya sebelum menghapus data ini.']);
                    }
                    $lesson->update(['status' => 'cancelled']);
                    $cancelled[] = $lesson->id;
                }
            };
            if ($record instanceof User) {
                $close('branch_admin_assignments', DB::table('branch_admin_assignments')->where('admin_user_id', $record->id));
            }
            if ($student) {
                $snapshot['linked_profiles'][] = $student->getRawOriginal();
                $closeMembers(DB::table('class_group_memberships')->where('student_id', $student->id));
                $close('student_enrollments', DB::table('student_enrollments')->where('student_id', $student->id));
                $close('student_teacher_assignments', DB::table('student_teacher_assignments')->where('student_id', $student->id));
                $student->delete();
            }
            if ($teacher) {
                $snapshot['linked_profiles'][] = $teacher->getRawOriginal();
                $cancel(LessonSession::where('teacher_id', $teacher->id));
                $close('student_teacher_assignments', DB::table('student_teacher_assignments')->where('teacher_id', $teacher->id));
                $close('teacher_branch_assignments', DB::table('teacher_branch_assignments')->where('teacher_id', $teacher->id));
                ClassGroup::where('default_teacher_id', $teacher->id)->update(['default_teacher_id' => null]);
                $teacher->delete();
            }
            if ($record instanceof ClassGroup || $record instanceof Branch || $record instanceof Program) {
                $groups = $record instanceof ClassGroup ? collect([$record]) : ClassGroup::where($record instanceof Branch ? 'branch_id' : 'program_id', $record->id)->orderBy('id')->lockForUpdate()->get();
                $ids = $groups->pluck('id');
                $cancel($record instanceof ClassGroup ? LessonSession::where('class_group_id', $record->id) : LessonSession::where($record instanceof Branch ? 'branch_id' : 'program_id', $record->id));
                $closeMembers(DB::table('class_group_memberships')->whereIn('class_group_id', $ids));
                foreach ($groups as $group) {
                    if (! $record instanceof ClassGroup) {
                        $snapshot['linked_profiles'][] = $group->getRawOriginal();
                        $group->delete();
                    }
                }
                if (! $record instanceof ClassGroup) {
                    $close('student_enrollments', DB::table('student_enrollments')->where($record instanceof Branch ? 'branch_id' : 'program_id', $record->id));
                }
                if ($record instanceof Branch) {
                    $close('branch_admin_assignments', DB::table('branch_admin_assignments')->where('branch_id', $record->id));
                    $close('teacher_branch_assignments', DB::table('teacher_branch_assignments')->where('branch_id', $record->id));
                }
            }
            $record->delete();
            DB::table('operational_deletion_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'record_table' => $record->getTable(), 'record_id' => $record->getKey(), 'before_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'cancelled_session_ids' => json_encode($cancelled, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }, 3);
    }
}
