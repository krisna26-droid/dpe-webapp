<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherMessage\StoreTeacherMessageRequest;
use App\Http\Requests\TeacherMessage\UpdateTeacherMessageRequest;
use App\Models\FileAsset;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherMessage;
use App\Services\TeacherMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeacherMessageController extends Controller
{
    public function __construct(
        private TeacherMessageService $teacherMessageService
    ) {}

    public function index(): View
    {
        $teacherMessages = TeacherMessage::query()
            ->with([
                'student.portalUser',
                'teacher.user',
                'session.branch',
                'session.program',
                'attachment',
            ])
            ->latest('created_at')
            ->paginate(15);

        return view(
            'teacher-messages.index',
            compact('teacherMessages')
        );
    }

    public function create(): View
    {
        return view(
            'teacher-messages.create',
            $this->formOptions()
        );
    }

    public function store(
        StoreTeacherMessageRequest $request
    ): RedirectResponse {
        $teacherMessage = $this->teacherMessageService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.teacher-messages.show',
                $teacherMessage
            )
            ->with(
                'success',
                'Teacher message berhasil dibuat.'
            );
    }

    public function show(
        TeacherMessage $teacherMessage
    ): View {
        $teacherMessage->load([
            'student.portalUser',
            'teacher.user',
            'session.branch',
            'session.teacher.user',
            'session.program',
            'session.classGroup',
            'attachment',
        ]);

        return view(
            'teacher-messages.show',
            compact('teacherMessage')
        );
    }

    public function edit(
        TeacherMessage $teacherMessage
    ): View {
        $teacherMessage->load([
            'student.portalUser',
            'teacher.user',
            'session.branch',
            'session.teacher.user',
            'session.program',
            'session.classGroup',
            'attachment',
        ]);

        return view(
            'teacher-messages.edit',
            [
                'teacherMessage' => $teacherMessage,
                ...$this->formOptions($teacherMessage),
            ]
        );
    }

    public function update(
        UpdateTeacherMessageRequest $request,
        TeacherMessage $teacherMessage
    ): RedirectResponse {
        $teacherMessage = $this->teacherMessageService->update(
            $teacherMessage,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.teacher-messages.show',
                $teacherMessage
            )
            ->with(
                'success',
                'Teacher message berhasil diperbarui.'
            );
    }

    public function destroy(
        TeacherMessage $teacherMessage
    ): RedirectResponse {
        $this->teacherMessageService->delete(
            $teacherMessage
        );

        return redirect()
            ->route(
                'superadmin.teacher-messages.index'
            )
            ->with(
                'success',
                'Teacher message berhasil dihapus.'
            );
    }

    private function formOptions(?TeacherMessage $current = null): array
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

        $selectedAttachmentId = $current?->attachment_file_id;
        $attachments = FileAsset::query()
            ->where(function ($query) use ($selectedAttachmentId) {
                $query->whereNotNull('original_name');

                if ($selectedAttachmentId !== null) {
                    $query->orWhere('id', $selectedAttachmentId);
                }
            })
            ->latest('created_at')
            ->limit(200)
            ->get();

        return [
            'students' => $students,
            'teachers' => $teachers,
            'sessions' => $sessions,
            'attachments' => $attachments,
        ];
    }
}
