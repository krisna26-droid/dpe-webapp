<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    public function test_user_has_correct_student_profile_relation(): void
    {
        $relation = (new User())->studentProfile();

        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertSame('portal_user_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getLocalKeyName());
        $this->assertSame('students', $relation->getRelated()->getTable());
    }

    public function test_user_has_correct_teacher_profile_relation(): void
    {
        $relation = (new User())->teacherProfile();

        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertSame('user_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getLocalKeyName());
        $this->assertSame('teachers', $relation->getRelated()->getTable());
    }

    public function test_student_and_teacher_have_correct_user_relations(): void
    {
        $studentRelation = (new Student())->portalUser();
        $teacherRelation = (new Teacher())->user();

        $this->assertInstanceOf(BelongsTo::class, $studentRelation);
        $this->assertSame('portal_user_id', $studentRelation->getForeignKeyName());
        $this->assertSame('users', $studentRelation->getRelated()->getTable());

        $this->assertInstanceOf(BelongsTo::class, $teacherRelation);
        $this->assertSame('user_id', $teacherRelation->getForeignKeyName());
        $this->assertSame('users', $teacherRelation->getRelated()->getTable());
    }
}
