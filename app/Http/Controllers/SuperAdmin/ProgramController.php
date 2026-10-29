<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreProgramRequest;
use App\Http\Requests\SuperAdmin\UpdateProgramRequest;
use App\Models\Program;
use App\Services\ProgramService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(): View
    {
        $programs = Program::query()
            ->withCount([
                'classGroups',
                'enrollments',
            ])
            ->orderBy('name')
            ->paginate(15);

        return view('superadmin.programs.index', [
            'programs' => $programs,
        ]);
    }

    public function create(): View
    {
        return view('superadmin.programs.create');
    }

    public function store(
        StoreProgramRequest $request,
        ProgramService $service
    ): RedirectResponse {
        $program = $service->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.programs.show',
                $program
            )
            ->with(
                'success',
                'Program berhasil dibuat.'
            );
    }

    public function show(Program $program): View
    {
        $program->loadCount([
            'classGroups',
            'enrollments',
        ]);

        return view('superadmin.programs.show', [
            'program' => $program,
        ]);
    }

    public function edit(Program $program): View
    {
        return view('superadmin.programs.edit', [
            'program' => $program,
        ]);
    }

    public function update(
        UpdateProgramRequest $request,
        Program $program,
        ProgramService $service
    ): RedirectResponse {
        $service->update(
            $program,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.programs.show',
                $program
            )
            ->with(
                'success',
                'Program berhasil diperbarui.'
            );
    }

    public function destroy(\Illuminate\Http\Request $request, Program $program): RedirectResponse
    {
        app(\App\Services\OperationalDeletionService::class)->delete($request->user(),$program);
        return redirect()->route('superadmin.programs.index')->with('success','Data berhasil dihapus. Riwayat sebelumnya tetap tersimpan.');
    }
}
