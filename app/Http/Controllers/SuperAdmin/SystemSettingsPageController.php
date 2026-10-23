<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSystemSettingsPageRequest;
use App\Services\DashboardSettingsService;
use Illuminate\Http\Request;

class SystemSettingsPageController extends Controller
{
    public function index(Request $request, DashboardSettingsService $settings)
    {
        $settings->actor($request->user(), global: true);
        $setting = $settings->setting();

        return view('superadmin.system-settings.index', ['setting' => $setting, 'recordVersion' => $settings->version($setting)]);
    }

    public function update(SaveSystemSettingsPageRequest $request, DashboardSettingsService $settings)
    {
        $settings->saveSystem($request->user(), $request->validated());

        return redirect()->route('superadmin.settings.index')->with('success', 'Pengaturan sistem berhasil disimpan.');
    }
}
