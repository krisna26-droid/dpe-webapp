<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\PaymentRecapRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRecapRunModelTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $role = 'superadmin'
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $role . '_' . Str::random(8),
            'email' => Str::uuid() . '@example.test',
            'password_hash' => password_hash(
                'password',
                PASSWORD_BCRYPT
            ),
            'full_name' => 'Test ' . ucfirst($role),
            'role_code' => $role,
            'is_active' => true,
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'timezone_name' => 'Asia/Makassar',
            'payment_recap_day' => 25,
            'is_active' => true,
        ]);
    }

    private function createFileAsset(): FileAsset
    {
        return FileAsset::query()->create([
            'id' => (string) Str::uuid(),
            'storage_key' => 'payment-recaps/' . Str::uuid() . '.pdf',
            'original_name' => 'payment-recap.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'sha256_hex' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
        ]);
    }

    private function createPaymentRecapRun(
        Branch $branch
    ): PaymentRecapRun {
        return PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'scheduled',
            'generated_at' => null,
            'generated_by_user_id' => null,
            'export_file_id' => null,
        ]);
    }

    public function test_payment_recap_run_uses_expected_table(): void
    {
        $model = new PaymentRecapRun();

        $this->assertSame(
            'payment_recap_runs',
            $model->getTable()
        );
    }

    public function test_payment_recap_run_does_not_use_incrementing_id(): void
    {
        $model = new PaymentRecapRun();

        $this->assertFalse(
            $model->getIncrementing()
        );

        $this->assertSame(
            'string',
            $model->getKeyType()
        );
    }

    public function test_payment_recap_run_has_expected_fillable_fields(): void
    {
        $model = new PaymentRecapRun();

        $this->assertSame(
            [
                'id',
                'branch_id',
                'recap_month',
                'scheduled_on',
                'status',
                'generated_at',
                'generated_by_user_id',
                'export_file_id',
            ],
            $model->getFillable()
        );
    }

    public function test_payment_recap_run_does_not_use_timestamps(): void
    {
        $model = new PaymentRecapRun();

        $this->assertNull(
            $model->getCreatedAtColumn()
        );

        $this->assertNull(
            $model->getUpdatedAtColumn()
        );
    }

    public function test_payment_recap_run_casts_date_and_datetime_fields(): void
    {
        $model = new PaymentRecapRun();

        $this->assertSame(
            'date',
            $model->getCasts()['recap_month']
        );

        $this->assertSame(
            'date',
            $model->getCasts()['scheduled_on']
        );

        $this->assertSame(
            'datetime',
            $model->getCasts()['generated_at']
        );
    }

    public function test_payment_recap_run_belongs_to_branch(): void
    {
        $branch = $this->createBranch();

        $run = $this->createPaymentRecapRun(
            $branch
        );

        $this->assertTrue(
            $run->branch->is($branch)
        );
    }

    public function test_payment_recap_run_can_have_generated_by_user(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $run = PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'generated',
            'generated_at' => '2026-10-25 10:00:00',
            'generated_by_user_id' => $user->id,
            'export_file_id' => null,
        ]);

        $this->assertTrue(
            $run->generatedBy->is($user)
        );
    }

    public function test_payment_recap_run_can_have_export_file(): void
    {
        $branch = $this->createBranch();
        $file = $this->createFileAsset();

        $run = PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'generated',
            'generated_at' => '2026-10-25 10:00:00',
            'generated_by_user_id' => null,
            'export_file_id' => $file->id,
        ]);

        $this->assertTrue(
            $run->exportFile->is($file)
        );
    }

    public function test_branch_has_many_payment_recap_runs(): void
    {
        $branch = $this->createBranch();

        $this->createPaymentRecapRun(
            $branch
        );

        $this->assertCount(
            1,
            $branch->paymentRecapRuns
        );
    }

    public function test_user_has_generated_payment_recap_runs(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $run = PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'generated',
            'generated_at' => '2026-10-25 10:00:00',
            'generated_by_user_id' => $user->id,
            'export_file_id' => null,
        ]);

        $this->assertCount(
            1,
            $user->generatedPaymentRecapRuns
        );

        $this->assertTrue(
            $user->generatedPaymentRecapRuns
                ->first()
                ->is($run)
        );
    }

    public function test_file_asset_has_many_payment_recap_runs(): void
    {
        $branch = $this->createBranch();
        $file = $this->createFileAsset();

        $run = PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'generated',
            'generated_at' => '2026-10-25 10:00:00',
            'generated_by_user_id' => null,
            'export_file_id' => $file->id,
        ]);

        $this->assertCount(
            1,
            $file->paymentRecapRuns
        );

        $this->assertTrue(
            $file->paymentRecapRuns
                ->first()
                ->is($run)
        );
    }

    public function test_status_is_not_restricted_to_assumed_values(): void
    {
        $branch = $this->createBranch();

        $run = PaymentRecapRun::query()->create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'recap_month' => '2026-10-01',
            'scheduled_on' => '2026-10-25',
            'status' => 'custom-status-from-schema',
            'generated_at' => null,
            'generated_by_user_id' => null,
            'export_file_id' => null,
        ]);

        $this->assertSame(
            'custom-status-from-schema',
            $run->status
        );
    }
}
