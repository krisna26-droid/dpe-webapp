<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

trait TeachingDirectorySchema
{
    /** Provide the empty relation tables missing from legacy in-memory fixtures. */
    protected function createTeachingDirectoryTables(): void
    {
        foreach (['programs', 'students', 'teachers', 'teacher_branch_assignments', 'student_enrollments',
            'student_teacher_assignments', 'class_groups', 'class_group_memberships'] as $table) {
            if (Schema::hasTable($table)) continue;
            Schema::create($table, function (Blueprint $schema) use ($table) {
                $schema->char('id', 36)->primary();
                if ($table === 'programs') {
                    $schema->string('code'); $schema->string('name'); $schema->string('class_type');
                    $schema->boolean('is_active')->default(true);
                } elseif ($table === 'students') {
                    $schema->char('portal_user_id', 36)->nullable(); $schema->string('full_name');
                    $schema->string('school_name')->nullable(); $schema->string('grade_name')->nullable();
                    $schema->date('began_on'); $schema->string('status')->default('active');
                } elseif ($table === 'teachers') {
                    $schema->char('user_id', 36); $schema->string('whatsapp_number')->nullable();
                    $schema->char('photo_file_id', 36)->nullable(); $schema->boolean('is_active')->default(true);
                    $schema->dateTime('created_at')->nullable();
                } elseif ($table === 'class_groups') {
                    $schema->char('branch_id', 36); $schema->char('program_id', 36);
                    $schema->char('default_teacher_id', 36)->nullable(); $schema->string('code');
                    $schema->string('name'); $schema->boolean('is_active')->default(true);
                } else {
                    foreach (match ($table) {
                        'teacher_branch_assignments' => ['teacher_id', 'branch_id'],
                        'student_enrollments' => ['student_id', 'branch_id', 'program_id'],
                        'student_teacher_assignments' => ['student_id', 'teacher_id'],
                        default => ['student_id', 'class_group_id'],
                    } as $column) $schema->char($column, 36);
                    $schema->date('starts_on'); $schema->date('ends_on')->nullable();
                    if ($table === 'student_teacher_assignments') $schema->boolean('is_report_owner')->default(false);
                }
            });
        }
    }
}
