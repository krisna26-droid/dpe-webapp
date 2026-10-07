<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\PublicContentSection;
use App\Services\PublicContentSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicContentSectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PublicContentSectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PublicContentSectionService();
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
        return $this->service->create([
            'id' => (string) Str::uuid(),
            'section_key' => 'section_' . Str::lower(Str::random(8)),
            'title' => 'Test Section',
            'body' => 'Test body.',
            'image_file_id' => $imageFileId,
            'display_order' => $displayOrder,
            'is_published' => $isPublished,
        ]);
    }

    public function test_create_creates_section(): void
    {
        $id = (string) Str::uuid();

        $section = $this->service->create([
            'id' => $id,
            'section_key' => 'about_' . Str::lower(Str::random(8)),
            'title' => 'About Us',
            'body' => 'About section body.',
            'image_file_id' => null,
            'display_order' => 1,
            'is_published' => true,
        ]);

        $this->assertSame($id, $section->id);
        $this->assertSame('About Us', $section->title);
        $this->assertTrue($section->is_published);

        $this->assertDatabaseHas(
            'public_content_sections',
            [
                'id' => $id,
                'title' => 'About Us',
                'is_published' => 1,
            ]
        );
    }

    public function test_create_allows_null_optional_fields(): void
    {
        $section = $this->service->create([
            'id' => (string) Str::uuid(),
            'section_key' => 'optional_' . Str::lower(Str::random(8)),
            'title' => 'Optional Section',
            'body' => null,
            'image_file_id' => null,
            'display_order' => 0,
            'is_published' => false,
        ]);

        $this->assertNull($section->body);
        $this->assertNull($section->image_file_id);
        $this->assertFalse($section->is_published);
    }

    public function test_find_returns_section(): void
    {
        $section = $this->makeSection();

        $result = $this->service->find($section->id);

        $this->assertTrue(
            $result->is($section)
        );
    }

    public function test_find_throws_exception_when_section_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->find(
            (string) Str::uuid()
        );
    }

    public function test_get_all_returns_sections_ordered_by_display_order(): void
    {
        $third = $this->makeSection(null, 3);
        $first = $this->makeSection(null, 1);
        $second = $this->makeSection(null, 2);

        $sections = $this->service->getAll();

        $this->assertCount(3, $sections);

        $this->assertSame(
            $first->id,
            $sections->get(0)->id
        );

        $this->assertSame(
            $second->id,
            $sections->get(1)->id
        );

        $this->assertSame(
            $third->id,
            $sections->get(2)->id
        );
    }

    public function test_get_published_returns_only_published_sections(): void
    {
        $publishedOne = $this->makeSection(null, 2, true);
        $this->makeSection(null, 1, false);
        $publishedTwo = $this->makeSection(null, 3, true);

        $sections = $this->service->getPublished();

        $this->assertCount(2, $sections);

        $this->assertSame(
            $publishedOne->id,
            $sections->get(0)->id
        );

        $this->assertSame(
            $publishedTwo->id,
            $sections->get(1)->id
        );
    }

    public function test_get_by_image_file_returns_matching_sections(): void
    {
        $fileAsset = $this->makeFileAsset();

        $first = $this->makeSection(
            $fileAsset->id,
            1
        );

        $second = $this->makeSection(
            $fileAsset->id,
            2
        );

        $this->makeSection(null, 3);

        $sections = $this->service->getByImageFile(
            $fileAsset
        );

        $this->assertCount(2, $sections);

        $this->assertSame(
            $first->id,
            $sections->get(0)->id
        );

        $this->assertSame(
            $second->id,
            $sections->get(1)->id
        );
    }

    public function test_update_updates_section(): void
    {
        $section = $this->makeSection();

        $result = $this->service->update(
            $section->id,
            [
                'section_key' => 'updated_' . Str::lower(Str::random(8)),
                'title' => 'Updated Section',
                'body' => 'Updated body.',
                'image_file_id' => null,
                'display_order' => 5,
                'is_published' => true,
            ]
        );

        $this->assertSame(
            'Updated Section',
            $result->title
        );

        $this->assertSame(
            'Updated body.',
            $result->body
        );

        $this->assertSame(
            5,
            $result->display_order
        );

        $this->assertTrue(
            $result->is_published
        );
    }

    public function test_update_can_set_nullable_fields_to_null(): void
    {
        $fileAsset = $this->makeFileAsset();

        $section = $this->makeSection(
            $fileAsset->id
        );

        $result = $this->service->update(
            $section->id,
            [
                'section_key' => 'nullable_' . Str::lower(Str::random(8)),
                'title' => 'Updated Section',
                'body' => null,
                'image_file_id' => null,
                'display_order' => 0,
                'is_published' => false,
            ]
        );

        $this->assertNull($result->body);
        $this->assertNull($result->image_file_id);
        $this->assertFalse($result->is_published);
    }

    public function test_delete_deletes_section(): void
    {
        $section = $this->makeSection();

        $this->assertDatabaseHas(
            'public_content_sections',
            [
                'id' => $section->id,
            ]
        );

        $this->service->delete($section->id);

        $this->assertDatabaseMissing(
            'public_content_sections',
            [
                'id' => $section->id,
            ]
        );
    }

    public function test_delete_throws_exception_when_section_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->delete(
            (string) Str::uuid()
        );
    }
}
