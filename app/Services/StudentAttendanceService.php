<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\SessionStudent;
use App\Models\Student;
use App\Models\StudentAttendanceAudit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentAttendanceService
{
    public const STATUSES = [
        'present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Tidak Hadir',
        'sick' => 'Sakit', 'permission' => 'Izin', 'rescheduled' => 'Dijadwalkan Ulang',
    ];

    public function __construct(private StudentAttendanceAccessService $access) {}

    public function meetingAt(LessonSession $session): Carbon
    {
        $session->loadMissing('branch');
        $zone = $session->branch?->timezone_name ?: config('app.timezone');
        if (! in_array($zone, timezone_identifiers_list(), true)) {
            $zone = config('app.timezone');
        }

        return Carbon::parse($session->getRawOriginal('actual_start_at') ?: $session->getRawOriginal('planned_start_at'), $zone);
    }

    public function isSuperseded(LessonSession $session): bool
    {
        $session->loadMissing('replacementSessions');

        return $session->replacementSessions->contains(fn ($replacement) => ! $replacement->isCancelled()
            && $replacement->branch_id === $session->branch_id && $replacement->program_id === $session->program_id
            && $replacement->class_group_id === $session->class_group_id);
    }

    public function roster(LessonSession $session): Collection
    {
        $session->loadMissing(['sessionStudents.student.enrollments', 'classGroup.memberships.student.enrollments']);
        $date = $this->meetingAt($session)->toDateString();
        $students = $session->sessionStudents->filter(function ($row) use ($session, $date) {
            if (!$row->student) return false;
            $recorded = $row->attendance_status !== null || $row->recorded_at !== null || $row->individual_learning_note !== null;
            if ($recorded) return true;
            if ($row->student->deleted_at && $date >= $row->student->deleted_at->toDateString()) return false;
            if (!$session->class_group_id) return true;
            $row->student->loadMissing('classGroupMemberships');
            $mapped=$row->student->classGroupMemberships->filter(fn($m)=>$m->class_group_id===$session->class_group_id && $m->session_selection_from && $date >= $m->session_selection_from->toDateString());
            return $mapped->isEmpty() || $mapped->contains(fn($m)=>$this->covers($m,$date) && app(ClassLearningSessionService::class)->includes($m,$session,$date));
        })->pluck('student');
        if ($session->classGroup) {
            $date = $this->meetingAt($session)->toDateString();
            foreach ($session->classGroup->memberships as $membership) {
                if ($membership->student && $this->covers($membership, $date)
                    && (!$membership->student->deleted_at || $date < $membership->student->deleted_at->toDateString())
                    && app(ClassLearningSessionService::class)->includes($membership, $session, $date)
                    && $this->enrolled($membership->student, $session)
                    && $membership->student->began_on->toDateString() <= $date) {
                    $students->push($membership->student);
                }
            }
        }

        return $students->unique('id')->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function covers($period, string $date): bool
    {
        return $period->starts_on->toDateString() <= $date
            && ($period->ends_on === null || $period->ends_on->toDateString() >= $date);
    }

    public function enrolled(Student $student, LessonSession $session): bool
    {
        $student->loadMissing('enrollments');
        $date = $this->meetingAt($session)->toDateString();

        return $student->enrollments->contains(fn ($e) => $e->branch_id === $session->branch_id
            && $e->program_id === $session->program_id && $this->covers($e, $date));
    }

    public function eligibleToAttach(Student $student, LessonSession $session): bool
    {
        $date = $this->meetingAt($session)->toDateString();
        if (($student->deleted_at && $date >= $student->deleted_at->toDateString()) || $student->began_on->toDateString() > $date || ! $this->enrolled($student, $session)) {
            return false;
        }
        if ($session->class_group_id) {
            $student->loadMissing('classGroupMemberships');

            return $student->classGroupMemberships->contains(fn ($m) => $m->class_group_id === $session->class_group_id && $this->covers($m, $date) && app(ClassLearningSessionService::class)->includes($m, $session, $date));
        }

        return true;
    }

    public function cell(LessonSession $session, Student $student): array
    {
        $record = $session->sessionStudents->firstWhere('student_id', $student->id);
        $date = $this->meetingAt($session);
        $status = $record?->attendance_status;
        $member = $this->roster($session)->contains('id', $student->id);
        $beforeJoin = $student->began_on->toDateString() > $date->toDateString();
        $future = $date->isFuture();
        $superseded = $this->isSuperseded($session);
        $cancelled = $session->isCancelled();
        $reason = ! $member ? 'Bukan peserta' : ($beforeJoin ? 'Sebelum mulai les' : ($cancelled ? 'Sesi dibatalkan' : ($superseded ? 'Jadwal pengganti tersedia' : ($future ? 'Belum berlangsung' : null))));
        $effective = $superseded && $member && ! $beforeJoin ? 'rescheduled' : $status;

        $cell = [
            'record' => $record, 'date' => $date, 'status' => $effective,
            'label' => $reason && ! $superseded ? $reason : (self::STATUSES[$effective] ?? ($effective ? 'Status lama: '.$effective : 'Belum dicatat')),
            'can_record' => $member && ! $beforeJoin && ! $cancelled && ! $superseded && ! $future,
            'can_reschedule' => $member && ! $beforeJoin && (! $cancelled || $superseded) && (! $future || $superseded),
            'counts_as_meeting' => $member && ! $beforeJoin && ! $cancelled && ! $superseded && ! $future && $effective !== 'rescheduled',
            'is_attending' => in_array($effective, ['present', 'late'], true),
            'requires_reason' => $record && ($record->attendance_status !== null || $record->recorded_at !== null || $record->individual_learning_note !== null),
            'version' => $record?->attendance_version ?? 0, 'reason' => $reason,
            'is_member' => $member, 'before_join' => $beforeJoin,
        ];
        $cell['contribution'] = $this->summarize(collect([$cell]));

        return $cell;
    }

    public function summarize(Collection $cells): array
    {
        $counts = array_fill_keys(['present', 'late', 'absent', 'sick', 'permission', 'rescheduled', 'unrecorded', 'unknown', 'meeting_count', 'attended_count'], 0);
        foreach ($cells as $cell) {
            if ($cell['is_member'] && ! $cell['before_join'] && $cell['status'] === 'rescheduled') {
                $counts['rescheduled']++;
            }
            if (! $cell['counts_as_meeting']) {
                continue;
            }
            $counts['meeting_count']++;
            $key = $cell['status'] === null ? 'unrecorded' : (array_key_exists($cell['status'], self::STATUSES) ? $cell['status'] : 'unknown');
            $counts[$key]++;
        }
        $counts['attended_count'] = $counts['present'] + $counts['late'];

        return $counts;
    }

    public function record(User $actor, LessonSession $session, Student $student, array $data, bool $allowAttach = false): SessionStudent
    {
        $this->access->authorize($actor, $session);

        return DB::transaction(function () use ($actor, $session, $student, $data, $allowAttach) {
            $session = LessonSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, $session);
            $student = Student::query()->whereKey($student->id)->firstOrFail();
            $existing = SessionStudent::query()->where('session_id', $session->id)->where('student_id', $student->id)->lockForUpdate()->first();
            $status = array_key_exists('attendance_status', $data) ? $data['attendance_status'] : $existing?->attendance_status;
            $status = $status === '' ? null : $status;
            if ($status !== null && ! array_key_exists($status, self::STATUSES)) {
                throw ValidationException::withMessages(['attendance_status' => 'Pilih status kehadiran yang tersedia.']);
            }
            $member = $this->roster($session)->contains('id', $student->id);
            if (! $member && ! ($allowAttach && $this->access->canManageRoster($actor) && $this->eligibleToAttach($student, $session))) {
                abort(403, 'Siswa tidak terdaftar pada sesi ini.');
            }
            $at = $this->meetingAt($session);
            if ($status !== null && $student->began_on->toDateString() > $at->toDateString()) {
                throw ValidationException::withMessages(['attendance_status' => 'Kehadiran tidak dapat dicatat sebelum tanggal mulai les siswa.']);
            }
            if ($status !== null && $status !== 'rescheduled' && ($at->isFuture() || $session->isCancelled() || $this->isSuperseded($session))) {
                throw ValidationException::withMessages(['attendance_status' => 'Sesi belum berlangsung, dibatalkan, atau sudah memiliki jadwal pengganti.']);
            }
            if ($status === 'rescheduled' && $at->isFuture() && ! $this->isSuperseded($session)) {
                throw ValidationException::withMessages(['attendance_status' => 'Jadwal pengganti perlu ditetapkan untuk sesi yang belum berlangsung.']);
            }
            if ($status === 'rescheduled' && $session->isCancelled() && ! $this->isSuperseded($session)) {
                throw ValidationException::withMessages(['attendance_status' => 'Sesi dibatalkan tanpa jadwal pengganti.']);
            }
            if ((int) ($data['expected_version'] ?? 0) !== (int) ($existing?->attendance_version ?? 0)) {
                throw ValidationException::withMessages(['expected_version' => 'Catatan sudah berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            $note = array_key_exists('individual_learning_note', $data)
                ? ($data['individual_learning_note'] === '' ? null : $data['individual_learning_note'])
                : $existing?->individual_learning_note;
            $reason = trim((string) ($data['correction_reason'] ?? ''));
            if ($existing && $existing->attendance_status === $status && $existing->individual_learning_note === $note) {
                return $existing;
            }
            if (! $existing && $status === null && $note === null) {
                if ($allowAttach && $this->access->canManageRoster($actor)) {
                    SessionStudent::query()->create(['session_id' => $session->id, 'student_id' => $student->id]);
                    $record = SessionStudent::query()->where('session_id', $session->id)->where('student_id', $student->id)->firstOrFail();
                    $this->audit($actor, $session, $student->id, 'participant_added', null, $this->snapshot($record, $session), null);

                    return $record;
                }

                return new SessionStudent(['session_id' => $session->id, 'student_id' => $student->id, 'attendance_status' => null, 'attendance_version' => 0]);
            }
            $hadAttendance = $existing && ($existing->attendance_status !== null || $existing->recorded_at !== null);
            $wasRecorded = $hadAttendance || ($existing && $existing->individual_learning_note !== null);
            if ($wasRecorded && $reason === '') {
                throw ValidationException::withMessages(['correction_reason' => 'Alasan koreksi wajib diisi.']);
            }
            $before = $existing ? $this->snapshot($existing, $session) : null;
            $now = now();
            $attributes = [
                'attendance_status' => $status, 'individual_learning_note' => $note,
                'recorded_at' => $existing?->getRawOriginal('recorded_at') ?? (! $hadAttendance && $status !== null ? $now : null),
                'attendance_updated_at' => $now,
                'attendance_recorded_by_user_id' => $existing?->attendance_recorded_by_user_id
                    ?? (! $hadAttendance && $status !== null ? $actor->id : null),
                'attendance_version' => (int) ($existing?->attendance_version ?? 0) + 1,
            ];
            if ($existing) {
                DB::table('session_students')->where('session_id', $session->id)->where('student_id', $student->id)->update($attributes);
            } else {
                SessionStudent::query()->create(['session_id' => $session->id, 'student_id' => $student->id] + $attributes);
            }
            $record = SessionStudent::query()->where('session_id', $session->id)->where('student_id', $student->id)->firstOrFail();
            $this->audit($actor, $session, $student->id, $wasRecorded ? 'corrected' : 'recorded', $before, $this->snapshot($record, $session), $reason ?: null);

            return $record;
        });
    }

    public function rosterSignature(LessonSession $session): string
    {
        $rows = DB::table('session_students')->where('session_id', $session->id)->orderBy('student_id')->get();

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function setRoster(User $actor, LessonSession $session, array $studentIds, string $expected): void
    {
        $this->access->authorize($actor, $session);
        abort_unless($this->access->canManageRoster($actor) && $session->class_group_id === null, 403);
        DB::transaction(function () use ($actor, $session, $studentIds, $expected) {
            $session = LessonSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, $session);
            if (! hash_equals($this->rosterSignature($session), $expected)) {
                throw ValidationException::withMessages(['student_ids' => 'Daftar peserta sudah berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            $ids = array_values(array_unique($studentIds));
            $existing = SessionStudent::query()->where('session_id', $session->id)->get()->keyBy('student_id');
            foreach ($existing as $id => $record) {
                if (! in_array($id, $ids, true) && $this->hasHistory($record)) {
                    throw ValidationException::withMessages(['student_ids' => 'Peserta yang sudah memiliki catatan tidak dapat dihapus. Gunakan koreksi kehadiran.']);
                }
            }
            foreach (array_diff($ids, $existing->keys()->all()) as $id) {
                $student = Student::query()->findOrFail($id);
                if (! $this->eligibleToAttach($student, $session)) {
                    throw ValidationException::withMessages(['student_ids' => 'Peserta harus terdaftar pada cabang/program dan tanggal pertemuan yang sesuai.']);
                }
                $record = SessionStudent::query()->create(['session_id' => $session->id, 'student_id' => $id]);
                $this->audit($actor, $session, $id, 'participant_added', null, $this->snapshot($record, $session), null);
            }
            foreach ($existing as $id => $record) {
                if (! in_array($id, $ids, true)) {
                    $this->audit($actor, $session, $id, 'participant_removed', $this->snapshot($record, $session), null, null);
                    DB::table('session_students')->where('session_id', $session->id)->where('student_id', $id)->delete();
                }
            }
        });
    }

    public function hasHistory(SessionStudent $record): bool
    {
        return $record->attendance_status !== null || $record->recorded_at !== null || $record->individual_learning_note !== null || $record->attendance_updated_at !== null;
    }

    public function removeBlankParticipant(User $actor, LessonSession $session, Student $student): void
    {
        $this->access->authorize($actor, $session);
        abort_unless($this->access->canManageRoster($actor), 403);
        DB::transaction(function () use ($actor, $session, $student) {
            $session = LessonSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $record = SessionStudent::query()->where('session_id', $session->id)->where('student_id', $student->id)->firstOrFail();
            if ($this->hasHistory($record)) {
                throw ValidationException::withMessages(['attendance_status' => 'Catatan kehadiran tidak dapat dihapus. Gunakan koreksi dengan alasan.']);
            }
            $this->audit($actor, $session, $student->id, 'participant_removed', $this->snapshot($record, $session), null, null);
            DB::table('session_students')->where('session_id', $session->id)->where('student_id', $student->id)->delete();
        });
    }

    public function snapshot(SessionStudent $record, LessonSession $session): array
    {
        return $record->getAttributes() + ['meeting_date' => $this->meetingAt($session)->toDateString(), 'branch_id' => $session->branch_id, 'teacher_id' => $session->teacher_id];
    }

    private function audit(User $actor, LessonSession $session, string $studentId, string $event, ?array $before, ?array $after, ?string $reason): void
    {
        StudentAttendanceAudit::query()->create([
            'session_id' => $session->id, 'student_id' => $studentId, 'actor_user_id' => $actor->id,
            'event_type' => $event, 'before_snapshot' => $before, 'after_snapshot' => $after,
            'reason' => $reason, 'happened_at' => now(),
        ]);
    }
}
