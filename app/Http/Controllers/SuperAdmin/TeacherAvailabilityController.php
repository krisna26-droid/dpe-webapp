<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTeacherAvailabilityRequest;
use App\Http\Requests\SuperAdmin\UpdateTeacherAvailabilityRequest;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Services\TeacherAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TeacherAvailabilityController extends Controller
{
    public function __construct(
        private TeacherAvailabilityService $teacherAvailabilityService
    ) {}

    public function index(): View
    {
        $availabilities = TeacherAvailability::query()
            ->with([
                'teacher.user',
                'branch',
            ])
            ->orderByDesc('available_on')
            ->orderBy('starts_at')
            ->paginate(15);

        return view(
            'superadmin.teacher-availability.index',
            [
                'availabilities' => $availabilities,
            ]
        );
    }

    public function create(): View
    {
        return view(
            'superadmin.teacher-availability.create',
            [
                'teachers' => $this->teacherOptions(),
                'branches' => $this->branchOptions(),
            ]
        );
    }

    public function store(
        StoreTeacherAvailabilityRequest $request
    ): RedirectResponse {
        $availability = $this->teacherAvailabilityService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.teacher-availability.show',
                $availability
            )
            ->with(
                'success',
                'Teacher availability berhasil dibuat.'
            );
    }

    public function show(
        TeacherAvailability $teacherAvailability
    ): View {
        $teacherAvailability->load([
            'teacher.user',
            'branch',
        ]);

        return view(
            'superadmin.teacher-availability.show',
            [
                'availability' => $teacherAvailability,
            ]
        );
    }

    public function edit(
        TeacherAvailability $teacherAvailability
    ): View {
        $teacherAvailability->load([
            'teacher.user',
            'branch',
        ]);

        return view(
            'superadmin.teacher-availability.edit',
            [
                'availability' => $teacherAvailability,
                'teachers' => $this->teacherOptions($teacherAvailability->teacher_id),
                'branches' => $this->branchOptions($teacherAvailability->branch_id),
            ]
        );
    }

    public function update(
        UpdateTeacherAvailabilityRequest $request,
        TeacherAvailability $teacherAvailability
    ): RedirectResponse {
        $this->teacherAvailabilityService->update(
            $teacherAvailability,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.teacher-availability.show',
                $teacherAvailability
            )
            ->with(
                'success',
                'Teacher availability berhasil diperbarui.'
            );
    }

    private function teacherOptions(?string $selectedId = null)
    {
        return Teacher::query()
            ->with('user')
            ->where(function ($query) use ($selectedId) {
                $query->where('is_active', true);

                if ($selectedId !== null) {
                    $query->orWhere('id', $selectedId);
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
    }

    private function branchOptions(?string $selectedId = null)
    {
        return Branch::query()
            ->where(function ($query) use ($selectedId) {
                $query->where('is_active', true);

                if ($selectedId !== null) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
