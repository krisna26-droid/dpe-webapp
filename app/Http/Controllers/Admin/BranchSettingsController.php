<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBranchAddressRequest;
use App\Models\Branch;
use App\Services\DashboardSettingsService;
use Illuminate\Http\Request;

class BranchSettingsController extends Controller
{
    public function index(Request $request, DashboardSettingsService $settings)
    {
        return view('admin.branch-settings.index', ['branches' => $settings->branches($request->user())->orderBy('name')->get()]);
    }

    public function edit(Request $request, Branch $branch, DashboardSettingsService $settings)
    {
        $branch = $settings->branch($request->user(), $branch->id, editable: true);

        return view('admin.branch-settings.edit', ['branch' => $branch, 'recordVersion' => $settings->version($branch)]);
    }

    public function update(SaveBranchAddressRequest $request, Branch $branch, DashboardSettingsService $settings)
    {
        $settings->saveAddress($request->user(), $branch, $request->validated());

        return redirect()->route('admin.branch-settings.index')->with('success', 'Alamat cabang berhasil diperbarui.');
    }
}
