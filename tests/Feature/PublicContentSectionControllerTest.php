<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicContentSectionController;
use App\Models\FileAsset;
use App\Models\PublicContentSection;
use App\Services\PublicContentSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicContentSectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private PublicContentSectionController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicContentSectionController(
            new PublicContentSectionService()
        );
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
            'section_key' => 'section_' . Str::lower(Str::random(8)),
            'title' => 'Test Section',
            'body' => 'Test body.',
            'image_file_id' => $imageFileId,
            'display_order' => $displayOrder,
            'is_published' => $isPublished,
        ]);
    }

    private function makeRequest(
        array $data
    ): \App\Http\Requests\PublicContentSectionStoreRequest {
        $request = \App\Http\Requests\PublicContentSectionStoreRequest::create(
            '/public-content-sections',
            'POST',
            $data
        );

        $request->setContainer(app());
        $request->merge($data);
        $request->setValidator(Validator::make($data, $request->rules()));

        return $request;
    }

    public function test_store_returns_created_section(): void
    {
        $id = (string) Str::uuid();

        $request = $this->makeRequest([
            'id' => $id,
            'section_key' => 'about_' . Str::lower(Str::random(8)),
            'title' => 'About Us',
            'body' => 'About body.',
            'image_file_id' => null,
            'display_order' => 1,
            'is_published' => true,
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

        $this->assertSame(
            $id,
            $response->getData()->id
        );

        $this->assertDatabaseHas(
            'public_content_sections',
            [
                'id' => $id,
                'title' => 'About Us',
            ]
        );
    }

    public function test_index_returns_all_sections(): void
    {
        $this->makeSection(null, 2);
        $this->makeSection(null, 1);

        $response = $this->controller->index();

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            2,
            $response->getData()
        );
    }

    public function test_published_returns_only_published_sections(): void
    {
        $this->makeSection(null, 1, false);
        $published = $this->makeSection(
            null,
            2,
            true
        );

        $response = $this->controller->published();

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            1,
            $response->getData()
        );

        $this->assertSame(
            $published->id,
            $response->getData()[0]->id
        );
    }

    public function test_show_returns_section(): void
    {
        $section = $this->makeSection();

        $response = $this->controller->show(
            $section->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            $section->id,
            $response->getData()->id
        );
    }

    public function test_by_image_file_returns_matching_sections(): void
    {
        $fileAsset = $this->makeFileAsset();

        $this->makeSection(
            $fileAsset->id,
            1
        );

        $this->makeSection(
            $fileAsset->id,
            2
        );

        $this->makeSection();

        $response = $this->controller->byImageFile(
            $fileAsset->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            2,
            $response->getData()
        );
    }

    public function test_update_returns_updated_section(): void
    {
        $section = $this->makeSection();

        $request = $this->makeRequest([
            'id' => $section->id,
            'section_key' => 'updated_' . Str::lower(Str::random(8)),
            'title' => 'Updated Section',
            'body' => 'Updated body.',
            'image_file_id' => null,
            'display_order' => 5,
            'is_published' => true,
        ]);

        $response = $this->controller->update(
            $request,
            $section->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            'Updated Section',
            $response->getData()->title
        );

        $this->assertTrue(
            $response->getData()->is_published
        );
    }

    public function test_destroy_deletes_section(): void
    {
        $section = $this->makeSection();

        $response = $this->controller->destroy(
            $section->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            'Public content section deleted successfully.',
            $response->getData()->message
        );

        $this->assertDatabaseMissing(
            'public_content_sections',
            [
                'id' => $section->id,
            ]
        );
    }

    public function test_show_throws_exception_when_section_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->controller->show(
            (string) Str::uuid()
        );
    }
}
