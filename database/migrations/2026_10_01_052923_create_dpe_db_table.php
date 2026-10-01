<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branch_admin_assignments', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('branch_id', 36);
            $table->char('admin_user_id', 36)->index('idx_branch_admin_assignments_admin_user_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->tinyInteger('active_branch_key')->nullable()->storedAs('(case when (`ends_on` is null) then 1 else NULL end)');
            $table->tinyInteger('active_admin_key')->nullable()->storedAs('(case when (`ends_on` is null) then 1 else NULL end)');

            $table->unique(['admin_user_id', 'active_admin_key'], 'uq_admin_active_branch');
            $table->unique(['branch_id', 'active_branch_key'], 'uq_branch_active_admin');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->string('code')->unique('uq_branches_code');
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('timezone_name');
            $table->smallInteger('payment_recap_day');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('class_group_memberships', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('class_group_id', 36);
            $table->char('student_id', 36)->index('fk_class_membership_student');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->tinyInteger('active_membership_key')->nullable()->storedAs('(case when (`ends_on` is null) then 1 else NULL end)');

            $table->unique(['class_group_id', 'student_id', 'active_membership_key'], 'uq_active_class_group_membership');
        });

        Schema::create('class_groups', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('branch_id', 36);
            $table->char('program_id', 36)->index('idx_class_groups_program_id');
            $table->char('default_teacher_id', 36)->nullable()->index('idx_class_groups_default_teacher_id');
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->unique(['branch_id', 'code'], 'uq_class_groups_branch_code');
        });

        Schema::create('file_assets', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->string('storage_key', 500)->unique('uq_file_assets_storage_key');
            $table->text('original_name');
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->string('sha256_hex')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36)->index('idx_guardians_student_id');
            $table->string('full_name');
            $table->string('relationship_name')->nullable();
            $table->string('whatsapp_number');
            $table->boolean('is_primary')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->tinyInteger('primary_student_key')->nullable()->storedAs('(case when (`is_primary` = 1) then 1 else NULL end)');

            $table->unique(['student_id', 'primary_student_key'], 'uq_guardians_primary_per_student');
        });

        Schema::create('learning_skills', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->string('code')->unique('uq_learning_skills_code');
            $table->string('name');
            $table->smallInteger('display_order');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('learning_videos', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36)->index('idx_learning_videos_student_id');
            $table->char('teacher_id', 36)->index('idx_learning_videos_teacher_id');
            $table->char('session_id', 36)->nullable()->index('idx_learning_videos_session_id');
            $table->date('video_on');
            $table->string('topic');
            $table->text('description')->nullable();
            $table->text('video_url');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('branch_id', 36)->index('fk_lesson_branch');
            $table->char('teacher_id', 36);
            $table->char('program_id', 36)->index('fk_lesson_program');
            $table->char('class_group_id', 36)->nullable();
            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->string('status');
            $table->char('rescheduled_from_session_id', 36)->nullable()->index('fk_lesson_rescheduled_from');
            $table->string('topic')->nullable();
            $table->string('material')->nullable();
            $table->string('activity')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['class_group_id', 'planned_start_at'], 'idx_lesson_sessions_class_group_start');
            $table->index(['teacher_id', 'planned_start_at'], 'idx_lesson_sessions_teacher_start');
        });

        Schema::create('monthly_charges', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36);
            $table->char('branch_id', 36)->index('idx_monthly_charges_branch_id');
            $table->date('charge_month');
            $table->decimal('amount_idr', 14);
            $table->date('due_on')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['student_id', 'charge_month'], 'uq_monthly_charges_student_month');
        });

        Schema::create('monthly_report_skills', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('report_id', 36);
            $table->char('skill_id', 36)->index('fk_monthly_report_skills_skill');
            $table->string('trend')->nullable();
            $table->text('description');

            $table->primary(['report_id', 'skill_id']);
        });

        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('cycle_id', 36);
            $table->char('student_id', 36);
            $table->char('branch_id', 36)->index('idx_monthly_reports_branch_id');
            $table->char('teacher_id', 36)->index('idx_monthly_reports_teacher_id');
            $table->date('report_month');
            $table->date('due_on');
            $table->smallInteger('video_target');
            $table->string('status');
            $table->text('development_summary')->nullable();
            $table->text('parent_challenges_summary')->nullable();
            $table->text('parent_message')->nullable();
            $table->text('internal_teacher_note')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->char('approved_by_user_id', 36)->nullable()->index('fk_monthly_reports_approved_by');
            $table->json('approved_snapshot')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();

            $table->index(['cycle_id', 'student_id'], 'fk_monthly_reports_cycle_student');
            $table->unique(['cycle_id', 'report_month'], 'uq_monthly_reports_cycle_month');
            $table->unique(['student_id', 'report_month'], 'uq_monthly_reports_student_month');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('user_id', 36);
            $table->string('notification_type');
            $table->string('title');
            $table->text('body');
            $table->text('action_path')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'idx_notifications_user_created');
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('charge_id', 36);
            $table->char('image_file_id', 36)->index('fk_payment_proofs_image');
            $table->char('uploaded_by_user_id', 36)->index('idx_payment_proofs_uploaded_by');
            $table->dateTime('submitted_at')->useCurrent();
            $table->string('status');
            $table->char('reviewed_by_user_id', 36)->nullable()->index('idx_payment_proofs_reviewed_by');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->tinyInteger('approved_charge_key')->nullable()->storedAs('(case when (`status` = _utf8mb4\'approved\') then 1 else NULL end)');

            $table->unique(['charge_id', 'approved_charge_key'], 'uq_payment_proofs_approved_charge');
        });

        Schema::create('payment_recap_runs', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('branch_id', 36)->index('idx_payment_recap_runs_branch_id');
            $table->date('recap_month');
            $table->date('scheduled_on');
            $table->string('status');
            $table->dateTime('generated_at')->nullable();
            $table->char('generated_by_user_id', 36)->nullable()->index('fk_payment_recap_generated_by');
            $table->char('export_file_id', 36)->nullable()->index('fk_payment_recap_export_file');

            $table->unique(['branch_id', 'recap_month'], 'uq_payment_recap_branch_month');
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->string('code')->unique('uq_programs_code');
            $table->string('name');
            $table->string('class_type');
            $table->smallInteger('monthly_video_target_override')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('public_content_sections', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->string('section_key')->unique('uq_public_content_sections_key');
            $table->string('title');
            $table->text('body')->nullable();
            $table->char('image_file_id', 36)->nullable()->index('fk_public_content_sections_image');
            $table->smallInteger('display_order');
            $table->boolean('is_published')->default(false);
        });

        Schema::create('quarterly_report_files', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('cycle_id', 36);
            $table->char('file_id', 36)->unique('uq_quarterly_report_file');
            $table->integer('version_number');
            $table->char('generated_by_user_id', 36)->index('idx_quarterly_report_files_generated_by');
            $table->dateTime('generated_at')->useCurrent();
            $table->text('source_snapshot_hash')->nullable();

            $table->unique(['cycle_id', 'version_number'], 'uq_quarterly_report_version');
        });

        Schema::create('report_cycles', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36)->index('idx_report_cycles_student_id');
            $table->integer('cycle_number');
            $table->date('start_month');
            $table->date('end_month');
            $table->date('share_due_on')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['id', 'student_id'], 'uq_report_cycles_id_student');
            $table->unique(['student_id', 'cycle_number'], 'uq_report_cycles_number');
            $table->unique(['student_id', 'start_month'], 'uq_report_cycles_start');
        });

        Schema::create('report_share_attempts', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('quarterly_report_file_id', 36)->index('fk_report_share_file');
            $table->char('teacher_id', 36)->index('idx_report_share_attempts_teacher_id');
            $table->char('guardian_id', 36)->index('idx_report_share_attempts_guardian_id');
            $table->string('recipient_phone_snapshot');
            $table->string('status');
            $table->dateTime('opened_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->text('teacher_note')->nullable();
        });

        Schema::create('report_status_events', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('report_id', 36)->index('idx_report_status_events_report_id');
            $table->char('actor_user_id', 36)->index('fk_report_status_events_actor');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('comment')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
        });

        Schema::create('session_students', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('session_id', 36);
            $table->char('student_id', 36)->index('idx_session_students_student_id');
            $table->string('attendance_status')->nullable();
            $table->string('individual_learning_note')->nullable();
            $table->dateTime('recorded_at')->nullable();

            $table->primary(['session_id', 'student_id']);
        });

        Schema::create('student_challenges', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36)->index('idx_student_challenges_student_id');
            $table->char('teacher_id', 36)->index('idx_student_challenges_teacher_id');
            $table->char('session_id', 36)->nullable()->index('fk_student_challenges_session');
            $table->string('category_name');
            $table->text('internal_note')->nullable();
            $table->date('logged_on');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36);
            $table->char('branch_id', 36)->index('idx_student_enrollments_branch_id');
            $table->char('program_id', 36)->index('idx_student_enrollments_program_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->tinyInteger('active_student_key')->nullable()->storedAs('(case when (`ends_on` is null) then 1 else NULL end)');

            $table->unique(['student_id', 'active_student_key'], 'uq_student_active_enrollment');
        });

        Schema::create('student_teacher_assignments', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36);
            $table->char('teacher_id', 36)->index('idx_student_teacher_assignments_teacher_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_report_owner')->default(false);
            $table->tinyInteger('active_report_owner_key')->nullable()->storedAs('(case when ((`ends_on` is null) and (`is_report_owner` = 1)) then 1 else NULL end)');

            $table->unique(['student_id', 'active_report_owner_key'], 'uq_student_active_report_owner');
        });

        Schema::create('students', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('portal_user_id', 36)->unique('uq_students_portal_user_id');
            $table->string('full_name');
            $table->char('photo_file_id', 36)->nullable()->index('fk_students_photo');
            $table->string('school_name')->nullable();
            $table->string('grade_name')->nullable();
            $table->date('began_on');
            $table->string('status');
            $table->text('special_notes_internal')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->smallInteger('id')->default(1)->primary();
            $table->string('organization_name');
            $table->text('organization_description')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->char('logo_file_id', 36)->nullable()->index('fk_system_settings_logo');
            $table->smallInteger('default_report_due_day');
            $table->smallInteger('default_monthly_video_target');
            $table->boolean('in_app_reminders_enabled')->default(true);
            $table->dateTime('updated_at')->useCurrent();
        });

        Schema::create('teacher_availability', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('teacher_id', 36)->index('fk_teacher_availability_teacher');
            $table->char('branch_id', 36);
            $table->date('available_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('class_type');
            $table->string('availability_status');
            $table->string('notes')->nullable();

            $table->index(['branch_id', 'available_on'], 'idx_teacher_availability_branch_date');
        });

        Schema::create('teacher_branch_assignments', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('teacher_id', 36);
            $table->char('branch_id', 36)->index('idx_teacher_branch_assignments_branch_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->tinyInteger('active_teacher_key')->nullable()->storedAs('(case when (`ends_on` is null) then 1 else NULL end)');

            $table->unique(['teacher_id', 'active_teacher_key'], 'uq_teacher_active_branch');
        });

        Schema::create('teacher_messages', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('student_id', 36)->index('idx_teacher_messages_student_id');
            $table->char('teacher_id', 36)->index('idx_teacher_messages_teacher_id');
            $table->char('session_id', 36)->nullable()->index('fk_teacher_messages_session');
            $table->text('message_body');
            $table->char('attachment_file_id', 36)->nullable()->index('fk_teacher_messages_attachment');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->unique('uq_teachers_user_id');
            $table->string('whatsapp_number');
            $table->char('photo_file_id', 36)->nullable()->index('fk_teachers_photo');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->collation = 'utf8mb4_0900_ai_ci';
            $table->charset = 'utf8mb4';

            $table->char('id', 36)->primary();
            $table->string('username')->unique('uq_users_username');
            $table->string('email')->nullable()->unique('uq_users_email');
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('role_code');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::table('branch_admin_assignments', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_branch_admin_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['admin_user_id'], 'fk_branch_admin_user')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('class_group_memberships', function (Blueprint $table) {
            $table->foreign(['class_group_id'], 'fk_class_membership_group')->references(['id'])->on('class_groups')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_class_membership_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('class_groups', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_class_groups_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['program_id'], 'fk_class_groups_program')->references(['id'])->on('programs')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['default_teacher_id'], 'fk_class_groups_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->foreign(['student_id'], 'fk_guardians_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('cascade');
        });

        Schema::table('learning_videos', function (Blueprint $table) {
            $table->foreign(['session_id'], 'fk_learning_videos_session')->references(['id'])->on('lesson_sessions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_learning_videos_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_learning_videos_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_lesson_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['class_group_id'], 'fk_lesson_class_group')->references(['id'])->on('class_groups')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['program_id'], 'fk_lesson_program')->references(['id'])->on('programs')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['rescheduled_from_session_id'], 'fk_lesson_rescheduled_from')->references(['id'])->on('lesson_sessions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_lesson_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('monthly_charges', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_monthly_charges_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_monthly_charges_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('monthly_report_skills', function (Blueprint $table) {
            $table->foreign(['report_id'], 'fk_monthly_report_skills_report')->references(['id'])->on('monthly_reports')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['skill_id'], 'fk_monthly_report_skills_skill')->references(['id'])->on('learning_skills')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->foreign(['approved_by_user_id'], 'fk_monthly_reports_approved_by')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['branch_id'], 'fk_monthly_reports_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['cycle_id', 'student_id'], 'fk_monthly_reports_cycle_student')->references(['id', 'student_id'])->on('report_cycles')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_monthly_reports_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_monthly_reports_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_notifications_user')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->foreign(['charge_id'], 'fk_payment_proofs_charge')->references(['id'])->on('monthly_charges')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['image_file_id'], 'fk_payment_proofs_image')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['reviewed_by_user_id'], 'fk_payment_proofs_reviewed_by')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['uploaded_by_user_id'], 'fk_payment_proofs_uploaded_by')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('payment_recap_runs', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_payment_recap_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['export_file_id'], 'fk_payment_recap_export_file')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['generated_by_user_id'], 'fk_payment_recap_generated_by')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('public_content_sections', function (Blueprint $table) {
            $table->foreign(['image_file_id'], 'fk_public_content_sections_image')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('quarterly_report_files', function (Blueprint $table) {
            $table->foreign(['cycle_id'], 'fk_quarterly_report_cycle')->references(['id'])->on('report_cycles')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['file_id'], 'fk_quarterly_report_file')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['generated_by_user_id'], 'fk_quarterly_report_generated_by')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('report_cycles', function (Blueprint $table) {
            $table->foreign(['student_id'], 'fk_report_cycles_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('report_share_attempts', function (Blueprint $table) {
            $table->foreign(['quarterly_report_file_id'], 'fk_report_share_file')->references(['id'])->on('quarterly_report_files')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['guardian_id'], 'fk_report_share_guardian')->references(['id'])->on('guardians')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_report_share_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('report_status_events', function (Blueprint $table) {
            $table->foreign(['actor_user_id'], 'fk_report_status_events_actor')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['report_id'], 'fk_report_status_events_report')->references(['id'])->on('monthly_reports')->onUpdate('no action')->onDelete('cascade');
        });

        Schema::table('session_students', function (Blueprint $table) {
            $table->foreign(['session_id'], 'fk_session_students_session')->references(['id'])->on('lesson_sessions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['student_id'], 'fk_session_students_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('student_challenges', function (Blueprint $table) {
            $table->foreign(['session_id'], 'fk_student_challenges_session')->references(['id'])->on('lesson_sessions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_student_challenges_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_student_challenges_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_student_enrollment_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['program_id'], 'fk_student_enrollment_program')->references(['id'])->on('programs')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_student_enrollment_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('student_teacher_assignments', function (Blueprint $table) {
            $table->foreign(['student_id'], 'fk_student_teacher_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_student_teacher_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreign(['photo_file_id'], 'fk_students_photo')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['portal_user_id'], 'fk_students_portal_user')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->foreign(['logo_file_id'], 'fk_system_settings_logo')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('teacher_availability', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_teacher_availability_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_teacher_availability_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('teacher_branch_assignments', function (Blueprint $table) {
            $table->foreign(['branch_id'], 'fk_teacher_branch_branch')->references(['id'])->on('branches')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_teacher_branch_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('teacher_messages', function (Blueprint $table) {
            $table->foreign(['attachment_file_id'], 'fk_teacher_messages_attachment')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['session_id'], 'fk_teacher_messages_session')->references(['id'])->on('lesson_sessions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['student_id'], 'fk_teacher_messages_student')->references(['id'])->on('students')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['teacher_id'], 'fk_teacher_messages_teacher')->references(['id'])->on('teachers')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreign(['photo_file_id'], 'fk_teachers_photo')->references(['id'])->on('file_assets')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['user_id'], 'fk_teachers_user')->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign('fk_teachers_photo');
            $table->dropForeign('fk_teachers_user');
        });

        Schema::table('teacher_messages', function (Blueprint $table) {
            $table->dropForeign('fk_teacher_messages_attachment');
            $table->dropForeign('fk_teacher_messages_session');
            $table->dropForeign('fk_teacher_messages_student');
            $table->dropForeign('fk_teacher_messages_teacher');
        });

        Schema::table('teacher_branch_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_teacher_branch_branch');
            $table->dropForeign('fk_teacher_branch_teacher');
        });

        Schema::table('teacher_availability', function (Blueprint $table) {
            $table->dropForeign('fk_teacher_availability_branch');
            $table->dropForeign('fk_teacher_availability_teacher');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropForeign('fk_system_settings_logo');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign('fk_students_photo');
            $table->dropForeign('fk_students_portal_user');
        });

        Schema::table('student_teacher_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_student_teacher_student');
            $table->dropForeign('fk_student_teacher_teacher');
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropForeign('fk_student_enrollment_branch');
            $table->dropForeign('fk_student_enrollment_program');
            $table->dropForeign('fk_student_enrollment_student');
        });

        Schema::table('student_challenges', function (Blueprint $table) {
            $table->dropForeign('fk_student_challenges_session');
            $table->dropForeign('fk_student_challenges_student');
            $table->dropForeign('fk_student_challenges_teacher');
        });

        Schema::table('session_students', function (Blueprint $table) {
            $table->dropForeign('fk_session_students_session');
            $table->dropForeign('fk_session_students_student');
        });

        Schema::table('report_status_events', function (Blueprint $table) {
            $table->dropForeign('fk_report_status_events_actor');
            $table->dropForeign('fk_report_status_events_report');
        });

        Schema::table('report_share_attempts', function (Blueprint $table) {
            $table->dropForeign('fk_report_share_file');
            $table->dropForeign('fk_report_share_guardian');
            $table->dropForeign('fk_report_share_teacher');
        });

        Schema::table('report_cycles', function (Blueprint $table) {
            $table->dropForeign('fk_report_cycles_student');
        });

        Schema::table('quarterly_report_files', function (Blueprint $table) {
            $table->dropForeign('fk_quarterly_report_cycle');
            $table->dropForeign('fk_quarterly_report_file');
            $table->dropForeign('fk_quarterly_report_generated_by');
        });

        Schema::table('public_content_sections', function (Blueprint $table) {
            $table->dropForeign('fk_public_content_sections_image');
        });

        Schema::table('payment_recap_runs', function (Blueprint $table) {
            $table->dropForeign('fk_payment_recap_branch');
            $table->dropForeign('fk_payment_recap_export_file');
            $table->dropForeign('fk_payment_recap_generated_by');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign('fk_payment_proofs_charge');
            $table->dropForeign('fk_payment_proofs_image');
            $table->dropForeign('fk_payment_proofs_reviewed_by');
            $table->dropForeign('fk_payment_proofs_uploaded_by');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign('fk_notifications_user');
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropForeign('fk_monthly_reports_approved_by');
            $table->dropForeign('fk_monthly_reports_branch');
            $table->dropForeign('fk_monthly_reports_cycle_student');
            $table->dropForeign('fk_monthly_reports_student');
            $table->dropForeign('fk_monthly_reports_teacher');
        });

        Schema::table('monthly_report_skills', function (Blueprint $table) {
            $table->dropForeign('fk_monthly_report_skills_report');
            $table->dropForeign('fk_monthly_report_skills_skill');
        });

        Schema::table('monthly_charges', function (Blueprint $table) {
            $table->dropForeign('fk_monthly_charges_branch');
            $table->dropForeign('fk_monthly_charges_student');
        });

        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropForeign('fk_lesson_branch');
            $table->dropForeign('fk_lesson_class_group');
            $table->dropForeign('fk_lesson_program');
            $table->dropForeign('fk_lesson_rescheduled_from');
            $table->dropForeign('fk_lesson_teacher');
        });

        Schema::table('learning_videos', function (Blueprint $table) {
            $table->dropForeign('fk_learning_videos_session');
            $table->dropForeign('fk_learning_videos_student');
            $table->dropForeign('fk_learning_videos_teacher');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropForeign('fk_guardians_student');
        });

        Schema::table('class_groups', function (Blueprint $table) {
            $table->dropForeign('fk_class_groups_branch');
            $table->dropForeign('fk_class_groups_program');
            $table->dropForeign('fk_class_groups_teacher');
        });

        Schema::table('class_group_memberships', function (Blueprint $table) {
            $table->dropForeign('fk_class_membership_group');
            $table->dropForeign('fk_class_membership_student');
        });

        Schema::table('branch_admin_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_branch_admin_branch');
            $table->dropForeign('fk_branch_admin_user');
        });

        Schema::dropIfExists('users');

        Schema::dropIfExists('teachers');

        Schema::dropIfExists('teacher_messages');

        Schema::dropIfExists('teacher_branch_assignments');

        Schema::dropIfExists('teacher_availability');

        Schema::dropIfExists('system_settings');

        Schema::dropIfExists('students');

        Schema::dropIfExists('student_teacher_assignments');

        Schema::dropIfExists('student_enrollments');

        Schema::dropIfExists('student_challenges');

        Schema::dropIfExists('session_students');

        Schema::dropIfExists('report_status_events');

        Schema::dropIfExists('report_share_attempts');

        Schema::dropIfExists('report_cycles');

        Schema::dropIfExists('quarterly_report_files');

        Schema::dropIfExists('public_content_sections');

        Schema::dropIfExists('programs');

        Schema::dropIfExists('payment_recap_runs');

        Schema::dropIfExists('payment_proofs');

        Schema::dropIfExists('notifications');

        Schema::dropIfExists('monthly_reports');

        Schema::dropIfExists('monthly_report_skills');

        Schema::dropIfExists('monthly_charges');

        Schema::dropIfExists('lesson_sessions');

        Schema::dropIfExists('learning_videos');

        Schema::dropIfExists('learning_skills');

        Schema::dropIfExists('guardians');

        Schema::dropIfExists('file_assets');

        Schema::dropIfExists('class_groups');

        Schema::dropIfExists('class_group_memberships');

        Schema::dropIfExists('branches');

        Schema::dropIfExists('branch_admin_assignments');
    }
};
