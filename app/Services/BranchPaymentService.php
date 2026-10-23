<?php

namespace App\Services;

use App\Models\MonthlyCharge;
use App\Models\PaymentProof;
use App\Models\StudentPortalEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchPaymentService
{
    public function __construct(private DashboardSettingsService $settings, private PortalPaymentService $payments) {}

    public function charges(User $user): Builder
    {
        return MonthlyCharge::whereIn('branch_id', $this->settings->branches($user)->select('id'));
    }

    public function charge(User $user, string $id): MonthlyCharge
    {
        $charge = $this->charges($user)->whereKey($id)->with(['student', 'branch'])->first();
        abort_unless($charge, 403);

        return $charge;
    }

    public function proof(User $user, string $id): PaymentProof
    {
        $this->settings->actor($user);
        $proof = PaymentProof::whereKey($id)->whereHas('charge', fn ($q) => $q->whereIn('id', $this->charges($user)->select('id')))
            ->with('imageFile')->first();
        abort_unless($proof, 403);

        return $proof;
    }

    public function filters(Request $request): array
    {
        $this->settings->actor($request->user());
        $valid = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
            'branch_id' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...array_keys(PortalPaymentService::LABELS)])],
            'page' => ['nullable', 'integer', 'between:1,100000'],
        ]);
        if (! empty($valid['branch_id'])) { $this->settings->branch($request->user(), $valid['branch_id']); }

        return ['month' => $valid['month'] ?? today()->format('Y-m'), 'branch_id' => $valid['branch_id'] ?? '',
            'q' => trim($valid['q'] ?? ''), 'status' => $valid['status'] ?? 'all'];
    }

    public function recap(User $user, array $filters): array
    {
        $actor = $this->settings->actor($user);
        $branches = $this->settings->branches($actor)->orderBy('name')->get();
        if ($filters['branch_id'] !== '') { $this->settings->branch($actor, $filters['branch_id']); }
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month']);
        $query = $this->charges($actor)->whereBetween('charge_month', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($filters['branch_id'] !== '', fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->when($filters['q'] !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where('full_name', 'like', '%'.$filters['q'].'%')));
        if ((clone $query)->count() > OperationalExportDefinition::MAX_ROWS) {
            throw ValidationException::withMessages(['payments' => 'Hasil lebih dari 3.000 tagihan. Pilih cabang atau gunakan pencarian siswa untuk mempersempit hasil.']);
        }
        $charges = $query->with(['student', 'branch'])->orderBy('branch_id')->orderBy('student_id')->orderBy('id')->get();
        $states = $this->payments->states($charges);
        $charges = $charges->filter(fn ($charge) => $filters['status'] === 'all' || $states[$charge->id]['code'] === $filters['status'])->values();
        $rows = $charges->map(function ($charge) use ($states, $month) {
            $day = min(max((int) $charge->branch->payment_recap_day, 1), $month->daysInMonth);

            return ['id' => $charge->id, 'student' => $charge->student?->full_name ?? 'Siswa tidak tersedia',
                'branch' => $charge->branch->name, 'month' => $charge->charge_month->format('Y-m-01'),
                'status' => $states[$charge->id]['label'], 'status_code' => $states[$charge->id]['code'],
                'amount' => $charge->amount_idr === null ? null : 'Rp '.number_format((float) $charge->amount_idr, 2, ',', '.'),
                'due' => $charge->due_on?->toDateString(), 'recap' => $month->setDay($day)->toDateString(),
                'proof_count' => $states[$charge->id]['proofs']->count()];
        })->all();
        $summary = array_fill_keys(array_keys(PortalPaymentService::LABELS), 0);
        foreach ($rows as $row) { $summary[$row['status_code']]++; }
        $snapshot = hash_hmac('sha256', json_encode([$actor->id, $actor->role_code, $branches->pluck('id'), $filters, $rows,
            $charges->map(fn ($c) => [$c->getRawOriginal(), $states[$c->id]['proofs']->map->getRawOriginal()->all()])->all()], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $fields = [['student', 'Siswa', 29, 'text'], ['branch', 'Cabang', 24, 'text'], ['month', 'Bulan', 18, 'month'],
            ['status', 'Status pembayaran', 30, 'text'], ['amount', 'Nominal tagihan', 22, 'text'], ['due', 'Jatuh tempo tagihan', 22, 'date'],
            ['recap', 'Tanggal rekap', 19, 'date'], ['proof_count', 'Jumlah bukti', 16, 'integer']];

        return compact('actor', 'branches', 'filters', 'rows', 'summary', 'snapshot') + ['total' => count($rows),
            'columns' => array_map(fn ($f) => ['key' => $f[0], 'label' => $f[1], 'width' => $f[2], 'type' => $f[3]], $fields),
            'title' => 'Rekap pembayaran cabang', 'generated' => now()->format('d/m/Y H:i:s'),
            'description' => 'Tagihan pada bulan dan cabang sesuai hak akses. Status mengikuti bukti pembayaran dan keputusan Super Admin. Tanggal rekap mengikuti pengaturan cabang; tanggal ini bukan jatuh tempo. Nominal dan jatuh tempo hanya ditampilkan bila tersedia.',
            'filterLabels' => ['Bulan' => $month->format('m/Y'), 'Cabang' => $filters['branch_id'] !== '' ? $branches->firstWhere('id', $filters['branch_id'])->name : 'Seluruh cabang dalam akses',
                'Status' => PortalPaymentService::LABELS[$filters['status']] ?? 'Semua status', 'Pencarian siswa' => $filters['q'] !== '' ? $filters['q'] : 'Semua siswa']];
    }

    public function detail(User $user, string $id): array
    {
        $charge = $this->charge($user, $id);
        $state = $this->payments->state($charge);
        $proofs = $state['proofs']->load(['uploadedBy', 'reviewedBy', 'imageFile']);
        $imageAvailable = $proofs->mapWithKeys(fn ($p) => [$p->id => $this->payments->imageBytes($p) !== null]);
        $events = StudentPortalEvent::where('charge_id', $charge->id)->whereIn('event_type', ['payment_uploaded', 'payment_reviewed'])
            ->with('actor')->orderByDesc('occurred_at')->orderByDesc('id')->get();

        return compact('charge', 'state', 'proofs', 'imageAvailable', 'events');
    }
}
