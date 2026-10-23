<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonScheduleSeries;
use App\Models\LessonScheduleSeriesChange;
use App\Models\LessonSession;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class LessonSeriesService
{
    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public function __construct(private BranchOperationsService $operations, private LessonSessionService $sessions,
        private LessonSchedulingService $scheduling) {}

    private function actor(User $actor, bool $lock = false): User
    {
        $current = User::whereKey($actor->id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        abort_unless($current?->is_active && in_array($current->role_code, ['admin', 'superadmin'], true), 403);

        return $current;
    }

    public function authorize(User $actor, LessonScheduleSeries $series): void
    {
        $this->operations->authorizeBranch($this->actor($actor), $series->branch_id);
    }

    public function anchor(User $actor, LessonScheduleSeries $series, string $sessionId): LessonSession
    {
        $this->authorize($actor, $series);

        return $series->sessions()->whereKey($sessionId)->firstOrFail();
    }

    private function definition(array $input): array
    {
        $data = Validator::make($input, [
            'branch_id' => ['required', 'string', Rule::exists('branches', 'id')],
            'teacher_id' => ['required', 'string', Rule::exists('teachers', 'id')],
            'program_id' => ['required', 'string', Rule::exists('programs', 'id')],
            'class_group_id' => ['required', 'string', Rule::exists('class_groups', 'id')],
            'teaching_location' => ['required', Rule::in(['branch', 'student_home'])],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2100-12-31'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'topic' => ['nullable', 'string', 'max:255'], 'material' => ['nullable', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
        ])->validate();
        if ($data['ends_on'] > CarbonImmutable::parse($data['starts_on'])->addMonthsNoOverflow(3)->subDay()->toDateString()) {
            throw ValidationException::withMessages(['ends_on' => 'Satu seri maksimal tiga bulan. Pilih tanggal akhir dalam rentang tersebut.']);
        }
        $data['weekdays'] = array_map('intval', $data['weekdays']);
        sort($data['weekdays']);
        foreach (['topic', 'material', 'activity'] as $field) { $data[$field] = isset($data[$field]) ? trim($data[$field]) ?: null : null; }

        return $data;
    }

    private function exclusions(array $input, array $dates): array
    {
        $data = Validator::make($input, ['excluded_dates' => ['sometimes', 'array', 'max:95'],
            'excluded_dates.*' => ['required', 'date_format:Y-m-d', 'distinct', Rule::in($dates)]])->validate();
        $excluded = $data['excluded_dates'] ?? [];
        sort($excluded);

        return $excluded;
    }

    private function dates(array $data): array
    {
        $dates = [];
        for ($day = CarbonImmutable::parse($data['starts_on']); $day->toDateString() <= $data['ends_on']; $day = $day->addDay()) {
            if (in_array($day->isoWeekday(), $data['weekdays'], true)) { $dates[] = $day->toDateString(); }
        }
        if (! $dates) { throw ValidationException::withMessages(['weekdays' => 'Tidak ada hari pilihan dalam rentang tanggal ini.']); }

        return $dates;
    }

    private function references(User $actor, array $data, bool $lock = false): array
    {
        $this->operations->authorizeBranch($actor, $data['branch_id']);
        // Same Teacher and class locks are also used by ordinary session scheduling.
        $teacher = Teacher::whereKey($data['teacher_id'])->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
        $group = ClassGroup::whereKey($data['class_group_id'])->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
        $branch = Branch::whereKey($data['branch_id'])->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
        $program = Program::whereKey($data['program_id'])->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
        if (! $branch->is_active || ! $group->is_active || ! $program->is_active) {
            throw ValidationException::withMessages(['class_group_id' => 'Cabang, program dan kelompok harus aktif untuk jadwal berulang.']);
        }
        if ($group->branch_id !== $branch->id || $group->program_id !== $program->id) {
            throw ValidationException::withMessages(['class_group_id' => 'Kelompok harus sesuai cabang dan program yang dipilih.']);
        }
        if (! $teacher->is_active || ! $teacher->user?->is_active || $teacher->user?->role_code !== 'teacher') {
            throw ValidationException::withMessages(['teacher_id' => 'Pilih Teacher dengan profil dan akun aktif.']);
        }

        return compact('teacher', 'group', 'branch', 'program');
    }

    private function token(User $actor, array $context): string
    {
        return Crypt::encryptString(json_encode($context + ['actor_id' => $actor->id, 'expires' => now()->addHour()->timestamp,
            'nonce' => (string) Str::uuid()], JSON_THROW_ON_ERROR));
    }

    private function open(User $actor, mixed $token, string $operation): array
    {
        if (! is_string($token) || strlen($token) > 20000) {
            throw ValidationException::withMessages(['preview_token' => 'Buka pratinjau jadwal sebelum menyimpan.']);
        }
        try { $context = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw ValidationException::withMessages(['preview_token' => 'Pratinjau tidak valid. Buat pratinjau baru.']); }
        if (($context['actor_id'] ?? null) !== $actor->id || ($context['operation'] ?? null) !== $operation
            || ($context['expires'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['preview_token' => 'Pratinjau telah kedaluwarsa atau bukan milik akun ini. Buat pratinjau baru.']);
        }

        return $context;
    }

    private function createRows(User $actor, array $data, array $excluded): array
    {
        $refs = $this->references($actor, $data);
        $zone = $refs['branch']->timezone_name ?: config('app.timezone');
        if ($data['starts_on'] < CarbonImmutable::now($zone)->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => 'Jadwal berulang dimulai hari ini atau setelahnya.']);
        }
        $rows = [];
        foreach ($this->dates($data) as $date) {
            $sessionData = array_intersect_key($data, array_flip(['branch_id', 'teacher_id', 'program_id', 'class_group_id', 'teaching_location', 'topic', 'material', 'activity']))
                + ['planned_start_at' => $date.' '.$data['starts_at'].':00', 'planned_end_at' => $date.' '.$data['ends_at'].':00', 'status' => 'scheduled'];
            $issues = [];
            $isExcluded = in_array($date, $excluded, true);
            if (! $isExcluded) {
                try {
                    if (CarbonImmutable::parse($sessionData['planned_start_at'], $zone)->lte(CarbonImmutable::now($zone))) {
                        throw ValidationException::withMessages(['starts_at' => 'Jam pertemuan ini sudah lewat.']);
                    }
                    $this->operations->validateTeacher($actor, $sessionData);
                    $this->scheduling->validate($sessionData);
                } catch (ValidationException $error) { $issues = $error->validator->errors()->all(); }
            }
            $rows[] = ['date' => $date, 'data' => $sessionData, 'excluded' => $isExcluded, 'issues' => $issues, 'preserved' => null];
        }

        return compact('rows', 'refs');
    }

    public function previewCreate(User $actor, array $input): array
    {
        $actor = $this->actor($actor);
        $context = ! empty($input['preview_token']) ? $this->open($actor, $input['preview_token'], 'create')
            : ['operation' => 'create', 'data' => $this->definition($input)];
        $data = $context['data'];
        $excluded = $this->exclusions($input, $this->dates($data));
        $preview = $this->createRows($actor, $data, $excluded);

        return $preview + ['data' => $data, 'excluded' => $excluded, 'operation' => 'create', 'series' => null, 'anchor' => null,
            'previewToken' => $this->token($actor, $context)];
    }

    private function assertRows(array $rows): void
    {
        $selected = array_filter($rows, fn ($row) => ! $row['excluded'] && ! $row['preserved']);
        if (! $selected) { throw ValidationException::withMessages(['schedule_dates' => 'Pilih setidaknya satu pertemuan yang dapat disimpan.']); }
        $errors = [];
        foreach ($selected as $row) {
            if ($row['issues']) { $errors[] = $row['date'].': '.implode(' ', $row['issues']); }
        }
        if ($errors) { throw ValidationException::withMessages(['schedule_dates' => $errors]); }
    }

    public function create(User $actor, array $input): LessonScheduleSeries
    {
        return DB::transaction(function () use ($actor, $input) {
            $actor = $this->actor($actor, true);
            $context = $this->open($actor, $input['preview_token'] ?? null, 'create');
            $data = $context['data'];
            $excluded = $this->exclusions($input, $this->dates($data));
            $this->operations->authorizeBranch($actor, $data['branch_id']);
            $hash = hash('sha256', json_encode([$data, $excluded], JSON_THROW_ON_ERROR));
            if ($existing = LessonScheduleSeries::where('request_key', $context['nonce'])->first()) {
                if (! hash_equals($existing->creation_hash, $hash)) {
                    throw ValidationException::withMessages(['preview_token' => 'Pratinjau ini sudah disimpan dengan pilihan tanggal berbeda. Buat pratinjau baru.']);
                }

                return $existing;
            }
            $this->references($actor, $data, true);
            $preview = $this->createRows($actor, $data, $excluded);
            $this->assertRows($preview['rows']);
            $series = LessonScheduleSeries::create(['id' => (string) Str::uuid(), 'request_key' => $context['nonce'],
                'creation_hash' => $hash, 'created_by_user_id' => $actor->id, 'branch_id' => $data['branch_id'],
                'class_group_id' => $data['class_group_id'], 'definition' => $data + ['excluded_dates' => $excluded]]);
            foreach ($preview['rows'] as $row) {
                if (! $row['excluded']) {
                    $session = $this->sessions->create($row['data']);
                    $session->update(['schedule_series_id' => $series->id, 'series_occurrence_on' => $row['date']]);
                }
            }

            return $series;
        }, 3);
    }

    private function seriesSessions(LessonScheduleSeries $series, bool $lock = false): Collection
    {
        return $series->sessions()->with(['teacher.user', 'branch'])->withCount([
            'teacherAttendance', 'replacementSessions', 'learningAudits', 'studentChallenges', 'teacherMessages',
            'sessionStudents as recorded_students_count' => fn ($q) => $q->where(fn ($q) => $q->whereNotNull('attendance_status')
                ->orWhereNotNull('recorded_at')->orWhereNotNull('individual_learning_note')),
        ])->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
    }

    private function state(Collection $sessions): string
    {
        return hash_hmac('sha256', $sessions->map(fn ($s) => $s->getRawOriginal())->toJson(), (string) config('app.key'));
    }

    private function protectedReason(LessonSession $session, string $zone): ?string
    {
        if (CarbonImmutable::parse($session->getRawOriginal('planned_start_at'), $zone)->lte(CarbonImmutable::now($zone))) { return 'Pertemuan sudah dimulai atau berlalu.'; }
        if ($session->status !== 'scheduled' || $session->actual_start_at || $session->actual_end_at) { return 'Status atau waktu pelaksanaan sudah dicatat.'; }
        if ($session->teacher_attendance_count || $session->recorded_students_count || $session->learning_audits_count
            || $session->student_challenges_count || $session->teacher_messages_count) { return 'Sudah memiliki absensi atau catatan belajar.'; }
        if ($session->rescheduled_from_session_id || $session->replacement_sessions_count) { return 'Memiliki hubungan jadwal pengganti.'; }

        return null;
    }

    private function futureDefinition(array $input): array
    {
        return Validator::make($input, [
            'teacher_id' => ['required', 'string', Rule::exists('teachers', 'id')],
            'planned_start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'planned_end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:planned_start_at'],
            'status' => ['required', Rule::in(['scheduled', 'cancelled'])],
            'topic' => ['nullable', 'string', 'max:255'], 'material' => ['nullable', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
        ])->validate();
    }

    private function futureRows(User $actor, LessonScheduleSeries $series, LessonSession $anchor, array $data, bool $lock = false): array
    {
        $this->authorize($actor, $series);
        $all = $this->seriesSessions($series, $lock);
        $anchor = $all->firstWhere('id', $anchor->id) ?? abort(404);
        $zone = $anchor->branch?->timezone_name ?: config('app.timezone');
        $oldStart = CarbonImmutable::parse($anchor->getRawOriginal('planned_start_at'), $zone);
        if ($this->protectedReason($anchor, $zone)) {
            throw ValidationException::withMessages(['schedule_dates' => 'Pilih pertemuan mendatang yang belum memiliki pelaksanaan atau catatan sebagai awal perubahan seri.']);
        }
        $start = CarbonImmutable::parse($data['planned_start_at'], $zone);
        $end = CarbonImmutable::parse($data['planned_end_at'], $zone);
        if (! $start->isSameDay($end)) { throw ValidationException::withMessages(['planned_end_at' => 'Jam mulai dan selesai jadwal berulang harus pada tanggal yang sama.']); }
        $shift = (int) $oldStart->startOfDay()->diffInDays($start->startOfDay(), false);
        $targets = $all->filter(fn ($s) => CarbonImmutable::parse($s->getRawOriginal('planned_start_at'), $zone)->gte($oldStart))
            ->sortBy(fn ($s) => $s->getRawOriginal('planned_start_at').$s->id)->values();
        $ignore = $targets->filter(fn ($s) => ! $this->protectedReason($s, $zone))->pluck('id')->all();
        $rows = [];
        foreach ($targets as $session) {
            $reason = $this->protectedReason($session, $zone);
            $date = CarbonImmutable::parse($session->getRawOriginal('planned_start_at'), $zone)->addDays($shift)->toDateString();
            $original = $session->only(['branch_id', 'teacher_id', 'program_id', 'class_group_id', 'teaching_location',
                'planned_start_at', 'planned_end_at', 'actual_start_at', 'actual_end_at', 'rescheduled_from_session_id', 'status', 'topic', 'material', 'activity']);
            $proposed = $original;
            if ($data['status'] === 'cancelled') { $proposed['status'] = 'cancelled'; }
            else {
                $proposed = array_replace($proposed, $data, ['planned_start_at' => $date.' '.$start->format('H:i:s'), 'planned_end_at' => $date.' '.$end->format('H:i:s')]);
            }
            if ($reason) { $proposed = $original; }
            $issues = [];
            if (! $reason) {
                try {
                    if ($data['status'] !== 'cancelled' && CarbonImmutable::parse($proposed['planned_start_at'], $zone)->lte(CarbonImmutable::now($zone))) {
                        throw ValidationException::withMessages(['planned_start_at' => 'Tanggal/jam pengganti sudah lewat. Pilih jadwal mendatang.']);
                    }
                    $this->operations->validateTeacher($actor, $proposed, $session);
                    $this->scheduling->validate($proposed, $session, $ignore);
                } catch (ValidationException $error) { $issues = $error->validator->errors()->all(); }
            }
            $rows[] = ['date' => substr((string) $proposed['planned_start_at'], 0, 10), 'data' => $proposed,
                'excluded' => false, 'preserved' => $reason, 'issues' => $issues, 'session' => $session];
        }
        // Excluding the old target positions from conflict checks must not allow new targets to overlap each other.
        foreach ($rows as $i => $row) {
            if ($row['preserved'] || $row['data']['status'] === 'cancelled') { continue; }
            foreach ($rows as $j => $other) {
                if ($j <= $i || $other['preserved'] || $other['data']['status'] === 'cancelled') { continue; }
                if ($row['data']['planned_start_at'] < $other['data']['planned_end_at'] && $row['data']['planned_end_at'] > $other['data']['planned_start_at']) {
                    $rows[$i]['issues'][] = $rows[$j]['issues'][] = 'Waktu bertabrakan dengan pertemuan lain dalam perubahan seri ini.';
                }
            }
        }

        return ['rows' => $rows, 'state' => $this->state($all), 'ignore' => $ignore,
            'refs' => ['branch' => $anchor->branch, 'group' => $series->classGroup, 'teacher' => Teacher::with('user')->find($data['teacher_id']), 'program' => $anchor->program]];
    }

    public function previewFuture(User $actor, LessonScheduleSeries $series, LessonSession $anchor, array $input): array
    {
        $actor = $this->actor($actor);
        $context = ! empty($input['preview_token']) ? $this->open($actor, $input['preview_token'], 'future')
            : ['operation' => 'future', 'data' => $this->futureDefinition($input), 'series_id' => $series->id, 'anchor_id' => $anchor->id];
        abort_unless($context['series_id'] === $series->id && $context['anchor_id'] === $anchor->id, 403);
        $preview = $this->futureRows($actor, $series, $anchor, $context['data']);
        $context['state'] = $preview['state'];

        return $preview + ['data' => $context['data'], 'excluded' => [], 'operation' => 'future', 'series' => $series, 'anchor' => $anchor,
            'previewToken' => $this->token($actor, $context)];
    }

    public function updateFuture(User $actor, LessonScheduleSeries $series, LessonSession $anchor, array $input): LessonScheduleSeries
    {
        return DB::transaction(function () use ($actor, $series, $anchor, $input) {
            $actor = $this->actor($actor, true);
            $series = LessonScheduleSeries::whereKey($series->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $series);
            $context = $this->open($actor, $input['preview_token'] ?? null, 'future');
            abort_unless($context['series_id'] === $series->id && $context['anchor_id'] === $anchor->id, 403);
            if (LessonScheduleSeriesChange::where('request_key', $context['nonce'])->exists()) { return $series; }
            $teacherIds = $series->sessions()->pluck('teacher_id')->push($context['data']['teacher_id'])->unique()->sort()->values();
            Teacher::whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();
            ClassGroup::whereKey($series->class_group_id)->lockForUpdate()->firstOrFail();
            $preview = $this->futureRows($actor, $series, $anchor, $context['data'], true);
            if (! hash_equals($context['state'], $preview['state'])) {
                throw ValidationException::withMessages(['preview_token' => 'Data seri berubah sejak pratinjau. Periksa pratinjau terbaru sebelum menyimpan.']);
            }
            $this->assertRows($preview['rows']);
            $before = $after = [];
            foreach ($preview['rows'] as $row) {
                if ($row['preserved']) { continue; }
                $session = $row['session'];
                $before[] = $session->only(['id', 'teacher_id', 'planned_start_at', 'planned_end_at', 'status', 'topic', 'material', 'activity']);
                $updated = $this->sessions->update($session, $row['data'], $preview['ignore']);
                $after[] = $updated->only(['id', 'teacher_id', 'planned_start_at', 'planned_end_at', 'status', 'topic', 'material', 'activity']);
            }
            LessonScheduleSeriesChange::create(['id' => (string) Str::uuid(), 'series_id' => $series->id, 'actor_user_id' => $actor->id,
                'request_key' => $context['nonce'], 'before_snapshot' => $before, 'after_snapshot' => $after, 'created_at' => now()]);

            return $series;
        }, 3);
    }
}
