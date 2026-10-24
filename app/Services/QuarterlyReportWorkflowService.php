<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\QuarterlyReport;
use App\Models\QuarterlyReportEvent;
use App\Models\ReportCycle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuarterlyReportWorkflowService
{
    public function __construct(private QuarterlyReportAccessService $access, private QuarterlyReportContentService $content) {}

    public function prepare(User $actor, ReportCycle $cycle, array $data): QuarterlyReport
    {
        return DB::transaction(function () use ($actor, $cycle, $data) {
            $actor = $actor->fresh();
            abort_unless($actor, 403);
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $actor->refresh();
            Student::whereKey($cycle->student_id)->lockForUpdate()->firstOrFail();
            $cycle = ReportCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $this->access->authorizeCycle($actor, $cycle);
            $existing = QuarterlyReport::where('cycle_id', $cycle->id)->lockForUpdate()->first();
            if ($existing) {
                $this->access->authorize($actor, $existing);

                return $existing;
            }
            MonthlyReport::where('cycle_id', $cycle->id)->orderBy('id')->lockForUpdate()->get();
            $context = $this->content->preparation($cycle);
            if ($context['errors']) {
                throw ValidationException::withMessages($context['errors']);
            }
            abort_unless($this->access->mayPrepareAtBranch($actor, $context['branch_id']), 403);
            $this->content->assertSignature($this->content->preparationSignature($cycle), $data);
            $owners = $this->access->owners($cycle);
            $valid = Validator::make($data, ['teacher_id' => ['required', 'uuid', Rule::in($owners->pluck('id')->all())]])->validate();
            abort_unless($actor->role_code !== 'teacher' || $this->access->teacherId($actor) === $valid['teacher_id'], 403);
            $report = QuarterlyReport::create(['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'student_id' => $cycle->student_id,
                'branch_id' => $context['branch_id'], 'teacher_id' => $valid['teacher_id'], 'status' => 'draft', 'revision_number' => 1, 'skills' => []]);
            $this->event($report, $actor, 'prepared', null, null);

            return $report->fresh();
        }, 3);
    }

    public function lock(User $actor, QuarterlyReport $report, array $data): QuarterlyReport
    {
        User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $actor->refresh();
        Student::whereKey($report->student_id)->lockForUpdate()->firstOrFail();
        ReportCycle::whereKey($report->cycle_id)->lockForUpdate()->firstOrFail();
        $current = QuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        $this->access->authorize($actor, $current);
        MonthlyReport::where('cycle_id', $current->cycle_id)->orderBy('id')->lockForUpdate()->get();
        $owner = Teacher::whereKey($current->teacher_id)->lockForUpdate()->first();
        if ($owner) {
            User::whereKey($owner->user_id)->lockForUpdate()->first();
        }
        DB::table('report_template_settings')->where('id', 1)->lockForUpdate()->first();
        app(ReportSignatureService::class)->lockManager();
        $this->content->assertSignature($this->content->signature($current), $data);

        return $current;
    }

    public function save(User $actor, QuarterlyReport $report, array $data): QuarterlyReport
    {
        return DB::transaction(function () use ($actor, $report, $data) {
            $actor = $actor->fresh();
            abort_unless($actor, 403);
            $report = $this->lock($actor, $report, $data);
            $this->editable($report);
            if ($actor->role_code !== 'teacher') {
                abort_unless($this->access->canManage($actor), 403);
                abort_if(array_key_exists('skills', $data) || array_key_exists('parent_note', $data), 403);
                $data['skills'] = collect(QuarterlyReportContentService::SKILLS)->map(fn ($label, $key) => $report->skills[$key] ?? ['description' => null, 'score' => null])->all();
                $data['parent_note'] = $report->parent_note;
            }
            $before = $report->only(['teacher_id', 'skills', 'parent_note', 'revision_number']);
            $rules = ['skills' => ['required', 'array:'.implode(',', array_keys(QuarterlyReportContentService::SKILLS)), 'size:'.count(QuarterlyReportContentService::SKILLS)], 'parent_note' => ['present', 'nullable', 'string', 'max:8000']];
            foreach (QuarterlyReportContentService::SKILLS as $code => $label) {
                $rules['skills.'.$code] = ['present', 'array:description,score'];
                $rules['skills.'.$code.'.description'] = ['present', 'nullable', 'string', 'max:4000'];
                $rules['skills.'.$code.'.score'] = ['present', 'nullable', Rule::in(QuarterlyReportContentService::SCORES)];
            }
            $valid = Validator::make($data, $rules)->validate();
            foreach ($valid['skills'] as $code => &$row) {
                $row['description'] = trim($row['description'] ?? '');
                $row['description'] = $row['description'] === '' ? null : $row['description'];
                $row['score'] = $row['score'] ?: null;
                if (($row['score'] !== null) !== ($row['description'] !== null)) {
                    throw ValidationException::withMessages(['skills.'.$code => 'Isi deskripsi dan skor bersama, atau kosongkan keduanya untuk draf.']);
                }
            }
            unset($row);
            if (array_key_exists('teacher_id', $data)) {
                abort_unless($actor->role_code !== 'teacher' || $data['teacher_id'] === $report->teacher_id, 403);
                $owners = $this->access->owners($report->cycle);
                $owner = Validator::make($data, ['teacher_id' => ['required', 'uuid', Rule::in($owners->pluck('id')->all())]])->validate();
                $report->teacher_id = $owner['teacher_id'];
            }
            $report->skills = $valid['skills'];
            $note = trim($valid['parent_note'] ?? '');
            $report->parent_note = $note === '' ? null : $note;
            if ($report->isDirty()) {
                $report->save();
                $this->event($report, $actor, 'draft_saved', $report->status, $before);
            }

            return $report->fresh();
        }, 3);
    }

    public function transition(User $actor, QuarterlyReport $report, string $action, array $data): QuarterlyReport
    {
        return DB::transaction(function () use ($actor, $report, $action, $data) {
            $actor = $actor->fresh();
            abort_unless($actor, 403);
            $report = $this->lock($actor, $report, $data);
            $before = $report->only(['teacher_id', 'skills', 'parent_note', 'revision_number', 'approved_snapshot', 'approved_at', 'approved_by_user_id']);
            $from = $report->status;
            $comment = null;
            if ($action === 'submit' || $action === 'reopen') {
                abort_unless($actor->role_code === 'teacher' && $this->access->teacherId($actor) === $report->teacher_id, 403);
            } else {
                abort_unless(in_array($action, ['approve', 'revision'], true) && $this->access->canManage($actor), 403);
            }
            if ($action === 'submit') {
                $this->editable($report);
            }
            if (in_array($action, ['approve', 'revision'], true) && $report->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Tindakan tinjauan hanya untuk rapor Diajukan.']);
            }
            if ($action === 'reopen' && $report->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Revisi baru hanya dimulai dari rapor Disetujui.']);
            }
            if (in_array($action, ['submit', 'approve'], true)) {
                $ready = $this->content->readiness($report);
                if (! $ready['ready']) {
                    throw ValidationException::withMessages($ready['errors']);
                }
            }
            if (in_array($action, ['revision', 'reopen'], true)) {
                $comment = trim(Validator::make($data, ['comment' => ['required', 'string', 'max:255']])->validate()['comment']);
                if ($comment === '') {
                    throw ValidationException::withMessages(['comment' => 'Alasan revisi wajib diisi.']);
                }
            }
            $report->status = ['submit' => 'submitted', 'approve' => 'approved', 'revision' => 'revision', 'reopen' => 'draft'][$action];
            if ($action === 'submit') {
                $report->submitted_at = now();
            }
            if ($action === 'approve') {
                $report->approved_at = now();
                $report->approved_by_user_id = $actor->id;
                $report->approved_snapshot = $this->content->snapshot($report);
            } else {
                $report->approved_at = null;
                $report->approved_by_user_id = null;
                $report->approved_snapshot = null;
            }
            if ($action === 'reopen') {
                $report->revision_number++;
                $report->submitted_at = null;
            }
            $report->save();
            $this->event($report, $actor, $action, $from, $before, $comment);

            return $report->fresh();
        }, 3);
    }

    private function editable(QuarterlyReport $report): void
    {
        if (! in_array($report->status, ['draft', 'revision'], true)) {
            throw ValidationException::withMessages(['status' => 'Isi rapor terkunci saat Diajukan atau Disetujui.']);
        }
    }

    public function event(QuarterlyReport $report, User $actor, string $type, ?string $from, ?array $before, ?string $comment = null, ?array $after = null): void
    {
        QuarterlyReportEvent::create(['id' => (string) Str::uuid7(), 'report_id' => $report->id, 'actor_user_id' => $actor->id,
            'event_type' => $type, 'from_status' => $from, 'to_status' => $report->status, 'comment' => $comment,
            'before_snapshot' => $before, 'after_snapshot' => $after ?? $report->only(['teacher_id', 'skills', 'parent_note', 'revision_number', 'approved_snapshot', 'approved_at', 'approved_by_user_id']), 'occurred_at' => now()]);
    }
}
