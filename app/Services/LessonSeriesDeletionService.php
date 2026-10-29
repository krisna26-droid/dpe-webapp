<?php

namespace App\Services;

use App\Models\LessonScheduleSeries;
use App\Models\LessonSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LessonSeriesDeletionService
{
    public function __construct(private BranchOperationsService $operations) {}

    private function actor(User $actor, bool $lock = false): User
    {
        $current = User::query()->whereKey($actor->id)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($current?->is_active && in_array($current->role_code, ['admin', 'superadmin'], true), 403);

        return $current;
    }

    private function sessions(LessonScheduleSeries $series, bool $lock = false): Collection
    {
        return $series->sessions()->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
    }

    private function version(LessonScheduleSeries $series, Collection $sessions): string
    {
        $state = [$series->getRawOriginal(), $sessions->map(fn ($session) => $session->getRawOriginal())->all()];

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function meetingVersion(LessonScheduleSeries $series, LessonSession $session): string
    {
        $state = ['single_meeting', $series->getRawOriginal(), $session->getRawOriginal()];

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function preview(User $actor, LessonScheduleSeries $series): array
    {
        $actor = $this->actor($actor);
        $this->operations->authorizeBranch($actor, $series->branch_id);
        $sessions = $this->sessions($series);

        return ['version' => $this->version($series, $sessions), 'count' => $sessions->count(),
            'meeting_versions' => $sessions->mapWithKeys(fn ($session) => [$session->id => $this->meetingVersion($series, $session)])->all()];
    }

    private function assertDeletable(Collection $sessions, bool $single = false): void
    {
        $reject = function (string $reason) use ($single): never {
            throw ValidationException::withMessages([
                $single ? 'delete_meeting' : 'delete_series' => ($single ? 'Pertemuan' : 'Seri').' tidak dapat dihapus karena '.$reason.'. '
                    .($single ? 'Jadwal tetap tersimpan.' : 'Seluruh jadwal tetap tersimpan.'),
            ]);
        };
        foreach ($sessions as $session) {
            if ($session->actual_start_at || $session->actual_end_at
                || ! in_array(strtolower(trim((string) $session->status)), ['scheduled', 'cancelled', 'canceled', 'dibatalkan'], true)) {
                $reject('ada pertemuan yang sudah berlangsung atau memiliki waktu pelaksanaan');
            }
            if ($session->rescheduled_from_session_id) {
                $reject('terhubung dengan jadwal pengganti');
            }
        }

        $ids = $sessions->modelKeys();
        if (LessonSession::whereIn('rescheduled_from_session_id', $ids)->exists()) {
            $reject('memiliki jadwal pengganti');
        }
        if (DB::table('session_students')->whereIn('session_id', $ids)->where(function ($query) {
            $query->whereNotNull('attendance_status')->orWhereNotNull('recorded_at')->orWhereNotNull('individual_learning_note')
                ->orWhereNotNull('attendance_updated_at')->orWhereNotNull('attendance_recorded_by_user_id')->orWhere('attendance_version', '>', 0);
        })->exists()) {
            $reject('ada absensi atau catatan siswa yang sudah tersimpan');
        }
        foreach ([
            'teacher_attendances' => 'absensi guru',
            'student_attendance_audits' => 'riwayat absensi siswa atau perubahan peserta',
            'lesson_learning_audits' => 'riwayat catatan pembelajaran',
            'learning_videos' => 'video pembelajaran',
            'student_challenges' => 'tantangan siswa',
            'teacher_messages' => 'pesan guru',
        ] as $table => $label) {
            if (DB::table($table)->whereIn('session_id', $ids)->exists()) {
                $reject('terhubung dengan '.$label);
            }
        }
    }

    public function deleteMeeting(User $actor, LessonScheduleSeries $series, LessonSession $session, string $expectedVersion): void
    {
        DB::transaction(function () use ($actor, $series, $session, $expectedVersion) {
            $actor = $this->actor($actor, true);
            $series = LessonScheduleSeries::query()->whereKey($series->id)->lockForUpdate()->firstOrFail();
            $this->operations->authorizeBranch($actor, $series->branch_id);
            $current = $series->sessions()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->operations->authorizeBranch($actor, $current->branch_id);
            if (! hash_equals($this->meetingVersion($series, $current), $expectedVersion)) {
                throw ValidationException::withMessages(['deletion_version' => 'Data pertemuan berubah sejak halaman dibuka. Muat ulang halaman dan periksa kembali sebelum menghapus.']);
            }
            $this->assertDeletable(new Collection([$current]), true);
            $participants = DB::table('session_students')->where('session_id', $current->id)->orderBy('student_id')->lockForUpdate()->get();
            DB::table('operational_deletion_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id,
                'record_table' => 'lesson_sessions', 'record_id' => $current->id,
                'before_snapshot' => json_encode([
                    'series' => $series->getRawOriginal(), 'sessions' => [$current->getRawOriginal()],
                    'participants' => $participants->map(fn ($row) => (array) $row)->all(),
                ], JSON_THROW_ON_ERROR),
                'cancelled_session_ids' => '[]', 'created_at' => now(),
            ]);
            DB::table('session_students')->where('session_id', $current->id)->delete();
            $current->delete();
        }, 3);
    }

    public function delete(User $actor, LessonScheduleSeries $series, string $expectedVersion): int
    {
        return DB::transaction(function () use ($actor, $series, $expectedVersion) {
            $actor = $this->actor($actor, true);
            $current = LessonScheduleSeries::query()->whereKey($series->id)->lockForUpdate()->firstOrFail();
            $this->operations->authorizeBranch($actor, $current->branch_id);
            $sessions = $this->sessions($current, true);
            if (! hash_equals($this->version($current, $sessions), $expectedVersion)) {
                throw ValidationException::withMessages(['deletion_version' => 'Data seri berubah sejak halaman dibuka. Muat ulang halaman dan periksa kembali sebelum menghapus.']);
            }
            $this->assertDeletable($sessions);
            $ids = $sessions->modelKeys();
            $participants = DB::table('session_students')->whereIn('session_id', $ids)->orderBy('session_id')->orderBy('student_id')->lockForUpdate()->get();
            $changes = $current->changes()->orderBy('id')->lockForUpdate()->get();
            $snapshot = [
                'series' => $current->getRawOriginal(),
                'sessions' => $sessions->map(fn ($session) => $session->getRawOriginal())->all(),
                'participants' => $participants->map(fn ($row) => (array) $row)->all(),
                'changes' => $changes->map(fn ($change) => $change->getRawOriginal())->all(),
            ];
            DB::table('operational_deletion_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id,
                'record_table' => 'lesson_schedule_series', 'record_id' => $current->id,
                'before_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'cancelled_session_ids' => '[]', 'created_at' => now(),
            ]);

            // Only empty participant links remain; capture them above before removing the mistaken schedule.
            DB::table('session_students')->whereIn('session_id', $ids)->delete();
            LessonSession::whereIn('id', $ids)->delete();
            $current->changes()->delete();
            $current->delete();

            return $sessions->count();
        }, 3);
    }
}
