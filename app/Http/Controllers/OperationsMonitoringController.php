<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReportContentService;
use App\Services\OperationsMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class OperationsMonitoringController extends Controller
{
    public function index(Request $request, OperationsMonitoringService $service)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'regex:/^(20[0-9]{2}|2100)-(0[1-9]|1[0-2])$/'],
            'branch_id' => ['nullable', 'uuid'], 'teacher_id' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...array_keys(MonthlyReportContentService::STATUSES)])],
            'final_status' => ['nullable', Rule::in(['all', ...array_keys(MonthlyReportContentService::STATUSES)])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'final_page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $monitoring = $service->data($request->user(), $data);
        foreach (['rows' => 'page', 'finalRows' => 'final_page'] as $key => $pageKey) {
            $rows = $monitoring[$key];
            $page = (int) ($data[$pageKey] ?? 1);
            $monitoring[$key] = new LengthAwarePaginator($rows->forPage($page, 15)->values(), $rows->count(), 15, $page,
                ['path' => route('monitoring.index'), 'query' => $request->except($pageKey), 'pageName' => $pageKey, 'fragment' => $key]);
        }

        return view('operations.monitoring', $monitoring + ['statusLabels' => MonthlyReportContentService::STATUSES]);
    }
}
