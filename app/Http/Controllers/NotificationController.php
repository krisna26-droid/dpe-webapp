<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationStoreRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $service
    ) {}

    public function store(
        NotificationStoreRequest $request
    ): JsonResponse {
        $notification = $this->service->create(
            $request->validated()
        );

        return response()->json($notification, 201);
    }

    public function show(string $id): JsonResponse
    {
        $notification = $this->service->find($id);

        return response()->json($notification);
    }

    public function byUser(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        return response()->json(
            $this->service->getByUser($user)
        );
    }

    public function update(
        NotificationStoreRequest $request,
        string $id
    ): JsonResponse {
        $notification = $this->service->update(
            $id,
            $request->validated()
        );

        return response()->json($notification);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ]);
    }
}
