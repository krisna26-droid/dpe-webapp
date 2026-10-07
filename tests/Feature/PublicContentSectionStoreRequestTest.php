<?php

namespace Tests\Feature;

use App\Http\Requests\PublicContentSectionStoreRequest;
use App\Models\FileAsset;
use App\Models\PublicContentSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicContentSectionStoreRequestTest extends TestCase
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
            'sha256_hex' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
        ]);
    }

    private function validData(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'section_key' => 'section_' . Str::lower(Str::random(8)),
            'title' => 'Test Section',
            'body' => 'Test body.',
            'image_file_id' => null,
            'display_order' => 0,
            'is_published' => false,
        ];
    }

    private function validator(array $data)
    {
        $request = new PublicContentSectionStoreRequest();

        return Validator::make(
            $data,
            $request->rules()
        );
    }

    public function test_valid_data_passes_validation(): void
    {
        $validator = $this->validator(
            $this->validData()
        );

        $this->assertFalse(
            $validator->fails()
        );
    }

    public function test_id_is_required(): void
    {
        $data = $this->validData();

        unset($data['id']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'id',
            $validator->errors()->toArray()
        );
    }

    public function test_id_must_be_uuid(): void
    {
        $data = $this->validData();
        $data['id'] = 'invalid-id';

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'id',
            $validator->errors()->toArray()
        );
    }

    public function test_id_must_be_unique(): void
    {
        $existingId = (string) Str::uuid();

        PublicContentSection::query()->create([
            'id' => $existingId,
            'section_key' => 'existing_' . Str::lower(Str::random(8)),
            'title' => 'Existing Section',
            'body' => null,
            'image_file_id' => null,
            'display_order' => 0,
            'is_published' => false,
        ]);

        $data = $this->validData();
        $data['id'] = $existingId;

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'id',
            $validator->errors()->toArray()
        );
    }

    public function test_section_key_is_required(): void
    {
        $data = $this->validData();

        unset($data['section_key']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'section_key',
            $validator->errors()->toArray()
        );
    }

    public function test_section_key_must_be_unique(): void
    {
        $sectionKey = 'existing_' . Str::lower(Str::random(8));

        PublicContentSection::query()->create([
            'id' => (string) Str::uuid(),
            'section_key' => $sectionKey,
            'title' => 'Existing Section',
            'body' => null,
            'image_file_id' => null,
            'display_order' => 0,
            'is_published' => false,
        ]);

        $data = $this->validData();
        $data['section_key'] = $sectionKey;

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'section_key',
            $validator->errors()->toArray()
        );
    }

    public function test_title_is_required(): void
    {
        $data = $this->validData();

        unset($data['title']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'title',
            $validator->errors()->toArray()
        );
    }

    public function test_body_is_nullable(): void
    {
        $data = $this->validData();
        $data['body'] = null;

        $validator = $this->validator($data);

        $this->assertFalse($validator->fails());
    }

    public function test_image_file_id_is_nullable(): void
    {
        $data = $this->validData();
        $data['image_file_id'] = null;

        $validator = $this->validator($data);

        $this->assertFalse($validator->fails());
    }

    public function test_image_file_id_must_exist_when_provided(): void
    {
        $data = $this->validData();
        $data['image_file_id'] = (string) Str::uuid();

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'image_file_id',
            $validator->errors()->toArray()
        );
    }

    public function test_display_order_is_required(): void
    {
        $data = $this->validData();

        unset($data['display_order']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'display_order',
            $validator->errors()->toArray()
        );
    }

    public function test_display_order_must_be_integer(): void
    {
        $data = $this->validData();
        $data['display_order'] = 'not-an-integer';

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'display_order',
            $validator->errors()->toArray()
        );
    }

    public function test_display_order_cannot_be_negative(): void
    {
        $data = $this->validData();
        $data['display_order'] = -1;

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'display_order',
            $validator->errors()->toArray()
        );
    }

    public function test_is_published_is_required(): void
    {
        $data = $this->validData();

        unset($data['is_published']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'is_published',
            $validator->errors()->toArray()
        );
    }

    public function test_is_published_must_be_boolean(): void
    {
        $data = $this->validData();
        $data['is_published'] = 'not-boolean';

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'is_published',
            $validator->errors()->toArray()
        );
    }

    public function test_string_fields_must_be_strings(): void
    {
        $data = $this->validData();

        $data['section_key'] = ['section'];
        $data['title'] = ['title'];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'section_key',
            $errors
        );

        $this->assertArrayHasKey(
            'title',
            $errors
        );
    }
}
