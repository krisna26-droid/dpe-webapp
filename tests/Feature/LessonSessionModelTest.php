<?php

namespace Tests\Feature;

use App\Models\LessonSession;
use App\Models\SessionStudent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class LessonSessionModelTest extends TestCase
{
    public function test_lesson_session_has_correct_branch_relation(): void
    {
        $relation = (new LessonSession())->branch();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('branch_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('branches', $relation->getRelated()->getTable());
    }

    public function test_lesson_session_has_correct_teacher_relation(): void
    {
        $relation = (new LessonSession())->teacher();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('teacher_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('teachers', $relation->getRelated()->getTable());
    }

    public function test_lesson_session_has_correct_program_relation(): void
    {
        $relation = (new LessonSession())->program();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('program_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('programs', $relation->getRelated()->getTable());
    }

    public function test_lesson_session_has_correct_class_group_relation(): void
    {
        $relation = (new LessonSession())->classGroup();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('class_group_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('class_groups', $relation->getRelated()->getTable());
    }

    public function test_lesson_session_has_correct_rescheduled_from_relation(): void
    {
        $relation = (new LessonSession())->rescheduledFrom();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame(
            'rescheduled_from_session_id',
            $relation->getForeignKeyName()
        );
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('lesson_sessions', $relation->getRelated()->getTable());
    }

    public function test_lesson_session_has_correct_session_students_relation(): void
    {
        $relation = (new LessonSession())->sessionStudents();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame('session_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getLocalKeyName());
        $this->assertSame('session_students', $relation->getRelated()->getTable());
    }

    public function test_lesson_session_has_correct_students_relation(): void
    {
        $relation = (new LessonSession())->students();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('session_students', $relation->getTable());
        $this->assertSame('session_id', $relation->getForeignPivotKeyName());
        $this->assertSame('student_id', $relation->getRelatedPivotKeyName());
        $this->assertSame('id', $relation->getParentKeyName());
        $this->assertSame('id', $relation->getRelatedKeyName());
    }

    public function test_session_student_has_correct_session_relation(): void
    {
        $relation = (new SessionStudent())->session();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('session_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('lesson_sessions', $relation->getRelated()->getTable());
    }

    public function test_session_student_has_correct_student_relation(): void
    {
        $relation = (new SessionStudent())->student();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('student_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame('students', $relation->getRelated()->getTable());
    }
}