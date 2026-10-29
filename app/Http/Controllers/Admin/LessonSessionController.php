<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchLessonSessionRequest;
use App\Models\ClassGroup;
use App\Models\LessonSession;
use App\Services\BranchOperationsService;
use App\Services\LessonSessionService;
use App\Services\LessonSessionListService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonSessionController extends Controller
{
    public function __construct(private BranchOperationsService $operations, private LessonSessionService $sessions) {}

    public function index(Request $request): View
    {
        return view('superadmin.lesson-sessions.index', app(LessonSessionListService::class)
            ->listing($request, $this->operations->sessions($request->user()), 'admin'));
    }

    public function create(Request $request): View
    {
        $options = $this->operations->options($request->user());
        $options['availabilitySlots'] = $this->operations->availabilitySlots($request->user());
        abort_if($options['branches']->isEmpty(), 403, 'Belum ada cabang aktif yang dapat Anda kelola.');

        return view('superadmin.lesson-sessions.create', $options);
    }

    public function store(BranchLessonSessionRequest $request): RedirectResponse
    {
        $session = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $this->validateReferences($request, $data);

            return $this->sessions->create($data);
        });

        return redirect()->route('admin.lesson-sessions.show', app(LessonSessionListService::class)->parameters($request, [$session]))->with('success', 'Sesi belajar berhasil ditambahkan. Buka Peserta / absensi untuk memeriksa peserta.');
    }

    public function show(Request $request, LessonSession $lessonSession): View
    {
        $this->operations->authorize($request->user(), $lessonSession);
        $lessonSession->load(['branch', 'teacher.user', 'program', 'classGroup', 'rescheduledFrom', 'sessionStudents.student']);

        return view('superadmin.lesson-sessions.show', ['lessonSession' => $lessonSession, 'operationRoutePrefix' => 'admin']);
    }

    public function edit(Request $request, LessonSession $lessonSession): View
    {
        $this->operations->authorize($request->user(), $lessonSession);

        return view('superadmin.lesson-sessions.edit', ['lessonSession' => $lessonSession,
            'availabilitySlots' => $this->operations->availabilitySlots($request->user())] + $this->operations->options($request->user(), $lessonSession));
    }

    public function update(BranchLessonSessionRequest $request, LessonSession $lessonSession): RedirectResponse
    {
        DB::transaction(function () use ($request, $lessonSession) {
            $current = LessonSession::whereKey($lessonSession->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $this->operations->authorize($request->user(), $current);
            $this->operations->checkVersion($current, $data);
            $this->validateReferences($request, $data, $current);
            $this->sessions->update($current, $data);
        });

        return redirect()->route('admin.lesson-sessions.show', app(LessonSessionListService::class)->parameters($request, [$lessonSession]))->with('success', 'Sesi belajar berhasil diperbarui.');
    }

    private function validateReferences(Request $request, array $data, ?LessonSession $current = null): void
    {
        $this->operations->validateTeacher($request->user(), $data, $current);
        if (! empty($data['class_group_id'])) {
            $this->operations->authorize($request->user(), ClassGroup::whereKey($data['class_group_id'])->lockForUpdate()->firstOrFail());
        }
        if (! empty($data['rescheduled_from_session_id'])) {
            $this->operations->authorize($request->user(), LessonSession::whereKey($data['rescheduled_from_session_id'])->lockForUpdate()->firstOrFail());
        }
    }
}
