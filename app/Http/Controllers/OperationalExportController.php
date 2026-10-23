<?php

namespace App\Http\Controllers;

use App\Services\OperationalExportDataService;
use App\Services\OperationalExportDefinition;
use App\Services\OperationalExportPdfWriter;
use App\Services\OperationalExportXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationalExportController extends Controller
{
    public function index(Request $request, OperationalExportDataService $service)
    {
        $data = $service->data($request->user(), $this->validateFilters($request));
        $page = (int) $request->input('page', 1);
        $data['pageRows'] = new LengthAwarePaginator(array_slice($data['rows'], ($page - 1) * 15, 15), $data['total'], 15, $page,
            ['path' => route('exports.index'), 'query' => $data['filters'], 'fragment' => 'export-preview']);

        return view('exports.index', $data);
    }

    public function download(Request $request, string $format, OperationalExportDataService $service,
        OperationalExportXlsxWriter $excel, OperationalExportPdfWriter $pdf)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $data = null;
        try {
            $input = $this->validateFilters($request);
            $request->validate(['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
            $data = $service->data($request->user(), $input);
            if (! hash_equals($data['snapshot'], (string) $request->input('expected_snapshot'))) {
                throw ValidationException::withMessages(['export' => 'Data atau filter berubah sejak pratinjau dibuka. Periksa pratinjau terbaru lalu unduh kembali.']);
            }
            if ($data['total'] === 0) {
                throw ValidationException::withMessages(['export' => 'Belum ada data sesuai filter untuk diunduh.']);
            }
            if (strlen(json_encode($data['rows'], JSON_THROW_ON_ERROR)) > 6 * 1024 * 1024) {
                throw ValidationException::withMessages(['export' => 'Isi rekap terlalu besar. Persempit filter lalu unduh kembali. Data tidak dipotong.']);
            }
            $bytes = $format === 'xlsx' ? $excel->render($data) : $pdf->render($data);
            $name = 'DPE_'.$data['filters']['dataset'].'_'.$data['filters']['from'].'_'.$data['filters']['to'].'.'.$format;

            return response($bytes, 200, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Content-Length' => strlen($bytes), 'X-DPE-Export-Rows' => (string) $data['total']]);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }
            return redirect()->route('exports.index', $data['filters'] ?? ['dataset' => 'students'])->withErrors($e->errors());
        }
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate(['dataset' => ['nullable', Rule::in(array_keys(OperationalExportDefinition::DATASETS))],
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'branch_id' => ['nullable', 'uuid'], 'teacher_id' => ['nullable', 'uuid'], 'program_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:100'], 'grade' => ['nullable', 'string', 'max:255'], 'class_type' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'], 'mode' => ['nullable', Rule::in(['branch_visit', 'branch_day', 'private_session', 'legacy'])],
            'location' => ['nullable', Rule::in(['branch', 'student_home'])], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
    }
}
