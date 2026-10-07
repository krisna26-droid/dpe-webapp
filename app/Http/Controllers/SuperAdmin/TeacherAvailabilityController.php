<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTeacherAvailabilityRequest;
use App\Http\Requests\SuperAdmin\UpdateTeacherAvailabilityRequest;
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
                'teacher',
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
            'superadmin.teacher-availability.create'
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
            'teacher',
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
            'teacher',
            'branch',
        ]);

        return view(
            'superadmin.teacher-availability.edit',
            [
                'availability' => $teacherAvailability,
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
}
