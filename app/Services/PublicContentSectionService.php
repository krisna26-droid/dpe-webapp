<?php

namespace App\Services;

use App\Models\FileAsset;
use App\Models\PublicContentSection;
use Illuminate\Support\Facades\DB;

class PublicContentSectionService
{
    public function create(array $data): PublicContentSection
    {
        return DB::transaction(function () use ($data) {
            return PublicContentSection::query()->create([
                'id' => $data['id'],
                'section_key' => $data['section_key'],
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'image_file_id' => $data['image_file_id'] ?? null,
                'display_order' => $data['display_order'],
                'is_published' => $data['is_published'] ?? false,
            ]);
        });
    }

    public function find(string $id): PublicContentSection
    {
        return PublicContentSection::query()
            ->findOrFail($id);
    }

    public function getAll()
    {
        return PublicContentSection::query()
            ->orderBy('display_order')
            ->get();
    }

    public function getPublished()
    {
        return PublicContentSection::query()
            ->where('is_published', true)
            ->orderBy('display_order')
            ->get();
    }

    public function getByImageFile(FileAsset $fileAsset)
    {
        return PublicContentSection::query()
            ->where('image_file_id', $fileAsset->id)
            ->orderBy('display_order')
            ->get();
    }

    public function update(
        string $id,
        array $data
    ): PublicContentSection {
        return DB::transaction(function () use ($id, $data) {
            $section = $this->find($id);

            $section->update([
                'section_key' => $data['section_key'],
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'image_file_id' => $data['image_file_id'] ?? null,
                'display_order' => $data['display_order'],
                'is_published' => $data['is_published'] ?? false,
            ]);

            return $section->refresh();
        });
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            $section = $this->find($id);

            $section->delete();
        });
    }
}
