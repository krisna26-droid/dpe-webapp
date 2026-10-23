<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\MonthlyCharge;
use App\Models\MonthlyReport;
use App\Models\PaymentProof;
use App\Models\Program;
use App\Models\ReportCycle;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPortalEvent;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MonthlyReportWorkflowService;
use App\Services\PortalPaymentService;
use App\Services\QuarterlyReportContentService;
use App\Services\QuarterlyReportPdfService;
use App\Services\QuarterlyReportWorkflowService;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentPortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_one_child_dashboard_profile_and_empty_payments_use_real_data_without_internal_notes(): void
    {
        $this->actingAs($this->parent)->get(route('student.dashboard'))->assertOk()->assertSee('Siswa Portal')->assertDontSee('Siswa Rahasia Lain')->assertDontSee('RAHASIA PROFIL SISWA')->assertDontSee('PESAN PUBLIK');
        $this->get(route('student.profile'))->assertOk()->assertSee('Sekolah Contoh')->assertSee('Teacher Portal')->assertSee('Program Portal')->assertDontSee('RAHASIA PROFIL SISWA');
        $this->get(route('student.payments.index'))->assertOk()->assertSee('Belum ada periode pembayaran');
        $this->get(route('student.reports.index'))->assertOk()->assertSee('Juli 2026')->assertDontSee('DESKRIPSI PUBLIK')->assertDontSee('CHALLENGES PUBLIK');
    }

    public function test_unlinked_student_gets_clear_dashboard_and_cannot_select_another_student(): void
    {
        $actor = $this->user('student', 'Belum Terhubung');
        $this->actingAs($actor)->get(route('student.dashboard', ['student_id' => $this->student->id]))->assertOk()->assertSee('Akun belum terhubung')->assertDontSee('Siswa Portal');
        $this->get(route('student.payments.index'))->assertForbidden();
    }

    #[DataProvider('forbiddenRoles')]
    public function test_staff_and_inactive_users_cannot_use_parent_routes(string $role, string $path): void
    {
        $actor = $this->user($role === 'inactive' ? 'student' : $role, 'Aktor');
        if ($role === 'inactive') {
            $actor->update(['is_active' => false]);
        }
        $this->actingAs($actor)->get('/student/'.$path)->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        $rows = [];
        foreach (['admin', 'teacher', 'superadmin', 'inactive'] as $role) {
            foreach (['dashboard', 'profile', 'schedule', 'history', 'reports', 'payments'] as $path) {
                $rows[] = [$role, $path];
            }
        }

        return $rows;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('superadmin.payment-review.index'))->assertRedirect(route('login'));
    }

    public function test_upload_is_pending_actual_actor_private_file_and_atomic_history_with_confirmation_markup(): void
    {
        $charge = $this->charge();
        $page = $this->actingAs($this->parent)->get(route('student.payments.show', $charge))->assertOk()->assertSee('data-confirm-form', false)->assertSee('Kembali ke Pembayaran')->assertSee('Nominal belum ditetapkan');
        $this->post(route('student.payments.upload', $charge), ['image' => UploadedFile::fake()->image('bukti.png'), 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge), 'status' => 'approved', 'uploaded_by_user_id' => $this->superadmin->id])->assertRedirect(route('student.payments.show', $charge));
        $proof = $charge->paymentProofs()->first();
        $this->assertSame('pending', $proof->status);
        $this->assertSame($this->parent->id, $proof->uploaded_by_user_id);
        $this->assertNull($proof->reviewed_by_user_id);
        $this->assertSame($this->parent->id, StudentPortalEvent::first()->actor_user_id);
        Storage::disk('local')->assertExists($proof->imageFile->storage_key);
        $this->get(route('student.payments.image', $proof))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $this->get(route('student.payments.show', $charge))->headers->get('Cache-Control'));
        $this->get(route('student.payments.show', $charge))->assertDontSee($proof->imageFile->storage_key)->assertDontSee('type="file"', false);
    }

    public function test_other_child_cannot_read_or_upload_charge_or_image_or_report(): void
    {
        $charge = $this->charge();
        $proof = $this->upload($charge);
        $this->actingAs($this->other)->get(route('student.payments.show', $charge))->assertNotFound();
        $this->post(route('student.payments.upload', $charge), ['image' => UploadedFile::fake()->image('x.png')])->assertNotFound();
        $this->get(route('student.payments.image', $proof))->assertNotFound();
        $this->get(route('student.reports.monthly', $this->monthly()))->assertNotFound();
        $this->get(route('student.payments.index', ['student_id' => $this->student->id]))->assertDontSee('Juli 2026');
    }

    public function test_missing_stale_and_double_uploads_do_not_create_additional_proofs_or_files(): void
    {
        $charge = $this->charge();
        $signature = app(PortalPaymentService::class)->chargeSignature($charge);
        $this->actingAs($this->parent)->post(route('student.payments.upload', $charge), ['image' => UploadedFile::fake()->image('x.png')])->assertSessionHasErrors('expected_snapshot');
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->upload($charge);
        $this->post(route('student.payments.upload', $charge), ['image' => UploadedFile::fake()->image('x.png'), 'expected_snapshot' => $signature])->assertSessionHasErrors('expected_snapshot');
        $this->post(route('student.payments.upload', $charge), ['image' => UploadedFile::fake()->image('x.png'), 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('payment_proofs', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-proofs'));
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_uploads_are_rejected_without_writes(string $kind): void
    {
        $charge = $this->charge();
        $image = match ($kind) {
            'pdf' => UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf'),
            'svg' => UploadedFile::fake()->createWithContent('bukti.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'spoof' => UploadedFile::fake()->createWithContent('bukti.png', 'not an image'),
            'size' => UploadedFile::fake()->image('bukti.png')->size(5121),
        };
        $this->actingAs($this->parent)->post(route('student.payments.upload', $charge), ['image' => $image, 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('payment-proofs'));
    }

    public static function invalidImages(): array
    {
        return [['pdf'], ['svg'], ['spoof'], ['size']];
    }

    public function test_rejection_resubmission_and_revalidation_preserve_old_bytes_and_decisions(): void
    {
        $charge = $this->charge();
        $first = $this->upload($charge);
        $bytes = Storage::disk('local')->get($first->imageFile->storage_key);
        $this->decide($first, 'rejected', 'Nama rekening tidak sesuai');
        $this->actingAs($this->parent)->get(route('student.payments.show', $charge))->assertSee('Nama rekening tidak sesuai')->assertSee('Unggah Ulang Bukti');
        $second = $this->upload($charge);
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('pending', $second->status);
        $this->decide($second);
        $this->assertSame($bytes, Storage::disk('local')->get($first->imageFile->storage_key));
        $this->assertDatabaseCount('student_portal_events', 4);
        $this->assertTrue(app(PortalPaymentService::class)->paid($this->student->id, '2026-07'));
    }

    public function test_review_pages_stale_forms_and_only_superadmin_can_validate(): void
    {
        $proof = $this->upload($this->charge());
        $signature = app(PortalPaymentService::class)->proofSignature($proof);
        $this->actingAs($this->superadmin)->get(route('superadmin.payment-review.index'))->assertOk()->assertSee('Siswa Portal')->assertSee('Periksa Bukti');
        $this->get(route('superadmin.payment-review.show', $proof))->assertOk()->assertSee('data-confirm-form', false)->assertSee('Bukti transfer siswa');
        $this->get(route('superadmin.payment-review.image', $proof))->assertOk();
        $this->post(route('superadmin.payment-review.review', $proof), ['status' => 'approved'])->assertSessionHasErrors('expected_snapshot');
        $this->decide($proof);
        $this->post(route('superadmin.payment-review.review', $proof), ['status' => 'rejected', 'rejection_reason' => 'lama', 'expected_snapshot' => $signature])->assertSessionHasErrors('expected_snapshot');
        foreach ([$this->parent, $this->other, $this->admin, $this->teacher->user] as $actor) {
            $this->actingAs($actor)->get(route('superadmin.payment-review.show', $proof))->assertForbidden();
            $this->post(route('superadmin.payment-review.review', $proof), ['status' => 'approved', 'expected_snapshot' => $signature])->assertForbidden();
        }
        $this->assertSame('approved', $proof->fresh()->status);
    }

    public function test_image_corruption_missing_file_and_unsafe_path_cannot_be_served_or_approved(): void
    {
        $proof = $this->upload($this->charge());
        Storage::disk('local')->put($proof->imageFile->storage_key, 'tampered');
        $this->actingAs($this->parent)->get(route('student.payments.image', $proof))->assertNotFound();
        $this->actingAs($this->superadmin)->post(route('superadmin.payment-review.review', $proof), ['status' => 'approved', 'expected_snapshot' => app(PortalPaymentService::class)->proofSignature($proof)])->assertSessionHasErrors('status');
        Storage::disk('local')->delete($proof->imageFile->storage_key);
        $this->get(route('superadmin.payment-review.image', $proof))->assertNotFound();
        $proof->imageFile->update(['storage_key' => 'payment-proofs/../private.png']);
        $this->get(route('superadmin.payment-review.image', $proof))->assertNotFound();
        $this->assertSame('pending', $proof->fresh()->status);
    }

    public function test_upload_and_review_roll_back_with_audit_failure_and_remove_new_file(): void
    {
        $charge = $this->charge();
        StudentPortalEvent::creating(fn () => throw new \RuntimeException('audit failed'));
        try {
            $this->upload($charge);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failed', $e->getMessage());
        }
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseCount('file_assets', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('payment-proofs'));
        StudentPortalEvent::flushEventListeners();
        $proof = $this->upload($charge);
        StudentPortalEvent::creating(fn () => throw new \RuntimeException('audit failed'));
        try {
            $this->decide($proof);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failed', $e->getMessage());
        }
        $this->assertSame('pending', $proof->fresh()->status);
        $this->assertDatabaseCount('student_portal_events', 1);
    }

    #[DataProvider('monthlyGates')]
    public function test_monthly_access_requires_same_month_payment_and_approval_without_leaking_draft(bool $approved, string $payment, bool $open): void
    {
        $report = $this->monthly();
        if ($approved) {
            $report = $this->approveMonth($report);
        }
        if ($payment !== 'none') {
            $proof = $this->upload($this->charge($payment === 'other' ? '2026-08-01' : '2026-07-15'));
            if ($payment !== 'pending') {
                $this->decide($proof, $payment === 'rejected' ? 'rejected' : 'approved');
            }
        }
        $response = $this->actingAs($this->parent)->get(route('student.reports.monthly', $report))->assertOk();
        if ($open) {
            $response->assertSee('RINGKASAN PUBLIK')->assertSee('CHALLENGES PUBLIK')->assertSee('PESAN PUBLIK');
        } else {
            $response->assertSee('Terkunci')->assertDontSee('RINGKASAN PUBLIK')->assertDontSee('DESKRIPSI PUBLIK');
        }
        $response->assertDontSee('RAHASIA TEACHER BULANAN')->assertDontSee('RAHASIA PROFIL SISWA');
    }

    public static function monthlyGates(): array
    {
        return [[false, 'none', false], [false, 'approved', false], [true, 'none', false], [true, 'pending', false], [true, 'rejected', false], [true, 'other', false], [true, 'approved', true]];
    }

    public function test_approved_snapshot_is_frozen_sanitized_and_legacy_or_wrong_identity_stays_locked(): void
    {
        $report = $this->approveMonth($this->monthly());
        $this->decide($this->upload($this->charge()));
        $report->update(['parent_message' => 'DRAF BARU RAHASIA', 'development_summary' => 'DRAF BARU RAHASIA']);
        $this->actingAs($this->parent)->get(route('student.reports.monthly', $report))->assertSee('PESAN PUBLIK')->assertDontSee('DRAF BARU RAHASIA');
        $snapshot = $report->approved_snapshot;
        $snapshot['public_content']['parent_message'] = '<script>alert("bad")</script>';
        $snapshot['public_content']['internal_teacher_note'] = 'RAHASIA TERSISIP';
        $snapshot['public_content']['videos'] = [['video_on' => '2026-07-20', 'topic' => 'Video', 'video_url' => 'javascript:alert(1)']];
        $report->update(['approved_snapshot' => $snapshot]);
        $this->get(route('student.reports.monthly', $report))->assertDontSee('<script>alert("bad")</script>', false)->assertDontSee('javascript:alert')->assertDontSee('RAHASIA TERSISIP');
        $snapshot['student_id'] = $this->otherStudent->id;
        $report->update(['approved_snapshot' => $snapshot]);
        $this->get(route('student.reports.monthly', $report))->assertSee('Salinan laporan')->assertDontSee('PESAN PUBLIK');
        $report->update(['approved_snapshot' => null]);
        $this->get(route('student.reports.monthly', $report))->assertSee('Terkunci')->assertDontSee('DRAF BARU RAHASIA');
    }

    public function test_ambiguous_calendar_charges_never_unlock_reports_or_allow_upload(): void
    {
        $report = $this->approveMonth($this->monthly());
        $charge = $this->charge();
        $this->decide($this->upload($charge));
        $this->charge('2026-07-15');
        $this->actingAs($this->parent)->get(route('student.reports.monthly', $report))->assertSee('Terkunci')->assertDontSee('RINGKASAN PUBLIK');
        $this->get(route('student.payments.show', $charge))->assertSee('Ada beberapa tagihan')->assertDontSee('type="file"', false);
    }

    public function test_final_pdf_real_download_current_revision_and_direct_url_revocation(): void
    {
        [$final, $file] = $this->publish();
        $url = route('student.reports.pdf', [$final, $file]);
        $this->actingAs($this->parent)->get(route('student.reports.quarterly', $final))->assertOk()->assertSee('NOTE FINAL PUBLIK')->assertSee('Unduh PDF')->assertDontSee('RAHASIA');
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get($url.'?download=1')->assertHeader('Content-Disposition', 'attachment; filename="DPE-Rapor-2026-07.pdf"');
        $this->actingAs($this->other)->get($url)->assertNotFound();
        $proof = $this->student->monthlyCharges()->first()->paymentProofs()->first();
        $this->decide($proof, 'rejected', 'Validasi dibatalkan setelah pemeriksaan');
        $this->actingAs($this->parent)->get($url)->assertForbidden();
        $this->get(route('student.reports.quarterly', $final))->assertSee('Terkunci')->assertDontSee('NOTE FINAL PUBLIK')->assertDontSee('Unduh PDF');
        $event = StudentPortalEvent::where('payment_proof_id', $proof->id)->where('event_type', 'payment_reviewed')->latest('id')->first();
        $this->assertSame('approved', $event->before_snapshot['status']);
        $this->assertSame('rejected', $event->after_snapshot['status']);
    }

    #[DataProvider('finalBlockers')]
    public function test_final_detail_and_known_pdf_url_recheck_every_gate(string $blocker): void
    {
        [$final, $file] = $this->publish();
        match ($blocker) {
            'monthly' => $this->monthly()->update(['status' => 'revision']),
            'final' => $final->update(['status' => 'draft']),
            'snapshot' => $final->update(['approved_snapshot' => null]),
            'missing' => Storage::disk('local')->delete($file->file->storage_key),
            'corrupt' => Storage::disk('local')->put($file->file->storage_key, '%PDF-tampered'),
        };
        $this->actingAs($this->parent)->get(route('student.reports.quarterly', $final))->assertOk()->assertSee('Terkunci')->assertDontSee('NOTE FINAL PUBLIK');
        $this->get(route('student.reports.pdf', [$final, $file]))->assertForbidden();
    }

    public static function finalBlockers(): array
    {
        return [['monthly'], ['final'], ['snapshot'], ['missing'], ['corrupt']];
    }

    public function test_regeneration_selects_latest_file_and_reopening_revokes_old_parent_pdf(): void
    {
        [$final, $file] = $this->publish();
        $next = app(QuarterlyReportPdfService::class)->generate($this->teacher->user, $final, ['expected_snapshot' => app(QuarterlyReportContentService::class)->signature($final), 'comment' => 'Buat ulang file'], true);
        $this->actingAs($this->parent)->get(route('student.reports.pdf', [$final, $file]))->assertNotFound();
        $this->get(route('student.reports.pdf', [$final, $next]))->assertOk();
        app(QuarterlyReportWorkflowService::class)->transition($this->teacher->user, $final, 'reopen', ['expected_snapshot' => app(QuarterlyReportContentService::class)->signature($final), 'comment' => 'Perbaiki rapor']);
        $this->get(route('student.reports.pdf', [$final, $next]))->assertForbidden();
        Storage::disk('local')->assertExists($file->file->storage_key);
    }

    private function lesson(string $date, ?ClassGroup $group = null, bool $attach = true): LessonSession
    {
        $session = LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'class_group_id' => $group?->id,
            'planned_start_at' => $date.' 16:00:00', 'planned_end_at' => $date.' 17:00:00', 'status' => 'scheduled', 'topic' => 'TOPIK '.$date, 'material' => 'MATERI PUBLIK', 'activity' => 'AKTIVITAS PUBLIK']);
        if ($attach) {
            SessionStudent::create(['session_id' => $session->id, 'student_id' => $this->student->id, 'attendance_status' => 'present', 'individual_learning_note' => 'RAHASIA PER SESI']);
        }

        return $session;
    }

    public function test_actual_roster_join_membership_enrollment_private_and_future_schedule(): void
    {
        $this->lesson('2026-07-08');
        $this->lesson('2026-07-10');
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'code' => Str::random(12), 'name' => 'Kelas Portal', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'class_group_id' => $group->id, 'starts_on' => '2026-07-15', 'ends_on' => '2026-07-25']);
        $this->lesson('2026-07-14', $group, false);
        $this->lesson('2026-07-20', $group, false);
        $this->lesson('2026-07-26', $group, false);
        $this->lesson('2026-07-21', null, false);
        $future = $this->lesson('2026-10-20');
        $history = $this->actingAs($this->parent)->get(route('student.history', ['month' => '2026-07']))->assertOk()->assertSee('TOPIK 2026-07-10')->assertSee('TOPIK 2026-07-20')->assertDontSee('TOPIK 2026-07-08')->assertDontSee('TOPIK 2026-07-14')->assertDontSee('TOPIK 2026-07-26')->assertDontSee('TOPIK 2026-07-21')->assertDontSee('RAHASIA PER SESI');
        $this->get(route('student.schedule', ['month' => '2026-10']))->assertOk()->assertSee('TOPIK 2026-10-20')->assertDontSee('Kehadiran siswa:');
        $this->get(route('student.history', ['month' => '2026-10']))->assertDontSee('TOPIK 2026-10-20');
        $this->get(route('student.dashboard'))->assertSee('20 Oktober 2026');
    }

    public function test_visibility_is_scoped_per_branch_history_and_monthly_and_stale_aba_settings_rejected(): void
    {
        $report = $this->approveMonth($this->monthly());
        $this->decide($this->upload($this->charge()));
        $this->lesson('2026-07-20');
        $otherBranch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'Cabang Lain', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 18, 'is_active' => true]);
        $old = app(PortalPaymentService::class)->settingSignature($this->branch);
        $this->actingAs($this->superadmin)->get(route('superadmin.portal-settings.index'))->assertOk()->assertSee('Simpan Pengaturan Cabang');
        $this->put(route('superadmin.portal-settings.save', $this->branch), ['visible' => 0, 'expected_snapshot' => $old])->assertRedirect();
        $this->assertFalse($this->branch->fresh()->portal_attendance_visible);
        $this->assertTrue($otherBranch->fresh()->portal_attendance_visible);
        $this->actingAs($this->parent)->get(route('student.history', ['month' => '2026-07']))->assertSee('TOPIK 2026-07-20')->assertDontSee('Kehadiran siswa:');
        $this->get(route('student.reports.monthly', $report))->assertSee('RINGKASAN PUBLIK')->assertDontSee('Kehadiran siswa');
        $this->put(route('superadmin.portal-settings.save', $this->branch), ['visible' => 1, 'expected_snapshot' => $old])->assertForbidden();
        $this->actingAs($this->superadmin)->put(route('superadmin.portal-settings.save', $this->branch), ['visible' => 1, 'expected_snapshot' => app(PortalPaymentService::class)->settingSignature($this->branch)])->assertRedirect();
        $this->put(route('superadmin.portal-settings.save', $this->branch), ['visible' => 0, 'expected_snapshot' => $old])->assertSessionHasErrors('expected_snapshot');
        $this->actingAs($this->parent)->get(route('student.reports.monthly', $report))->assertSee('Kehadiran siswa')->assertSee('Total hadir: 1');
    }

    public function test_inactive_student_retains_history_but_cannot_upload_new_proof(): void
    {
        $charge = $this->charge();
        $this->student->update(['status' => 'inactive']);
        $this->actingAs($this->parent)->get(route('student.profile'))->assertOk()->assertSee('Nonaktif');
        $this->get(route('student.payments.show', $charge))->assertOk()->assertDontSee('type="file"', false);
        $this->post(route('student.payments.upload', $charge), ['image' => UploadedFile::fake()->image('x.png'), 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge)])->assertSessionHasErrors('image');
    }

    public function test_account_reassignment_and_inactive_authenticated_user_revoke_existing_links(): void
    {
        $charge = $this->charge();
        $proof = $this->upload($charge);
        $replacement = $this->user('student', 'Akun Pengganti');
        $this->actingAs($this->parent);
        $this->student->update(['portal_user_id' => $replacement->id]);
        $this->get(route('student.payments.image', $proof))->assertNotFound();
        $this->get(route('student.reports.monthly', $this->monthly()))->assertForbidden();
        $this->actingAs($replacement)->get(route('student.payments.image', $proof))->assertOk();
        $replacement->update(['is_active' => false]);
        $this->get(route('student.payments.image', $proof))->assertForbidden();
    }

    public function test_get_routes_do_not_write_and_filters_reject_invalid_month(): void
    {
        $before = StudentPortalEvent::count();
        $this->actingAs($this->parent);
        foreach (['student.dashboard', 'student.profile', 'student.schedule', 'student.history', 'student.reports.index', 'student.payments.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('student.history', ['month' => '2026-99']))->assertSessionHasErrors('month');
        $this->assertSame($before, StudentPortalEvent::count());
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseCount('quarterly_reports', 0);
    }

    public function test_optional_nominal_can_be_saved_and_unique_calendar_month_is_preserved(): void
    {
        $this->actingAs($this->superadmin)->post(route('superadmin.monthly-charges.store'), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-07-15'])->assertRedirect();
        $charge = $this->student->monthlyCharges()->first();
        $this->assertNull($charge->amount_idr);
        $this->assertSame('2026-07-01', $charge->charge_month->toDateString());
        $this->get(route('superadmin.monthly-charges.show', $charge))->assertSee('Belum ditetapkan')->assertDontSee('Rp 0');
        $this->post(route('superadmin.monthly-charges.store'), ['student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-07-01'])->assertSessionHasErrors('charge_month');
    }

    public function test_branch_setting_is_rolled_back_when_its_history_cannot_be_saved(): void
    {
        StudentPortalEvent::creating(fn () => throw new \RuntimeException('Riwayat tidak tersedia'));
        try {
            app(PortalPaymentService::class)->saveVisibility($this->superadmin, $this->branch, ['visible' => 0, 'expected_snapshot' => app(PortalPaymentService::class)->settingSignature($this->branch)]);
            $this->fail('Pengaturan tidak boleh tersimpan tanpa riwayat.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Riwayat tidak tersedia', $error->getMessage());
        }
        $this->assertTrue($this->branch->fresh()->portal_attendance_visible);
        $this->assertDatabaseCount('student_portal_events', 0);
    }

    #[DataProvider('rollbackBlockers')]
    public function test_migration_rollback_preserves_new_history_settings_and_unset_amounts(string $blocker): void
    {
        match ($blocker) {
            'history' => $this->upload($this->charge()),
            'visibility' => $this->branch->forceFill(['portal_attendance_visible' => false])->save(),
            'nominal' => $this->charge(),
        };
        $migration = require database_path('migrations/2026_10_12_010000_add_student_portal_and_payment_history.php');
        try {
            $migration->down();
            $this->fail('Rollback harus menolak penghapusan data portal.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Rollback ditolak', $error->getMessage());
        }
        $this->assertTrue(Schema::hasTable('student_portal_events'));
        $this->assertTrue(Schema::hasColumn('branches', 'portal_attendance_visible'));
        if ($blocker === 'history') {
            $this->assertDatabaseCount('student_portal_events', 1);
        }
        if ($blocker === 'visibility') {
            $this->assertFalse($this->branch->fresh()->portal_attendance_visible);
        }
        if ($blocker === 'nominal') {
            $this->assertNull($this->student->monthlyCharges()->first()->amount_idr);
        }
    }

    public static function rollbackBlockers(): array
    {
        return [['history'], ['visibility'], ['nominal']];
    }

    public function test_migration_keeps_existing_charge_and_student_data_and_defaults_visibility_to_enabled(): void
    {
        $charge = $this->charge();
        $charge->update(['amount_idr' => '375000.50']);
        $migration = require database_path('migrations/2026_10_12_010000_add_student_portal_and_payment_history.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('student_portal_events'));
        $migration->up();
        $this->assertSame('375000.50', $charge->fresh()->amount_idr);
        $this->assertSame($this->parent->id, $this->student->fresh()->portal_user_id);
        $this->assertSame('2026-07-09', $this->student->fresh()->began_on->toDateString());
        $this->assertTrue($this->branch->fresh()->portal_attendance_visible);
        $charge->update(['amount_idr' => null]);
        $this->assertNull($charge->fresh()->amount_idr);
    }

    private function enforceCsrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends ValidateCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
    }

    public function test_upload_context_uses_current_session_preserves_reviewed_version_and_does_not_write(): void
    {
        $this->enforceCsrf();
        $charge = $this->charge();
        $snapshot = app(PortalPaymentService::class)->chargeSignature($charge);
        $page = $this->actingAs($this->parent)->withSession(['_token' => str_repeat('a', 40)])
            ->get(route('student.payments.show', $charge))->assertOk();
        $page->assertSee('data-payment-upload', false)->assertSee('dpe-portal-upload-form')->assertSee('portal-payment.js');
        // Export actual Blade output when regenerating the frontend DOM fixture.
        if ($fixturePath = getenv('DPE_PORTAL_DOM_FIXTURE')) {
            file_put_contents($fixturePath, $page->getContent());
        }
        $context = $this->withSession(['_token' => str_repeat('b', 40)])
            ->getJson(route('student.payments.upload-context', $charge))->assertOk()
            ->assertJsonPath('csrf_token', str_repeat('b', 40))->assertJsonPath('expected_snapshot', $snapshot)->assertJsonPath('can_upload', true);
        $this->assertStringContainsString('no-store', $context->headers->get('Cache-Control'));
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseCount('student_portal_events', 0);
        $this->post(route('student.payments.upload', $charge), [
            '_token' => $context->json('csrf_token'), 'expected_snapshot' => $snapshot, 'image' => UploadedFile::fake()->image('bukti.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message', 'Bukti berhasil diunggah dan menunggu validasi Super Admin.');
        $this->assertDatabaseCount('payment_proofs', 1);
        $this->assertSame('pending', $charge->paymentProofs()->first()->status);
        $this->assertSame($this->parent->id, $charge->paymentProofs()->first()->uploaded_by_user_id);
        $this->get(route('student.payments.show', $charge))->assertOk()->assertSee('Menunggu Validasi')->assertDontSee('type="file"', false);
    }

    public function test_real_csrf_rejects_stale_or_missing_tokens_without_saving(): void
    {
        $this->enforceCsrf();
        $charge = $this->charge();
        $this->actingAs($this->parent)->withSession(['_token' => str_repeat('b', 40)]);
        foreach ([str_repeat('a', 40), null] as $token) {
            $this->post(route('student.payments.upload', $charge), [
                '_token' => $token, 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge), 'image' => UploadedFile::fake()->image('bukti.png'),
            ], ['Accept' => 'application/json'])->assertStatus(419);
        }
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseCount('student_portal_events', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_valid_csrf_keeps_stale_data_and_invalid_image_validation_active(): void
    {
        $this->enforceCsrf();
        $charge = $this->charge();
        $signature = app(PortalPaymentService::class)->chargeSignature($charge);
        $charge->update(['amount_idr' => 750000]);
        $this->actingAs($this->parent)->withSession(['_token' => str_repeat('b', 40)]);
        $this->post(route('student.payments.upload', $charge), ['_token' => str_repeat('b', 40), 'expected_snapshot' => $signature, 'image' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
        $this->post(route('student.payments.upload', $charge), ['_token' => str_repeat('b', 40), 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge), 'image' => UploadedFile::fake()->createWithContent('x.png', 'gambar palsu')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    #[DataProvider('contextForbiddenRoles')]
    public function test_upload_context_requires_active_student_role(string $role): void
    {
        $actor = $this->user($role === 'inactive' ? 'student' : $role, 'Aktor Konteks');
        if ($role === 'inactive') {
            $actor->update(['is_active' => false]);
        }
        $this->actingAs($actor)->getJson(route('student.payments.upload-context', $this->charge()))->assertForbidden();
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public static function contextForbiddenRoles(): array
    {
        return [['superadmin'], ['admin'], ['teacher'], ['inactive']];
    }

    public function test_other_child_and_guest_cannot_obtain_payment_session_context(): void
    {
        $charge = $this->charge();
        $this->getJson(route('student.payments.upload-context', $charge))->assertUnauthorized();
        $this->actingAs($this->other)->getJson(route('student.payments.upload-context', $charge))->assertNotFound();
    }

    public function test_context_detects_pending_proofs_and_inactive_students_without_overwriting_form_version(): void
    {
        $charge = $this->charge();
        $old = app(PortalPaymentService::class)->chargeSignature($charge);
        $this->upload($charge);
        $context = $this->actingAs($this->parent)->getJson(route('student.payments.upload-context', $charge))->assertOk()->assertJsonPath('can_upload', false);
        $this->assertNotSame($old, $context->json('expected_snapshot'));
        $charge = $this->charge('2026-08-01');
        $this->student->update(['status' => 'inactive']);
        $this->getJson(route('student.payments.upload-context', $charge))->assertJsonPath('can_upload', false);
    }
}
