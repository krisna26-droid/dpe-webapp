<?php

namespace App\Services;

use App\Models\LearningSkill;
use App\Models\LearningVideo;
use App\Models\MonthlyProgressAudit;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportSkill;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentProgressService
{
    public const CATEGORIES = [
        'speaking' => 'Speaking', 'listening' => 'Listening', 'reading' => 'Reading', 'writing' => 'Writing',
        'vocabulary' => 'Vocabulary', 'grammar' => 'Grammar', 'pronunciation' => 'Pronunciation',
        'confidence_participation' => 'Confidence/Participation',
    ];

    public const TRENDS = ['Improving', 'Stable', 'Needs Improvement'];

    public const CONTENT = [
        'development_summary' => 'Ringkasan perkembangan', 'parent_challenges_summary' => 'Challenges untuk orang tua',
        'parent_message' => 'Teacher’s Message', 'internal_teacher_note' => 'Catatan internal bulanan',
    ];

    public function __construct(private StudentProgressAccessService $access, private StudentAttendanceService $attendance) {}

    public static function categoryKey(string $name): string
    {
        return preg_replace('/[^a-z]/', '', strtolower($name));
    }

    public function categories(): Collection
    {
        $all = LearningSkill::orderBy('id')->get();

        return collect(self::CATEGORIES)->map(fn ($name, $code) => $all->first(fn ($s) => strtolower($s->code) === $code)
            ?? $all->first(fn ($s) => self::categoryKey($s->name) === self::categoryKey($name)));
    }

    public function preparationContext(User $actor, Student $student, CarbonImmutable $month): array
    {
        abort_unless($this->access->canManage($actor), 403);
        abort_unless($this->access->students($actor, $month)->whereKey($student->id)->exists(), 403);
        $start = max($month->toDateString(), $student->began_on->toDateString());
        $enrollments = StudentEnrollment::where('student_id', $student->id)->whereDate('starts_on', '<=', $start)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))->get();
        if ($enrollments->isEmpty()) {
            $first = StudentEnrollment::where('student_id', $student->id)->whereDate('starts_on', '>=', $start)->whereDate('starts_on', '<=', $month->endOfMonth()->toDateString())->min('starts_on');
            if ($first) {
                $start = CarbonImmutable::parse($first)->toDateString();
                $enrollments = StudentEnrollment::where('student_id', $student->id)->whereDate('starts_on', '<=', $start)
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))->get();
            }
        }
        if ($enrollments->isEmpty() || $enrollments->pluck('branch_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['month' => 'Periksa pendaftaran siswa: diperlukan satu cabang yang jelas pada awal periode belajarnya.']);
        }
        $enrollment = $enrollments->first();
        abort_unless($actor->role_code === 'superadmin' || $this->access->branchIds($actor)->where('branch_id', $enrollment->branch_id)->exists(), 403);
        $teacherContext = app(StudentProgressTeacherService::class)->context($student, $month, $enrollment->branch_id);

        return $teacherContext + ['enrollment' => $enrollment->load('branch'), 'startsOn' => $start,
            'firstPartialMonth' => $student->began_on->format('Y-m') === $month->format('Y-m') && $student->began_on->day > 1,
            'defaultTarget' => SystemSetting::first()?->default_monthly_video_target ?? 4];
    }

    public function prepare(User $actor, Student $student, CarbonImmutable $month, array $data): MonthlyReport
    {
        return DB::transaction(function () use ($actor, $student, $month, $data) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canManage($actor), 403);
            $existing = MonthlyReport::where('student_id', $student->id)->whereDate('report_month', $month->toDateString())->lockForUpdate()->first();
            if ($existing) {
                $this->access->authorize($actor, $existing);

                return $existing;
            }
            $context = $this->preparationContext($actor, $student, $month);
            $validated = Validator::make($data, [
                'teacher_id' => ['required', 'string', Rule::in($context['teachers']->pluck('id')->all())],
                'video_target' => [$context['firstPartialMonth'] ? 'required' : 'nullable', 'integer', 'min:0', 'max:32767'],
            ], ['teacher_id.in' => 'Pilih Teacher aktif yang mengajar siswa ini atau ditugaskan di cabang selama periode belajarnya pada bulan ini.',
                'video_target.required' => 'Admin perlu menetapkan target bulan pertama sesuai pertemuan yang dijadwalkan.'])->validate();
            $cycle = ReportCycle::where('student_id', $student->id)->whereDate('start_month', '<=', $month->toDateString())
                ->whereDate('end_month', '>=', $month->toDateString())->lockForUpdate()->get();
            if ($cycle->count() > 1) {
                throw ValidationException::withMessages(['month' => 'Siklus laporan siswa bertumpang tindih. Periksa siklus sebelum menyiapkan bulan ini.']);
            }
            $cycle = $cycle->first();
            if (! $cycle) {
                $join = CarbonImmutable::parse($student->began_on->toDateString())->startOfMonth();
                $offset = ($month->year - $join->year) * 12 + $month->month - $join->month;
                $cycleStart = $join->addMonths(intdiv($offset, 3) * 3);
                $cycleEnd = $cycleStart->addMonths(2);
                if (ReportCycle::where('student_id', $student->id)->whereDate('start_month', '<=', $cycleEnd->toDateString())
                    ->whereDate('end_month', '>=', $cycleStart->toDateString())->exists()) {
                    throw ValidationException::withMessages(['month' => 'Rentang tiga bulan bertabrakan dengan siklus yang sudah ada. Periksa siklus siswa terlebih dahulu.']);
                }
                $cycle = ReportCycle::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $student->id,
                    'cycle_number' => max(intdiv($offset, 3) + 1, (int) ReportCycle::where('student_id', $student->id)->max('cycle_number') + 1),
                    'start_month' => $cycleStart->toDateString(), 'end_month' => $cycleEnd->toDateString()]);
            }
            $settings = SystemSetting::first();
            $target = $validated['video_target'] ?? $context['defaultTarget'];
            if ($target < 0 || $target > 32767) {
                throw ValidationException::withMessages(['video_target' => 'Target video pada pengaturan tidak valid.']);
            }
            $report = MonthlyReport::forceCreate(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'cycle_id' => $cycle->id,
                'branch_id' => $context['enrollment']->branch_id, 'teacher_id' => $validated['teacher_id'],
                'report_month' => $month->toDateString(), 'due_on' => $month->setDay(min(max($settings?->default_report_due_day ?? 31, 1), $month->daysInMonth))->toDateString(),
                'video_target' => $target, 'video_target_confirmed_at' => isset($validated['video_target']) ? now() : null,
                'video_target_confirmed_by_user_id' => isset($validated['video_target']) ? $actor->id : null, 'status' => 'draft']);
            $this->audit($actor, $report, 'prepared', null);

            return $report;
        });
    }

    public function videoRows(MonthlyReport $report): Collection
    {
        return LearningVideo::where('student_id', $report->student_id)
            ->whereDate('video_on', '>=', $report->report_month->toDateString())
            ->whereDate('video_on', '<=', $report->report_month->copy()->endOfMonth()->toDateString())
            ->with(['teacher.user', 'session.classGroup'])->orderByDesc('video_on')->orderBy('id')->get();
    }

    public function validVideo(MonthlyReport $report, LearningVideo $video): bool
    {
        $report->loadMissing(['student', 'branch']);

        return $video->video_on->toDateString() >= $report->student->began_on->toDateString()
            && $video->video_on->toDateString() <= $this->today($report) && self::safeUrl($video->video_url);
    }

    public static function safeUrl(?string $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    public function today(MonthlyReport $report): string
    {
        $report->loadMissing('branch');
        $zone = $report->branch?->timezone_name;

        return now(in_array($zone, timezone_identifiers_list(), true) ? $zone : config('app.timezone'))->toDateString();
    }

    public function targetReady(MonthlyReport $report): bool
    {
        $report->loadMissing('student');

        return $report->student->began_on->format('Y-m') !== $report->report_month->format('Y-m')
            || $report->student->began_on->day === 1 || $report->video_target_confirmed_at !== null;
    }

    public function blockReason(MonthlyReport $report): ?string
    {
        $report->loadMissing('student');
        if ($report->report_month->copy()->endOfMonth()->toDateString() < $report->student->began_on->toDateString()) {
            return 'Periode ini berada sebelum siswa mulai les. Data lama dipertahankan untuk pemeriksaan petugas.';
        }
        if (! in_array($report->status, ['draft', 'revision'], true)) {
            return 'Isi periode ini dikunci karena sudah diajukan, disetujui, atau memiliki status yang perlu diperiksa.';
        }
        if ($report->report_month->toDateString() > $this->today($report)) {
            return 'Periode ini belum berlangsung. Isian pembelajaran dapat dilengkapi setelah bulan dimulai.';
        }

        return null;
    }

    public function snapshot(MonthlyReport $report): array
    {
        $snapshot = ['report' => $report->only(['id', 'student_id', 'branch_id', 'teacher_id', 'cycle_id', 'report_month', 'status', 'video_target',
            'video_target_confirmed_at', 'video_target_confirmed_by_user_id', ...array_keys(self::CONTENT)]),
            'skills' => $report->reportSkills()->orderBy('skill_id')->get(['skill_id', 'trend', 'description'])->toArray(),
            'videos' => $this->videoRows($report)->map(fn ($v) => $v->only(['id', 'teacher_id', 'session_id', 'video_on', 'topic', 'description', 'video_url']))->all()];

        // Compare stable scalar values, including dates, rather than Carbon object identities.
        return json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function signature(MonthlyReport $report): string
    {
        $report->loadMissing('student');

        return hash('sha256', json_encode([$this->snapshot($report), $report->student->began_on->toDateString(),
            $this->categories()->map(fn ($s) => $s?->only(['id', 'is_active']))], JSON_THROW_ON_ERROR));
    }

    private function lock(User $actor, MonthlyReport $report, array $data, bool $target = false): MonthlyReport
    {
        $report = MonthlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        $this->access->authorize($actor, $report);
        $report->loadMissing('student');
        if ($report->report_month->copy()->endOfMonth()->toDateString() < $report->student->began_on->toDateString()) {
            throw ValidationException::withMessages(['status' => 'Periode berada sebelum siswa mulai les.']);
        }
        $reason = $target && in_array($report->status, ['draft', 'revision'], true) ? null : $this->blockReason($report);
        if ($reason) {
            throw ValidationException::withMessages(['status' => $reason]);
        }
        $validated = Validator::make($data, ['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();
        if (! hash_equals($this->signature($report), $validated['expected_snapshot'])) {
            throw ValidationException::withMessages(['expected_snapshot' => 'Data periode berubah sejak halaman dibuka. Muat data terbaru sebelum menyimpan kembali.']);
        }

        return $report;
    }

    public function save(User $actor, MonthlyReport $report, array $data): bool
    {
        return DB::transaction(function () use ($actor, $report, $data) {
            $report = $this->lock($actor, $report, $data);
            $categories = $this->categories();
            if ($categories->contains(fn ($s) => ! $s || ! $s->is_active)) {
                throw ValidationException::withMessages(['skills' => 'Delapan kategori progres perlu tersedia dan aktif. Super Admin dapat memeriksa Kompetensi & Skill.']);
            }
            if (array_diff(array_keys((array) ($data['skills'] ?? [])), array_keys(self::CATEGORIES)) !== []) {
                throw ValidationException::withMessages(['skills' => 'Kategori progres tidak dikenali.']);
            }
            $rules = ['skills' => ['required', 'array', 'size:8']];
            foreach (self::CATEGORIES as $code => $name) {
                $rules["skills.$code"] = ['present', 'array:trend,description'];
                $rules["skills.$code.trend"] = ['present', 'nullable', Rule::in(self::TRENDS)];
                $rules["skills.$code.description"] = ['present', 'nullable', 'string', 'max:4000'];
            }
            foreach (self::CONTENT as $key => $label) {
                $rules[$key] = ['present', 'nullable', 'string', 'max:8000'];
            }
            $validated = Validator::make($data, $rules)->validate();
            foreach ($validated['skills'] as $code => $values) {
                if ((filled($values['trend']) && blank($values['description'])) || (blank($values['trend']) && filled($values['description']))) {
                    throw ValidationException::withMessages(["skills.$code.description" => 'Isi status dan deskripsi bersama, atau kosongkan keduanya untuk melanjutkan draf nanti.']);
                }
            }
            $before = $this->snapshot($report);
            foreach ($categories as $code => $skill) {
                $values = $validated['skills'][$code];
                $query = MonthlyReportSkill::where('report_id', $report->id)->where('skill_id', $skill->id);
                if (blank($values['trend'])) {
                    $query->delete();
                } else {
                    DB::table('monthly_report_skills')->updateOrInsert(['report_id' => $report->id, 'skill_id' => $skill->id],
                        ['trend' => $values['trend'], 'description' => trim($values['description'])]);
                }
            }
            foreach (self::CONTENT as $key => $label) {
                $report->$key = $this->normalize($validated[$key]);
            }
            if ($before === $this->snapshot($report)) {
                return false;
            }
            $report->save();
            $this->audit($actor, $report, 'progress_saved', $before);

            return true;
        });
    }

    public function setTarget(User $actor, MonthlyReport $report, array $data): void
    {
        abort_unless($this->access->canManage($actor), 403);
        DB::transaction(function () use ($actor, $report, $data) {
            $report = $this->lock($actor, $report, $data, true);
            $values = Validator::make($data, ['video_target' => ['required', 'integer', 'min:0', 'max:32767']])->validate();
            if ($report->video_target === (int) $values['video_target'] && $report->video_target_confirmed_at !== null) {
                return;
            }
            $before = $this->snapshot($report);
            $report->forceFill(['video_target' => $values['video_target'], 'video_target_confirmed_at' => now(), 'video_target_confirmed_by_user_id' => $actor->id])->save();
            $this->audit($actor, $report, 'target_set', $before);
        });
    }

    public function canEditVideo(User $actor, MonthlyReport $report, LearningVideo $video): bool
    {
        return $video->student_id === $report->student_id && $video->video_on->format('Y-m') === $report->report_month->format('Y-m')
            && ($actor->role_code !== 'teacher' || $video->teacher_id === $this->access->teacherId($actor));
    }

    public function sessions(User $actor, MonthlyReport $report): Collection
    {
        return app(StudentAttendanceAccessService::class)->sessions($actor)->where('branch_id', $report->branch_id)
            ->whereBetween(DB::raw('COALESCE(actual_start_at, planned_start_at)'), [$report->report_month->toDateString().' 00:00:00', $report->report_month->copy()->endOfMonth()->toDateString().' 23:59:59'])
            ->with(['branch', 'classGroup', 'replacementSessions'])->orderBy('planned_start_at')->get()
            ->filter(fn ($s) => ! $s->isCancelled() && ! $this->attendance->isSuperseded($s)
                && $this->attendance->roster($s)->contains('id', $report->student_id)
                && $this->attendance->eligibleToAttach($report->student, $s))->values();
    }

    public function saveVideo(User $actor, MonthlyReport $report, array $data, ?LearningVideo $video = null): LearningVideo
    {
        return DB::transaction(function () use ($actor, $report, $data, $video) {
            $report = $this->lock($actor, $report, $data);
            if ($video) {
                $video = LearningVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->canEditVideo($actor, $report, $video), 403);
            }
            $validated = Validator::make($data, [
                'video_on' => ['required', 'date_format:Y-m-d'], 'topic' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:4000'], 'video_url' => ['required', 'string', 'max:2048', 'url:http,https'],
                'session_id' => ['nullable', 'uuid'],
            ])->validate();
            $min = max($report->report_month->toDateString(), $report->student->began_on->toDateString());
            $max = min($report->report_month->copy()->endOfMonth()->toDateString(), $this->today($report));
            if ($validated['video_on'] < $min || $validated['video_on'] > $max) {
                throw ValidationException::withMessages(['video_on' => 'Tanggal video harus berada dalam bulan ini, sejak siswa mulai les, dan tidak melewati hari ini.']);
            }
            if (! self::safeUrl($validated['video_url'])) {
                throw ValidationException::withMessages(['video_url' => 'Masukkan tautan HTTP atau HTTPS yang lengkap.']);
            }
            if (! empty($validated['session_id'])) {
                $session = $this->sessions($actor, $report)->firstWhere('id', $validated['session_id']);
                if (! $session || $this->attendance->meetingAt($session)->toDateString() !== $validated['video_on']) {
                    throw ValidationException::withMessages(['session_id' => 'Pilih pertemuan siswa yang berwenang dengan tanggal yang sama dengan video.']);
                }
            }
            if (LearningVideo::where('student_id', $report->student_id)->whereDate('video_on', $validated['video_on'])
                ->where('video_url', trim($validated['video_url']))->when($video, fn ($q) => $q->where('id', '!=', $video->id))->exists()) {
                throw ValidationException::withMessages(['video_url' => 'Tautan video siswa pada tanggal ini sudah tercatat.']);
            }
            $before = $this->snapshot($report);
            $values = ['video_on' => $validated['video_on'], 'topic' => trim($validated['topic']),
                'description' => $this->normalize($validated['description'] ?? null), 'video_url' => trim($validated['video_url']), 'session_id' => $validated['session_id'] ?? null];
            if ($video) {
                $video->update($values);
            } else {
                $video = LearningVideo::create($values + ['id' => (string) Str::uuid(), 'student_id' => $report->student_id,
                    'teacher_id' => $actor->role_code === 'teacher' ? $this->access->teacherId($actor) : $report->teacher_id]);
            }
            if ($before !== $this->snapshot($report)) {
                $this->audit($actor, $report, 'video_saved', $before);
            }

            return $video;
        });
    }

    public function deleteVideo(User $actor, MonthlyReport $report, LearningVideo $video, array $data): void
    {
        DB::transaction(function () use ($actor, $report, $video, $data) {
            $report = $this->lock($actor, $report, $data);
            $video = LearningVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canEditVideo($actor, $report, $video), 403);
            $before = $this->snapshot($report);
            $video->delete();
            $this->audit($actor, $report, 'video_deleted', $before);
        });
    }

    private function normalize(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value === '' ? null : $value;
    }

    private function audit(User $actor, MonthlyReport $report, string $event, ?array $before): void
    {
        MonthlyProgressAudit::create(['report_id' => $report->id, 'actor_user_id' => $actor->id, 'event_type' => $event,
            'before_snapshot' => $before, 'after_snapshot' => $this->snapshot($report), 'happened_at' => now()]);
    }
}
