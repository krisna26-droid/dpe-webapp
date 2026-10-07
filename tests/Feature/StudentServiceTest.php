<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\Student;
use App\Models\User;
use App\Services\FileAssetService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->service = app(StudentService::class);
    }

    private function makePortalUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'student_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test Student',
            'role_code' => 'student',
            'is_active' => true,
        ]);
    }

    private function makeStudent(
        ?User $portalUser = null,
        ?string $photoFileId = null
    ): Student {
        $portalUser ??= $this->makePortalUser();

        return Student::query()->create([
            'id' => (string) Str::uuid(),
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Existing Student',
            'school_name' => 'Test School',
            'grade_name' => 'Grade 5',
            'began_on' => '2026-01-01',
            'status' => 'active',
            'special_notes_internal' => 'Initial note',
            'photo_file_id' => $photoFileId,
        ]);
    }

    private function makeFileAsset(
        string $directory = 'student-photos'
    ): FileAsset {
        $id = (string) Str::uuid();

        $storageKey = $directory . '/' . $id . '.jpg';

        Storage::disk('local')->put(
            $storageKey,
            'fake-image-content'
        );

        return FileAsset::query()->create([
            'id' => $id,
            'storage_key' => $storageKey,
            'original_name' => 'student-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen('fake-image-content'),
            'sha256_hex' => hash(
                'sha256',
                'fake-image-content'
            ),
        ]);
    }

    public function test_create_student_without_photo(): void
    {
        $portalUser = $this->makePortalUser();

        $student = $this->service->create([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'New Student',
            'school_name' => 'New School',
            'grade_name' => 'Grade 6',
            'began_on' => '2026-02-01',
            'special_notes_internal' => 'Test note',
        ]);

        $this->assertInstanceOf(
            Student::class,
            $student
        );

        $this->assertNotEmpty($student->id);

        $this->assertSame(
            $portalUser->id,
            $student->portal_user_id
        );

        $this->assertSame(
            'New Student',
            $student->full_name
        );

        $this->assertSame(
            'active',
            $student->status
        );

        $this->assertNull(
            $student->photo_file_id
        );

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'portal_user_id' => $portalUser->id,
            'full_name' => 'New Student',
            'status' => 'active',
            'photo_file_id' => null,
        ]);
    }

    public function test_create_student_with_photo_creates_file_asset(): void
    {
        $portalUser = $this->makePortalUser();

        $photo = UploadedFile::fake()->image(
            'student-photo.jpg',
            100,
            100
        );

        $student = $this->service->create(
            [
                'portal_user_id' => $portalUser->id,
                'full_name' => 'Student With Photo',
                'school_name' => 'Test School',
                'grade_name' => 'Grade 6',
                'began_on' => '2026-02-01',
            ],
            $photo
        );

        $this->assertNotNull(
            $student->photo_file_id
        );

        $fileAsset = FileAsset::query()->find(
            $student->photo_file_id
        );

        $this->assertNotNull($fileAsset);

        $this->assertSame(
            'student-photos',
            dirname($fileAsset->storage_key)
        );

        $this->assertSame(
            'student-photo.jpg',
            $fileAsset->original_name
        );

        $this->assertSame(
            'image/jpeg',
            $fileAsset->mime_type
        );

        $this->assertGreaterThan(
            0,
            $fileAsset->size_bytes
        );

        Storage::disk('local')->assertExists(
            $fileAsset->storage_key
        );
    }

    public function test_create_student_generates_uuid(): void
    {
        $portalUser = $this->makePortalUser();

        $student = $this->service->create([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'UUID Student',
            'began_on' => '2026-02-01',
        ]);

        $this->assertTrue(
            Str::isUuid($student->id)
        );
    }

    public function test_create_student_sets_status_to_active(): void
    {
        $portalUser = $this->makePortalUser();

        $student = $this->service->create([
            'portal_user_id' => $portalUser->id,
            'full_name' => 'Active Student',
            'began_on' => '2026-02-01',
        ]);

        $this->assertSame(
            'active',
            $student->status
        );
    }

    public function test_update_student_without_new_photo_keeps_existing_photo(): void
    {
        $portalUser = $this->makePortalUser();
        $fileAsset = $this->makeFileAsset();

        $student = $this->makeStudent(
            $portalUser,
            $fileAsset->id
        );

        $updatedStudent = $this->service->update(
            $student,
            [
                'portal_user_id' => $portalUser->id,
                'full_name' => 'Updated Student',
                'school_name' => 'Updated School',
                'grade_name' => 'Grade 6',
                'began_on' => '2026-03-01',
                'special_notes_internal' => 'Updated note',
            ]
        );

        $this->assertSame(
            'Updated Student',
            $updatedStudent->full_name
        );

        $this->assertSame(
            'Updated School',
            $updatedStudent->school_name
        );

        $this->assertSame(
            $fileAsset->id,
            $updatedStudent->photo_file_id
        );

        $this->assertDatabaseHas('file_assets', [
            'id' => $fileAsset->id,
        ]);

        Storage::disk('local')->assertExists(
            $fileAsset->storage_key
        );
    }

    public function test_update_student_with_new_photo_replaces_photo(): void
    {
        $portalUser = $this->makePortalUser();

        $oldFile = $this->makeFileAsset();

        $student = $this->makeStudent(
            $portalUser,
            $oldFile->id
        );

        $newPhoto = UploadedFile::fake()->image(
            'new-student-photo.jpg',
            120,
            120
        );

        $updatedStudent = $this->service->update(
            $student,
            [
                'portal_user_id' => $portalUser->id,
                'full_name' => 'Student With New Photo',
                'school_name' => 'Updated School',
                'grade_name' => 'Grade 6',
                'began_on' => '2026-03-01',
                'special_notes_internal' => 'Updated with new photo',
            ],
            $newPhoto
        );

        $this->assertNotNull(
            $updatedStudent->photo_file_id
        );

        $this->assertNotSame(
            $oldFile->id,
            $updatedStudent->photo_file_id
        );

        $newFile = FileAsset::query()->find(
            $updatedStudent->photo_file_id
        );

        $this->assertNotNull($newFile);

        Storage::disk('local')->assertExists(
            $newFile->storage_key
        );

        $this->assertDatabaseMissing('file_assets', [
            'id' => $oldFile->id,
        ]);

        Storage::disk('local')->assertMissing(
            $oldFile->storage_key
        );
    }

    public function test_update_student_without_photo_does_not_create_new_file_asset(): void
    {
        $portalUser = $this->makePortalUser();

        $oldFile = $this->makeFileAsset();

        $student = $this->makeStudent(
            $portalUser,
            $oldFile->id
        );

        $fileAssetCountBefore = FileAsset::query()->count();

        $updatedStudent = $this->service->update(
            $student,
            [
                'portal_user_id' => $portalUser->id,
                'full_name' => 'No New Photo',
                'school_name' => 'Updated School',
                'grade_name' => 'Grade 6',
                'began_on' => '2026-03-01',
            ]
        );

        $this->assertSame(
            $fileAssetCountBefore,
            FileAsset::query()->count()
        );

        $this->assertSame(
            $oldFile->id,
            $updatedStudent->photo_file_id
        );
    }

    public function test_update_student_returns_fresh_student_with_relations(): void
    {
        $portalUser = $this->makePortalUser();

        $student = $this->makeStudent(
            $portalUser
        );

        $updatedStudent = $this->service->update(
            $student,
            [
                'portal_user_id' => $portalUser->id,
                'full_name' => 'Fresh Student',
                'school_name' => 'Fresh School',
                'grade_name' => 'Grade 7',
                'began_on' => '2026-04-01',
            ]
        );

        $this->assertTrue(
            $updatedStudent->relationLoaded('portalUser')
        );

        $this->assertTrue(
            $updatedStudent->relationLoaded('photoFile')
        );

        $this->assertInstanceOf(
            User::class,
            $updatedStudent->portalUser
        );
    }
}
