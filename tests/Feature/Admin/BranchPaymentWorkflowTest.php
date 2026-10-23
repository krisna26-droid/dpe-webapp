<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchAdminAssignment;
use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use App\Services\BranchPaymentService;
use App\Services\PortalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Support\ZipFormatAssertions;

class BranchPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use ZipFormatAssertions;

    private User $sa;
    private User $admin;
    private Branch $branch;
    private Branch $foreign;
    private MonthlyCharge $charge;
    private MonthlyCharge $secret;

    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'Asia/Makassar'));
        Storage::fake('local'); Http::preventStrayRequests();
        $this->sa = $this->user('superadmin'); $this->admin = $this->user('admin');
        $this->branch = $this->branch('Cabang A Uji'); $this->foreign = $this->branch('Cabang Luar Rahasia');
        BranchAdminAssignment::create(['id' => (string) Str::uuid(), 'admin_user_id' => $this->admin->id,
            'branch_id' => $this->branch->id, 'starts_on' => '2026-01-01']);
        $this->charge = $this->charge($this->branch, 'Siswa A Uji'); $this->secret = $this->charge($this->foreign, 'Siswa Luar Rahasia');
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function user(string $role): User
    {
        return User::create(['id' => (string) Str::uuid(), 'username' => Str::random(12), 'full_name' => 'Pengguna '.$role,
            'role_code' => $role, 'password_hash' => Hash::make('Password123!'), 'is_active' => true]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create(['id' => (string) Str::uuid(), 'code' => Str::random(8), 'name' => $name,
            'timezone_name' => 'Asia/Makassar', 'payment_recap_day' => 20, 'is_active' => true]);
    }

    private function charge(Branch $branch, string $name, string $month = '2026-10-01'): MonthlyCharge
    {
        $student = Student::create(['id' => (string) Str::uuid(), 'portal_user_id' => $this->user('student')->id,
            'full_name' => $name, 'began_on' => '2026-01-01', 'status' => 'active', 'special_notes_internal' => 'RAHASIA INTERNAL']);

        return MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'branch_id' => $branch->id,
            'charge_month' => $month, 'amount_idr' => null]);
    }

    private function upload(MonthlyCharge $charge): PaymentProof
    {
        $portal = app(PortalPaymentService::class);

        return $portal->upload($charge->student->portalUser, $charge, ['image' => UploadedFile::fake()->image('bukti.png'),
            'expected_snapshot' => $portal->chargeSignature($charge)]);
    }

    private function review(PaymentProof $proof, string $status = 'approved'): PaymentProof
    {
        $portal = app(PortalPaymentService::class);

        return $portal->review($this->sa, $proof, ['status' => $status, 'rejection_reason' => 'Alasan dari Super Admin',
            'expected_snapshot' => $portal->proofSignature($proof)]);
    }

    private function recap(array $extra = [], ?User $actor = null): array
    {
        return app(BranchPaymentService::class)->recap($actor ?? $this->admin,
            $extra + ['month' => '2026-10', 'branch_id' => '', 'q' => '', 'status' => 'all']);
    }

    public function test_admin_list_detail_history_and_images_are_scoped_and_read_only(): void
    {
        $proof = $this->review($this->upload($this->charge), 'rejected');
        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()->assertSee('Siswa A Uji')->assertSee('Ditolak')
            ->assertDontSee('Siswa Luar Rahasia')->assertDontSee('Cabang Luar Rahasia')->assertDontSee('RAHASIA INTERNAL');
        $this->get(route('admin.payments.show', $this->charge))->assertOk()->assertSee('Alasan dari Super Admin')->assertSee('Keputusan pembayaran')
            ->assertDontSee('Validasi Pembayaran')->assertDontSee('Tolak Bukti')->assertDontSee('RAHASIA INTERNAL');
        $this->get(route('admin.payments.image', $proof))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('admin.payments.show', $this->secret))->assertForbidden();
        $other = $this->upload($this->secret);
        $this->get(route('admin.payments.image', $other))->assertForbidden();
        $this->get(route('admin.payments.index', ['branch_id' => $this->foreign->id]))->assertForbidden();
        $this->patchJson(route('superadmin.payment-proofs.review', $proof), ['status' => 'approved'])->assertForbidden();
    }

    public function test_states_match_parent_portal_across_rejection_resubmission_and_approval(): void
    {
        $portal = app(PortalPaymentService::class);
        $this->assertSame('empty', $this->recap()['rows'][0]['status_code']);
        $proof = $this->upload($this->charge);
        foreach (['pending', 'rejected', 'approved'] as $status) {
            if ($status === 'rejected') { $this->review($proof, 'rejected'); }
            if ($status === 'approved') { $proof = $this->upload($this->charge); $this->review($proof); }
            $row = $this->recap()['rows'][0];
            $this->assertSame($portal->state($this->charge)['code'], $row['status_code']);
            $this->assertSame($status, $row['status_code']);
        }
        $this->assertSame(2, $this->recap()['rows'][0]['proof_count']);
        $this->review($proof, 'rejected');
        $this->assertSame('rejected', $this->recap()['rows'][0]['status_code']);
    }

    public function test_ambiguous_same_calendar_month_charge_is_flagged_without_foreign_details(): void
    {
        $this->review($this->upload($this->charge));
        MonthlyCharge::create(['id' => (string) Str::uuid(), 'student_id' => $this->charge->student_id, 'branch_id' => $this->foreign->id,
            'charge_month' => '2026-10-21', 'amount_idr' => null]);
        $this->assertSame('ambiguous', $this->recap()['rows'][0]['status_code']);
        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()->assertSee('Perlu Pemeriksaan Admin')->assertDontSee('Cabang Luar Rahasia');
        $this->get(route('admin.payments.show', $this->charge))->assertOk()->assertSee('beberapa tagihan')->assertDontSee('Cabang Luar Rahasia');
    }

    public function test_actual_charge_branch_controls_access_after_pupil_moves(): void
    {
        $this->charge->update(['branch_id' => $this->foreign->id]);
        $this->actingAs($this->admin)->get(route('admin.payments.show', $this->charge))->assertForbidden();
        $this->assertSame(0, $this->recap()['total']);
    }

    public function test_filters_summary_and_pagination_include_zero_search(): void
    {
        $this->charge->student->update(['full_name' => 'Siswa 0 Uji']);
        for ($i = 0; $i < 17; $i++) { $this->charge($this->branch, 'Peserta '.$i); }
        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()
            ->assertViewHas('pageRows', fn ($rows) => $rows->count() === 15 && $rows->total() === 18);
        $this->get(route('admin.payments.index', ['q' => '0', 'status' => 'empty']))->assertOk()
            ->assertViewHas('total', 3)->assertViewHas('summary', fn ($summary) => $summary['empty'] === 3);
        $this->get(route('admin.payments.index', ['month' => '2026-09']))->assertOk()->assertViewHas('total', 0);
        $this->get(route('admin.payments.index', ['status' => 'approved']))->assertOk()->assertViewHas('total', 0);
        $firstRows = array_slice($this->recap()['rows'], 0, 15);
        $this->get(route('admin.payments.index', ['page' => '']))->assertOk()
            ->assertViewHas('pageRows', fn ($pageRows) => $pageRows->items() === $firstRows && $pageRows->currentPage() === 1);
    }

    public function test_recap_date_is_clamped_to_month_and_does_not_create_a_due_date(): void
    {
        $this->branch->update(['payment_recap_day' => 31]);
        $this->charge->update(['charge_month' => '2026-02-15']);
        $row = $this->recap(['month' => '2026-02'])['rows'][0];
        $this->assertSame('2026-02-28', $row['recap']); $this->assertNull($row['due']); $this->assertNull($row['amount']);
        $this->assertDatabaseHas('monthly_charges', ['id' => $this->charge->id, 'due_on' => null, 'amount_idr' => null]);
    }

    public function test_current_assignment_boundaries_and_revocation_are_checked_on_every_request(): void
    {
        $proof = $this->upload($this->charge);
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['starts_on' => '2026-10-14', 'ends_on' => '2026-10-14']);
        $this->actingAs($this->admin)->get(route('admin.payments.show', $this->charge))->assertOk();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['ends_on' => '2026-10-13']);
        $this->get(route('admin.payments.index'))->assertOk()->assertViewHas('total', 0);
        $this->get(route('admin.payments.show', $this->charge))->assertForbidden();
        $this->get(route('admin.payments.image', $proof))->assertForbidden();
        BranchAdminAssignment::where('admin_user_id', $this->admin->id)->update(['starts_on' => '2026-10-15', 'ends_on' => null]);
        $this->get(route('admin.payments.show', $this->charge))->assertForbidden();
    }

    public static function forbiddenRoles(): array { return [['teacher'], ['student']]; }

    #[DataProvider('forbiddenRoles')]
    public function test_teacher_and_student_cannot_use_any_admin_payment_endpoint(string $role): void
    {
        $proof = $this->upload($this->charge);
        $this->actingAs($this->user($role));
        foreach ([route('admin.payments.index'), route('admin.payments.show', $this->charge), route('admin.payments.image', $proof),
            route('admin.payments.download', ['format' => 'xlsx'])] as $url) { $this->get($url)->assertForbidden(); }
    }

    public function test_guest_and_inactive_admin_do_not_get_access(): void
    {
        $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
        $this->admin->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertForbidden();
    }

    public static function badFilters(): array
    {
        return [['month', 'invalid'], ['month', '2026-13'], ['month', '1999-12'], ['month', '2101-01'],
            ['status', 'forged'], ['branch_id', 'not-a-uuid'], ['q', ['array']], ['page', 0]];
    }

    #[DataProvider('badFilters')]
    public function test_invalid_filters_fail_cleanly(string $field, mixed $value): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.payments.index', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_missing_and_tampered_proof_images_are_not_served(): void
    {
        $proof = $this->upload($this->charge); $asset = $proof->imageFile;
        Storage::disk('local')->put($asset->storage_key, 'corrupt');
        $this->actingAs($this->admin)->get(route('admin.payments.image', $proof))->assertNotFound();
        $this->get(route('admin.payments.show', $this->charge))->assertOk()->assertSee('Gambar tidak tersedia');
        Storage::disk('local')->delete($asset->storage_key);
        $this->get(route('admin.payments.image', $proof))->assertNotFound();
    }

    public static function formats(): array { return [['xlsx'], ['pdf']]; }

    #[DataProvider('formats')]
    public function test_export_contains_all_pages_and_only_allowed_branch(string $format): void
    {
        for ($i = 1; $i <= 17; $i++) { $this->charge($this->branch, 'Peserta Rekap '.$i); }
        $data = $this->recap();
        $response = $this->actingAs($this->admin)->get(route('admin.payments.download', ['format' => $format] + $data['filters'] + ['expected_snapshot' => $data['snapshot']]))
            ->assertOk()->assertHeader('X-DPE-Export-Rows', '18');
        $bytes = $response->getContent();
        $this->assertStringStartsWith($format === 'xlsx' ? 'PK' : '%PDF-', $bytes);
        $this->assertStringNotContainsString('Siswa Luar Rahasia', $bytes);
        $this->assertStringNotContainsString('RAHASIA INTERNAL', $bytes);
        if ($format === 'xlsx') {
            $parts = $this->assertStandardZip($bytes);
            $xml = $parts['xl/worksheets/sheet1.xml'];
            $this->assertStringContainsString('Peserta Rekap 17', $xml);
            $this->assertStringNotContainsString('Siswa Luar Rahasia', $xml);
            $this->assertSame(19, substr_count($xml, '<row '));
        }
    }

    public function test_export_snapshot_detects_upload_review_branch_changes_and_forged_filter(): void
    {
        $data = $this->recap(); $proof = $this->upload($this->charge);
        $url = fn ($d) => route('admin.payments.download', ['format' => 'xlsx'] + $d['filters'] + ['expected_snapshot' => $d['snapshot']]);
        $this->actingAs($this->admin)->getJson($url($data))->assertUnprocessable()->assertJsonValidationErrors('export');
        $data = $this->recap(); $this->review($proof);
        $this->getJson($url($data))->assertUnprocessable()->assertJsonValidationErrors('export');
        $data = $this->recap(); $this->branch->update(['payment_recap_day' => 18]);
        $this->getJson($url($data))->assertUnprocessable()->assertJsonValidationErrors('export');
        $data = $this->recap(); $data['filters']['status'] = 'empty';
        $this->getJson($url($data))->assertUnprocessable()->assertJsonValidationErrors('export');
        $this->getJson(route('admin.payments.download', ['format' => 'xlsx']))->assertUnprocessable()->assertJsonValidationErrors('expected_snapshot');
    }

    public function test_empty_export_requires_new_preview_and_superadmin_can_view_all_branches(): void
    {
        $this->assertSame(2, $this->recap(actor: $this->sa)['total']);
        $data = $this->recap(['month' => '2026-09']);
        $this->actingAs($this->admin)->getJson(route('admin.payments.download', ['format' => 'xlsx'] + $data['filters'] + ['expected_snapshot' => $data['snapshot']]))
            ->assertUnprocessable()->assertJsonValidationErrors('export');
    }

    public function test_batch_status_queries_are_bounded_and_get_requests_do_not_write(): void
    {
        for ($i = 0; $i < 30; $i++) { $this->charge($this->branch, 'Peserta '.$i); }
        DB::enableQueryLog(); $data = $this->recap(); $queries = DB::getQueryLog(); DB::disableQueryLog();
        $this->assertSame(31, $data['total']); $this->assertLessThan(20, count($queries));
        $counts = [MonthlyCharge::count(), PaymentProof::count(), DB::table('student_portal_events')->count()];
        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.payments.show', $this->charge))->assertOk();
        $this->assertSame($counts, [MonthlyCharge::count(), PaymentProof::count(), DB::table('student_portal_events')->count()]);
    }
}
