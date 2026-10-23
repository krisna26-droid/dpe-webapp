<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FileAsset;
use App\Services\PublicWebsiteService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicImageController extends Controller
{
    public function __invoke(string $id, PublicWebsiteService $website): BinaryFileResponse
    {
        $file = FileAsset::query()->whereKey($id)
            ->whereHas('publicContentSections', fn ($query) => $query->where('is_published', true))->firstOrFail();
        $path = $website->imagePath($file);
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
