<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicContentSectionStoreRequest;
use App\Models\FileAsset;
use App\Services\PublicContentSectionService;
use Illuminate\Http\JsonResponse;

class PublicContentSectionController extends Controller
{
    public function __construct(
        private PublicContentSectionService $service
    ) {}

    public function store(
        PublicContentSectionStoreRequest $request
    ): JsonResponse {
        $section = $this->service->create(
            $request->validated()
        );

        return response()->json($section, 201);
    }

    public function index(): JsonResponse
    {
        return response()->json(
            $this->service->getAll()
        );
    }

    public function published(): JsonResponse
    {
        return response()->json(
            $this->service->getPublished()
        );
    }

    public function show(string $id): JsonResponse
    {
        $section = $this->service->find($id);

        return response()->json($section);
    }

    public function byImageFile(
        string $imageFileId
    ): JsonResponse {
        $fileAsset = FileAsset::query()->findOrFail(
            $imageFileId
        );

        return response()->json(
            $this->service->getByImageFile($fileAsset)
        );
    }

    public function update(
        PublicContentSectionStoreRequest $request,
        string $id
    ): JsonResponse {
        $section = $this->service->update(
            $id,
            $request->validated()
        );

        return response()->json($section);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'message' => 'Public content section deleted successfully.',
        ]);
    }
}
