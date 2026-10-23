<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\QuarterlyReport;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAvailability;
use App\Models\Program;
use App\Models\User;
use App\Services\MonthlyReportWorkflowService;
use App\Services\OperationalExportDataService;
use App\Services\OperationalExportDefinition;
use App\Services\OperationalExportPdfWriter;
use App\Services\OperationalExportXlsxWriter;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Support\ZipFormatAssertions;

class OperationalExportWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use ZipFormatAssertions;

    private User $sa;
    private User $admin;
    private Teacher $teacher;
    private Teacher $otherTeacher;
    private Branch $branch;
    private Branch $otherBranch;
    private Student $student;
    private Student $otherStudent;
    private Program $program;
    private MonthlyReport $report;
    private LessonSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Makassar'));
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->sa = $this->user('superadmin', 'Super Admin Uji');
        $this->admin = $this->user('admin', 'Admin Uji');
        $this->teacher = $this->teacher('Teacher A Uji');
        $this->otherTeacher = $this->teacher('Teacher B Rahasia');
        $this->branch = $this->branch('Cabang A Uji');
        $this->otherBranch = $this->branch('Cabang B Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->program = Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(12), 'name' => 'English Private', 'class_type' => 'Private', 'is_active' => true]);
        $this->student = $this->student('Siswa A Uji', $this->branch, $this->teacher);
        $this->otherStudent = $this->student('Siswa B Rahasia', $this->otherBranch, $this->otherTeacher);
        $this->report = $this->report($this->student, $this->branch, $this->teacher);
        $this->report($this->otherStudent, $this->otherBranch, $this->otherTeacher);
        $this->session = $this->lesson($this->student, $this->branch, $this->teacher);
        $this->lesson($this->otherStudent, $this->otherBranch, $this->otherTeacher);
        foreach ([[$this->teacher, $this->branch], [$this->otherTeacher, $this->otherBranch]] as [$teacher, $branch]) {
            TeacherAttendance::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id, 'branch_id' => $branch->id, 'attendance_date' => '2026-10-09',
                'attendance_mode' => 'branch_visit', 'attendance_type' => 'present', 'status' => 'pending_review', 'check_in_at' => '2026-10-09 09:00:00',
                'check_in_latitude' => -8.1234567, 'manual_note' => 'PRIVATE ATTENDANCE NOTE']);
            TeacherAvailability::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id, 'branch_id' => $branch->id, 'available_on' => '2026-10-20',
                'starts_at' => '09:00:00', 'ends_at' => '10:00:00', 'class_type' => 'Private', 'availability_status' => 'Available', 'notes' => 'PRIVATE SLOT NOTE']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(15), 'password_hash' => 'PRIVATE PASSWORD', 'full_name' => $name, 'role_code' => $role, 'is_active' => true]);
    }

    private function teacher(string $name): Teacher
    {
        return Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id, 'whatsapp_number' => '081234567890', 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name, 'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function student(string $name, Branch $branch, Teacher $teacher): Student
    {
        $s = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student', $name.' Portal')->id, 'full_name' => $name,
            'began_on' => '2026-10-01', 'school_name' => 'Sekolah Harapan', 'grade_name' => 'Kelas 6', 'status' => 'active', 'special_notes_internal' => 'PRIVATE PROFILE NOTE']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $s->id, 'branch_id' => $branch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-01']);
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $s->id, 'teacher_id' => $teacher->id, 'starts_on' => '2026-10-01', 'is_report_owner' => true]);
        return $s;
    }

    private function report(Student $student, Branch $branch, Teacher $teacher): MonthlyReport
    {
        $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'cycle_number' => 1, 'start_month' => '2026-10-01', 'end_month' => '2026-12-01', 'share_due_on' => '2027-01-07']);
        $report = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $student->id, 'branch_id' => $branch->id, 'teacher_id' => $teacher->id,
            'report_month' => '2026-10-01', 'due_on' => '2026-10-25', 'video_target' => 1, 'status' => 'draft', 'development_summary' => 'Ringkasan perkembangan publik',
            'parent_challenges_summary' => 'Latihan di rumah', 'parent_message' => 'Pesan untuk orang tua', 'internal_teacher_note' => 'PRIVATE TEACHER NOTE']);
        foreach (app(StudentProgressService::class)->categories() as $skill) {
            DB::table('monthly_report_skills')->insert(['report_id' => $report->id, 'skill_id' => $skill->id, 'trend' => 'Improving', 'description' => 'Siswa mampu menyusun kalimat sederhana dan mengikuti latihan kelas.']);
        }
        LearningVideo::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'teacher_id' => $teacher->id, 'video_on' => '2026-10-08', 'topic' => 'Speaking practice',
            'description' => 'Latihan pengucapan bersama Teacher', 'video_url' => 'https://example.org/video/latihan']);
        QuarterlyReport::forceCreate(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $student->id, 'branch_id' => $branch->id,
            'teacher_id' => $teacher->id, 'status' => 'draft', 'revision_number' => 1]);
        return $report;
    }

    private function lesson(Student $student, Branch $branch, Teacher $teacher, string $date = '2026-10-09 09:00:00', ?string $status = null): LessonSession
    {
        $session = LessonSession::create(['id' => (string) Str::uuid(), 'branch_id' => $branch->id, 'program_id' => $this->program->id, 'teacher_id' => $teacher->id,
            'teaching_location' => 'student_home', 'planned_start_at' => $date, 'planned_end_at' => Carbon::parse($date)->addHour()->format('Y-m-d H:i:s'), 'status' => 'scheduled']);
        DB::table('session_students')->insert(['session_id' => $session->id, 'student_id' => $student->id, 'attendance_status' => $status]);
        return $session;
    }

    private function data(string $dataset = 'students', ?User $actor = null, array $filters = []): array
    {
        return app(OperationalExportDataService::class)->data($actor ?? $this->teacher->user, $filters + ['dataset' => $dataset, 'from' => '2026-10-01', 'to' => '2026-10-31']);
    }

    private function url(array $data, string $format): string
    {
        return route('exports.download', ['format' => $format] + $data['filters'] + ['expected_snapshot' => $data['snapshot']]);
    }

    public static function datasetsAndRoles(): array
    {
        $out = [];
        foreach (array_keys(OperationalExportDefinition::DATASETS) as $dataset) {
            foreach (['superadmin', 'admin', 'teacher'] as $role) {
                $out[$dataset.' '.$role] = [$dataset, $role];
            }
        }
        return $out;
    }

    #[DataProvider('datasetsAndRoles')]
    public function test_all_datasets_have_the_same_scoped_preview_and_read_only_source(string $dataset, string $role): void
    {
        $actor = match ($role) { 'superadmin' => $this->sa, 'admin' => $this->admin, default => $this->teacher->user };
        $before = DB::table('monthly_reports')->get()->toJson().DB::table('session_students')->get()->toJson().DB::table('in_app_notification_reads')->get()->toJson();
        $data = $this->data($dataset, $actor);
        $this->assertGreaterThan(0, $data['total']);
        $encoded = json_encode($data['rows']);
        $this->assertStringNotContainsString('PRIVATE', $encoded);
        $this->assertStringNotContainsString('latitude', $encoded);
        if ($role !== 'superadmin') {
            $this->assertStringNotContainsString('Rahasia', $encoded);
            $this->assertFalse($data['branches']->contains('id', $this->otherBranch->id));
            $this->assertFalse($data['teachers']->contains('id', $this->otherTeacher->id));
        }
        $page = $this->actingAs($actor)->get(route('exports.index', $data['filters']));
        $page->assertOk()->assertSee('Data &amp; Ekspor', false)->assertSee($data['snapshot']);
        $page->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $after = DB::table('monthly_reports')->get()->toJson().DB::table('session_students')->get()->toJson().DB::table('in_app_notification_reads')->get()->toJson();
        $this->assertSame($before, $after);
        Http::assertNothingSent();
    }

    public static function datasetsAndFormats(): array
    {
        $out = [];
        foreach (array_keys(OperationalExportDefinition::DATASETS) as $dataset) {
            foreach (['xlsx', 'pdf'] as $format) {
                $out[$dataset.' '.$format] = [$dataset, $format];
            }
        }
        return $out;
    }

    #[DataProvider('datasetsAndFormats')]
    public function test_download_is_a_real_complete_document_with_private_headers(string $dataset, string $format): void
    {
        $data = $this->data($dataset);
        $response = $this->actingAs($this->teacher->user)->get($this->url($data, $format));
        $response->assertOk()->assertHeader('X-DPE-Export-Rows', (string) $data['total']);
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $bytes = $response->getContent();
        if ($format === 'pdf') {
            $response->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $bytes);
        } else {
            $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith("PK\x03\x04", $bytes);
            $parts = $this->parts($bytes);
            $this->assertStringContainsString('Rincian', $parts['xl/workbook.xml']);
            $this->assertStringContainsString('autoFilter', $parts['xl/worksheets/sheet1.xml']);
            $this->assertStringContainsString('state="frozen"', $parts['xl/worksheets/sheet1.xml']);
            $xml = new \DOMDocument;
            $this->assertTrue($xml->loadXML($parts['xl/worksheets/sheet1.xml']));
            $xpath = new \DOMXPath($xml);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame($data['total'] + 1, $xpath->query('//s:row')->length);
            $this->assertSame(0, $xpath->query('//s:f')->length);
            $this->assertStringNotContainsString('PRIVATE', implode('', $parts));
        }
        if ($dir = getenv('DPE_V43_QA_DIR')) {
            File::ensureDirectoryExists($dir);
            File::put($dir.'/'.$dataset.'.'.$format, $bytes);
            if ($format === 'xlsx') {
                File::put($dir.'/'.$dataset.'.html', $this->get(route('exports.index', $data['filters']))->getContent());
            }
        }
    }

    private function parts(string $bytes): array
    {
        return $this->assertStandardZip($bytes);
    }

    public function test_guests_and_students_cannot_access_staff_exports(): void
    {
        $this->get(route('exports.index'))->assertRedirect(route('login'));
        $data = $this->data();
        $this->get($this->url($data, 'xlsx'))->assertRedirect(route('login'));
        $this->actingAs($this->student->portalUser)->get(route('exports.index'))->assertForbidden();
        $this->get($this->url($data, 'pdf'))->assertForbidden();
    }

    public function test_inactive_teacher_profile_and_stale_user_are_rechecked(): void
    {
        $actor = $this->teacher->user;
        $data = $this->data();
        $this->teacher->update(['is_active' => false]);
        $this->actingAs($actor)->get($this->url($data, 'xlsx'))->assertForbidden();
        $this->teacher->update(['is_active' => true]);
        $actor->update(['is_active' => false]);
        $this->get(route('exports.index'))->assertForbidden();
    }

    public function test_tampered_branch_or_teacher_is_denied_before_download(): void
    {
        $data = $this->data();
        $this->actingAs($this->teacher->user)->getJson(route('exports.index', ['branch_id' => $this->otherBranch->id]))->assertForbidden();
        $this->getJson(route('exports.download', ['format' => 'xlsx', 'teacher_id' => $this->otherTeacher->id, 'expected_snapshot' => $data['snapshot']]))->assertForbidden();
        $this->actingAs($this->admin)->getJson(route('exports.index', ['branch_id' => $this->otherBranch->id]))->assertForbidden();
    }

    public function test_changed_access_assignment_is_rechecked_after_preview(): void
    {
        $data = $this->data('students', $this->admin);
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->delete();
        $this->actingAs($this->admin)->getJson($this->url($data, 'xlsx'))->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_changed_data_or_filters_requires_a_new_preview(): void
    {
        $data = $this->data();
        $this->student->update(['full_name' => 'Nama berubah']);
        $this->actingAs($this->teacher->user)->getJson($this->url($data, 'xlsx'))->assertUnprocessable()->assertJsonValidationErrors('export');
        $fresh = $this->data();
        $this->getJson(route('exports.download', ['format' => 'xlsx'] + array_replace($fresh['filters'], ['q' => 'berubah']) + ['expected_snapshot' => $fresh['snapshot']]))
            ->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_snapshot_from_another_actor_is_not_accepted(): void
    {
        $data = $this->data('students', $this->admin);
        $this->actingAs($this->teacher->user)->getJson($this->url($data, 'xlsx'))->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_html_download_error_returns_to_latest_preview(): void
    {
        $data = $this->data();
        $this->student->update(['full_name' => 'Nama baru']);
        $this->actingAs($this->teacher->user)->get($this->url($data, 'xlsx'))->assertRedirect(route('exports.index', $data['filters']))->assertSessionHasErrors('export');
    }

    public function test_date_bounds_and_inapplicable_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)->getJson(route('exports.index', ['from' => '2026-10-31', 'to' => '2026-10-01']))->assertUnprocessable();
        $this->getJson(route('exports.index', ['from' => '2025-01-01', 'to' => '2026-10-01']))->assertUnprocessable();
        $this->getJson(route('exports.index', ['from' => '2026-02-30']))->assertUnprocessable();
        $this->getJson(route('exports.index', ['dataset' => 'teachers', 'grade' => 'Kelas 6']))->assertUnprocessable();
        $this->getJson(route('exports.index', ['dataset' => 'videos', 'status' => 'absent']))->assertUnprocessable();
    }

    public function test_filtered_results_have_exact_counts_and_do_not_turn_unrecorded_into_absent(): void
    {
        $this->assertSame(1, $this->data('students', null, ['grade' => 'Kelas 6', 'program_id' => $this->program->id, 'class_type' => 'Private', 'q' => 'siswa a'])['total']);
        $this->assertSame(0, $this->data('students', null, ['grade' => 'Kelas 5'])['total']);
        $this->assertSame(0, $this->data('student-attendance', null, ['status' => 'absent'])['total']);
        $this->assertSame('Belum dicatat', $this->data('student-attendance', null, ['status' => 'unrecorded'])['rows'][0]['status']);
        $future = $this->lesson($this->student, $this->branch, $this->teacher, '2026-10-20 09:00:00', 'present');
        $excluded = $this->data('student-attendance', null, ['status' => 'excluded']);
        $this->assertSame(1, $excluded['total']);
        $this->assertSame('Belum berlangsung', $excluded['rows'][0]['status']);
        $this->assertSame('Tidak', $excluded['rows'][0]['counted']);
        $this->assertSame(0, $this->data('student-attendance', null, ['status' => 'present'])['total']);
        $future->update(['status' => 'cancelled']);
        $this->assertSame('Sesi dibatalkan', $this->data('student-attendance', null, ['status' => 'excluded'])['rows'][0]['status']);
    }

    public function test_rescheduled_sessions_and_grade_location_filters_match_the_source(): void
    {
        $replacement = $this->lesson($this->student, $this->branch, $this->teacher, '2026-10-10 09:00:00', 'late');
        $replacement->update(['rescheduled_from_session_id' => $this->session->id]);
        $this->assertSame('Dijadwalkan Ulang', $this->data('student-attendance', null, ['status' => 'rescheduled'])['rows'][0]['status']);
        $this->assertSame(1, $this->data('student-attendance', null, ['status' => 'late', 'grade' => 'Kelas 6', 'location' => 'student_home'])['total']);
        $this->assertSame(0, $this->data('sessions', null, ['location' => 'branch'])['total']);
    }

    public function test_teacher_without_branch_assignment_is_visible_to_admin_through_actual_work(): void
    {
        $this->assertSame(0, $this->teacher->branchAssignments()->count());
        $data = $this->data('teachers', $this->admin, ['teacher_id' => $this->teacher->id]);
        $this->assertSame(1, $data['total']);
        $this->assertSame('Cabang A Uji', $data['rows'][0]['branches']);
    }

    public function test_admin_does_not_export_other_enrollments_of_a_shared_student(): void
    {
        StudentEnrollment::where('student_id', $this->student->id)->update(['ends_on' => '2026-10-05']);
        StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $this->student->id, 'branch_id' => $this->otherBranch->id, 'program_id' => $this->program->id, 'starts_on' => '2026-10-06']);
        $data = $this->data('students', $this->admin);
        $this->assertSame(1, $data['total']);
        $this->assertStringNotContainsString('Rahasia', json_encode($data['rows']));
    }

    public function test_exact_assignment_date_and_class_roster_allow_teacher_filters(): void
    {
        $student = $this->student('Siswa Penugasan Lama', $this->branch, $this->teacher);
        StudentTeacherAssignment::where('student_id', $student->id)->update(['ends_on' => '2026-10-10']);
        $this->assertSame(0, $this->data('students', null, ['from' => '2026-10-20', 'q' => 'Penugasan Lama'])['total']);
        $classStudent = $this->student('Siswa Kelas', $this->branch, $this->otherTeacher);
        StudentTeacherAssignment::where('student_id', $classStudent->id)->delete();
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id, 'default_teacher_id' => $this->teacher->id,
            'code' => 'UJI', 'name' => 'Kelas Uji', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'student_id' => $classStudent->id, 'class_group_id' => $group->id, 'starts_on' => '2026-10-01']);
        $this->assertSame(1, $this->data('students', $this->admin, ['teacher_id' => $this->teacher->id, 'q' => 'Siswa Kelas'])['total']);
        $this->assertSame(1, $this->data('students', null, ['q' => 'Siswa Kelas'])['total']);
    }

    public function test_approved_progress_summary_and_video_use_frozen_content(): void
    {
        $workflow = app(MonthlyReportWorkflowService::class);
        $report = $workflow->approve($workflow->submit($this->report, $this->teacher->user), $this->sa);
        DB::table('monthly_reports')->where('id', $report->id)->update(['development_summary' => 'Changed after approval']);
        DB::table('monthly_report_skills')->where('report_id', $report->id)->update(['description' => 'Changed after approval']);
        LearningVideo::where('student_id', $this->student->id)->update(['video_url' => 'https://example.org/changed']);
        $this->assertSame('Ringkasan perkembangan publik', $this->data('monthly-reports')['rows'][0]['summary']);
        $this->assertStringNotContainsString('Changed after approval', json_encode($this->data('progress')['rows']));
        $this->assertSame('https://example.org/video/latihan', $this->data('videos')['rows'][0]['url']);
        $this->assertSame('Salinan persetujuan', $this->data('videos')['rows'][0]['source']);
    }

    public function test_video_target_automatic_month_and_quarterly_calendar_intersection(): void
    {
        $this->assertSame(1, $this->data('monthly-reports')['rows'][0]['target']);
        $this->student->update(['began_on' => '2026-10-08']);
        $this->assertNull($this->data('monthly-reports')['rows'][0]['target']);
        $this->assertSame(1, $this->data('quarterly-reports', null, ['from' => '2026-12-20', 'to' => '2026-12-31'])['total']);
        $this->assertSame(0, $this->data('quarterly-reports', null, ['from' => '2027-01-01', 'to' => '2027-01-31'])['total']);
        $this->assertSame('Belum siap', $this->data('quarterly-reports')['rows'][0]['ready']);
    }

    public function test_teacher_slots_in_another_branch_remain_hidden_from_admin(): void
    {
        TeacherAvailability::create(['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->otherBranch->id, 'available_on' => '2026-10-20',
            'starts_at' => '14:00:00', 'ends_at' => '15:00:00', 'class_type' => 'Online', 'availability_status' => 'Available']);
        $this->assertSame(1, $this->data('availability', $this->admin)['total']);
        $this->assertSame(2, $this->data('availability')['total']);
        $this->assertSame(3, $this->data('availability', $this->sa)['total']);
    }

    public function test_xlsx_dates_counts_and_phone_are_typed_and_user_text_is_literal(): void
    {
        $this->student->update(['school_name' => '=HYPERLINK("https://example.org","test")']);
        $parts = $this->parts(app(OperationalExportXlsxWriter::class)->render($this->data()));
        $xml = new \DOMDocument;
        $xml->loadXML($parts['xl/worksheets/sheet1.xml']);
        $x = new \DOMXPath($xml);
        $x->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertSame('inlineStr', $x->query('//s:c[@r="C2"]')->item(0)->getAttribute('t'));
        $this->assertSame('=HYPERLINK("https://example.org","test")', $x->evaluate('string(//s:c[@r="C2"]/s:is/s:t)'));
        $this->assertSame('n', $x->query('//s:c[@r="E2"]')->item(0)->getAttribute('t'));
        $this->assertSame('46296', $x->evaluate('string(//s:c[@r="E2"]/s:v)'));
        $this->assertSame(0, $x->query('//s:f')->length);
        $teacherParts = $this->parts(app(OperationalExportXlsxWriter::class)->render($this->data('teachers')));
        $this->assertStringContainsString('081234567890', $teacherParts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('r="C2" s="2" t="inlineStr"', $teacherParts['xl/worksheets/sheet1.xml']);
    }

    public function test_long_text_is_preserved_in_xlsx_and_pdf_continuation_rows(): void
    {
        $text = str_repeat('Kemajuan belajar siswa dengan latihan setiap hari. ', 120).'PENANDA AKHIR';
        $this->report->update(['development_summary' => $text]);
        $data = $this->data('monthly-reports');
        $parts = $this->parts(app(OperationalExportXlsxWriter::class)->render($data));
        $this->assertStringContainsString('Uraian', $parts['xl/workbook.xml']);
        $xml = new \DOMDocument;
        $xml->loadXML($parts['xl/worksheets/sheet3.xml']);
        $x = new \DOMXPath($xml);
        $x->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $chunks = '';
        foreach ($x->query('//s:row[@r!="1"]/s:c[4]/s:is/s:t') as $chunk) {
            $chunks .= $chunk->textContent;
        }
        $this->assertSame($text, $chunks);
        $pdf = app(OperationalExportPdfWriter::class)->render($data);
        $this->assertStringStartsWith('%PDF-', $pdf);
        if ($dir = getenv('DPE_V43_QA_DIR')) {
            File::put($dir.'/long-text.pdf', $pdf);
            File::put($dir.'/long-text.xlsx', app(OperationalExportXlsxWriter::class)->render($data));
        }
    }

    public function test_pdf_row_limit_and_empty_download_are_explicit(): void
    {
        $data = $this->data();
        $data['total'] = 301;
        try {
            app(OperationalExportPdfWriter::class)->render($data);
            $this->fail('Expected a PDF limit error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('300', $e->errors()['export'][0]);
        }
        $empty = $this->data('students', null, ['q' => 'Tidak ditemukan']);
        $this->actingAs($this->teacher->user)->getJson($this->url($empty, 'xlsx'))->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_pagination_downloads_all_matching_records(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->student('Siswa Tambahan '.sprintf('%02d', $i), $this->branch, $this->teacher);
        }
        $data = $this->data();
        $this->assertSame(17, $data['total']);
        $page = $this->actingAs($this->teacher->user)->get(route('exports.index', ['page' => 2] + $data['filters']));
        $page->assertOk()->assertSee('Siswa Tambahan 15')->assertDontSee('Siswa Tambahan 00');
        $this->get($this->url($data, 'xlsx'))->assertOk()->assertHeader('X-DPE-Export-Rows', '17');
    }

    public function test_excel_limit_never_silently_truncates_and_search_can_narrow_the_source(): void
    {
        $batch = [];
        for ($i = 0; $i < 3000; $i++) {
            $batch[] = ['id' => (string) Str::uuid(), 'teacher_id' => $this->teacher->id, 'branch_id' => $this->branch->id,
                'available_on' => '2026-10-21', 'starts_at' => '09:00:00', 'ends_at' => '10:00:00', 'class_type' => 'Regular', 'availability_status' => 'Available'];
        }
        foreach (array_chunk($batch, 200) as $chunk) {
            DB::table('teacher_availability')->insert($chunk);
        }
        $this->actingAs($this->teacher->user)->getJson(route('exports.index', ['dataset' => 'availability']))->assertUnprocessable()->assertJsonValidationErrors('export');
        $this->assertSame(1, $this->data('availability', null, ['q' => 'Private'])['total']);
        $this->assertSame(3000, $this->data('availability', null, ['class_type' => 'Regular'])['total']);
    }

    public function test_unassigned_students_and_future_join_dates_are_explicit(): void
    {
        $this->student->enrollments()->delete();
        $all = $this->data('students', $this->sa, ['q' => 'Siswa A']);
        $this->assertSame(1, $all['total']);
        $this->assertSame('Tidak ada pendaftaran pada rentang ini', $all['rows'][0]['branch']);
        $this->assertSame(0, $this->data('students', $this->admin, ['q' => 'Siswa A'])['total']);
        $this->student->update(['began_on' => '2026-11-01']);
        $this->assertSame(0, $this->data('students', $this->sa, ['q' => 'Siswa A'])['total']);
    }

    public function test_teacher_recap_does_not_count_only_expired_operational_responsibilities(): void
    {
        StudentTeacherAssignment::where('teacher_id', $this->teacher->id)->delete();
        QuarterlyReport::where('teacher_id', $this->teacher->id)->delete();
        $this->report->update(['report_month' => '2026-09-01']);
        $this->session->update(['planned_start_at' => '2026-09-09 09:00:00']);
        TeacherAttendance::where('teacher_id', $this->teacher->id)->update(['attendance_date' => '2026-09-09']);
        TeacherAvailability::where('teacher_id', $this->teacher->id)->update(['available_on' => '2026-09-20']);
        $this->assertSame(0, $this->data('teachers', $this->admin)['total']);
        $this->assertSame('', $this->data('teachers')['rows'][0]['branches']);
        $this->assertSame(1, $this->data('teachers', $this->admin, ['from' => '2026-09-01', 'to' => '2026-09-30'])['total']);
    }
}
