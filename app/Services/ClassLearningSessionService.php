<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\ClassGroupMembership;
use App\Models\ClassLearningSession;
use App\Models\LessonSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassLearningSessionService
{
    public function options(User $actor): Collection
    {
        $ids = app(BranchOperationsService::class)->groups($actor)->where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('is_active', true))->whereHas('program', fn ($q) => $q->where('is_active', true))->select('id');

        return ClassLearningSession::whereIn('class_group_id', $ids)->with(['classGroup.branch', 'classGroup.program', 'lessonSessions.teacher.user', 'lessonSessions.rescheduledFrom'])->orderBy('name')->get();
    }

    public function selectionKey(string $id, ?int $weekday): string
    {
        return $id.($weekday === null ? '' : '@'.$weekday);
    }

    public function selections(array $keys): Collection
    {
        $result = collect();
        foreach ($keys as $key) {
            if (! is_string($key) || ! preg_match('/^([^@\s]{1,36})(?:@([1-7]))?$/D', $key, $match)) {
                throw ValidationException::withMessages(['learning_session_ids' => 'Pilih sesi dan hari yang tersedia pada daftar.']);
            }
            $weekday = isset($match[2]) ? (int) $match[2] : null;
            $result->put($key, ['id' => $match[1], 'weekday' => $weekday, 'key' => $key]);
        }
        foreach ($result->groupBy('id') as $choices) {
            if ($choices->count() > 1 && $choices->contains(fn ($choice) => $choice['weekday'] === null)) {
                throw ValidationException::withMessages(['learning_session_ids' => 'Pilih hari sesi secara terpisah, tanpa menggabungkannya dengan pilihan semua hari.']);
            }
        }

        return $result->values();
    }

    public function weekday(LessonSession $session): int
    {
        $seen = [];
        while ($session->rescheduled_from_session_id && ! isset($seen[$session->id])) {
            $seen[$session->id] = true;
            $source = $session->rescheduledFrom;
            if (! $source || $source->class_group_id !== $session->class_group_id
                || $source->class_learning_session_id !== $session->class_learning_session_id) {
                break;
            }
            $session = $source;
        }

        return CarbonImmutable::parse($session->series_occurrence_on ?: $session->planned_start_at)->isoWeekday();
    }

    public function choices(Collection $sessions): Collection
    {
        $choices = collect();
        foreach ($sessions as $session) {
            $session->loadMissing(['classGroup', 'lessonSessions.teacher.user', 'lessonSessions.rescheduledFrom']);
            $days = $session->lessonSessions->filter(fn ($lesson) => ! $lesson->isCancelled())
                ->groupBy(fn ($lesson) => $this->weekday($lesson))->sortKeys();
            if ($days->isEmpty()) {
                $days = collect(['' => collect()]);
            }
            foreach ($days as $day => $meetings) {
                $weekday = $day === '' ? null : (int) $day;
                $upcoming = $meetings->filter(fn ($lesson) => $lesson->planned_start_at->isFuture());
                $display = ($upcoming->isNotEmpty() ? $upcoming : $meetings)->sortBy('planned_start_at');
                $details = $display->map(function ($lesson) use ($weekday) {
                    $moved = $weekday !== $lesson->planned_start_at->isoWeekday();

                    return ($moved ? LessonSeriesService::DAYS[$lesson->planned_start_at->isoWeekday()].' ' : '')
                        .$lesson->planned_start_at->format('H:i').'–'.$lesson->planned_end_at->format('H:i').' · '
                        .($lesson->teacher?->user?->full_name ?: 'Guru').($moved ? ' (pengganti jadwal '.LessonSeriesService::DAYS[$weekday].')' : '');
                })->unique()->implode('; ');
                if ($session->slot_capacity !== null) {
                    $details .= ($details ? ' · ' : '').'Kapasitas '.$session->slot_capacity.' siswa';
                }
                $choices->push(['key' => $this->selectionKey($session->id, $weekday), 'id' => $session->id,
                    'weekday' => $weekday, 'day_name' => $weekday ? LessonSeriesService::DAYS[$weekday] : 'Belum ada jadwal',
                    'session' => $session, 'meetings' => $meetings, 'details' => $details ?: 'Belum ada jadwal aktif']);
            }
        }

        return $choices->sortBy(fn ($choice) => sprintf('%02d', $choice['weekday'] ?? 8).'|'.$choice['session']->name.'|'.$choice['session']->class_group_id)->values();
    }

    public function resolve(array $data, ?LessonSession $current = null): ?string
    {
        $group = $data['class_group_id'] ?? null;
        if (! $group) {
            return null;
        }
        ClassGroup::whereKey($group)->lockForUpdate()->firstOrFail();
        if (! empty($data['rescheduled_from_session_id'])) {
            $source = LessonSession::findOrFail($data['rescheduled_from_session_id']);
            if ($source->class_group_id === $group) {
                return $source->class_learning_session_id;
            }
        }
        $id = $data['class_learning_session_id'] ?? null;
        $name = trim((string) ($data['learning_session_name'] ?? ''));
        if ($id) {
            if (! ClassLearningSession::whereKey($id)->where('class_group_id', $group)->exists()) {
                throw ValidationException::withMessages(['class_learning_session_id' => 'Pilih sesi dari kelompok kelas yang dipilih.']);
            }
        } elseif ($name !== '') {
            if (mb_strlen($name) > 100) {
                throw ValidationException::withMessages(['learning_session_name' => 'Nama sesi maksimal 100 karakter.']);
            }
            if (ClassLearningSession::where('class_group_id', $group)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['learning_session_name' => 'Nama sesi sudah ada. Pilih sesi yang tersedia untuk melanjutkan jadwalnya.']);
            }
            $id = ClassLearningSession::create(['id' => (string) Str::uuid(), 'class_group_id' => $group, 'name' => $name])->id;
        } elseif ($current?->class_group_id === $group && $current->class_learning_session_id) {
            $id = $current->class_learning_session_id;
        } elseif (array_key_exists('learning_session_name', $data)) {
            throw ValidationException::withMessages(['learning_session_name' => 'Isi nama sesi, misalnya Sesi 1, atau pilih sesi yang tersedia.']);
        } else {
            $id = ClassLearningSession::firstOrCreate(['class_group_id' => $group, 'name' => 'Sesi umum'], ['id' => (string) Str::uuid()])->id;
        }
        if ($current && $id !== $current->class_learning_session_id && ($current->schedule_series_id || $current->teacherAttendance()->exists() || $current->sessionStudents()->where(fn ($q) => $q->whereNotNull('attendance_status')->orWhereNotNull('recorded_at')->orWhereNotNull('individual_learning_note'))->exists() || $current->learningAudits()->exists())) {
            throw ValidationException::withMessages(['class_learning_session_id' => 'Sesi pada seri atau pertemuan dengan catatan tersimpan tidak dapat dipindahkan. Buat jadwal baru.']);
        }

        return $id;
    }

    public function includes(ClassGroupMembership $m, LessonSession $s, string $date): bool
    {
        if (! $m->session_selection_from || $date < $m->session_selection_from->toDateString()) {
            return true;
        }
        $m->loadMissing('learningSessionEnrollments');

        $weekday = $this->weekday($s);

        return $m->learningSessionEnrollments->contains(fn ($e) => $e->class_learning_session_id === $s->class_learning_session_id
            && ($e->weekday === null || $e->weekday === $weekday)
            && $e->starts_on->toDateString() <= $date && (! $e->ends_on || $e->ends_on->toDateString() >= $date));
    }

    public function select(ClassGroupMembership $m, array $ids, string $from, bool $creating = false): void
    {
        $choices = $this->selections($ids);
        $ids = $choices->pluck('id')->unique()->values()->all();
        $from = CarbonImmutable::parse($from)->toDateString();
        $zone = $m->classGroup->branch?->timezone_name ?: config('app.timezone');
        $sessions = ClassLearningSession::where('class_group_id', $m->class_group_id)->whereIn('id', $ids)->with('lessonSessions.rescheduledFrom')->get();
        if (! $ids || $sessions->count() !== count($ids)) {
            throw ValidationException::withMessages(['learning_session_ids' => 'Pilih minimal satu sesi pada kelompok kelas ini.']);
        }
        foreach ($choices as $choice) {
            if ($choice['weekday'] !== null && ! $sessions->firstWhere('id', $choice['id'])->lessonSessions
                ->contains(fn ($lesson) => ! $lesson->isCancelled() && $this->weekday($lesson) === $choice['weekday'])) {
                throw ValidationException::withMessages(['learning_session_ids' => 'Hari sesi tidak tersedia atau jadwalnya telah berubah. Muat ulang pilihan sesi.']);
            }
        }
        if ($from < $m->starts_on->toDateString() || ($m->ends_on && $from > $m->ends_on->toDateString()) || (! $creating && $from < CarbonImmutable::now($zone)->toDateString())) {
            throw ValidationException::withMessages(['session_effective_on' => 'Perubahan sesi berlaku mulai hari ini atau setelahnya, dalam periode keanggotaan.']);
        }
        foreach ($choices as $choice) {
            $session = $sessions->firstWhere('id', $choice['id']);
            $this->assertCapacity($m, $session, $choice['weekday'], $from);
        }
        $links = $m->learningSessionEnrollments()->lockForUpdate()->get();
        foreach ($links as $e) {
            if ($e->starts_on->toDateString() >= $from) {
                $e->delete();
            } elseif (! $e->ends_on || $e->ends_on->toDateString() >= $from) {
                $e->update(['ends_on' => CarbonImmutable::parse($from)->subDay()->toDateString()]);
            }
        }
        foreach ($choices as $choice) {
            $m->learningSessionEnrollments()->create(['id' => (string) Str::uuid(), 'class_learning_session_id' => $choice['id'],
                'weekday' => $choice['weekday'], 'starts_on' => $from, 'ends_on' => null]);
        }
        $m->update(['session_selection_from' => min($from, $m->session_selection_from?->toDateString() ?? $from), 'session_selection_revision' => $m->session_selection_revision + 1]);
        $m->unsetRelation('learningSessionEnrollments');
    }

    /**
     * A configured capacity is enforced when a student is assigned to a
     * learning session. Existing sessions with a null capacity remain
     * unlimited until an Admin or Super Admin configures them.
     */
    private function assertCapacity(
        ClassGroupMembership $membership,
        ClassLearningSession $session,
        ?int $weekday,
        string $from
    ): void {
        if ($session->slot_capacity === null) {
            return;
        }

        $meetings = $session->lessonSessions
            ->filter(fn ($lesson) => ! $lesson->isCancelled()
                && ($weekday === null || $this->weekday($lesson) === $weekday));
        if ($meetings->isEmpty()) {
            return;
        }

        $others = ClassGroupMembership::query()
            ->where('class_group_id', $membership->class_group_id)
            ->where('id', '!=', $membership->id)
            ->whereDate('starts_on', '<=', $from)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
            ->with('learningSessionEnrollments')
            ->get();
        $filled = [];
        foreach ($others as $other) {
            foreach ($meetings as $meeting) {
                if ($this->includes($other, $meeting, $from)) {
                    $filled[$other->student_id] = true;
                    break;
                }
            }
        }

        if (count($filled) >= $session->slot_capacity) {
            throw ValidationException::withMessages([
                'learning_session_ids' => "Sesi {$session->name} sudah penuh ({$session->slot_capacity} slot). Pilih sesi lain atau tambah kapasitasnya terlebih dahulu.",
            ]);
        }
    }

    public function label(ClassLearningSession $s): string
    {
        $s->loadMissing(['classGroup', 'lessonSessions.teacher.user']);
        $meetings = $s->lessonSessions->filter(fn ($l) => ! $l->isCancelled())->sortBy('planned_start_at');
        $future = $meetings->filter(fn ($l) => $l->planned_start_at->isFuture());
        $days = ($future->isNotEmpty() ? $future : $meetings)->map(fn ($l) => (LessonSeriesService::DAYS[$l->planned_start_at->isoWeekday()] ?? '').' '.$l->planned_start_at->format('H:i').'–'.$l->planned_end_at->format('H:i').' · '.($l->teacher?->user?->full_name ?? 'Guru'))->unique()->implode('; ');

        return $s->name.' · '.$s->classGroup?->name.' · '.($days ?: 'Belum ada jadwal');
    }
}
