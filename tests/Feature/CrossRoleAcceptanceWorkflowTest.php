<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\LessonSession;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\Program;
use App\Models\QuarterlyReport;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DashboardSettingsService;
use App\Services\MonthlyReportContentService;
use App\Services\PortalPaymentService;
use App\Services\QuarterlyReportContentService;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossRoleAcceptanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $sa;

    private User $admin;

    private User $teacherUser;

    private User $parent;

    private Teacher $teacher;

    private Branch $branch;

    private Program $program;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Makassar'));
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->sa = $this->user('superadmin');
        $this->admin = $this->user('admin');
        $this->teacherUser = $this->user('teacher');
        $this->parent = $this->user('student');
        $this->branch = Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => 'Cabang Alur Uji', 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => 'English Alur Uji', 'class_type' => 'private', 'is_active' => true]);
        $this->teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->teacherUser->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->student = $this->pupil('Peserta 0 Alur Uji', $this->parent);
        SystemSetting::create(['id' => 1, 'organization_name' => 'DPE Uji', 'default_report_due_day' => 31, 'default_monthly_video_target' => 4]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(16), 'password_hash' => Hash::make('Password123!'), 'full_name' => ucfirst($role).' Alur Uji', 'role_code' => $role, 'is_active' => true]);
    }

    private function pupil(string $name, ?User $parent = null): Student
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => ($parent ?? $this->user('student'))->id, 'full_name' => $name, 'began_on' => '2026-10-05', 'status' => 'active', 'special_notes_internal' => 'RAHASIA PROFIL ALUR']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-05']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => $this->teacher->id, 'starts_on' => '2026-10-05', 'is_report_owner' => true]);

        return $student;
    }

    private function lesson(): LessonSession
    {
        return LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'teaching_location' => 'student_home', 'planned_start_at' => '2026-10-10 09:00:00', 'planned_end_at' => '2026-10-10 10:00:00', 'status' => 'completed']);
    }

    public static function searchRoles(): array
    {
        return [['superadmin'], ['admin'], ['teacher']];
    }

    #[DataProvider('searchRoles')]
    public function test_zero_search_filters_assigned_pupils_before_report_is_prepared(string $role): void
    {
        $other = $this->pupil('Peserta 1 Alur Uji');
        $actor = ['superadmin' => $this->sa, 'admin' => $this->admin, 'teacher' => $this->teacherUser][$role];
        $this->actingAs($actor)->get(route($role.'.student-progress.index', ['month' => '2026-10', 'q' => '0']))->assertOk()
            ->assertViewHas('students', fn ($rows) => $rows->modelKeys() === [$this->student->id])
            ->assertDontSee($other->full_name);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    #[DataProvider('searchRoles')]
    public function test_zero_search_filters_attendance_roster_without_recording_attendance(string $role): void
    {
        $other = $this->pupil('Peserta 1 Alur Uji');
        $lesson = $this->lesson();
        foreach ([$this->student, $other] as $student) {
            SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $student->id]);
        }
        $actor = ['superadmin' => $this->sa, 'admin' => $this->admin, 'teacher' => $this->teacherUser][$role];
        $this->actingAs($actor)->get(route($role.'.student-attendance.index', ['period' => '2026-10', 'month' => '2026-10', 'class_group_id' => 'private', 'q' => '0']))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('student.id')->values()->all() === [$this->student->id])
            ->assertViewHas('summary', fn ($summary) => $summary['unrecorded'] === 1);
        $this->assertDatabaseCount('student_attendance_audits', 0);
    }

    private function badge(string $html, string $code, string $tone, string $label): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $nodes = (new \DOMXPath($dom))->query('//span[@data-status="'.$code.'" and @data-status-tone="'.$tone.'"]');
        $this->assertGreaterThan(0, $nodes->length, 'Missing '.$code.' / '.$tone.' status');
        $this->assertTrue(collect($nodes)->contains(fn ($node) => str_contains($node->textContent, $label)), 'Missing status label '.$label);
    }

    private function paymentPage(MonthlyCharge $charge, string $code, string $tone, string $label): void
    {
        foreach ([
            [$this->admin, route('admin.payments.index', ['month' => $charge->charge_month->format('Y-m')])],
            [$this->admin, route('admin.payments.show', $charge)],
            [$this->parent, route('student.payments.index')],
            [$this->parent, route('student.payments.show', $charge)],
        ] as [$actor, $url]) {
            $response = $this->actingAs($actor)->get($url)->assertOk();
            $this->badge($response->getContent(), $code, $tone, $label);
        }
    }

    private function upload(MonthlyCharge $charge): PaymentProof
    {
        $image = UploadedFile::fake()->createWithContent('bukti-alur.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGP4DwQACfsD/fteaysAAAAASUVORK5CYII='));
        $this->actingAs($this->parent)->post(route('student.payments.upload', $charge), ['image' => $image, 'expected_snapshot' => app(PortalPaymentService::class)->chargeSignature($charge)])->assertRedirect()->assertSessionHasNoErrors();

        return $charge->paymentProofs()->latest('submitted_at')->latest('id')->firstOrFail();
    }

    private function review(PaymentProof $proof, string $status): void
    {
        $this->actingAs($this->sa)->post(route('superadmin.payment-review.review', $proof), ['status' => $status, 'rejection_reason' => 'Pemeriksaan bukti alur uji', 'expected_snapshot' => app(PortalPaymentService::class)->proofSignature($proof->fresh())])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($status, $proof->fresh()->status);
    }

    public function test_admin_and_student_payment_colors_track_real_state_changes_without_changing_permissions(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.branch-settings.index'))->assertOk();
        $this->badge($response->getContent(), 'active', 'success', 'Aktif');
        $this->branch->update(['is_active' => false]);
        $response = $this->get(route('admin.branch-settings.index'))->assertOk();
        $this->badge($response->getContent(), 'inactive', 'danger', 'Nonaktif');
        $this->branch->update(['is_active' => true]);

        $charge = MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => '2026-10-01']);
        $this->paymentPage($charge, 'empty', 'warning', 'Belum Unggah Bukti');
        $proof = $this->upload($charge);
        $this->paymentPage($charge, 'pending', 'warning', 'Menunggu Validasi');
        $this->actingAs($this->admin)->post(route('superadmin.payment-review.review', $proof), ['status' => 'approved'])->assertForbidden();
        $this->review($proof, 'rejected');
        $this->paymentPage($charge, 'rejected', 'danger', 'Ditolak');
        Carbon::setTestNow(now()->addSecond());
        $newProof = $this->upload($charge);
        $this->assertNotSame($proof->id, $newProof->id);
        $this->review($newProof, 'approved');
        $this->paymentPage($charge, 'approved', 'success', 'Tervalidasi');
        $this->review($newProof, 'rejected');
        $this->paymentPage($charge, 'rejected', 'danger', 'Ditolak');
        $this->assertDatabaseCount('payment_proofs', 2);
    }

    public function test_four_role_journey_from_assigned_pupil_to_three_month_pdf_and_revocation(): void
    {
        $this->actingAs($this->teacherUser)->get(route('teacher.student-progress.index', ['month' => '2026-10']))->assertOk()->assertSee($this->student->full_name)->assertSee('Menunggu Admin menyiapkan periode');
        $lesson = $this->lesson();
        $lesson->update(['planned_start_at' => '2026-10-15 09:00:00', 'planned_end_at' => '2026-10-15 10:00:00', 'status' => 'ongoing']);
        foreach (['teacher' => $this->teacherUser, 'admin' => $this->admin] as $role => $actor) {
            $dashboard = $this->actingAs($actor)->get(route($role.'.dashboard'))->assertOk();
            $this->badge($dashboard->getContent(), 'ongoing', 'info', 'Berlangsung');
        }
        SessionStudent::create(['session_id' => $lesson->id, 'student_id' => $this->student->id]);
        $this->actingAs($this->teacherUser)->get(route('teacher.student-attendance.show', $lesson))->assertOk()->assertSee('data-attendance-confirm-form', false);
        $this->assertNull($lesson->sessionStudents()->first()->attendance_status);
        $this->postJson(route('teacher.student-attendance.save', [$lesson, $this->student]), ['attendance_status' => 'present', 'expected_version' => 0])->assertOk();
        $this->assertSame('present', $lesson->sessionStudents()->first()->attendance_status);

        $reports = [];
        $proofs = [];
        foreach (['2026-10', '2026-11', '2026-12'] as $index => $month) {
            Carbon::setTestNow(Carbon::parse($month.'-28 12:00:00', 'Asia/Makassar'));
            $prepare = ['month' => $month, 'teacher_id' => $this->teacher->id];
            if ($index === 0) {
                $prepare['video_target'] = 1;
            }
            $this->actingAs($this->admin)->post(route('admin.student-progress.prepare', $this->student), $prepare)->assertRedirect()->assertSessionHasNoErrors();
            $report = $this->student->monthlyReports()->whereDate('report_month', $month.'-01')->firstOrFail();
            $this->assertSame($index === 0 ? 1 : 3, $report->video_target);
            $this->assertSame($index === 0 ? $month.'-31' : $month.'-20', $report->due_on->toDateString());
            if ($reports !== []) {
                $this->assertSame(1, $reports[0]->fresh()->video_target);
            }
            if ($index === 0) {
                $settings = app(DashboardSettingsService::class);
                $this->actingAs($this->sa)->put(route('superadmin.settings.update'), ['organization_name' => 'DPE Uji', 'default_report_due_day' => 20, 'default_monthly_video_target' => 3, 'in_app_reminders_enabled' => 1, 'record_version' => $settings->version($settings->setting())])->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame('2026-10-31', $report->fresh()->due_on->toDateString());
            }
            $progress = app(StudentProgressService::class);
            $payload = ['expected_snapshot' => $progress->signature($report->fresh()), 'skills' => collect(StudentProgressService::CATEGORIES)->map(fn ($label) => ['trend' => 'Improving', 'description' => 'Latihan publik '.$label])->all(), 'development_summary' => 'Perkembangan publik '.$month, 'parent_challenges_summary' => 'Latihan di rumah '.$month, 'parent_message' => 'Pesan publik '.$month, 'internal_teacher_note' => 'RAHASIA BULANAN ALUR'];
            $this->actingAs($this->teacherUser)->put(route('teacher.student-progress.save', $report), $payload)->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route('teacher.student-progress.videos.store', $report), ['expected_snapshot' => $progress->signature($report->fresh()), 'video_on' => $month.'-10', 'topic' => 'Latihan percakapan', 'video_url' => 'https://example.com/latihan', 'description' => 'Video belajar'])->assertRedirect()->assertSessionHasNoErrors();
            $monthly = app(MonthlyReportContentService::class);
            $this->post(route('teacher.monthly-reports.submit', $report), ['expected_snapshot' => $monthly->signature($report->fresh())])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('submitted', $report->fresh()->status);
            $response = $this->actingAs($this->admin)->get(route('admin.monthly-reports.show', $report))->assertOk();
            $this->badge($response->getContent(), 'submitted', 'warning', 'Diajukan');
            if ($index === 0) {
                $this->post(route('admin.monthly-reports.revision', $report), ['comment' => 'Lengkapi uraian latihan', 'expected_snapshot' => $monthly->signature($report->fresh())])->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame('revision', $report->fresh()->status);
                $response = $this->get(route('admin.monthly-reports.show', $report))->assertOk();
                $this->badge($response->getContent(), 'revision', 'danger', 'Perlu revisi');
                $payload['expected_snapshot'] = $progress->signature($report->fresh());
                $payload['development_summary'] = 'Perkembangan publik diperbaiki '.$month;
                $this->actingAs($this->teacherUser)->put(route('teacher.student-progress.save', $report), $payload)->assertRedirect()->assertSessionHasNoErrors();
                $this->post(route('teacher.monthly-reports.submit', $report), ['expected_snapshot' => $monthly->signature($report->fresh())])->assertRedirect()->assertSessionHasNoErrors();
            }
            $this->actingAs($this->admin)->post(route('admin.monthly-reports.approve', $report), ['expected_snapshot' => $monthly->signature($report->fresh())])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('approved', $report->fresh()->status);
            $response = $this->get(route('admin.monthly-reports.show', $report))->assertOk();
            $this->badge($response->getContent(), 'approved', 'success', 'Disetujui');
            $charge = MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->branch->id, 'charge_month' => $month.'-01']);
            $this->actingAs($this->parent)->get(route('student.reports.monthly', $report))->assertOk()->assertSee('Terkunci')->assertDontSee('Pesan publik '.$month);
            $proof = $this->upload($charge);
            if ($index === 0) {
                $this->review($proof, 'rejected');
                Carbon::setTestNow(now()->addSecond());
                $proof = $this->upload($charge);
            }
            $this->review($proof, 'approved');
            $this->actingAs($this->parent)->get(route('student.reports.monthly', $report))->assertOk()->assertSee('Pesan publik '.$month)->assertDontSee('RAHASIA');
            $reports[] = $report;
            $proofs[] = $proof;
        }

        $this->assertDatabaseCount('report_cycles', 1);
        $content = app(QuarterlyReportContentService::class);
        $cycle = $reports[0]->cycle;
        $this->actingAs($this->teacherUser)->post(route('teacher.quarterly-reports.store', $cycle), ['teacher_id' => $this->teacher->id, 'expected_snapshot' => $content->preparationSignature($cycle->fresh())])->assertRedirect()->assertSessionHasNoErrors();
        $final = QuarterlyReport::firstOrFail();
        $this->put(route('teacher.quarterly-reports.save', $final), ['expected_snapshot' => $content->signature($final->fresh()), 'skills' => collect(QuarterlyReportContentService::SKILLS)->map(fn ($name) => ['score' => 'B', 'description' => 'Kemampuan publik '.$name])->all(), 'parent_note' => 'Catatan rapor final publik'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('teacher.quarterly-reports.submit', $final), ['expected_snapshot' => $content->signature($final->fresh())])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.quarterly-reports.approve', $final), ['expected_snapshot' => $content->signature($final->fresh())])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->teacherUser)->post(route('teacher.quarterly-reports.generate', $final), ['expected_snapshot' => $content->signature($final->fresh())])->assertRedirect()->assertSessionHasNoErrors();
        $file = $final->files()->firstOrFail();
        $pdfUrl = route('student.reports.pdf', [$final, $file]);
        $this->actingAs($this->parent)->get(route('student.reports.quarterly', $final))->assertOk()->assertSee('Catatan rapor final publik')->assertDontSee('RAHASIA');
        $pdf = $this->get($pdfUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $otherParent = $this->user('student');
        $this->pupil('Peserta Lain Alur Uji', $otherParent);
        $this->actingAs($otherParent)->get($pdfUrl)->assertNotFound();
        $this->actingAs($this->user('admin'))->get(route('admin.quarterly-reports.show', $final))->assertForbidden();
        $this->review($proofs[0], 'rejected');
        $this->actingAs($this->parent)->get($pdfUrl)->assertForbidden();
        $this->get(route('student.reports.quarterly', $final))->assertOk()->assertSee('Terkunci')->assertDontSee('Catatan rapor final publik');
    }
}
