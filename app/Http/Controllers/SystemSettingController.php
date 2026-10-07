<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSettingUpdateRequest;
use App\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;

class SystemSettingController extends Controller
{
    public function __construct(
        private SystemSettingService $service
    ) {}

    public function show(): JsonResponse
    {
        $setting = $this->service->get();

        return response()->json($setting);
    }

    public function update(
        SystemSettingUpdateRequest $request
    ): JsonResponse {
        $setting = $this->service->update(
            $request->validated()
        );

        return response()->json($setting);
    }
}
