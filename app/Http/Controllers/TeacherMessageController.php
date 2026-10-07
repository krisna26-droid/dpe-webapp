<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherMessage\StoreTeacherMessageRequest;
use App\Http\Requests\TeacherMessage\UpdateTeacherMessageRequest;
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
                'student',
                'teacher',
                'session',
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
        return view('teacher-messages.create');
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
            'student',
            'teacher',
            'session',
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
            'student',
            'teacher',
            'session',
            'attachment',
        ]);

        return view(
            'teacher-messages.edit',
            compact('teacherMessage')
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
}
