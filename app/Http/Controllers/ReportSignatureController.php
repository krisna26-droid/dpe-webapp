<?php

namespace App\Http\Controllers;

use App\Services\ReportSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

class ReportSignatureController extends Controller
{
    public function __construct(private ReportSignatureService $signatures) {}

    public function teacherStore(Request $request): RedirectResponse
    {
        return $this->store($request, 'teacher', 'teacher.profile.show');
    }

    public function managerStore(Request $request): RedirectResponse
    {
        return $this->store($request, 'manager', 'superadmin.report-template.edit');
    }

    private function store(Request $request, string $scope, string $route): RedirectResponse
    {
        $upload = $request->file('signature_file');
        $this->signatures->save($request->user(), $scope, $request->all(), $upload instanceof UploadedFile ? $upload : null);

        return redirect()->route($route)->with('success', 'Tanda tangan tersimpan. Periksa Preview PDF sebelum rapor disetujui.');
    }

    public function teacherImage(Request $request): Response
    {
        return $this->image($request, 'teacher');
    }

    public function managerImage(Request $request): Response
    {
        return $this->image($request, 'manager');
    }

    private function image(Request $request, string $scope): Response
    {
        $image = $this->signatures->image($request->user(), $scope);

        return response($image['bytes'], 200, ['Content-Type' => $image['mime'],
            'Content-Disposition' => 'inline; filename="tanda-tangan.'.($image['mime'] === 'image/png' ? 'png' : 'jpg').'"',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }
}
