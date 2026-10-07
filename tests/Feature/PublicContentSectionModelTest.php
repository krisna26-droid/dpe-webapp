<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\PublicContentSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicContentSectionModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'public/test/' . Str::uuid() . '.jpg',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256_hex' => hash('sha256', Str::uuid()->toString()),
        ]);
    }

    private function makeSection(
        ?string $imageFileId = null
    ): PublicContentSection {
        return PublicContentSection::query()->create([
            'id' => (string) Str::uuid(),
            'section_key' => 'section_' . Str::lower(Str::random(8)),
            'title' => 'Test Section',
            'body' => 'Test section body.',
            'image_file_id' => $imageFileId,
            'display_order' => 1,
            'is_published' => false,
        ]);
    }

    public function test_public_content_section_uses_correct_table(): void
    {
        $section = new PublicContentSection();

        $this->assertSame(
            'public_content_sections',
            $section->getTable()
        );
    }

    public function test_public_content_section_uses_string_non_incrementing_primary_key(): void
    {
        $section = new PublicContentSection();

        $this->assertSame(
            'id',
            $section->getKeyName()
        );

        $this->assertFalse(
            $section->getIncrementing()
        );

        $this->assertSame(
            'string',
            $section->getKeyType()
        );
    }

    public function test_public_content_section_does_not_use_timestamps(): void
    {
        $section = new PublicContentSection();

        $this->assertFalse(
            $section->usesTimestamps()
        );
    }

    public function test_public_content_section_can_be_created_without_image(): void
    {
        $section = $this->makeSection();

        $this->assertDatabaseHas(
            'public_content_sections',
            [
                'id' => $section->id,
                'section_key' => $section->section_key,
                'title' => 'Test Section',
                'body' => 'Test section body.',
                'image_file_id' => null,
                'display_order' => 1,
                'is_published' => 0,
            ]
        );
    }

    public function test_public_content_section_can_reference_file_asset(): void
    {
        $fileAsset = $this->makeFileAsset();

        $section = $this->makeSection(
            $fileAsset->id
        );

        $this->assertSame(
            $fileAsset->id,
            $section->image_file_id
        );
    }

    public function test_public_content_section_belongs_to_image_file(): void
    {
        $fileAsset = $this->makeFileAsset();

        $section = $this->makeSection(
            $fileAsset->id
        );

        $this->assertSame(
            $fileAsset->id,
            $section->imageFile->id
        );
    }

    public function test_file_asset_has_many_public_content_sections(): void
    {
        $fileAsset = $this->makeFileAsset();

        $first = $this->makeSection(
            $fileAsset->id
        );

        $second = $this->makeSection(
            $fileAsset->id
        );

        $this->assertCount(
            2,
            $fileAsset->publicContentSections
        );

        $this->assertTrue(
            $fileAsset->publicContentSections
                ->contains('id', $first->id)
        );

        $this->assertTrue(
            $fileAsset->publicContentSections
                ->contains('id', $second->id)
        );
    }

    public function test_body_can_be_null(): void
    {
        $section = PublicContentSection::query()->create([
            'id' => (string) Str::uuid(),
            'section_key' => 'nullable_body_' . Str::lower(Str::random(8)),
            'title' => 'Section Without Body',
            'body' => null,
            'image_file_id' => null,
            'display_order' => 0,
            'is_published' => false,
        ]);

        $this->assertNull(
            $section->body
        );
    }

    public function test_is_published_is_cast_to_boolean(): void
    {
        $section = $this->makeSection();

        $section->is_published = true;
        $section->save();
        $section->refresh();

        $this->assertIsBool(
            $section->is_published
        );

        $this->assertTrue(
            $section->is_published
        );
    }

    public function test_display_order_is_cast_to_integer(): void
    {
        $section = $this->makeSection();

        $this->assertIsInt(
            $section->display_order
        );
    }

    public function test_section_key_must_be_unique(): void
    {
        $sectionKey = 'unique_section_' . Str::lower(Str::random(8));

        PublicContentSection::query()->create([
            'id' => (string) Str::uuid(),
            'section_key' => $sectionKey,
            'title' => 'First Section',
            'body' => null,
            'image_file_id' => null,
            'display_order' => 0,
            'is_published' => false,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        PublicContentSection::query()->create([
            'id' => (string) Str::uuid(),
            'section_key' => $sectionKey,
            'title' => 'Duplicate Section',
            'body' => null,
            'image_file_id' => null,
            'display_order' => 1,
            'is_published' => false,
        ]);
    }
}
