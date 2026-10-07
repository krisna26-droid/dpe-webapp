<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentChallenge\StoreStudentChallengeRequest;
use App\Http\Requests\StudentChallenge\UpdateStudentChallengeRequest;
use App\Models\StudentChallenge;
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
            ->with(['student', 'teacher', 'session'])
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
        return view('student-challenges.create');
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
            'student',
            'teacher',
            'session',
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
            'student',
            'teacher',
            'session',
        ]);

        return view(
            'student-challenges.edit',
            compact('studentChallenge')
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
}
