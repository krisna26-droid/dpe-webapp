<?php

namespace Tests\Concerns;

use App\Models\{Branch, BranchAdminAssignment, FileAsset, MonthlyCharge, MonthlyReport, PaymentProof, Program, ReportCycle, Student, StudentEnrollment, StudentPortalEvent, StudentTeacherAssignment, Teacher, User};
use App\Services\{MonthlyReportWorkflowService, PortalPaymentService, QuarterlyReportContentService, QuarterlyReportPdfService, QuarterlyReportWorkflowService, StudentProgressService};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

trait WhatsappReportFixtures
{
    private User $parent;
    private User $other;
    private User $superadmin;
    private User $admin;
    private Teacher $teacher;
    private Student $student;
    private Student $otherStudent;
    private Branch $branch;
    private Program $program;
    private ReportCycle $cycle;
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Makassar'));
        Storage::fake('local');
        $this->parent = $this->user('student', 'Parent Contoh');
        $this->other = $this->user('student', 'Parent Lain');
        $this->superadmin = $this->user('superadmin', 'Super Admin Contoh');
        $this->admin = $this->user('admin', 'Admin Contoh');
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', 'Teacher Portal')->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Portal', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Program Portal', 'class_type' => 'private', 'is_active' => true]);
        $this->student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->parent->id, 'full_name' => 'Siswa Portal', 'school_name' => 'Sekolah Contoh', 'grade_name' => 'Grade 4', 'began_on' => '2026-07-09', 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL SISWA']);
        $this->otherStudent = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->other->id, 'full_name' => 'Siswa Rahasia Lain', 'began_on' => '2026-07-09', 'status' => 'active']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-07-09']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-07-09', 'is_report_owner' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_number' => 1, 'start_month' => '2026-07-01', 'end_month' => '2026-09-01']);
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $month) {
            $report = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'cycle_id' => $this->cycle->id, 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id,
                'report_month' => $month, 'due_on' => Carbon::parse($month)->endOfMonth()->toDateString(), 'status' => 'draft', 'video_target' => 0, 'video_target_confirmed_at' => now(), 'video_target_confirmed_by_user_id' => $this->admin->id,
                'development_summary' => 'RINGKASAN PUBLIK', 'parent_challenges_summary' => 'CHALLENGES PUBLIK', 'parent_message' => 'PESAN PUBLIK', 'internal_teacher_note' => 'RAHASIA TEACHER BULANAN']);
            foreach (app(StudentProgressService::class)->categories() as $code => $skill) {
                DB::table('monthly_report_skills')->insert(['report_id' => $report->id, 'skill_id' => $skill->id, 'trend' => 'Improving', 'description' => 'DESKRIPSI PUBLIK '.$code]);
            }
        }
    }

    protected function tearDown(): void
    {
        StudentPortalEvent::flushEventListeners();
        FileAsset::flushEventListeners();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => 'fixture-password', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function monthly(): MonthlyReport
    {
        return $this->student->monthlyReports()->oldest('report_month')->first();
    }

    private function approveMonth(MonthlyReport $report): MonthlyReport
    {
        $workflow = app(MonthlyReportWorkflowService::class);
        $report = $workflow->submit($report, $this->teacher->user);

        return $workflow->approve($report, $this->admin);
    }

    private function charge(string $month = '2026-07-01', ?Student $student = null): MonthlyCharge
    {
        return MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => ($student ?? $this->student)->id, 'branch_id' => $this->branch->id, 'charge_month' => $month, 'amount_idr' => null]);
    }

    private function upload(MonthlyCharge $charge): PaymentProof
    {
        return app(PortalPaymentService::class)->upload($this->parent, $charge, ['image' => UploadedFile::fake()->image('bukti.png'), 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge)]);
    }

    private function decide(PaymentProof $proof, string $status = 'approved', string $reason = 'Bukti tidak sesuai'): PaymentProof
    {
        return app(PortalPaymentService::class)->review($this->superadmin, $proof, ['status' => $status, 'rejection_reason' => $reason, 'expected_snapshot' => app(PortalPaymentService::class)->proofSignature($proof)]);
    }

    private function publish(): array
    {
        foreach ($this->student->monthlyReports()->get() as $monthly) {
            $this->approveMonth($monthly);
            $this->decide($this->upload($this->charge($monthly->report_month->toDateString())));
        }
        $workflow = app(QuarterlyReportWorkflowService::class);
        $content = app(QuarterlyReportContentService::class);
        $final = $workflow->prepare($this->admin, $this->cycle, ['teacher_id' => $this->teacher->id, 'expected_snapshot' => $content->preparationSignature($this->cycle->fresh())]);
        $skills = collect(QuarterlyReportContentService::SKILLS)->map(fn ($name) => ['score' => 'B', 'description' => 'FINAL PUBLIK '.$name])->all();
        $final = $workflow->save($this->teacher->user, $final, ['skills' => $skills, 'parent_note' => 'NOTE FINAL PUBLIK', 'expected_snapshot' => $content->signature($final)]);
        $final = $workflow->transition($this->teacher->user, $final, 'submit', ['expected_snapshot' => $content->signature($final)]);
        $final = $workflow->transition($this->admin, $final, 'approve', ['expected_snapshot' => $content->signature($final)]);
        $file = app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $final, ['expected_snapshot' => $content->signature($final)]);

        return [$final, $file];
    }

}
