<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreLessonSessionRequest;
use App\Http\Requests\SuperAdmin\UpdateLessonSessionRequest;
use App\Models\LessonSession;
use App\Services\LessonSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LessonSessionController extends Controller
{
    public function __construct(
        private LessonSessionService $lessonSessionService
    ) {
    }

    public function index(): View
    {
        $lessonSessions = LessonSession::query()
            ->with([
                'branch',
                'teacher',
                'program',
                'classGroup',
            ])
            ->orderByDesc('planned_start_at')
            ->paginate(15);

        return view('superadmin.lesson-sessions.index', [
            'lessonSessions' => $lessonSessions,
        ]);
    }

    public function create(): View
    {
        return view('superadmin.lesson-sessions.create');
    }

    public function store(
        StoreLessonSessionRequest $request
    ): RedirectResponse {
        $lessonSession = $this->lessonSessionService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.lesson-sessions.show',
                $lessonSession
            )
            ->with(
                'success',
                'Lesson session berhasil dibuat.'
            );
    }

    public function show(
        LessonSession $lessonSession
    ): View {
        $lessonSession->load([
            'branch',
            'teacher',
            'program',
            'classGroup',
            'rescheduledFrom',
            'sessionStudents.student',
        ]);

        return view('superadmin.lesson-sessions.show', [
            'lessonSession' => $lessonSession,
        ]);
    }

    public function edit(
        LessonSession $lessonSession
    ): View {
        $lessonSession->load([
            'branch',
            'teacher',
            'program',
            'classGroup',
            'rescheduledFrom',
        ]);

        return view('superadmin.lesson-sessions.edit', [
            'lessonSession' => $lessonSession,
        ]);
    }

    public function update(
        UpdateLessonSessionRequest $request,
        LessonSession $lessonSession
    ): RedirectResponse {
        $this->lessonSessionService->update(
            $lessonSession,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.lesson-sessions.show',
                $lessonSession
            )
            ->with(
                'success',
                'Lesson session berhasil diperbarui.'
            );
    }
}