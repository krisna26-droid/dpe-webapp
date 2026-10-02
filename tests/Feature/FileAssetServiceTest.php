<?php

namespace Tests\Feature;

use App\Models\FileAsset;
use App\Services\FileAssetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;

class FileAssetServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');


        Schema::connection('sqlite')->create(
            'file_assets',
            function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('storage_key')->unique();
                $table->text('original_name');
                $table->string('mime_type');
                $table->bigInteger('size_bytes');
                $table->string('sha256_hex')->nullable();
                $table->dateTime('created_at')->nullable();
            }
        );
    }


    protected function tearDown(): void
    {
        Schema::connection('sqlite')
            ->dropIfExists('file_assets');

        parent::tearDown();
    }


    public function test_file_asset_can_be_stored(): void
    {
        Storage::fake('local');


        $file = UploadedFile::fake()
            ->image('teacher.jpg');


        $service = app(FileAssetService::class);


        $asset = $service->store(
            $file,
            'teacher-photos'
        );


        $this->assertDatabaseHas(
            'file_assets',
            [
                'id' => $asset->id,
                'original_name' => 'teacher.jpg',
                'mime_type' => 'image/jpeg',
            ]
        );


        Storage::disk('local')
            ->assertExists(
                $asset->storage_key
            );
    }
    public function test_file_asset_can_be_deleted(): void
    {
        Storage::fake('local');


        $file = UploadedFile::fake()
            ->image('teacher.jpg');


        $service = app(FileAssetService::class);


        $asset = $service->store(
            $file,
            'teacher-photos'
        );


        Storage::disk('local')
            ->assertExists($asset->storage_key);


        $service->delete($asset);


        Storage::disk('local')
            ->assertMissing($asset->storage_key);


        $this->assertDatabaseMissing(
            'file_assets',
            [
                'id' => $asset->id,
            ]
        );
    }

    public function test_metadata_is_preserved_when_physical_file_deletion_fails(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('teacher.jpg');

        $service = app(FileAssetService::class);

        $asset = $service->store($file, 'teacher-photos');

        $storageKey = $asset->storage_key;

        Storage::disk('local')->assertExists($storageKey);

        $disk = Mockery::mock(
            Storage::disk('local')
        );

        $disk->shouldReceive('exists')
            ->with($storageKey)
            ->once()
            ->andReturn(true);

        $disk->shouldReceive('delete')
            ->with($storageKey)
            ->once()
            ->andReturn(false);

        $disk->shouldReceive('exists')
            ->with($storageKey)
            ->once()
            ->andReturn(true);

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        try {
            $service->delete($asset);
            $this->fail('Seharusnya penghapusan file menghasilkan exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'File fisik gagal dihapus: ' . $storageKey,
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('file_assets', [
            'id' => $asset->id,
            'storage_key' => $storageKey,
        ]);
    }
}