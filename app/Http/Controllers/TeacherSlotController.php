<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeacherSlotRequest;
use App\Models\ClassLearningSession;
use App\Services\TeacherSlotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeacherSlotController extends Controller
{
    public function __construct(private TeacherSlotService $slots) {}

    public function index(Request $request): View
    {
        return view('teacher-slots.index', [
            'rows' => $this->slots->rows($request->user()),
            'operationRoutePrefix' => $this->prefix($request),
        ]);
    }

    public function update(
        UpdateTeacherSlotRequest $request,
        ClassLearningSession $classLearningSession
    ): RedirectResponse {
        $data = $request->validated();
        $this->slots->update($request->user(), $classLearningSession, (int) $data['slot_capacity']);

        return redirect()
            ->route($this->prefix($request).'.teacher-slots.index')
            ->with('success', 'Kapasitas slot sesi berhasil diperbarui.');
    }

    private function prefix(Request $request): string
    {
        return explode('.', (string) $request->route()?->getName())[0] ?: 'admin';
    }
}
