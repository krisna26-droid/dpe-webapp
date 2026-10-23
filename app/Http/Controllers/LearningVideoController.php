<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningVideo\StoreLearningVideoRequest;
use App\Http\Requests\LearningVideo\UpdateLearningVideoRequest;
use App\Models\LearningVideo;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\LearningVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LearningVideoController extends Controller
{
    public function __construct(
        private LearningVideoService $learningVideoService
    ) {}

    public function index(): View
    {
        $learningVideos = LearningVideo::query()
            ->with([
                'student',
                'teacher.user',
                'session',
            ])
            ->latest('video_on')
            ->latest('created_at')
            ->paginate(15);

        return view('learning-videos.index', compact('learningVideos'));
    }

    public function create(): View
    {
        return view(
            'learning-videos.create',
            $this->formOptions()
        );
    }

    public function store(
        StoreLearningVideoRequest $request
    ): RedirectResponse {
        $learningVideo = $this->learningVideoService->create(
            $request->validated()
        );

        return redirect()
            ->route('superadmin.learning-videos.show', $learningVideo)
            ->with('success', 'Learning video berhasil dibuat.');
    }

    public function show(
        LearningVideo $learningVideo
    ): View {
        $learningVideo->load([
            'student.portalUser',
            'teacher.user',
            'session.branch',
            'session.program',
            'session.classGroup',
        ]);

        return view(
            'learning-videos.show',
            compact('learningVideo')
        );
    }

    public function edit(
        LearningVideo $learningVideo
    ): View {
        $learningVideo->load([
            'student.portalUser',
            'teacher.user',
            'session',
        ]);

        return view(
            'learning-videos.edit',
            [
                'learningVideo' => $learningVideo,
                ...$this->formOptions($learningVideo),
            ]
        );
    }

    public function update(
        UpdateLearningVideoRequest $request,
        LearningVideo $learningVideo
    ): RedirectResponse {
        $learningVideo = $this->learningVideoService->update(
            $learningVideo,
            $request->validated()
        );

        return redirect()
            ->route('superadmin.learning-videos.show', $learningVideo)
            ->with('success', 'Learning video berhasil diperbarui.');
    }

    public function destroy(
        LearningVideo $learningVideo
    ): RedirectResponse {
        $this->learningVideoService->delete($learningVideo);

        return redirect()
            ->route('superadmin.learning-videos.index')
            ->with('success', 'Learning video berhasil dihapus.');
    }

    private function formOptions(?LearningVideo $current = null): array
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
            ->where(function ($query) use ($selectedSessionId) {
                $query->whereNotNull('id');

                if ($selectedSessionId !== null) {
                    $query->orWhere('id', $selectedSessionId);
                }
            })
            ->with([
                'branch',
                'teacher.user',
                'program',
                'classGroup',
            ])
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
