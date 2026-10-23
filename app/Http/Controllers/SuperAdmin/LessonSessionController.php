<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreLessonSessionRequest;
use App\Http\Requests\SuperAdmin\UpdateLessonSessionRequest;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
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
                'teacher.user',
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
        return view(
            'superadmin.lesson-sessions.create',
            $this->formOptions()
        );
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
            'teacher.user',
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
            'teacher.user',
            'program',
            'classGroup',
            'rescheduledFrom',
        ]);

        return view('superadmin.lesson-sessions.edit', [
            'lessonSession' => $lessonSession,
            ...$this->formOptions($lessonSession),
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

    private function formOptions(?LessonSession $current = null): array
    {
        $selectedBranchId = $current?->branch_id;
        $selectedTeacherId = $current?->teacher_id;
        $selectedProgramId = $current?->program_id;
        $selectedClassGroupId = $current?->class_group_id;

        $branches = Branch::query()
            ->where(function ($query) use ($selectedBranchId) {
                $query->where('is_active', true);

                if ($selectedBranchId !== null) {
                    $query->orWhere('id', $selectedBranchId);
                }
            })
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->with('user')
            ->where(function ($query) use ($selectedTeacherId) {
                $query->where('is_active', true);

                if ($selectedTeacherId !== null) {
                    $query->orWhere('id', $selectedTeacherId);
                }
            })
            ->get()
            ->sortBy(function (Teacher $teacher) {
                return strtolower((string) (
                    $teacher->user?->full_name
                    ?: $teacher->user?->username
                    ?: $teacher->id
                ));
            })
            ->values();

        $programs = Program::query()
            ->where(function ($query) use ($selectedProgramId) {
                $query->where('is_active', true);

                if ($selectedProgramId !== null) {
                    $query->orWhere('id', $selectedProgramId);
                }
            })
            ->orderBy('name')
            ->get();

        $classGroups = ClassGroup::query()
            ->with(['branch', 'program'])
            ->where(function ($query) use ($selectedClassGroupId) {
                $query->where('is_active', true);

                if ($selectedClassGroupId !== null) {
                    $query->orWhere('id', $selectedClassGroupId);
                }
            })
            ->orderBy('name')
            ->get();

        $reschedulableSessions = LessonSession::query()
            ->with(['branch', 'program'])
            ->when(
                $current !== null,
                fn ($query) => $query->where('id', '!=', $current->id)
            )
            ->orderByDesc('planned_start_at')
            ->limit(100)
            ->get();

        return [
            'branches' => $branches,
            'teachers' => $teachers,
            'programs' => $programs,
            'classGroups' => $classGroups,
            'reschedulableSessions' => $reschedulableSessions,
        ];
    }
}
