<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Services\StaffDashboardService;

class DashboardController extends Controller
{
    public function index(Request $request, StaffDashboardService $service): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^(20[0-9]{2}|2100)-(0[1-9]|1[0-2])$/'],
        ]);

        return view('superadmin.dashboard', $service->data($request->user(), $filters));
    }
}
