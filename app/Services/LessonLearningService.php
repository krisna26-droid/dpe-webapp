<?php

namespace App\Services;

use App\Models\LessonLearningAudit;
use App\Models\LessonSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LessonLearningService
{
    public const FIELDS = ['topic' => 'Topik', 'material' => 'Materi', 'activity' => 'Aktivitas'];

    public function __construct(private StudentAttendanceAccessService $access, private StudentAttendanceService $attendance) {}

    public function relations(): array
    {
        return ['branch', 'teacher.user', 'program', 'classGroup.memberships.student.enrollments', 'sessionStudents.student.enrollments', 'replacementSessions', 'rescheduledFrom'];
    }

    public function participants(LessonSession $session): Collection
    {
        $session->loadMissing($this->relations());

        return $this->attendance->roster($session)->map(function ($student) use ($session) {
            $cell = $this->attendance->cell($session, $student);

            return [
                'student' => $student, 'cell' => $cell,
                'can_edit_note' => $cell['can_record'] && ($cell['status'] === null || array_key_exists($cell['status'], StudentAttendanceService::STATUSES)),
            ];
        })->keyBy(fn ($row) => $row['student']->id);
    }

    public function blockReason(LessonSession $session): ?string
    {
        if ($session->isCancelled()) {
            return 'Sesi dibatalkan. Catatan yang sudah ada tetap dapat dilihat.';
        }
        if ($this->attendance->isSuperseded($session)) {
            return 'Sesi ini sudah memiliki jadwal pengganti. Catat hasil belajar pada pertemuan penggantinya.';
        }
        if ($this->attendance->meetingAt($session)->isFuture()) {
            return 'Pertemuan belum berlangsung. Hasil belajar dapat dicatat setelah waktu mulai pertemuan.';
        }

        return null;
    }

    public function snapshot(LessonSession $session, Collection $participants): array
    {
        $snapshot = [];
        foreach (self::FIELDS as $field => $label) {
            $snapshot[$field] = $session->getRawOriginal($field);
        }
        $snapshot['student_notes'] = $participants->sortKeys()->map(fn ($row) => $row['cell']['record']?->individual_learning_note)->all();

        return $snapshot;
    }

    public function signature(LessonSession $session, Collection $participants): string
    {
        $identity = [];
        foreach (['id', 'teacher_id', 'branch_id', 'program_id', 'class_group_id', 'planned_start_at', 'actual_start_at', 'status', 'rescheduled_from_session_id'] as $field) {
            $identity[$field] = $session->getRawOriginal($field);
        }
        $cells = $participants->sortKeys()->map(fn ($row) => [
            'version' => $row['cell']['version'], 'status' => $row['cell']['record']?->attendance_status,
            'recorded_at' => $row['cell']['record']?->getRawOriginal('recorded_at'),
            'can_edit_note' => $row['can_edit_note'], 'began_on' => $row['student']->began_on->toDateString(),
        ])->all();

        return hash('sha256', json_encode([$identity, $this->snapshot($session, $participants), $cells], JSON_THROW_ON_ERROR));
    }

    public function save(User $actor, LessonSession $session, array $data): bool
    {
        $this->access->authorize($actor, $session);

        return DB::transaction(function () use ($actor, $session, $data) {
            $session = LessonSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, $session);
            $session->load($this->relations());
            if ($reason = $this->blockReason($session)) {
                throw ValidationException::withMessages(['session' => $reason]);
            }
            $participants = $this->participants($session);
            if (! hash_equals($this->signature($session, $participants), $data['expected_snapshot'])) {
                throw ValidationException::withMessages(['expected_snapshot' => 'Catatan, peserta, atau absensi sudah berubah. Muat ulang halaman dan periksa data terbaru sebelum menyimpan.']);
            }
            $before = $this->snapshot($session, $participants);
            $fields = [];
            foreach (self::FIELDS as $field => $label) {
                $fields[$field] = $this->normalize($data[$field]);
            }
            $noteChanges = [];
            foreach ($data['student_notes'] ?? [] as $studentId => $value) {
                abort_unless($participants->has($studentId), 403, 'Siswa tidak terdaftar pada pertemuan ini.');
                $row = $participants[$studentId];
                if (! $row['can_edit_note']) {
                    throw ValidationException::withMessages(['student_notes.'.$studentId => 'Catatan siswa ini tidak dapat diubah. Periksa tanggal mulai les dan status kehadiran pada detail absensi.']);
                }
                $note = $this->normalize($value);
                if ($row['cell']['record']?->individual_learning_note !== $note) {
                    $noteChanges[$studentId] = $note;
                }
            }
            $changedFields = collect($fields)->contains(fn ($value, $field) => $before[$field] !== $value);
            if (! $changedFields && $noteChanges === []) {
                return false;
            }
            $hadLearning = collect(self::FIELDS)->keys()->contains(fn ($field) => $before[$field] !== null && $before[$field] !== '')
                || collect($before['student_notes'])->contains(fn ($note) => $note !== null && $note !== '');
            $needsReason = $hadLearning || collect(array_keys($noteChanges))->contains(fn ($id) => $participants[$id]['cell']['requires_reason']);
            $reason = trim((string) ($data['correction_reason'] ?? ''));
            if ($needsReason && $reason === '') {
                throw ValidationException::withMessages(['correction_reason' => 'Alasan perubahan wajib diisi untuk catatan atau absensi yang sudah tersimpan.']);
            }
            if ($changedFields) {
                $session->update($fields);
            }
            foreach ($noteChanges as $studentId => $note) {
                $row = $participants[$studentId];
                $this->attendance->record($actor, $session, $row['student'], [
                    'attendance_status' => $row['cell']['record']?->attendance_status,
                    'individual_learning_note' => $note, 'expected_version' => $row['cell']['version'],
                    'correction_reason' => $reason,
                ]);
            }
            $session->refresh()->load($this->relations());
            LessonLearningAudit::query()->create([
                'session_id' => $session->id, 'actor_user_id' => $actor->id,
                'event_type' => $hadLearning ? 'corrected' : 'recorded',
                'before_snapshot' => $before, 'after_snapshot' => $this->snapshot($session, $this->participants($session)),
                'reason' => $reason !== '' ? $reason : null, 'happened_at' => now(),
            ]);

            return true;
        });
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
