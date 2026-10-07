<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreStudentEnrollmentRequest;
use App\Http\Requests\SuperAdmin\UpdateStudentEnrollmentRequest;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\StudentEnrollmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StudentEnrollmentController extends Controller
{
    public function index(): View
    {
        $enrollments = StudentEnrollment::query()
            ->with([
                'student',
                'branch',
                'program',
            ])
            ->orderByDesc('starts_on')
            ->paginate(15);

        return view(
            'superadmin.student-enrollments.index',
            [
                'enrollments' => $enrollments,
            ]
        );
    }

    public function create(): View
    {
        $students = Student::query()
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $programs = Program::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'superadmin.student-enrollments.create',
            [
                'students' => $students,
                'branches' => $branches,
                'programs' => $programs,
            ]
        );
    }

    public function store(
        StoreStudentEnrollmentRequest $request,
        StudentEnrollmentService $service
    ): RedirectResponse {
        $enrollment = $service->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.student-enrollments.show',
                $enrollment
            )
            ->with(
                'success',
                'Enrollment siswa berhasil dibuat.'
            );
    }

    public function show(
        StudentEnrollment $studentEnrollment
    ): View {
        $studentEnrollment->load([
            'student',
            'branch',
            'program',
        ]);

        return view(
            'superadmin.student-enrollments.show',
            [
                'enrollment' => $studentEnrollment,
            ]
        );
    }

    public function edit(
        StudentEnrollment $studentEnrollment
    ): View {
        $students = Student::query()
            ->where('status', 'active')
            ->orWhere(
                'id',
                $studentEnrollment->student_id
            )
            ->orderBy('full_name')
            ->get();

        $branches = Branch::query()
            ->where('is_active', true)
            ->orWhere(
                'id',
                $studentEnrollment->branch_id
            )
            ->orderBy('name')
            ->get();

        $programs = Program::query()
            ->where('is_active', true)
            ->orWhere(
                'id',
                $studentEnrollment->program_id
            )
            ->orderBy('name')
            ->get();

        return view(
            'superadmin.student-enrollments.edit',
            [
                'enrollment' => $studentEnrollment,
                'students' => $students,
                'branches' => $branches,
                'programs' => $programs,
            ]
        );
    }

    public function update(
        UpdateStudentEnrollmentRequest $request,
        StudentEnrollment $studentEnrollment,
        StudentEnrollmentService $service
    ): RedirectResponse {
        $service->update(
            $studentEnrollment,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.student-enrollments.show',
                $studentEnrollment
            )
            ->with(
                'success',
                'Enrollment siswa berhasil diperbarui.'
            );
    }

    public function destroy(
        StudentEnrollment $studentEnrollment,
        StudentEnrollmentService $service
    ): RedirectResponse {
        $service->delete($studentEnrollment);

        return redirect()
            ->route(
                'superadmin.student-enrollments.index'
            )
            ->with(
                'success',
                'Enrollment siswa berhasil dihapus.'
            );
    }
}