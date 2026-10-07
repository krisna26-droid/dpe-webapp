<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Models\PaymentProof;
use App\Models\PaymentRecapRun;
use App\Models\PublicContentSection;
use App\Models\QuarterlyReportFile;
use App\Models\SystemSetting;
use App\Models\TeacherMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileAssetModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeFileAsset(
        ?string $storageKey = null
    ): FileAsset {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => $storageKey
                ?? 'test/' . Str::uuid() . '.png',
            'original_name' => 'test-file.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'sha256_hex' => hash(
                'sha256',
                'test-file'
            ),
        ]);
    }

    public function test_uses_correct_table(): void
    {
        $model = new FileAsset();

        $this->assertSame(
            'file_assets',
            $model->getTable()
        );
    }

    public function test_uses_string_primary_key_without_auto_increment(): void
    {
        $model = new FileAsset();

        $this->assertSame(
            'string',
            $model->getKeyType()
        );

        $this->assertFalse(
            $model->incrementing
        );
    }

    public function test_uses_created_at_without_updated_at(): void
    {
        $model = new FileAsset();

        $this->assertSame(
            'created_at',
            $model->getCreatedAtColumn()
        );

        $this->assertNull(
            $model->getUpdatedAtColumn()
        );
    }

    public function test_has_expected_fillable_columns(): void
    {
        $model = new FileAsset();

        $this->assertSame([
            'id',
            'storage_key',
            'original_name',
            'mime_type',
            'size_bytes',
            'sha256_hex',
        ], $model->getFillable());
    }

    public function test_casts_expected_attributes(): void
    {
        $model = new FileAsset();

        $casts = $model->getCasts();

        $this->assertSame(
            'integer',
            $casts['size_bytes']
        );

        $this->assertSame(
            'datetime',
            $casts['created_at']
        );
    }

    public function test_can_create_file_asset(): void
    {
        $asset = $this->makeFileAsset();

        $this->assertTrue(
            Str::isUuid($asset->id)
        );

        $this->assertSame(
            'test-file.png',
            $asset->original_name
        );

        $this->assertSame(
            'image/png',
            $asset->mime_type
        );

        $this->assertSame(
            1024,
            $asset->size_bytes
        );

        $this->assertDatabaseHas(
            'file_assets',
            [
                'id' => $asset->id,
                'storage_key' => $asset->storage_key,
            ]
        );
    }

    public function test_storage_key_must_be_unique(): void
    {
        $storageKey = 'test/duplicate.png';

        $this->makeFileAsset($storageKey);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $this->makeFileAsset($storageKey);
    }

    public function test_sha256_hex_can_be_null(): void
    {
        $asset = FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'test/no-hash.png',
            'original_name' => 'no-hash.png',
            'mime_type' => 'image/png',
            'size_bytes' => 0,
            'sha256_hex' => null,
        ]);

        $this->assertNull(
            $asset->sha256_hex
        );
    }
}
