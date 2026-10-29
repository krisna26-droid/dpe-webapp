<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\ClassLearningSession;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use Illuminate\Support\Str;

trait StudentAccountSessionFixture
{
    protected function accountSessionFields(Branch $branch, Program $program): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'username' => 'teacher_'.Str::random(12), 'full_name' => 'Guru sesi uji', 'password_hash' => 'fixture', 'role_code' => 'teacher', 'is_active' => true]);
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id, 'branch_id' => $branch->id, 'starts_on' => today()->subYear()->toDateString()]);
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $branch->id, 'program_id' => $program->id, 'default_teacher_id' => $teacher->id, 'code' => Str::random(12), 'name' => 'Kelompok sesi uji', 'is_active' => true]);
        $slot = ClassLearningSession::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'name' => 'Sesi 1']);
        LessonSession::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'class_learning_session_id' => $slot->id, 'branch_id' => $branch->id, 'program_id' => $program->id, 'teacher_id' => $teacher->id, 'planned_start_at' => today()->addDays(3)->setTime(14, 0), 'planned_end_at' => today()->addDays(3)->setTime(15, 0), 'status' => 'scheduled', 'teaching_location' => 'branch']);

        return ['branch_id' => $branch->id, 'teacher_id' => $teacher->id, 'learning_session_ids' => [$slot->id], 'guardian_phone' => '081234567890'];
    }
}
