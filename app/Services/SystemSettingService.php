<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemSettingService
{
    public function get(): SystemSetting
    {
        return SystemSetting::query()
            ->where('id', 1)
            ->firstOrFail();
    }

    public function update(array $data): SystemSetting
    {
        return DB::transaction(function () use ($data) {
            $setting = SystemSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $before = $setting->getAttributes();

            $setting->update([
                'organization_name' => $data['organization_name'],
                'organization_description' => $data['organization_description'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'logo_file_id' => $data['logo_file_id'] ?? null,
                'default_report_due_day' => $data['default_report_due_day'],
                'default_monthly_video_target' => $data['default_monthly_video_target'],
                'in_app_reminders_enabled' => $data['in_app_reminders_enabled'],
            ]);
            $setting->forceFill(['updated_at' => now()])->save();
            if (Schema::hasTable('student_portal_events') && ($actor = User::find(auth()->id()))) {
                app(PortalPaymentService::class)->event($actor, 'system_settings_changed', before: $before, after: $setting->getAttributes());
            }

            return $setting->refresh();
        });
    }
}
