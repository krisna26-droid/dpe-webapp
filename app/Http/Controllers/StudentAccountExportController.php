<?php

namespace App\Http\Controllers;

use App\Services\OperationalExportPdfWriter;
use App\Services\OperationalExportXlsxWriter;
use App\Services\StudentAccountExportService;
use App\Services\UserDirectoryFilterService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentAccountExportController extends Controller
{
    public function __invoke(Request $request, string $format, UserDirectoryFilterService $directory,
        StudentAccountExportService $service, OperationalExportPdfWriter $pdf, OperationalExportXlsxWriter $excel)
    {
        abort_unless(in_array($request->user()->role_code, ['superadmin', 'admin'], true), 403);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $filters = $directory->filters($request, studentsOnly: true);
        try {
            $data = $service->data($request->user(), $filters, $format);
            $bytes = $format === 'pdf' ? $pdf->render($data) : $excel->render($data);
            $name = 'DPE_akun_siswa_'.today()->format('Y-m-d').'.'.$format;

            return response($bytes, 200, [
                'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
                'Content-Length' => strlen($bytes), 'X-DPE-Export-Rows' => (string) $data['total'],
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        } catch (ValidationException $error) {
            if ($request->expectsJson()) throw $error;

            return redirect()->route($request->user()->role_code.'.users.index', array_filter($filters, fn ($value) => $value !== ''))
                ->withErrors($error->errors());
        }
    }
}
