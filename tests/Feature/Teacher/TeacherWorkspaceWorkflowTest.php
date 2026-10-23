<?php

namespace Tests\Feature\Teacher;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\LessonSession;
use App\Models\MonthlyReport;
use App\Models\Program;
use App\Models\ReportCycle;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherBranchAssignment;
use App\Models\User;
use App\Services\BranchOperationsService;
use App\Services\TeacherWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherWorkspaceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher;
    private Teacher $otherTeacher;
    private Branch $branch;
    private Branch $otherBranch;
    private Program $program;
    private Program $otherProgram;
    private Student $student;
    private Student $otherStudent;
    private ClassGroup $group;
    private LessonSession $session;
    private LessonSession $otherSession;
    private TeacherAvailability $slot;
    private TeacherAvailability $otherSlot;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-14 18:00:00', 'Asia/Makassar'));
        Http::fake();
        $this->branch = $this->branch('Cabang Utama');
        $this->otherBranch = $this->branch('Cabang Luar Rahasia');
        $this->teacher = $this->teacher('Dipta Uji');
        $this->otherTeacher = $this->teacher('Teacher Luar Rahasia');
        $this->program = $this->program('English Regular');
        $this->otherProgram = $this->program('Program Luar Rahasia');
        $this->student = $this->student('Siswa Utama', $this->branch, $this->teacher);
        $this->otherStudent = $this->student('Siswa Luar Rahasia', $this->otherBranch, $this->otherTeacher);
        $this->group = $this->group($this->teacher);
        ClassGroupMembership::create(['id'=>(string)Str::uuid(),'class_group_id'=>$this->group->id,'student_id'=>$this->student->id,'starts_on'=>'2026-10-01']);
        $this->session = $this->makeSession($this->teacher);
        $this->otherSession = $this->makeSession($this->otherTeacher, ['branch_id'=>$this->otherBranch->id,
            'program_id'=>$this->otherProgram->id,'class_group_id'=>null,'topic'=>'Topik Luar Rahasia']);
        $this->slot = $this->slot($this->teacher, $this->branch);
        $this->otherSlot = $this->slot($this->otherTeacher, $this->otherBranch, ['notes'=>'Catatan Slot Luar Rahasia']);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function user(string $role, string $name = 'Pengguna Uji'): User
    {
        return User::create(['id'=>(string)Str::uuid(),'username'=>Str::random(14),'full_name'=>$name,
            'password_hash'=>'fixture-hash','role_code'=>$role,'is_active'=>true]);
    }

    private function teacher(string $name): Teacher
    {
        return Teacher::create(['id'=>(string)Str::uuid(),'user_id'=>$this->user('teacher',$name)->id,'is_active'=>true,'whatsapp_number'=>'081234567890']);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id'=>(string)Str::uuid(),'code'=>Str::random(10),'name'=>$name,'timezone_name'=>'Asia/Makassar','payment_recap_day'=>20,'is_active'=>true]);
    }

    private function program(string $name): Program
    {
        return Program::create(['id'=>(string)Str::uuid(),'code'=>Str::random(10),'name'=>$name,'class_type'=>'regular','is_active'=>true]);
    }

    private function student(string $name, Branch $branch, ?Teacher $teacher = null): Student
    {
        $s=Student::create(['id'=>(string)Str::uuid(),'portal_user_id'=>$this->user('student',$name)->id,'full_name'=>$name,
            'school_name'=>'Sekolah Uji','grade_name'=>'5','began_on'=>'2026-10-01','status'=>'active','special_notes_internal'=>'Rahasia Profil Internal']);
        StudentEnrollment::create(['id'=>(string)Str::uuid(),'student_id'=>$s->id,'branch_id'=>$branch->id,'program_id'=>$this->program->id,'starts_on'=>'2026-10-01']);
        if($teacher){StudentTeacherAssignment::create(['id'=>(string)Str::uuid(),'student_id'=>$s->id,'teacher_id'=>$teacher->id,'starts_on'=>'2026-10-01','is_report_owner'=>true]);}

        return $s;
    }

    private function group(Teacher $teacher, array $extra=[]): ClassGroup
    {
        return ClassGroup::create($extra+['id'=>(string)Str::uuid(),'branch_id'=>$this->branch->id,'program_id'=>$this->program->id,
            'default_teacher_id'=>$teacher->id,'code'=>Str::random(10),'name'=>'Kelas Utama','is_active'=>true]);
    }

    private function makeSession(Teacher $teacher, array $extra=[]): LessonSession
    {
        return LessonSession::create($extra+['id'=>(string)Str::uuid(),'branch_id'=>$this->branch->id,'teacher_id'=>$teacher->id,
            'program_id'=>$this->program->id,'class_group_id'=>$this->group->id,'teaching_location'=>'branch',
            'planned_start_at'=>'2026-10-14 09:00:00','planned_end_at'=>'2026-10-14 10:00:00','status'=>'scheduled','topic'=>'Topik Utama']);
    }

    private function slot(Teacher $teacher, Branch $branch, array $extra=[]): TeacherAvailability
    {
        return TeacherAvailability::create($extra+['id'=>(string)Str::uuid(),'teacher_id'=>$teacher->id,'branch_id'=>$branch->id,
            'available_on'=>'2026-10-20','starts_at'=>'09:00:00','ends_at'=>'12:00:00','class_type'=>'Regular','availability_status'=>'Available','notes'=>'Slot Utama']);
    }

    private function payload(array $extra=[]): array
    {
        return $extra+['branch_id'=>$this->branch->id,'available_on'=>'2026-10-21','starts_at'=>'13:00','ends_at'=>'15:00',
            'class_type'=>'Regular','availability_status'=>'Available','notes'=>'Slot Baru'];
    }

    private function editPayload(TeacherAvailability $slot, array $extra=[]): array
    {
        return $extra+['branch_id'=>$slot->branch_id,'available_on'=>$slot->available_on->toDateString(),'starts_at'=>$slot->starts_at,
            'ends_at'=>$slot->ends_at,'class_type'=>$slot->class_type,'availability_status'=>$slot->availability_status,'notes'=>$slot->notes,
            'record_version'=>app(BranchOperationsService::class)->version($slot->fresh())];
    }

    private function report(Student $student, Teacher $teacher, string $month='2026-10-01'): MonthlyReport
    {
        $cycle=ReportCycle::forceCreate(['id'=>(string)Str::uuid(),'student_id'=>$student->id,'cycle_number'=>1,
            'start_month'=>'2026-10-01','end_month'=>'2026-12-01','share_due_on'=>'2027-01-05']);

        return MonthlyReport::forceCreate(['id'=>(string)Str::uuid(),'student_id'=>$student->id,'cycle_id'=>$cycle->id,'branch_id'=>$this->branch->id,
            'teacher_id'=>$teacher->id,'report_month'=>$month,'due_on'=>Carbon::parse($month)->endOfMonth()->toDateString(),'video_target'=>0,
            'status'=>'draft','development_summary'=>'Isi Rapor Rahasia','internal_teacher_note'=>'Catatan Teacher Rahasia']);
    }

    private function login(): void { $this->actingAs($this->teacher->user); }

    public function test_sidebar_opens_both_teacher_menus_with_active_slot_navigation(): void
    {
        $this->login();
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('href="'.route('teacher.schedule.index').'"',false)
            ->assertSee('href="'.route('teacher.students.index').'"',false)->assertSee('Profil Saya');
        $this->get(route('teacher.availability.index'))->assertOk()->assertSee('Slot Ketersediaan')->assertSee('Tambah slot');
    }

    public static function pages(): array
    {
        return array_map(fn($x)=>[$x],['schedule.index','students.index','availability.index','availability.create']);
    }

    #[DataProvider('pages')]
    public function test_guest_is_redirected_to_login(string $name): void
    {
        $this->get(route('teacher.'.$name))->assertRedirect(route('login'));
    }

    public static function forbiddenRoles(): array
    {
        $cases=[];foreach(['superadmin','admin','student'] as $role){foreach(['schedule.index','students.index','availability.index','availability.create'] as $page){$cases[]=[$role,$page];}}

        return $cases;
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_cannot_use_a_teacher_identity(string $role,string $name): void
    {
        $this->actingAs($this->user($role))->get(route('teacher.'.$name))->assertForbidden();
    }

    public function test_schedule_is_scoped_to_session_owner_and_read_only(): void
    {
        $this->login();$before=SessionStudent::count();$reports=MonthlyReport::count();
        $this->get(route('teacher.schedule.index'))->assertOk()->assertSee('Kelas Utama')->assertSee('1 siswa')
            ->assertDontSee('Topik Luar Rahasia')->assertDontSee('Program Luar Rahasia')->assertDontSee('Cabang Luar Rahasia')
            ->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Cache-Control','max-age=0, no-store, private');
        $this->get(route('teacher.schedule.show',$this->session))->assertOk()->assertSee('Siswa Utama')->assertSee('Buka Absensi Siswa')
            ->assertSee('href="'.route('teacher.student-attendance.show',$this->session).'"',false)->assertDontSee('Rahasia Profil Internal');
        $this->assertSame($before,SessionStudent::count());$this->assertSame($reports,MonthlyReport::count());
    }

    public function test_foreign_session_slot_and_student_urls_are_forbidden(): void
    {
        $this->login();
        $this->get(route('teacher.schedule.show',$this->otherSession))->assertForbidden();
        $this->get(route('teacher.students.show',$this->otherStudent))->assertForbidden();
        foreach(['show','edit'] as $action){$this->get(route('teacher.availability.'.$action,$this->otherSlot))->assertForbidden();}
        $this->putJson(route('teacher.availability.update',$this->otherSlot),$this->editPayload($this->otherSlot))->assertForbidden();
        $this->assertSame('Catatan Slot Luar Rahasia',$this->otherSlot->fresh()->notes);
    }

    public function test_default_class_teacher_does_not_gain_another_teachers_session(): void
    {
        $this->session->update(['teacher_id'=>$this->otherTeacher->id]);$this->login();
        $this->get(route('teacher.schedule.show',$this->session))->assertForbidden();
        $this->get(route('teacher.schedule.index'))->assertOk()->assertViewHas('sessions',fn($x)=>$x->total()===0);
    }

    public function test_actual_date_is_used_for_filters_and_private_participants_are_unique(): void
    {
        $this->session->update(['actual_start_at'=>'2026-11-01 09:00:00','actual_end_at'=>'2026-11-01 10:00:00']);
        SessionStudent::create(['session_id'=>$this->session->id,'student_id'=>$this->student->id]);$this->login();
        $this->get(route('teacher.schedule.index'))->assertOk()->assertViewHas('sessions',fn($x)=>$x->total()===0);
        $this->get(route('teacher.schedule.index',['from'=>'2026-11-01','to'=>'2026-11-01']))->assertOk()->assertSee('1 siswa');
        $this->session->update(['class_group_id'=>null,'teaching_location'=>'student_home']);
        $this->get(route('teacher.schedule.show',$this->session))->assertOk()->assertSee('Rumah siswa')->assertSee('Siswa Utama')
            ->assertViewHas('participants',fn($x)=>$x->count()===1);
    }

    public function test_roster_excludes_expired_membership_and_enrollment_and_future_join(): void
    {
        $late=$this->student('Siswa Belum Mulai',$this->branch);$late->update(['began_on'=>'2026-10-20']);
        $expired=$this->student('Siswa Keanggotaan Berakhir',$this->branch);
        $noEnrollment=$this->student('Siswa Pendaftaran Berakhir',$this->branch);
        foreach([$late,$expired,$noEnrollment] as $s){ClassGroupMembership::create(['id'=>(string)Str::uuid(),'class_group_id'=>$this->group->id,
            'student_id'=>$s->id,'starts_on'=>'2026-10-01','ends_on'=>$s->id===$expired->id?'2026-10-13':null]);}
        $noEnrollment->enrollments()->update(['ends_on'=>'2026-10-13']);$this->login();
        $this->get(route('teacher.schedule.show',$this->session))->assertOk()->assertSee('Siswa Utama')
            ->assertDontSee('Siswa Belum Mulai')->assertDontSee('Siswa Keanggotaan Berakhir')->assertDontSee('Siswa Pendaftaran Berakhir');
    }

    public function test_rescheduled_status_is_filterable_and_links_only_owned_sessions(): void
    {
        $replacement=$this->makeSession($this->teacher,['rescheduled_from_session_id'=>$this->session->id,'planned_start_at'=>'2026-10-15 09:00:00',
            'planned_end_at'=>'2026-10-15 10:00:00','topic'=>'Pengganti Utama']);$this->login();
        $this->get(route('teacher.schedule.index',['status'=>'rescheduled']))->assertOk()->assertViewHas('sessions',fn($x)=>$x->total()===1);
        $this->get(route('teacher.schedule.show',$this->session))->assertOk()->assertSee('href="'.route('teacher.schedule.show',$replacement).'"',false);
        $replacement->update(['teacher_id'=>$this->otherTeacher->id]);
        $this->get(route('teacher.schedule.show',$this->session))->assertOk()->assertSee('memiliki jadwal pengganti')
            ->assertDontSee('href="'.route('teacher.schedule.show',$replacement).'"',false);
        $this->get(route('teacher.schedule.index',['status'=>'scheduled']))->assertOk()->assertViewHas('sessions',fn($x)=>$x->total()===0);
    }

    public function test_cancelled_alias_and_search_filters_are_supported(): void
    {
        $this->session->update(['status'=>'Dibatalkan']);$this->login();
        $this->get(route('teacher.schedule.index',['status'=>'cancelled','q'=>'Topik Utama']))->assertOk()->assertSee('Dibatalkan');
        $this->get(route('teacher.schedule.index',['q'=>'Nama Tidak Ada']))->assertOk()->assertSee('Belum ada jadwal untuk filter ini');
    }

    public static function badFilters(): array
    {
        return [['schedule.index',['from'=>'2026-02-30'],'from'],['schedule.index',['from'=>'2026-11-01','to'=>'2026-10-01'],'to'],
            ['schedule.index',['status'=>'bogus'],'status'],['students.index',['month'=>'2026-13'],'month'],
            ['students.index',['month'=>'1999-12'],'month'],['students.index',['q'=>['array']],'q'],['availability.index',['to'=>'invalid'],'to']];
    }

    #[DataProvider('badFilters')]
    public function test_bad_filter_input_returns_validation_instead_of_server_error(string $name,array $values,string $field): void
    {
        $this->login();$this->getJson(route('teacher.'.$name,$values))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_foreign_branch_and_program_filters_are_not_accepted(): void
    {
        $this->login();
        $this->get(route('teacher.schedule.index',['branch_id'=>$this->otherBranch->id]))->assertForbidden();
        $this->get(route('teacher.schedule.index',['program_id'=>$this->otherProgram->id]))->assertForbidden();
        $this->get(route('teacher.availability.index',['branch_id'=>$this->otherBranch->id]))->assertForbidden();
    }

    public function test_direct_assignment_lists_student_before_report_preparation(): void
    {
        $this->session->delete();$this->group->memberships()->delete();$this->group->delete();$this->login();
        $this->get(route('teacher.students.index'))->assertOk()->assertSee('Siswa Utama')->assertSee('Menunggu Admin menyiapkan periode rapor')
            ->assertDontSee('Siswa Luar Rahasia');
        $this->get(route('teacher.students.show',$this->student))->assertOk()->assertSee('Sekolah Uji')->assertSee('Admin belum menyiapkan periode')
            ->assertSee('Kembali ke Siswa Binaan')->assertDontSee('Rahasia Profil Internal');
        $this->assertDatabaseCount('monthly_reports',0);$this->assertDatabaseCount('session_students',0);
    }

    public function test_class_and_private_roster_pupils_appear_without_direct_assignment_or_report(): void
    {
        $this->student->studentTeacherAssignments()->delete();
        $private=$this->student('Siswa Private',$this->branch);
        $session=$this->makeSession($this->teacher,['class_group_id'=>null,'teaching_location'=>'student_home','planned_start_at'=>'2026-10-14 13:00:00','planned_end_at'=>'2026-10-14 14:00:00']);
        SessionStudent::create(['session_id'=>$session->id,'student_id'=>$private->id]);$this->login();
        $this->get(route('teacher.students.index'))->assertOk()->assertSee('Siswa Utama')->assertSee('Siswa Private')->assertViewHas('students',fn($x)=>$x->total()===2);
        $this->get(route('teacher.students.show',$private))->assertOk()->assertSee('Rumah siswa');
    }

    public function test_assignments_are_checked_against_month_and_student_join_date(): void
    {
        $this->session->delete();$this->group->memberships()->delete();$this->group->delete();
        $this->student->studentTeacherAssignments()->update(['ends_on'=>'2026-10-10']);$this->student->update(['began_on'=>'2026-10-20']);$this->login();
        $this->get(route('teacher.students.index'))->assertOk()->assertViewHas('students',fn($x)=>$x->total()===0);
        $this->student->update(['began_on'=>'2026-10-01']);
        $this->get(route('teacher.students.index',['month'=>'2026-11']))->assertOk()->assertViewHas('students',fn($x)=>$x->total()===0);
        $this->get(route('teacher.students.show',['student'=>$this->student,'month'=>'2026-10']))->assertOk();
        $this->get(route('teacher.students.show',['student'=>$this->student,'month'=>'2026-11']))->assertForbidden();
    }

    public function test_inactive_pupils_and_search_are_supported_without_losing_history(): void
    {
        $this->student->update(['status'=>'inactive']);$this->login();
        $this->get(route('teacher.students.index',['status'=>'inactive','q'=>'Utama']))->assertOk()->assertSee('Siswa Utama')->assertSee('Nonaktif');
        $this->get(route('teacher.students.index',['status'=>'active']))->assertOk()->assertViewHas('students',fn($x)=>$x->total()===0);
    }

    public function test_report_ownership_never_exposes_another_teachers_content_or_links(): void
    {
        $report=$this->report($this->student,$this->otherTeacher);$this->login();
        $this->get(route('teacher.students.index'))->assertOk()->assertSee('Periode dikelola Teacher lain')->assertDontSee($report->id);
        $this->get(route('teacher.students.show',$this->student))->assertOk()->assertSee('Periode ini dikelola Teacher lain')
            ->assertDontSee('Isi Rapor Rahasia')->assertDontSee('Catatan Teacher Rahasia')->assertDontSee($report->id);
        $report->update(['teacher_id'=>$this->teacher->id]);
        $this->get(route('teacher.students.show',$this->student))->assertOk()->assertSee('href="'.route('teacher.student-progress.edit',$report).'"',false)
            ->assertSee('href="'.route('teacher.monthly-reports.show',$report).'"',false);
    }

    public function test_student_detail_does_not_include_other_teachers_sessions_for_the_same_child(): void
    {
        SessionStudent::create(['session_id'=>$this->otherSession->id,'student_id'=>$this->student->id]);$this->login();
        $this->get(route('teacher.students.show',$this->student))->assertOk()->assertViewHas('sessions',fn($x)=>$x->total()===1)
            ->assertDontSee('href="'.route('teacher.schedule.show',$this->otherSession).'"',false)->assertDontSee('Topik Luar Rahasia');
    }

    public function test_stale_membership_does_not_add_a_student_detail_session(): void
    {
        $this->group->memberships()->update(['ends_on'=>'2026-10-13']);$this->login();
        $this->get(route('teacher.students.show',$this->student))->assertOk()->assertViewHas('sessions',fn($x)=>$x->total()===0);
    }

    public function test_own_slot_can_be_created_and_updated_without_posting_teacher_identity(): void
    {
        $this->login();$page=$this->get(route('teacher.availability.create'))->assertOk()->assertSee('Cabang Utama')->assertDontSee('Cabang Luar Rahasia');
        $page->assertDontSee('name="teacher_id"',false);
        $this->post(route('teacher.availability.store'),$this->payload())->assertRedirect();
        $new=TeacherAvailability::where('notes','Slot Baru')->firstOrFail();$this->assertSame($this->teacher->id,$new->teacher_id);
        $this->assertSame('13:00:00',$new->starts_at);
        $this->get(route('teacher.availability.show',$new))->assertOk()->assertSee('Kembali ke slot saya');
        $this->put(route('teacher.availability.update',$new),$this->editPayload($new,['availability_status'=>'Blocked']))->assertRedirect(route('teacher.availability.show',$new));
        $this->assertSame('Blocked',$new->fresh()->availability_status);
    }

    public function test_forged_teacher_and_branch_ids_cannot_create_or_move_a_slot(): void
    {
        $this->login();
        $this->postJson(route('teacher.availability.store'),$this->payload(['teacher_id'=>$this->otherTeacher->id]))->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->postJson(route('teacher.availability.store'),$this->payload(['branch_id'=>$this->otherBranch->id]))->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->putJson(route('teacher.availability.update',$this->slot),$this->editPayload($this->slot,['branch_id'=>$this->otherBranch->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->assertDatabaseCount('teacher_availability',2);$this->assertSame($this->branch->id,$this->slot->fresh()->branch_id);
    }

    public static function invalidSlots(): array
    {
        return [[['starts_at'=>'15:00','ends_at'=>'14:00'],'ends_at'],[['starts_at'=>'09:00','ends_at'=>'09:00'],'ends_at'],
            [['starts_at'=>['array']],'starts_at'],[['availability_status'=>['array']],'availability_status'],
            [['class_type'=>'Unknown'],'class_type'],[['availability_status'=>'Unknown'],'availability_status'],
            [['available_on'=>'2026-02-30'],'available_on'],[['notes'=>str_repeat('x',256)],'notes']];
    }

    #[DataProvider('invalidSlots')]
    public function test_invalid_slot_input_does_not_write_data(array $extra,string $field): void
    {
        $this->login();$this->postJson(route('teacher.availability.store'),$this->payload($extra))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('teacher_availability',2);
    }

    public function test_lowercase_legacy_values_are_normalized_and_both_minute_and_second_times_work(): void
    {
        $this->slot->update(['class_type'=>'regular','availability_status'=>'available']);$this->login();
        $this->get(route('teacher.availability.edit',$this->slot))->assertOk()->assertSee('value="Regular" selected',false);
        $this->put(route('teacher.availability.update',$this->slot),$this->editPayload($this->slot,['starts_at'=>'09:00:30','ends_at'=>'12:00:30']))->assertRedirect();
        $this->assertSame('Regular',$this->slot->fresh()->class_type);$this->assertSame('09:00:30',$this->slot->fresh()->starts_at);
    }

    public function test_branch_options_support_future_pupil_assignments_but_validate_the_exact_slot_date(): void
    {
        $futureTeacher=$this->teacher('Teacher Mendatang');$s=$this->student('Siswa Mendatang',$this->branch);
        StudentTeacherAssignment::create(['id'=>(string)Str::uuid(),'student_id'=>$s->id,'teacher_id'=>$futureTeacher->id,'starts_on'=>'2026-11-10','ends_on'=>'2026-11-20','is_report_owner'=>true]);
        $this->actingAs($futureTeacher->user)->get(route('teacher.availability.create'))->assertOk()->assertSee('Cabang Utama');
        $this->postJson(route('teacher.availability.store'),$this->payload(['available_on'=>'2026-11-09']))->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->post(route('teacher.availability.store'),$this->payload(['available_on'=>'2026-11-10']))->assertRedirect();
        $this->postJson(route('teacher.availability.store'),$this->payload(['available_on'=>'2026-11-21']))->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_branch_options_match_operational_sources_on_the_same_date(): void
    {
        $workspace=app(TeacherWorkspaceService::class);$actor=$workspace->actor($this->teacher->user);
        foreach(['2026-10-14','2026-10-20','2026-11-10'] as $day){
            $expected=Branch::get()->filter(fn($b)=>app(BranchOperationsService::class)->teachers([$b->id],$day,$day)->whereKey($this->teacher->id)->exists())->pluck('id')->sort()->values()->all();
            $this->assertSame($expected,$workspace->branches($actor,$day,$day)->pluck('id')->sort()->values()->all());
        }
    }

    public function test_an_empty_teacher_workspace_remains_usable_and_shows_clear_empty_states(): void
    {
        $t=$this->teacher('Teacher Baru');$this->actingAs($t->user);
        $this->get(route('teacher.schedule.index'))->assertOk()->assertSee('Belum ada jadwal untuk filter ini');
        $this->get(route('teacher.students.index'))->assertOk()->assertSee('Belum ada siswa untuk filter ini');
        $this->get(route('teacher.availability.create'))->assertOk()->assertSee('Belum ada cabang penugasan aktif')->assertDontSee('Simpan slot');
        $this->postJson(route('teacher.availability.store'),$this->payload())->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_retired_branch_reference_can_be_kept_but_cannot_be_used_for_a_new_slot_date(): void
    {
        $this->branch->update(['is_active'=>false]);$this->login();
        $this->get(route('teacher.availability.edit',$this->slot))->assertOk()->assertSee('referensi lama');
        $this->put(route('teacher.availability.update',$this->slot),$this->editPayload($this->slot,['notes'=>'Perbaikan catatan']))->assertRedirect();
        $this->putJson(route('teacher.availability.update',$this->slot),$this->editPayload($this->slot,['available_on'=>'2026-10-21']))
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->postJson(route('teacher.availability.store'),$this->payload())->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_concurrent_superadmin_change_rejects_stale_teacher_form_and_retains_old_signature(): void
    {
        $payload=$this->editPayload($this->slot);$this->slot->update(['notes'=>'Admin telah mengubah']);$this->login();
        $this->putJson(route('teacher.availability.update',$this->slot),$payload)->assertUnprocessable()->assertJsonValidationErrors('record_version');
        $this->from(route('teacher.availability.edit',$this->slot))->put(route('teacher.availability.update',$this->slot),$payload)
            ->assertRedirect(route('teacher.availability.edit',$this->slot))->assertSessionHasErrors('record_version');
        $this->get(route('teacher.availability.edit',$this->slot))->assertOk()->assertSee('value="'.$payload['record_version'].'"',false);
        $this->assertSame('Admin telah mengubah',$this->slot->fresh()->notes);
    }

    public function test_update_also_requires_version_when_using_patch(): void
    {
        $this->login();$payload=$this->editPayload($this->slot);unset($payload['record_version']);
        $this->patchJson(route('teacher.availability.update',$this->slot),$payload)->assertUnprocessable()->assertJsonValidationErrors('record_version');
    }

    public function test_already_open_page_cannot_write_after_account_or_profile_is_disabled(): void
    {
        $this->login();$user=$this->teacher->user;$user->teacherProfile;$this->teacher->update(['is_active'=>false]);
        $this->postJson(route('teacher.availability.store'),$this->payload())->assertForbidden();
        $this->get(route('teacher.students.index'))->assertForbidden();
        $this->teacher->update(['is_active'=>true]);User::whereKey($user->id)->update(['is_active'=>false]);
        $this->postJson(route('teacher.availability.store'),$this->payload())->assertForbidden();
        $this->assertDatabaseCount('teacher_availability',2);
    }

    public function test_status_change_does_not_cancel_existing_sessions_or_remove_attendance(): void
    {
        $this->slot->update(['available_on'=>'2026-10-14']);SessionStudent::create(['session_id'=>$this->session->id,'student_id'=>$this->student->id,'attendance_status'=>'present']);$this->login();
        $this->get(route('teacher.availability.show',$this->slot))->assertOk()->assertSee('href="'.route('teacher.schedule.show',$this->session).'"',false);
        $this->put(route('teacher.availability.update',$this->slot),$this->editPayload($this->slot,['availability_status'=>'Blocked']))->assertRedirect();
        $this->assertSame('scheduled',$this->session->fresh()->status);$this->assertDatabaseHas('session_students',['session_id'=>$this->session->id,'attendance_status'=>'present']);
    }

    public function test_teacher_slot_is_visible_to_superadmin_and_admin_scheduling_guard(): void
    {
        $this->login();$this->post(route('teacher.availability.store'),$this->payload(['availability_status'=>'Blocked','available_on'=>'2026-10-22']))->assertRedirect();
        $this->actingAs($this->user('superadmin'))->get(route('superadmin.teacher-availability.index'))->assertOk()->assertSee('Dipta Uji')->assertViewHas('availabilities', fn($items) => $items->contains(fn($item) => $item->teacher_id === $this->teacher->id && $item->available_on->toDateString() === '2026-10-22'));
        $admin=$this->user('admin');BranchAdminAssignment::create(['id'=>(string)Str::uuid(),'admin_user_id'=>$admin->id,'branch_id'=>$this->branch->id,'starts_on'=>'2026-01-01']);
        $this->actingAs($admin)->get(route('admin.lesson-sessions.create'))->assertOk()->assertSee('Blocked');
        $this->postJson(route('admin.lesson-sessions.store'),['branch_id'=>$this->branch->id,'teacher_id'=>$this->teacher->id,'program_id'=>$this->program->id,
            'class_group_id'=>$this->group->id,'teaching_location'=>'branch','planned_start_at'=>'2026-10-22 13:00:00','planned_end_at'=>'2026-10-22 14:00:00','status'=>'scheduled'])
            ->assertUnprocessable()->assertJsonValidationErrors('planned_start_at');
    }

    public static function availabilityPanelSizes(): array
    {
        return ['empty' => [0], 'more_than_twelve' => [13], 'fifty' => [50], 'above_fifty' => [51]];
    }

    #[DataProvider('availabilityPanelSizes')]
    public function test_admin_availability_panel_shows_at_most_fifty_sorted_future_slots_in_own_branch(int $futureCount): void
    {
        $this->slot->update(['available_on' => '2026-10-13']);
        $ids = [];
        for ($offset = $futureCount; $offset >= 1; --$offset) {
            $ids[$offset] = $this->slot($this->teacher, $this->branch, [
                'available_on' => Carbon::parse('2026-10-14')->addDays($offset)->toDateString(),
            ])->id;
        }
        ksort($ids);
        $expectedIds = array_slice(array_values($ids), 0, 50);
        $admin = $this->user('admin');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);

        foreach ([route('admin.lesson-sessions.create'), route('admin.lesson-sessions.edit', $this->session)] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk()
                ->assertViewHas('availabilitySlots', fn ($slots) => $slots->pluck('id')->all() === $expectedIds)
                ->assertDontSee('Cabang Luar Rahasia')->assertDontSee('Teacher Luar Rahasia');
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
            $rows = (new \DOMXPath($dom))->query('//article[.//h2[normalize-space()="Ketersediaan Teacher"]]//tbody/tr');
            $this->assertSame(min($futureCount, 50), $rows->length);
            if ($futureCount === 0) {
                $response->assertSee('Belum ada slot ketersediaan tersimpan.');
            }
        }
        $this->assertDatabaseCount('teacher_availability', $futureCount + 2);
    }

    public function test_profile_and_slot_output_escape_user_content(): void
    {
        $this->student->update(['school_name'=>'<script>alert(1)</script>']);$this->slot->update(['notes'=>'<img src=x onerror=alert(1)>']);$this->login();
        $this->get(route('teacher.students.show',$this->student))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $this->get(route('teacher.availability.show',$this->slot))->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;',false)->assertDontSee('<img src=x onerror=alert(1)>',false);
    }

    public function test_schedule_pagination_preserves_filters(): void
    {
        for($i=0;$i<13;$i++){$this->makeSession($this->teacher,['topic'=>'Uji Pagination']);}$this->login();
        $this->get(route('teacher.schedule.index',['from'=>'2026-10-14','to'=>'2026-10-14','q'=>'Uji Pagination']))->assertOk()
            ->assertViewHas('sessions',fn($x)=>$x->total()===13 && $x->count()===12)->assertSee('q=Uji%20Pagination',false);
        $this->get(route('teacher.schedule.index',['from'=>'2026-10-14','to'=>'2026-10-14','q'=>'Uji Pagination','page'=>2]))->assertOk()
            ->assertViewHas('sessions',fn($x)=>$x->count()===1);
    }
}
