<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentChallenge\StoreStudentChallengeRequest;
use App\Http\Requests\StudentChallenge\UpdateStudentChallengeRequest;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\StudentChallenge;
use App\Models\Teacher;
use App\Services\StudentChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentChallengeController extends Controller
{
    public function __construct(
        private StudentChallengeService $studentChallengeService
    ) {}

    public function index(): View
    {
        $studentChallenges = StudentChallenge::query()
            ->with([
                'student.portalUser',
                'teacher.user',
                'session.branch',
                'session.program',
                'session.classGroup',
            ])
            ->latest('logged_on')
            ->latest('created_at')
            ->paginate(15);

        return view(
            'student-challenges.index',
            compact('studentChallenges')
        );
    }

    public function create(): View
    {
        return view(
            'student-challenges.create',
            $this->formOptions()
        );
    }

    public function store(
        StoreStudentChallengeRequest $request
    ): RedirectResponse {
        $studentChallenge = $this->studentChallengeService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.student-challenges.show',
                $studentChallenge
            )
            ->with(
                'success',
                'Student challenge berhasil dibuat.'
            );
    }

    public function show(
        StudentChallenge $studentChallenge
    ): View {
        $studentChallenge->load([
            'student.portalUser',
            'teacher.user',
            'session.branch',
            'session.program',
            'session.classGroup',
        ]);

        return view(
            'student-challenges.show',
            compact('studentChallenge')
        );
    }

    public function edit(
        StudentChallenge $studentChallenge
    ): View {
        $studentChallenge->load([
            'student.portalUser',
            'teacher.user',
            'session.branch',
            'session.program',
            'session.classGroup',
        ]);

        return view(
            'student-challenges.edit',
            [
                'studentChallenge' => $studentChallenge,
                ...$this->formOptions($studentChallenge),
            ]
        );
    }

    public function update(
        UpdateStudentChallengeRequest $request,
        StudentChallenge $studentChallenge
    ): RedirectResponse {
        $studentChallenge = $this->studentChallengeService->update(
            $studentChallenge,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.student-challenges.show',
                $studentChallenge
            )
            ->with(
                'success',
                'Student challenge berhasil diperbarui.'
            );
    }

    public function destroy(
        StudentChallenge $studentChallenge
    ): RedirectResponse {
        $this->studentChallengeService->delete(
            $studentChallenge
        );

        return redirect()
            ->route(
                'superadmin.student-challenges.index'
            )
            ->with(
                'success',
                'Student challenge berhasil dihapus.'
            );
    }

    /**
     * Keep active people at the top of the form while preserving the
     * currently selected record when it has since become inactive.
     */
    private function formOptions(?StudentChallenge $current = null): array
    {
        $students = Student::query()
            ->where(function ($query) use ($current) {
                $query->where('status', 'active');

                if ($current?->student_id !== null) {
                    $query->orWhere('id', $current->student_id);
                }
            })
            ->with('portalUser')
            ->orderBy('full_name')
            ->get();

        $selectedTeacherId = $current?->teacher_id;
        $teachers = Teacher::query()
            ->where(function ($query) use ($selectedTeacherId) {
                $query->where('is_active', true);

                if ($selectedTeacherId !== null) {
                    $query->orWhere('id', $selectedTeacherId);
                }
            })
            ->with('user')
            ->get()
            ->sortBy(function (Teacher $teacher) {
                return mb_strtolower((string) (
                    $teacher->user?->full_name
                    ?: $teacher->user?->username
                    ?: $teacher->id
                ));
            })
            ->values();

        $selectedSessionId = $current?->session_id;
        $sessions = LessonSession::query()
            ->with([
                'branch',
                'teacher.user',
                'program',
                'classGroup',
            ])
            ->when(
                $selectedSessionId !== null,
                fn ($query) => $query->where(function ($query) use ($selectedSessionId) {
                    $query->whereNotNull('id')
                        ->orWhere('id', $selectedSessionId);
                })
            )
            ->orderByDesc('planned_start_at')
            ->limit(200)
            ->get();

        return [
            'students' => $students,
            'teachers' => $teachers,
            'sessions' => $sessions,
        ];
    }
}
