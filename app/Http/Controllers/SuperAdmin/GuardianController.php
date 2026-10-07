<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreGuardianRequest;
use App\Http\Requests\SuperAdmin\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Services\GuardianService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GuardianController extends Controller
{
    public function __construct(
        private GuardianService $guardianService
    ) {}

    public function index(): View
    {
        $guardians = Guardian::query()
            ->with('student')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view(
            'superadmin.guardians.index',
            [
                'guardians' => $guardians,
            ]
        );
    }

    public function create(): View
    {
        return view(
            'superadmin.guardians.create'
        );
    }

    public function store(
        StoreGuardianRequest $request
    ): RedirectResponse {
        $guardian = $this->guardianService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.guardians.show',
                $guardian
            )
            ->with(
                'success',
                'Guardian berhasil dibuat.'
            );
    }

    public function show(
        Guardian $guardian
    ): View {
        $guardian->load('student');

        return view(
            'superadmin.guardians.show',
            [
                'guardian' => $guardian,
            ]
        );
    }

    public function edit(
        Guardian $guardian
    ): View {
        $guardian->load('student');

        return view(
            'superadmin.guardians.edit',
            [
                'guardian' => $guardian,
            ]
        );
    }

    public function update(
        UpdateGuardianRequest $request,
        Guardian $guardian
    ): RedirectResponse {
        $this->guardianService->update(
            $guardian,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.guardians.show',
                $guardian
            )
            ->with(
                'success',
                'Guardian berhasil diperbarui.'
            );
    }

    public function destroy(
        Guardian $guardian
    ): RedirectResponse {
        $this->guardianService->delete($guardian);

        return redirect()
            ->route(
                'superadmin.guardians.index'
            )
            ->with(
                'success',
                'Guardian berhasil dihapus.'
            );
    }
}
