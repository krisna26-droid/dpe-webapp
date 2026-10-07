<?php

namespace App\Http\Controllers;

use App\Http\Requests\SessionStudentStoreRequest;
use App\Models\LessonSession;
use App\Models\Student;
use App\Services\SessionStudentService;
use Illuminate\Http\JsonResponse;

class SessionStudentController extends Controller
{
    public function __construct(
        private SessionStudentService $service
    ) {}

    public function store(
        SessionStudentStoreRequest $request
    ): JsonResponse {
        $sessionStudent = $this->service->create(
            $request->validated()
        );

        return response()->json($sessionStudent, 201);
    }

    public function show(
        string $sessionId,
        string $studentId
    ): JsonResponse {
        $sessionStudent = $this->service->find(
            $sessionId,
            $studentId
        );

        return response()->json($sessionStudent);
    }

    public function bySession(
        string $sessionId
    ): JsonResponse {
        $session = LessonSession::query()
            ->findOrFail($sessionId);

        return response()->json(
            $this->service->getBySession($session)
        );
    }

    public function byStudent(
        string $studentId
    ): JsonResponse {
        $student = Student::query()
            ->findOrFail($studentId);

        return response()->json(
            $this->service->getByStudent($student)
        );
    }

    public function update(
        SessionStudentStoreRequest $request,
        string $sessionId,
        string $studentId
    ): JsonResponse {
        $sessionStudent = $this->service->update(
            $sessionId,
            $studentId,
            $request->validated()
        );

        return response()->json($sessionStudent);
    }

    public function destroy(
        string $sessionId,
        string $studentId
    ): JsonResponse {
        $this->service->delete(
            $sessionId,
            $studentId
        );

        return response()->json([
            'message' => 'Session student deleted successfully.',
        ]);
    }
}
