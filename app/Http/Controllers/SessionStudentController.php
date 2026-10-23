<?php

namespace App\Http\Controllers;

use App\Http\Requests\SessionStudentStoreRequest;
use App\Models\LessonSession;
use App\Models\Student;
use App\Services\SessionStudentService;
use App\Services\StudentAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SessionStudentController extends Controller
{
    public function __construct(
        private SessionStudentService $service
    ) {}

    public function store(
        SessionStudentStoreRequest $request
    ): JsonResponse {
        $data = $request->validated();
        $sessionStudent = app(StudentAttendanceService::class)->record(
            $request->user() ?? auth()->user(),
            LessonSession::query()->findOrFail($data['session_id']),
            Student::query()->findOrFail($data['student_id']),
            $data,
            true
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
        $data = $request->validated();
        if ($data['session_id'] !== $sessionId || $data['student_id'] !== $studentId) {
            throw ValidationException::withMessages(['student_id' => 'Identitas siswa dan pertemuan pada catatan tidak dapat dipindahkan.']);
        }
        $this->service->find($sessionId, $studentId);
        $sessionStudent = app(StudentAttendanceService::class)->record(
            $request->user() ?? auth()->user(),
            LessonSession::query()->findOrFail($sessionId),
            Student::query()->findOrFail($studentId),
            $data
        );

        return response()->json($sessionStudent);
    }

    public function destroy(
        string $sessionId,
        string $studentId
    ): JsonResponse {
        app(StudentAttendanceService::class)->removeBlankParticipant(
            auth()->user(),
            LessonSession::query()->findOrFail($sessionId),
            Student::query()->findOrFail($studentId)
        );

        return response()->json([
            'message' => 'Session student deleted successfully.',
        ]);
    }
}
