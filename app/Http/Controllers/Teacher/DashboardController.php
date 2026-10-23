<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Services\StaffDashboardService;

class DashboardController extends Controller
{
    public function index(Request $request, StaffDashboardService $service): View
    {
        return view('teacher.dashboard', $service->data($request->user()));
    }
}
