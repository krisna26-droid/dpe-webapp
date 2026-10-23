<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\MonthlyReport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MonthlyReportContentService
{
    public const STATUSES = ['draft' => 'Draf', 'revision' => 'Perlu revisi', 'submitted' => 'Diajukan', 'approved' => 'Disetujui'];

    public function __construct(private StudentProgressService $progress, private StudentAttendanceService $attendance) {}

    public function readiness(MonthlyReport $report): array
    {
        $report->loadMissing(['student', 'branch']);
        $categories = $this->progress->categories();
        $rows = $report->reportSkills()->get();
        $errors = [];
        $completed = 0;
        foreach (StudentProgressService::CATEGORIES as $code => $label) {
            $skill = $categories->get($code);
            $row = $skill ? $rows->firstWhere('skill_id', $skill->id) : null;
            if (! $skill || ! $skill->is_active) {
                $errors['skills.'.$code] = $label.': kategori belum tersedia atau nonaktif. Hubungi Super Admin.';
            } elseif (! $row || ! in_array($row->trend, StudentProgressService::TRENDS, true) || blank($row->description) || mb_strlen($row->description) > 4000) {
                $errors['skills.'.$code] = $label.': lengkapi status perkembangan dan deskripsi (maksimal 4.000 karakter).';
            } else {
                $completed++;
            }
        }
        if ($completed !== 8 || $categories->filter()->unique('id')->count() !== 8) {
            $errors['skills'] = 'Delapan kategori perkembangan yang berbeda harus tersedia, aktif, dan lengkap.';
        }
        if ($categories->filter()->unique('id')->count() !== 8) {
            $errors['category_master'] = 'Periksa delapan kategori master yang berbeda melalui Super Admin.';
        }
        foreach (array_slice(StudentProgressService::CONTENT, 0, 3, true) as $field => $label) {
            if (blank($report->$field) || mb_strlen($report->$field) > 8000) {
                $errors[$field] = $label.' wajib diisi dan maksimal 8.000 karakter.';
            }
        }
        if (mb_strlen($report->internal_teacher_note ?? '') > 8000) {
            $errors['internal_teacher_note'] = 'Catatan internal maksimal 8.000 karakter.';
        }
        if (! $report->student || $report->report_month->copy()->endOfMonth()->toDateString() < $report->student->began_on->toDateString()) {
            $errors['period'] = 'Periode laporan harus sesuai bulan siswa mulai les.';
        } elseif (max($report->report_month->toDateString(), $report->student->began_on->toDateString()) > $this->progress->today($report)) {
            $errors['period'] = 'Periode belajar siswa belum dimulai. Laporan dapat diajukan setelah periode berlangsung.';
        }
        $targetReady = $report->student && $this->progress->targetReady($report);
        if (! $targetReady || $report->video_target < 0 || $report->video_target > 32767) {
            $errors['video_target'] = 'Admin perlu memastikan target video periode ini, termasuk target bulan pertama.';
        }
        $videoCount = $this->progress->videoRows($report)->filter(fn ($video) => $this->progress->validVideo($report, $video))->count();

        return ['ready' => $errors === [], 'errors' => $errors, 'completed' => $completed, 'targetReady' => $targetReady,
            'videoCount' => $videoCount, 'videoShortage' => max(0, $report->video_target - $videoCount)];
    }

    public function assertReady(MonthlyReport $report): void
    {
        $readiness = $this->readiness($report);
        if (! $readiness['ready']) {
            throw ValidationException::withMessages($readiness['errors']);
        }
    }

    public function today(MonthlyReport $report): string
    {
        return $this->progress->today($report);
    }

    public function signature(MonthlyReport $report): string
    {
        $report->loadMissing(['student', 'branch', 'teacher.user']);

        return hash('sha256', json_encode([
            $this->progress->signature($report),
            $this->identity($report), $report->due_on?->toDateString(),
            $this->progress->videoRows($report)->map(fn ($video) => [$video->id, $video->teacher?->user?->full_name])->all(),
            $report->only(['submitted_at', 'approved_at', 'approved_by_user_id', 'updated_at']),
            $report->statusEvents()->orderBy('id')->get(['id', 'from_status', 'to_status', 'comment', 'actor_user_id', 'occurred_at'])->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    public function assertSnapshot(MonthlyReport $report, ?array $data): void
    {
        // Trusted service callers may omit a form version. Every HTTP mutation passes an array.
        if ($data === null) {
            return;
        }
        $validated = Validator::make($data, ['expected_snapshot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']])->validate();
        if (! hash_equals($this->signature($report), $validated['expected_snapshot'])) {
            throw ValidationException::withMessages(['expected_snapshot' => 'Laporan berubah sejak halaman dibuka. Muat data terbaru dan periksa kembali sebelum melanjutkan.']);
        }
    }

    private function identity(MonthlyReport $report): array
    {
        $report->loadMissing(['student', 'branch', 'teacher.user']);

        return ['student_name' => $report->student?->full_name, 'began_on' => $report->student?->began_on?->toDateString(),
            'branch_name' => $report->branch?->name, 'timezone_name' => $report->branch?->timezone_name,
            'teacher_name' => $report->teacher?->user?->full_name];
    }

    public function approvalSnapshot(MonthlyReport $report): array
    {
        $categories = $this->progress->categories();
        $rows = $report->reportSkills()->get();
        $skills = collect(StudentProgressService::CATEGORIES)->map(function ($label, $code) use ($categories, $rows) {
            $skill = $categories->get($code);
            $row = $skill ? $rows->firstWhere('skill_id', $skill->id) : null;

            return ['code' => $code, 'name' => $label, 'skill_id' => $skill?->id, 'trend' => $row?->trend, 'description' => $row?->description];
        })->values()->all();
        $videos = $this->progress->videoRows($report)->filter(fn ($video) => $this->progress->validVideo($report, $video))
            ->map(fn ($video) => ['id' => $video->id, 'video_on' => $video->video_on->toDateString(), 'topic' => $video->topic,
                'description' => $video->description, 'video_url' => $video->video_url, 'teacher_name' => $video->teacher?->user?->full_name])->values()->all();
        $public = $this->identity($report) + $report->only(['development_summary', 'parent_challenges_summary', 'parent_message']);
        $public += ['report_month' => $report->report_month->toDateString(), 'video_target' => $report->video_target, 'skills' => $skills, 'videos' => $videos];

        return ['snapshot_version' => 2, 'report_id' => $report->id, 'cycle_id' => $report->cycle_id,
            'student_id' => $report->student_id, 'branch_id' => $report->branch_id, 'teacher_id' => $report->teacher_id,
            'report_month' => $report->report_month->toDateString(), 'due_on' => $report->due_on?->toDateString(),
            'video_target' => $report->video_target, 'video_target_confirmed_at' => $report->video_target_confirmed_at?->toIso8601String(),
            ...$report->only(array_keys(StudentProgressService::CONTENT)), 'skills' => $skills, 'videos' => $videos,
            'public_content' => $public];
    }

    public function display(MonthlyReport $report): array
    {
        $approved = $report->status === 'approved';
        $snapshot = is_array($report->approved_snapshot) ? $report->approved_snapshot : null;
        $notice = null;
        $videosKnown = true;
        if ($approved && $snapshot !== null) {
            $public = is_array($snapshot['public_content'] ?? null) ? $snapshot['public_content'] : $snapshot;
            $videosKnown = is_array($public['videos'] ?? null);
            $identity = $this->identity($report);
            $skills = collect(is_array($public['skills'] ?? null) ? $public['skills'] : [])->filter(fn ($row) => is_array($row));
            $categories = $this->progress->categories();
            $skills = $skills->map(function ($row) use ($categories) {
                $code = array_search($row['skill_id'] ?? null, $categories->map(fn ($s) => $s?->id)->all(), true);

                return ['name' => $this->text($row['name'] ?? ($code ? StudentProgressService::CATEGORIES[$code] : 'Kategori lama')),
                    'trend' => $this->text($row['trend'] ?? null), 'description' => $this->text($row['description'] ?? null)];
            })->values()->all();
            if (($snapshot['snapshot_version'] ?? null) !== 2) {
                $notice = 'Persetujuan lama belum menyimpan identitas dan tautan video. Identitas saat ini ditampilkan; isi penilaian mengikuti salinan persetujuan yang tersedia.';
            }
            $data = $identity;
            foreach (array_keys($identity) as $field) {
                if (array_key_exists($field, $public)) {
                    $data[$field] = $this->text($public[$field]);
                }
            }
            foreach (array_slice(StudentProgressService::CONTENT, 0, 3, true) as $field => $label) {
                $data[$field] = $this->text($public[$field] ?? null);
            }
            $data += ['report_month' => $this->text($public['report_month'] ?? $snapshot['report_month'] ?? $report->report_month->toDateString()),
                'video_target' => is_numeric($public['video_target'] ?? $snapshot['video_target'] ?? null) ? (int) ($public['video_target'] ?? $snapshot['video_target']) : $report->video_target, 'skills' => $skills, 'videos' => is_array($public['videos'] ?? null) ? $public['videos'] : []];
            $internal = $this->text($snapshot['internal_teacher_note'] ?? null);
        } else {
            $snapshot = $this->approvalSnapshot($report);
            $data = $snapshot['public_content'];
            $internal = $report->internal_teacher_note;
            if ($approved) {
                $notice = 'Salinan persetujuan lama belum tersedia. Isi tersimpan ditampilkan untuk pemeriksaan petugas.';
            }
        }
        $data['videos'] = collect($data['videos'])->filter(fn ($row) => is_array($row))->map(fn ($video) => [
            'video_on' => $this->text($video['video_on'] ?? null), 'topic' => $this->text($video['topic'] ?? null),
            'description' => $this->text($video['description'] ?? null), 'teacher_name' => $this->text($video['teacher_name'] ?? null),
            'video_url' => StudentProgressService::safeUrl($this->text($video['video_url'] ?? null)) ? $video['video_url'] : null,
        ])->values()->all();

        return ['public' => $data, 'internal' => $internal, 'notice' => $notice, 'videosKnown' => $videosKnown, 'frozen' => $approved && $report->approved_snapshot !== null];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    public function attendanceSummary(MonthlyReport $report): array
    {
        $report->loadMissing('student');
        $sessions = LessonSession::where('branch_id', $report->branch_id)
            ->whereRaw('DATE(COALESCE(actual_start_at, planned_start_at)) BETWEEN ? AND ?', [$report->report_month->toDateString(), $report->report_month->copy()->endOfMonth()->toDateString()])
            ->where(fn ($q) => $q->whereHas('sessionStudents', fn ($s) => $s->where('student_id', $report->student_id))
                ->orWhereHas('classGroup.memberships', fn ($m) => $m->where('student_id', $report->student_id)))
            ->with(['branch', 'replacementSessions', 'sessionStudents.student.enrollments', 'classGroup.memberships.student.enrollments'])->get();
        $cells = $sessions->map(fn ($session) => $this->attendance->cell($session, $report->student));

        return $this->attendance->summarize($cells);
    }
}
