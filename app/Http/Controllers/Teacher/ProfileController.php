<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SaveOwnProfileRequest;
use App\Models\ClassGroup;
use App\Services\OwnAccountService;
use App\Services\TeacherWorkspaceService;
use App\Services\ReportSignatureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function __construct(private OwnAccountService $account, private TeacherWorkspaceService $workspace) {}

    public function show(Request $request): View
    {
        $actor = $this->account->actor($request->user(), 'teacher');
        $teacher = $this->account->teacher($actor)?->load(['photoFile', 'branchAssignments.branch']);
        $canEdit = (bool) $teacher?->is_active;
        $branches = $canEdit ? $this->workspace->branches($actor, today()->toDateString(), today()->toDateString())->orderBy('name')->get() : collect();
        $groups = $canEdit ? ClassGroup::where('default_teacher_id', $teacher->id)->where('is_active', true)
            ->with(['branch', 'program'])->orderBy('name')->get() : collect();

        return view('teacher.profile.show', compact('actor', 'teacher', 'canEdit', 'branches', 'groups')
            + ['signatureState' => $canEdit ? app(ReportSignatureService::class)->state($actor, 'teacher') : null]);
    }

    public function edit(Request $request): View
    {
        $actor = $this->account->actor($request->user(), 'teacher');
        $teacher = $this->account->teacher($actor, active: true)->load('photoFile');

        return view('teacher.profile.edit', compact('actor', 'teacher') + ['recordVersion' => $this->account->version($actor, $teacher)]);
    }

    public function update(SaveOwnProfileRequest $request): RedirectResponse
    {
        $this->account->saveProfile($request->user(), $request->validated(), $request->file('photo'));

        return redirect()->route('teacher.profile.show')->with('success', 'Profil Anda berhasil diperbarui.');
    }

    public function photo(Request $request): BinaryFileResponse
    {
        $actor = $this->account->actor($request->user(), 'teacher');
        $file = $this->account->teacher($actor)?->photoFile;
        // Existing Admin uploads may be GIF/BMP; new self-service uploads remain JPG/PNG/WebP.
        abort_unless($file && preg_match('#^teacher-photos/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp|gif|bmp)$#iD', $file->storage_key), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_key), 404);
        $path = $disk->path($file->storage_key);
        $info = @getimagesize($path);
        abort_unless($info && in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'], true)
            && hash_equals($file->sha256_hex, hash_file('sha256', $path)), 404);

        return response()->file($path, ['Content-Type' => $info['mime'], 'Content-Disposition' => 'inline; filename="foto-profil"',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }
}
