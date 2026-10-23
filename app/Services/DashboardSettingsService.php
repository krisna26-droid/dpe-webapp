<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\SystemSetting;
use App\Models\StudentPortalEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardSettingsService
{
    public function __construct(private OperationsAccessService $access, private StudentProgressAccessService $monthly) {}

    public function actor(User $user, bool $global = false): User
    {
        $actor = $this->access->actor($user);
        abort_unless(in_array($actor->role_code, $global ? ['superadmin'] : ['admin', 'superadmin'], true), 403);

        return $actor;
    }

    public function branches(User $user): Builder
    {
        $actor = $this->actor($user);

        return Branch::query()->when($actor->role_code === 'admin', fn ($q) => $q->whereIn('id', $this->monthly->branchIds($actor)));
    }

    public function branch(User $user, string $id, bool $editable = false): Branch
    {
        $branch = $this->branches($user)->whereKey($id)->first();
        abort_unless($branch && (! $editable || $branch->is_active), 403);

        return $branch;
    }

    public function setting(): SystemSetting
    {
        return SystemSetting::find(1) ?? new SystemSetting(['id' => 1, 'organization_name' => 'Dharma Private English',
            'default_report_due_day' => 31, 'default_monthly_video_target' => 4, 'in_app_reminders_enabled' => true]);
    }

    public function version(Branch|SystemSetting $record): string
    {
        $values = $record->exists ? $record->getRawOriginal() : ['not_configured' => true];
        ksort($values);
        $events = StudentPortalEvent::query()->when($record instanceof Branch,
            fn ($q) => $q->where('branch_id', $record->id)->whereIn('event_type', ['branch_address_changed', 'branch_settings_changed']),
            fn ($q) => $q->whereNull('branch_id')->where('event_type', 'system_settings_changed'))
            ->orderByDesc('occurred_at')->orderByDesc('id')->first();

        return hash_hmac('sha256', $record::class.'|'.json_encode([$values, $events?->id], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function checkVersion(Branch|SystemSetting $record, array $data): void
    {
        if (! hash_equals($this->version($record), (string) ($data['record_version'] ?? ''))) {
            throw ValidationException::withMessages(['record_version' => 'Data telah berubah sejak halaman dibuka. Muat ulang halaman, periksa data terbaru, lalu simpan kembali.']);
        }
    }

    public function saveSystem(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->actor($actor, global: true);
            $setting = SystemSetting::whereKey(1)->lockForUpdate()->first() ?? $this->setting();
            $this->checkVersion($setting, $data);
            $before = $setting->exists ? $setting->getAttributes() : null;
            $values = array_intersect_key($data, array_flip(['organization_name', 'organization_description', 'contact_email',
                'contact_phone', 'default_report_due_day', 'default_monthly_video_target', 'in_app_reminders_enabled']));
            $values['updated_at'] = now();
            if ($setting->exists) {
                // Keep the existing logo reference; the form cannot submit asset identifiers.
                $setting->forceFill($values)->save();
            } elseif (! DB::table('system_settings')->insertOrIgnore(['id' => 1, ...$values])) {
                throw ValidationException::withMessages(['record_version' => 'Pengaturan baru saja disiapkan oleh petugas lain. Muat ulang dan periksa data terbaru.']);
            }
            app(PortalPaymentService::class)->event($actor, 'system_settings_changed', before: $before,
                after: SystemSetting::findOrFail(1)->getAttributes());
        }, 3);
    }

    public function saveAddress(User $user, Branch $branch, array $data): void
    {
        DB::transaction(function () use ($user, $branch, $data) {
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->actor($actor);
            $current = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            if ($actor->role_code === 'admin') {
                $assignment = BranchAdminAssignment::where('admin_user_id', $actor->id)->where('branch_id', $current->id)
                    ->whereDate('starts_on', '<=', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                    ->lockForUpdate()->first();
                abort_unless($assignment, 403);
            }
            $this->branch($actor, $current->id, editable: true);
            $this->checkVersion($current, $data);
            $before = ['address' => $current->address];
            $current->update(['address' => $data['address']]);
            app(PortalPaymentService::class)->event($actor, 'branch_address_changed', before: $before,
                after: ['address' => $current->address], branch: $current);
        }, 3);
    }
}
