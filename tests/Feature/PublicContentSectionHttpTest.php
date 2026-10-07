<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\PublicContentSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicContentSectionHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'public/test/' . Str::uuid() . '.jpg',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256_hex' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
        ]);
    }

    private function makeSection(
        ?string $imageFileId = null,
        int $displayOrder = 1,
        bool $isPublished = false
    ): PublicContentSection {
        return PublicContentSection::query()->create([
            'id' => (string) Str::uuid(),
            'section_key' =>
            'section_' . Str::lower(Str::random(8)),
            'title' => 'Test Section',
            'body' => 'Test body.',
            'image_file_id' => $imageFileId,
            'display_order' => $displayOrder,
            'is_published' => $isPublished,
        ]);
    }

    public function test_guest_cannot_access_public_content_sections(): void
    {
        $response = $this->getJson(
            '/superadmin/public-content-sections'
        );

        $response->assertUnauthorized();
    }

    public function test_non_superadmin_cannot_access_public_content_sections(): void
    {
        $user = $this->makeUser('teacher');

        $response = $this
            ->actingAs($user)
            ->getJson('/superadmin/public-content-sections');

        $response->assertForbidden();
    }

    public function test_superadmin_can_get_all_sections(): void
    {
        $user = $this->makeUser('superadmin');

        $this->makeSection(null, 2);
        $this->makeSection(null, 1);

        $response = $this
            ->actingAs($user)
            ->getJson('/superadmin/public-content-sections');

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_superadmin_can_store_section(): void
    {
        $user = $this->makeUser('superadmin');

        $id = (string) Str::uuid();

        $response = $this
            ->actingAs($user)
            ->postJson('/superadmin/public-content-sections', [
                'id' => $id,
                'section_key' =>
                'about_' . Str::lower(Str::random(8)),
                'title' => 'About Us',
                'body' => 'About body.',
                'image_file_id' => null,
                'display_order' => 1,
                'is_published' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJson([
                'id' => $id,
                'title' => 'About Us',
            ]);

        $this->assertDatabaseHas(
            'public_content_sections',
            [
                'id' => $id,
                'title' => 'About Us',
            ]
        );
    }

    public function test_superadmin_can_get_published_sections(): void
    {
        $user = $this->makeUser('superadmin');

        $this->makeSection(null, 1, false);
        $published = $this->makeSection(null, 2, true);

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/superadmin/public-content-sections/published'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath(
                '0.id',
                $published->id
            );
    }

    public function test_superadmin_can_get_section_by_id(): void
    {
        $user = $this->makeUser('superadmin');

        $section = $this->makeSection();

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/superadmin/public-content-sections/' .
                    $section->id
            );

        $response
            ->assertOk()
            ->assertJson([
                'id' => $section->id,
            ]);
    }

    public function test_superadmin_can_get_sections_by_image_file(): void
    {
        $user = $this->makeUser('superadmin');

        $fileAsset = $this->makeFileAsset();

        $this->makeSection($fileAsset->id, 1);
        $this->makeSection($fileAsset->id, 2);
        $this->makeSection();

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/superadmin/file-assets/' .
                    $fileAsset->id .
                    '/public-content-sections'
            );

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_superadmin_can_update_section(): void
    {
        $user = $this->makeUser('superadmin');

        $section = $this->makeSection();

        $updatedKey =
            'updated_' . Str::lower(Str::random(8));

        $response = $this
            ->actingAs($user)
            ->putJson(
                '/superadmin/public-content-sections/' .
                    $section->id,
                [
                    'id' => $section->id,
                    'section_key' => $updatedKey,
                    'title' => 'Updated Section',
                    'body' => 'Updated body.',
                    'image_file_id' => null,
                    'display_order' => 5,
                    'is_published' => true,
                ]
            );

        $response
            ->assertOk()
            ->assertJson([
                'id' => $section->id,
                'title' => 'Updated Section',
                'section_key' => $updatedKey,
            ]);

        $this->assertDatabaseHas(
            'public_content_sections',
            [
                'id' => $section->id,
                'title' => 'Updated Section',
                'display_order' => 5,
                'is_published' => 1,
            ]
        );
    }

    public function test_superadmin_can_delete_section(): void
    {
        $user = $this->makeUser('superadmin');

        $section = $this->makeSection();

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                '/superadmin/public-content-sections/' .
                    $section->id
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' =>
                'Public content section deleted successfully.',
            ]);

        $this->assertDatabaseMissing(
            'public_content_sections',
            [
                'id' => $section->id,
            ]
        );
    }
}
