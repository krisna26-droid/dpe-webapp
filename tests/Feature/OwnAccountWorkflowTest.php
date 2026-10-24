<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\FileAsset;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTeacherAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\OwnAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnAccountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;
    private User $parent;
    private Teacher $teacher;
    private Teacher $otherTeacher;
    private Student $student;
    private Branch $branch;
    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();Carbon::setTestNow(Carbon::parse('2026-10-14 18:00:00','Asia/Makassar'));
        Storage::fake('local');Http::fake();
        $this->teacherUser=$this->account('teacher','Dipta Uji');$this->parent=$this->account('student','Parent Utama');
        $this->teacher=Teacher::create(['id'=>(string)Str::uuid(),'user_id'=>$this->teacherUser->id,'whatsapp_number'=>'081234567890','is_active'=>true]);
        $this->otherTeacher=Teacher::create(['id'=>(string)Str::uuid(),'user_id'=>$this->account('teacher','Teacher Luar Rahasia')->id,'whatsapp_number'=>'089999999999','is_active'=>true]);
        $this->branch=$this->branch('Cabang Utama');$this->otherBranch=$this->branch('Cabang Luar Rahasia');
        $program=Program::create(['id'=>(string)Str::uuid(),'code'=>'TEST_REG','name'=>'Program Utama','class_type'=>'regular','is_active'=>true]);
        $this->student=Student::create(['id'=>(string)Str::uuid(),'portal_user_id'=>$this->parent->id,'full_name'=>'Siswa Utama','status'=>'active','began_on'=>'2026-10-01']);
        StudentEnrollment::create(['id'=>(string)Str::uuid(),'student_id'=>$this->student->id,'branch_id'=>$this->branch->id,'program_id'=>$program->id,'starts_on'=>'2026-10-01']);
        StudentTeacherAssignment::create(['id'=>(string)Str::uuid(),'student_id'=>$this->student->id,'teacher_id'=>$this->teacher->id,'starts_on'=>'2026-10-01','is_report_owner'=>true]);
        ClassGroup::create(['id'=>(string)Str::uuid(),'branch_id'=>$this->otherBranch->id,'program_id'=>$program->id,'default_teacher_id'=>$this->otherTeacher->id,'code'=>'SECRET','name'=>'Kelas Luar Rahasia','is_active'=>true]);
    }

    protected function tearDown(): void { Carbon::setTestNow();parent::tearDown(); }

    private function account(string $role,string $name='Pengguna Uji'): User
    {
        return User::create(['id'=>(string)Str::uuid(),'username'=>Str::random(14),'full_name'=>$name,'email'=>null,'role_code'=>$role,
            'password_hash'=>Hash::make('Password123!'),'is_active'=>true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id'=>(string)Str::uuid(),'code'=>Str::random(8),'name'=>$name,'timezone_name'=>'Asia/Makassar','payment_recap_day'=>20,'is_active'=>true]);
    }

    private function profileData(array $extra=[]): array
    {
        return $extra+['full_name'=>'Dipta Baru','email'=>'dipta@example.test','whatsapp_number'=>'+6281234567890',
            'record_version'=>app(OwnAccountService::class)->version($this->teacherUser->fresh(),$this->teacher->fresh())];
    }

    private function passwordData(array $extra=[]): array
    {
        return $extra+['current_password'=>'Password123!','password'=>'Password456!','password_confirmation'=>'Password456!'];
    }

    private function photo(array $extra=[]): FileAsset
    {
        $this->actingAs($this->teacherUser)->put(route('teacher.profile.update'),$this->profileData($extra+['photo'=>UploadedFile::fake()->image('photo.png',100,100)]))->assertRedirect(route('teacher.profile.show'));

        return $this->teacher->fresh()->photoFile;
    }

    public function test_teacher_dashboard_removes_only_the_requested_monitoring_block(): void
    {
        $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'))->assertOk()->assertDontSee('Monitoring laporan ·')
            ->assertDontSee('Tenggat terlewati')->assertSee('Siswa binaan bulan ini')->assertSee('Periode laporan bulan ini')
            ->assertSee('Sesi hari ini')->assertSee('Laporan diajukan bulan ini')->assertSee('Jadwal hari ini')
            ->assertSee('href="'.route('monitoring.index').'"',false);
        $this->get(route('monitoring.index'))->assertOk()->assertSee('Monitoring Laporan');
    }

    public function test_teacher_and_parent_sidebar_menus_are_active(): void
    {
        $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'))->assertOk()->assertSee('href="'.route('teacher.profile.show').'"',false)
            ->assertSee('href="'.route('teacher.settings.index').'"',false);
        $this->actingAs($this->parent)->get(route('student.dashboard'))->assertOk()->assertSee('href="'.route('student.settings.index').'"',false);
    }

    public static function pages(): array { return [['teacher.profile.show'],['teacher.profile.edit'],['teacher.profile.photo'],['teacher.settings.index'],['student.settings.index']]; }

    #[DataProvider('pages')]
    public function test_guest_must_login(string $route): void { $this->get(route($route))->assertRedirect(route('login')); }

    public static function wrongRoles(): array
    {
        $cases=[];foreach(['superadmin','admin','student'] as $role){foreach(['teacher.profile.show','teacher.profile.edit','teacher.profile.photo','teacher.settings.index'] as $page){$cases[]=[$role,$page];}}
        foreach(['superadmin','admin','teacher'] as $role){$cases[]=[$role,'student.settings.index'];}

        return $cases;
    }

    #[DataProvider('wrongRoles')]
    public function test_other_roles_do_not_gain_an_own_teacher_or_parent_workspace(string $role,string $page): void
    {
        $this->actingAs($this->account($role))->get(route($page))->assertForbidden();
    }

    public function test_profile_shows_own_identity_and_real_pupil_branch_without_branch_assignment(): void
    {
        $this->actingAs($this->teacherUser)->get(route('teacher.profile.show'))->assertOk()->assertSee('Dipta Uji')->assertSee('Cabang Utama')
            ->assertDontSee('Teacher Luar Rahasia')->assertDontSee('Cabang Luar Rahasia')->assertDontSee('Kelas Luar Rahasia')
            ->assertHeader('Cache-Control','max-age=0, no-store, private')->assertHeader('X-Content-Type-Options','nosniff');
        $this->get(route('teacher.profile.edit'))->assertOk()->assertSee('Kembali ke Profil Saya')->assertDontSee('name="username"',false);
    }

    public function test_profile_update_keeps_identity_role_and_assignment_references(): void
    {
        $id=$this->teacher->id;$username=$this->teacherUser->username;
        $this->actingAs($this->teacherUser)->put(route('teacher.profile.update'),$this->profileData())->assertRedirect(route('teacher.profile.show'));
        $this->assertSame('Dipta Baru',$this->teacherUser->fresh()->full_name);$this->assertSame($username,$this->teacherUser->fresh()->username);
        $this->assertSame('teacher',$this->teacherUser->fresh()->role_code);$this->assertSame($this->teacherUser->id,$this->teacher->fresh()->user_id);
        $this->assertDatabaseHas('student_teacher_assignments',['student_id'=>$this->student->id,'teacher_id'=>$id]);
        $this->get(route('teacher.profile.show'))->assertOk()->assertSee('Dipta Baru');Http::assertNothingSent();
    }

    public function test_blank_email_and_normalization_are_supported(): void
    {
        $this->actingAs($this->teacherUser)->put(route('teacher.profile.update'),$this->profileData(['email'=>'  DIPTA@EXAMPLE.TEST  ','full_name'=>'  Dipta Baru  ']))->assertRedirect();
        $this->assertSame('dipta@example.test',$this->teacherUser->fresh()->email);
        $this->put(route('teacher.profile.update'),$this->profileData(['email'=>'']))->assertRedirect();$this->assertNull($this->teacherUser->fresh()->email);
    }

    public static function forbiddenProfileFields(): array { return array_map(fn($x)=>[$x],['user_id','teacher_id','role_code','username','is_active','photo_file_id','password_hash']); }

    #[DataProvider('forbiddenProfileFields')]
    public function test_profile_rejects_forged_identity_or_privileged_fields(string $field): void
    {
        $this->actingAs($this->teacherUser)->putJson(route('teacher.profile.update'),$this->profileData([$field=>'forged-value']))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Dipta Uji',$this->teacherUser->fresh()->full_name);$this->assertSame('089999999999',$this->otherTeacher->fresh()->whatsapp_number);
    }

    public static function invalidProfileValues(): array
    {
        return [[['full_name'=>['array']],'full_name'],[['email'=>['array']],'email'],[['whatsapp_number'=>['array']],'whatsapp_number'],
            [['email'=>'invalid'],'email'],[['whatsapp_number'=>'abc'],'whatsapp_number'],[['whatsapp_number'=>'1234567'],'whatsapp_number'],
            [['whatsapp_number'=>'1234567890123456'],'whatsapp_number'],[['record_version'=>'bad'],'record_version']];
    }

    #[DataProvider('invalidProfileValues')]
    public function test_invalid_profile_values_return_validation_without_server_errors(array $values,string $field): void
    {
        $this->actingAs($this->teacherUser)->putJson(route('teacher.profile.update'),$this->profileData($values))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_email_collision_is_rejected_without_changing_other_fields(): void
    {
        $this->otherTeacher->user->update(['email'=>'used@example.test']);
        $this->actingAs($this->teacherUser)->putJson(route('teacher.profile.update'),$this->profileData(['email'=>'used@example.test']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('Dipta Uji',$this->teacherUser->fresh()->full_name);
    }

    public function test_stale_admin_changes_are_rejected_and_old_signature_is_retained(): void
    {
        $data=$this->profileData();$this->teacherUser->update(['full_name'=>'Admin changed']);
        $this->actingAs($this->teacherUser)->from(route('teacher.profile.edit'))->put(route('teacher.profile.update'),$data)->assertRedirect(route('teacher.profile.edit'))->assertSessionHasErrors('record_version');
        $this->get(route('teacher.profile.edit'))->assertOk()->assertSee('value="'.$data['record_version'].'"',false);
        $this->assertSame('Admin changed',$this->teacherUser->fresh()->full_name);
    }

    public function test_photo_is_private_and_only_own_photo_can_be_served(): void
    {
        $photo=$this->photo();$path=Storage::disk('local')->path($photo->storage_key);
        $this->get(route('teacher.profile.photo',['teacher_id'=>$this->otherTeacher->id]))->assertOk()->assertHeader('Content-Type','image/png')
            ->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Cache-Control','max-age=0, no-store, private');
        $this->assertSame(hash_file('sha256',$path),$photo->sha256_hex);
        $this->actingAs($this->otherTeacher->user)->get(route('teacher.profile.photo'))->assertNotFound();
        $this->actingAs($this->parent)->get(route('teacher.profile.photo'))->assertForbidden();
    }

    public function test_corrupted_or_missing_photo_has_no_download(): void
    {
        $photo=$this->photo();Storage::disk('local')->put($photo->storage_key,'<script>corrupt</script>');
        $this->get(route('teacher.profile.photo'))->assertNotFound();Storage::disk('local')->delete($photo->storage_key);
        $this->get(route('teacher.profile.photo'))->assertNotFound();
    }

    public function test_legacy_raster_photo_uploaded_by_admin_can_still_be_viewed(): void
    {
        $legacy=app(\App\Services\FileAssetService::class)->store(UploadedFile::fake()->image('legacy.gif',100,100),'teacher-photos');
        $this->teacher->update(['photo_file_id'=>$legacy->id]);
        $this->actingAs($this->teacherUser)->get(route('teacher.profile.photo'))->assertOk()->assertHeader('Content-Type','image/gif');
    }

    public static function invalidPhotos(): array { return [['gif'],['svg'],['php'],['big'],['dimensions']]; }

    #[DataProvider('invalidPhotos')]
    public function test_invalid_photos_do_not_create_metadata_or_files(string $kind): void
    {
        $photo=match($kind){'gif'=>UploadedFile::fake()->image('photo.gif'),'svg'=>UploadedFile::fake()->createWithContent('photo.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'php'=>UploadedFile::fake()->image('photo.php'), 'big'=>UploadedFile::fake()->image('photo.png')->size(2049),
            default=>UploadedFile::fake()->image('photo.png',4097,100)};
        $this->actingAs($this->teacherUser)->put(route('teacher.profile.update'),$this->profileData(['photo'=>$photo]),['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertDatabaseCount('file_assets',0);$this->assertSame([],Storage::disk('local')->allFiles('teacher-photos'));
    }

    public function test_replacing_photo_cleans_unreferenced_old_photo_only_after_commit(): void
    {
        $old=$this->photo();$new=$this->photo();$this->assertNotSame($old->id,$new->id);
        Storage::disk('local')->assertMissing($old->storage_key);Storage::disk('local')->assertExists($new->storage_key);
        $this->assertDatabaseMissing('file_assets',['id'=>$old->id]);
    }

    private function shellPhotoSources(string $html): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $nodes = (new \DOMXPath($dom))->query('//span[contains(concat(" ", normalize-space(@class), " "), " dpe-dashboard-role-card__badge ") or contains(concat(" ", normalize-space(@class), " "), " dpe-dashboard-avatar ")]/img[@data-account-avatar-image]');

        return array_map(fn ($image) => $image->getAttribute('src'), iterator_to_array($nodes));
    }

    public function test_saved_photo_appears_in_both_shell_boxes_across_pages_and_changes_after_replacement(): void
    {
        $photo = $this->photo();
        $url = route('teacher.profile.photo', ['v' => $photo->id]);
        foreach (['teacher.dashboard', 'teacher.profile.show', 'teacher.profile.edit', 'teacher.settings.index', 'inbox.index'] as $page) {
            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertSame([$url, $url], $this->shellPhotoSources($html));
            if ($page === 'teacher.profile.edit' && ($dir = getenv('DPE_PROFILE_QA_DIR'))) {
                if (! is_dir($dir)) mkdir($dir, 0777, true);
                file_put_contents($dir.'/profile-with-photo.html', $html);
            }
        }
        $replacement = $this->photo();
        $newUrl = route('teacher.profile.photo', ['v' => $replacement->id]);
        $this->assertNotSame($url, $newUrl);
        $html = $this->get(route('teacher.dashboard'))->assertOk()->getContent();
        $this->assertSame([$newUrl, $newUrl], $this->shellPhotoSources($html));
    }

    public static function dashboardRoles(): array { return [['superadmin'], ['admin'], ['teacher'], ['student']]; }

    #[DataProvider('dashboardRoles')]
    public function test_accounts_without_a_photo_keep_their_shell_fallbacks_and_do_not_borrow_another_photo(string $role): void
    {
        $this->photo();
        $user = $role === 'teacher' ? $this->otherTeacher->user : ($role === 'student' ? $this->parent : $this->account($role));
        $html = $this->actingAs($user)->get(route($role.'.dashboard'))->assertOk()->getContent();
        $this->assertSame($role === 'teacher' ? ['', ''] : [], $this->shellPhotoSources($html));
        $this->assertStringNotContainsString(route('teacher.profile.photo').'?v=', $html);
        if ($role === 'teacher' && ($dir = getenv('DPE_PROFILE_QA_DIR'))) {
            if (! is_dir($dir)) mkdir($dir, 0777, true);
            $this->actingAs($this->teacherUser);
            $this->teacher->update(['photo_file_id' => null]);
            file_put_contents($dir.'/profile-without-photo.html', $this->get(route('teacher.profile.edit'))->assertOk()->getContent());
        }
    }

    public function test_removing_photo_clears_both_shell_boxes_and_only_deletes_the_own_unreferenced_asset(): void
    {
        $photo = $this->photo();
        $other = app(\App\Services\FileAssetService::class)->store(UploadedFile::fake()->image('other.png'), 'teacher-photos');
        $this->otherTeacher->update(['photo_file_id' => $other->id]);
        $this->put(route('teacher.profile.update'), $this->profileData(['remove_photo' => '1']))
            ->assertRedirect(route('teacher.profile.show'));
        $this->assertNull($this->teacher->fresh()->photo_file_id);
        Storage::disk('local')->assertMissing($photo->storage_key);
        $this->assertDatabaseMissing('file_assets', ['id' => $photo->id]);
        $this->assertSame($other->id, $this->otherTeacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($other->storage_key);
        $this->get(route('teacher.profile.photo'))->assertNotFound();
        $this->assertSame(['', ''], $this->shellPhotoSources($this->get(route('teacher.dashboard'))->assertOk()->getContent()));
        Http::assertNothingSent();
    }

    public function test_a_normal_save_or_cancelled_removal_preserves_the_existing_photo(): void
    {
        $photo = $this->photo();
        foreach ([[], ['remove_photo' => false], ['remove_photo' => '0']] as $extra) {
            $this->put(route('teacher.profile.update'), $this->profileData($extra))->assertRedirect();
            $this->assertSame($photo->id, $this->teacher->fresh()->photo_file_id);
            Storage::disk('local')->assertExists($photo->storage_key);
        }
    }

    public function test_removing_an_already_empty_photo_is_safe(): void
    {
        $this->actingAs($this->teacherUser)->put(route('teacher.profile.update'), $this->profileData(['remove_photo' => true]))->assertRedirect();
        $this->assertNull($this->teacher->fresh()->photo_file_id);
        $this->assertDatabaseCount('file_assets', 0);
    }

    public function test_upload_and_removal_cannot_be_submitted_together(): void
    {
        $photo = $this->photo();
        $name = $this->teacherUser->fresh()->full_name;
        $this->putJson(route('teacher.profile.update'), $this->profileData(['full_name' => 'Should Not Save',
            'remove_photo' => '1', 'photo' => UploadedFile::fake()->image('conflicting.png')]))
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertSame($name, $this->teacherUser->fresh()->full_name);
        $this->assertSame($photo->id, $this->teacher->fresh()->photo_file_id);
        $this->assertDatabaseCount('file_assets', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('teacher-photos'));
    }

    public static function invalidPhotoRemovalValues(): array { return [[['array']], ['yes'], [2]]; }

    #[DataProvider('invalidPhotoRemovalValues')]
    public function test_invalid_photo_removal_flags_do_not_delete_a_photo($value): void
    {
        $photo = $this->photo();
        $this->putJson(route('teacher.profile.update'), $this->profileData(['remove_photo' => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors('remove_photo');
        $this->assertSame($photo->id, $this->teacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($photo->storage_key);
    }

    public function test_an_old_edit_form_cannot_delete_a_newer_photo(): void
    {
        $this->photo();
        $data = $this->profileData(['remove_photo' => '1']);
        $current = $this->photo();
        $this->from(route('teacher.profile.edit'))->put(route('teacher.profile.update'), $data)
            ->assertRedirect(route('teacher.profile.edit'))->assertSessionHasErrors('record_version');
        $this->assertSame($current->id, $this->teacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($current->storage_key);
        $this->get(route('teacher.profile.edit'))->assertOk()->assertSee('data-account-photo-remove-input checked', false);
    }

    public function test_failed_profile_save_does_not_remove_photo_or_change_other_fields(): void
    {
        $photo = $this->photo();
        $name = $this->teacherUser->fresh()->full_name;
        DB::statement("CREATE TRIGGER fail_photo_removal BEFORE UPDATE ON teachers BEGIN SELECT RAISE(FAIL, 'Fixture failure'); END");
        $this->put(route('teacher.profile.update'), $this->profileData(['remove_photo' => '1', 'full_name' => 'Should Roll Back']))->assertStatus(500);
        $this->assertSame($name, $this->teacherUser->fresh()->full_name);
        $this->assertSame($photo->id, $this->teacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($photo->storage_key);
        $this->assertDatabaseHas('file_assets', ['id' => $photo->id]);
    }

    public function test_removing_a_shared_photo_keeps_the_other_profile_and_physical_file(): void
    {
        $photo = $this->photo();
        $this->otherTeacher->update(['photo_file_id' => $photo->id]);
        $this->put(route('teacher.profile.update'), $this->profileData(['remove_photo' => 1]))->assertRedirect();
        $this->assertNull($this->teacher->fresh()->photo_file_id);
        $this->assertSame($photo->id, $this->otherTeacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($photo->storage_key);
        $this->assertDatabaseHas('file_assets', ['id' => $photo->id]);
        $this->actingAs($this->otherTeacher->user)->get(route('teacher.profile.photo'))->assertOk();
    }

    public function test_photo_removal_cannot_be_used_to_target_another_profile(): void
    {
        $photo = $this->photo();
        $this->putJson(route('teacher.profile.update'), $this->profileData(['remove_photo' => 1, 'teacher_id' => $this->otherTeacher->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertSame($photo->id, $this->teacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($photo->storage_key);
    }

    public function test_photo_and_user_update_roll_back_together_if_teacher_save_fails(): void
    {
        $old=$this->photo();$name=$this->teacherUser->fresh()->full_name;
        DB::statement("CREATE TRIGGER fail_own_profile BEFORE UPDATE ON teachers BEGIN SELECT RAISE(FAIL, 'Fixture failure'); END");
        $this->put(route('teacher.profile.update'),$this->profileData(['full_name'=>'Should Roll Back','photo'=>UploadedFile::fake()->image('new.png')]))->assertStatus(500);
        $this->assertSame($name,$this->teacherUser->fresh()->full_name);$this->assertSame($old->id,$this->teacher->fresh()->photo_file_id);
        Storage::disk('local')->assertExists($old->storage_key);$this->assertCount(1,Storage::disk('local')->allFiles('teacher-photos'));$this->assertDatabaseCount('file_assets',1);
    }

    public function test_unprepared_or_inactive_teacher_profile_has_helpful_read_only_state(): void
    {
        $user=$this->account('teacher','Belum disiapkan');$this->actingAs($user)->get(route('teacher.profile.show'))->assertOk()->assertSee('profil Teacher belum disiapkan');
        $this->get(route('teacher.profile.edit'))->assertForbidden();$this->get(route('teacher.settings.index'))->assertOk();
        $this->teacher->update(['is_active'=>false]);$this->actingAs($this->teacherUser)->get(route('teacher.profile.show'))->assertOk()->assertSee('Profil Teacher nonaktif');
        $this->putJson(route('teacher.profile.update'),$this->profileData())->assertForbidden();
    }

    public function test_revoked_account_is_checked_again_before_saving(): void
    {
        $data=$this->profileData();$this->actingAs($this->teacherUser);User::whereKey($this->teacherUser->id)->update(['is_active'=>false]);
        $this->putJson(route('teacher.profile.update'),$data)->assertForbidden();
        $this->putJson(route('teacher.settings.password'),$this->passwordData())->assertForbidden();
    }

    public static function personalRoles(): array { return [['teacher'],['student']]; }

    #[DataProvider('personalRoles')]
    public function test_settings_and_password_change_work_for_teacher_and_parent(string $role): void
    {
        $user=$role==='teacher'?$this->teacherUser:$this->parent;$this->actingAs($user);
        $this->get(route($role.'.settings.index'))->assertOk()->assertSee('data-confirm-form',false)->assertDontSee($user->password_hash)
            ->assertSee('Kembali ke dashboard')->assertSee($role==='student'?'Siswa Utama':'Teacher');
        $this->put(route($role.'.settings.password'),$this->passwordData())->assertRedirect(route('login'))
            ->assertSessionHas('account_success');$this->assertGuest();
        $this->assertTrue(Hash::check('Password456!',$user->fresh()->password_hash));$this->assertFalse(Hash::check('Password123!',$user->fresh()->password_hash));
        $this->get(route('login'))->assertOk()->assertSee('Kata sandi berhasil diubah')->assertDontSee('Password456!');
        $this->post(route('login.process'),['username'=>$user->username,'password'=>'Password123!'])->assertSessionHasErrors('username');
        $this->post(route('login.process'),['username'=>$user->username,'password'=>'Password456!'])->assertRedirect(route($role.'.dashboard'));
    }

    public function test_incorrect_old_password_and_failed_confirmation_do_not_change_password_or_flash_secrets(): void
    {
        $hash=$this->teacherUser->password_hash;$this->actingAs($this->teacherUser);
        $this->from(route('teacher.settings.index'))->put(route('teacher.settings.password'),$this->passwordData(['current_password'=>'WrongSecret123!']))
            ->assertRedirect(route('teacher.settings.index'))->assertSessionHasErrors('current_password');
        $this->assertSame($hash,$this->teacherUser->fresh()->password_hash);
        $old=session('_old_input',[]);foreach(['current_password','password','password_confirmation'] as $field){$this->assertArrayNotHasKey($field,$old);}
        $this->get(route('teacher.settings.index'))->assertOk()->assertDontSee('WrongSecret123!')->assertDontSee('Password456!');
        $this->putJson(route('teacher.settings.password'),$this->passwordData(['password_confirmation'=>'Different123!']))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public static function invalidPasswords(): array
    {
        return [[['password'=>'short1','password_confirmation'=>'short1'],'password'],[['password'=>'onlyletters','password_confirmation'=>'onlyletters'],'password'],
            [['password'=>'123456789','password_confirmation'=>'123456789'],'password'],[['password'=>['array']],'password'],[['current_password'=>['array']],'current_password'],
            [['password'=>str_repeat('é',36).'1a','password_confirmation'=>str_repeat('é',36).'1a'],'password'],
            [['password'=>'Password123!','password_confirmation'=>'Password123!'],'password']];
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_values_are_rejected_without_server_errors(array $extra,string $field): void
    {
        $hash=$this->teacherUser->password_hash;$this->actingAs($this->teacherUser)->putJson(route('teacher.settings.password'),$this->passwordData($extra))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($hash,$this->teacherUser->fresh()->password_hash);
    }

    public function test_password_change_cannot_target_another_user_or_role(): void
    {
        $other=$this->otherTeacher->user->password_hash;
        $this->actingAs($this->teacherUser)->putJson(route('teacher.settings.password'),$this->passwordData(['user_id'=>$this->otherTeacher->user_id]))->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->putJson(route('student.settings.password'),$this->passwordData())->assertForbidden();
        $this->assertSame($other,$this->otherTeacher->user->fresh()->password_hash);
    }

    public function test_existing_session_with_old_password_hash_is_rejected(): void
    {
        $hash=$this->teacherUser->password_hash;$this->teacherUser->update(['password_hash'=>Hash::make('ChangedByAdmin123!')]);
        $this->actingAs($this->teacherUser)->withSession(['password_hash_web'=>$hash])->get(route('teacher.profile.show'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_profile_output_escapes_user_content(): void
    {
        $this->teacherUser->update(['full_name'=>'<script>alert(1)</script>']);
        $this->actingAs($this->teacherUser)->get(route('teacher.profile.show'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
    }
}
