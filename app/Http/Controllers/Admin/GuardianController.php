<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGuardianPhoneRequest;
use App\Models\Guardian;
use App\Services\GuardianService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GuardianController extends Controller
{
    public function edit(Guardian $guardian): View
    {
        $student = $guardian->student;
        abort_unless($student, 404);
        Gate::authorize('view', $student);

        return view('admin.guardians.edit', compact('guardian', 'student'));
    }

    public function update(
        UpdateGuardianPhoneRequest $request,
        Guardian $guardian,
        GuardianService $guardians
    ): RedirectResponse {
        $student = $guardian->student;
        abort_unless($student, 404);
        Gate::authorize('update', $student);

        $guardians->updatePhone($guardian, $request->validated('whatsapp_number'));

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Nomor telepon wali berhasil diperbarui.');
    }
}
