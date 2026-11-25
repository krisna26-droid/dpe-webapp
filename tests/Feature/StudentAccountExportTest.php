<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\StudentAccountExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\ZipFormatAssertions;
use Tests\TestCase;

class StudentAccountExportTest extends TestCase
{
    use RefreshDatabase, ZipFormatAssertions;

    private User $sa;
    private User $admin;
    private Branch $branch;
    private Branch $otherBranch;
    private Program $program;
    private Program $otherProgram;
    private Teacher $teacher;
    private Teacher $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-24 10:00:00', 'Asia/Makassar'));
        $this->sa = $this->user('superadmin', 'Super Admin Uji');
        $this->admin = $this->user('admin', 'Admin Uji');
        $this->branch = $this->branch('Cabang A Uji');
        $this->otherBranch = $this->branch('Cabang B Uji');
        $this->program = $this->program('Regular Uji', 'Regular');
        $this->otherProgram = $this->program('Private Uji', 'Private');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->teacher = $this->teacher('Guru A Uji', $this->branch);
        $this->otherTeacher = $this->teacher('Guru B Uji', $this->otherBranch);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $name, ?string $username = null, bool $active = true): User
    {
        return User::create(['id' => (string) Str::uuid(), 'role_code' => $role, 'full_name' => $name,
            'username' => $username ?? 'fixture_'.Str::random(12), 'email' => Str::random(12).'@example.test',
            'password_hash' => 'fixture-password-not-for-export', 'is_active' => $active]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function program(string $name, string $type): Program
    {
        return Program::create(['id' => (string) Str::uuid(), 'code' => Str::random(10), 'name' => $name,
            'class_type' => $type, 'is_active' => true]);
    }

    private function teacher(string $name, Branch $branch): Teacher
    {
        $teacher = Teacher::create(['id' => (string) Str::uuid(), 'user_id' => $this->user('teacher', $name)->id,
            'whatsapp_number' => '080000000000', 'is_active' => true]);
        TeacherBranchAssignment::create(['id' => (string) Str::uuid(), 'teacher_id' => $teacher->id,
            'branch_id' => $branch->id, 'starts_on' => '2026-01-01']);

        return $teacher;
    }

    private function placement(Student $student, Branch $branch, Program $program, string $starts, ?string $ends = null): StudentEnrollment
    {
        return StudentEnrollment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'branch_id' => $branch->id, 'program_id' => $program->id, 'starts_on' => $starts, 'ends_on' => $ends]);
    }

    private function assign(Student $student, Teacher $teacher, bool $owner = true, string $starts = '2026-01-01', ?string $ends = null): void
    {
        StudentTeacherAssignment::create(['id' => (string) Str::uuid(), 'student_id' => $student->id,
            'teacher_id' => $teacher->id, 'starts_on' => $starts, 'ends_on' => $ends, 'is_report_owner' => $owner]);
    }

    private function student(string $name, Branch $branch, Program $program, ?Teacher $teacher = null, ?string $username = null,
        bool $active = true, string $starts = '2026-01-01', ?string $ends = null): Student
    {
        $account = $this->user('student', $name, $username, $active);
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $account->id,
            'full_name' => $name, 'began_on' => $starts, 'status' => 'active', 'special_notes_internal' => 'fixture-private-note']);
        $this->placement($student, $branch, $program, $starts, $ends);
        if ($teacher) $this->assign($student, $teacher, true, $starts, $ends);

        return $student;
    }

    private function sheet(string $bytes): \DOMXPath
    {
        $parts = $this->assertStandardZip($bytes);
        $xml = new \DOMDocument;
        $this->assertTrue($xml->loadXML($parts['xl/worksheets/sheet1.xml']));
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        return $xpath;
    }

    private function values(\DOMXPath $sheet, string $column): array
    {
        $values = [];
        foreach ($sheet->query('//s:row[@r!="1"]/s:c[starts-with(@r,"'.$column.'")]/s:is/s:t') as $cell) {
            $values[] = $cell->textContent;
        }
        return $values;
    }

    public function test_superadmin_downloads_only_student_accounts_with_the_eight_agreed_columns(): void
    {
        $first = $this->student('A Siswa Uji', $this->branch, $this->program, $this->teacher, '000123');
        $second = $this->student('B Siswa Uji', $this->otherBranch, $this->otherProgram, $this->otherTeacher, active: false);
        $unlinked = $this->user('student', 'C Belum terhubung');
        $closed = $this->student('D Pendaftaran berakhir', $this->branch, $this->program, $this->teacher, ends: '2026-10-31');
        $deleted = $this->user('student', 'E Terhapus');
        $deleted->delete();

        $response = $this->actingAs($this->sa)->get(route('superadmin.users.export', ['format' => 'xlsx']))->assertOk()
            ->assertHeader('X-DPE-Export-Rows', '4')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $sheet = $this->sheet($response->getContent());
        $headers = [];
        foreach ($sheet->query('//s:row[@r="1"]/s:c/s:is/s:t') as $cell) $headers[] = $cell->textContent;
        $this->assertSame(['No.', 'Nama siswa', 'Username', 'Cabang', 'Program', 'Guru utama', 'Status akun', 'Tanggal pembuatan akun'], $headers);
        $this->assertSame([$first->portalUser->username, $second->portalUser->username, $unlinked->username, $closed->portalUser->username], $this->values($sheet, 'C'));
        $this->assertSame('inlineStr', $sheet->query('//s:c[@r="C2"]')->item(0)->getAttribute('t'));
        $this->assertSame('000123', $sheet->evaluate('string(//s:c[@r="C2"]/s:is/s:t)'));
        $this->assertSame('n', $sheet->query('//s:c[@r="H2"]')->item(0)->getAttribute('t'));
        $this->assertSame(['Aktif', 'Nonaktif', 'Aktif', 'Aktif'], $this->values($sheet, 'G'));
        $this->assertSame(0, $sheet->query('//s:f')->length);
        $this->assertStringNotContainsString('fixture-password-not-for-export', $sheet->evaluate('string(/)'));
        $this->assertStringNotContainsString('@example.test', $sheet->evaluate('string(/)'));
        $this->assertStringNotContainsString('fixture-private-note', $sheet->evaluate('string(/)'));
    }

    public function test_admin_filters_and_downloads_all_pages_without_leaking_other_branches(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->student('Cocok '.sprintf('%02d', $i), $this->branch, $this->program, $this->teacher);
        }
        $outside = $this->student('Cocok luar', $this->otherBranch, $this->otherProgram, $this->otherTeacher);
        $this->student('Cocok nonaktif', $this->branch, $this->program, $this->teacher, active: false);
        $this->student('Cocok selesai', $this->branch, $this->program, $this->teacher, ends: '2026-10-31');
        $filters = ['q' => 'Cocok', 'teacher_id' => $this->teacher->id, 'program_id' => $this->program->id, 'status' => 'active'];
        $index = $this->actingAs($this->admin)->get(route('admin.users.index', $filters + ['page' => 2]))->assertOk()
            ->assertSee('Ekspor PDF')->assertSee('Ekspor Excel')->assertSee('name="teacher_id"', false)
            ->assertDontSee('name="branch_id"', false)->assertDontSee($outside->portalUser->username)->assertDontSee($this->otherTeacher->user->full_name);
        $this->assertSame(17, $index->viewData('users')->total());
        $response = $this->get(route('admin.users.export', $filters + ['format' => 'xlsx', 'page' => 2]))->assertOk()->assertHeader('X-DPE-Export-Rows', '17');
        $sheet = $this->sheet($response->getContent());
        $this->assertCount(17, $this->values($sheet, 'C'));
        $this->assertSame([$this->branch->name], array_values(array_unique($this->values($sheet, 'D'))));
        $this->assertNotContains($outside->portalUser->username, $this->values($sheet, 'C'));
        $this->getJson(route('admin.users.export', ['format' => 'xlsx', 'branch_id' => $this->otherBranch->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->getJson(route('admin.users.export', ['format' => 'xlsx', 'teacher_id' => $this->otherTeacher->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_owner_is_preferred_over_makeup_and_regular_group_is_the_fallback(): void
    {
        $additional = $this->teacher('Guru Tambahan', $this->branch);
        $assigned = $this->student('Pemilik laporan', $this->branch, $this->program, $this->teacher);
        $this->assign($assigned, $additional, false);
        $groupStudent = $this->student('Anggota kelompok', $this->branch, $this->program);
        $this->assign($groupStudent, $additional, false);
        $group = ClassGroup::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch->id, 'program_id' => $this->program->id,
            'default_teacher_id' => $this->teacher->id, 'name' => 'Kelompok Uji', 'code' => 'GROUP-UJI', 'is_active' => true]);
        ClassGroupMembership::create(['id' => (string) Str::uuid(), 'class_group_id' => $group->id, 'student_id' => $groupStudent->id, 'starts_on' => '2026-01-01']);
        $service = app(StudentAccountExportService::class);
        $data = $service->data($this->admin, ['teacher_id' => $this->teacher->id], 'xlsx');
        $this->assertSame(2, $data['total']);
        $this->assertSame([$this->teacher->user->full_name], array_values(array_unique(array_column($data['rows'], 'teacher'))));
        $this->actingAs($this->admin)->getJson(route('admin.users.export', ['format' => 'xlsx', 'teacher_id' => $additional->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_superadmin_filters_current_and_future_placements_and_admin_stays_in_own_branch(): void
    {
        $move = $this->student('Siswa pindah', $this->branch, $this->program, $this->teacher, ends: '2026-11-30');
        $this->placement($move, $this->otherBranch, $this->otherProgram, '2026-12-01');
        $this->assign($move, $this->otherTeacher, starts: '2026-12-01');
        $service = app(StudentAccountExportService::class);
        $local = $service->data($this->admin, [], 'xlsx')['rows'][0];
        $this->assertSame($this->branch->name, $local['branch']);
        $this->assertSame($this->teacher->user->full_name, $local['teacher']);
        $filters = ['branch_id' => $this->otherBranch->id, 'program_id' => $this->otherProgram->id, 'teacher_id' => $this->otherTeacher->id];
        $listing = $this->actingAs($this->sa)->get(route('superadmin.users.index', $filters))->assertOk()->assertSee('name="branch_id"', false);
        $this->assertSame([$move->portal_user_id], $listing->viewData('users')->pluck('id')->all());
        $data = $service->data($this->sa, $filters, 'xlsx');
        $this->assertSame(1, $data['total']);
        $this->assertSame($this->otherBranch->name, $data['rows'][0]['branch']);
        $this->assertSame($this->otherProgram->name, $data['rows'][0]['program']);
        $this->assertSame($this->otherTeacher->user->full_name, $data['rows'][0]['teacher']);
        $this->assertSame(1, $service->data($this->sa, [], 'xlsx')['total'], 'one account even if placements change');
    }

    public function test_pdf_attachment_and_export_authorization(): void
    {
        $student = $this->student('Siswa PDF Uji', $this->branch, $this->program, $this->teacher);
        $url = route('admin.users.export', ['format' => 'pdf']);
        $this->get($url)->assertRedirect(route('login'));
        foreach ([$this->teacher->user, $student->portalUser] as $actor) {
            $this->actingAs($actor)->get($url)->assertForbidden();
            $this->get(route('superadmin.users.export', ['format' => 'xlsx']))->assertForbidden();
        }
        $response = $this->actingAs($this->admin)->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-DPE-Export-Rows', '1');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->get(route('superadmin.users.export', ['format' => 'xlsx']))->assertForbidden();
        $this->getJson(route('admin.users.export', ['format' => 'xlsx', 'role_code' => 'teacher']))
            ->assertUnprocessable()->assertJsonValidationErrors('role_code');
        $this->getJson(route('admin.users.export', ['format' => 'xlsx', 'program_id' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('program_id');
        $this->get(route('admin.users.export', ['format' => 'xlsx', 'q' => 'Tidak ditemukan']))
            ->assertRedirect(route('admin.users.index', ['q' => 'Tidak ditemukan']))->assertSessionHasErrors('export');
        $this->actingAs($this->sa)->getJson(route('superadmin.users.export', ['format' => 'xlsx', 'role_code' => 'teacher']))
            ->assertUnprocessable()->assertJsonValidationErrors('role_code');
    }

    public function test_an_admin_whose_branch_assignment_ended_cannot_download_students(): void
    {
        $this->student('Siswa scope uji', $this->branch, $this->program, $this->teacher);
        $this->actingAs($this->admin)->get(route('admin.users.export', ['format' => 'xlsx']))->assertOk();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-11-23']);
        $this->getJson(route('admin.users.export', ['format' => 'xlsx']))->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_pdf_limit_reports_an_error_instead_of_truncating_accounts(): void
    {
        for ($i = 0; $i < 301; $i++) $this->user('student', 'Akun batas '.$i);
        $this->actingAs($this->sa)->getJson(route('superadmin.users.export', ['format' => 'pdf']))
            ->assertUnprocessable()->assertJsonValidationErrors('export');
    }
}
