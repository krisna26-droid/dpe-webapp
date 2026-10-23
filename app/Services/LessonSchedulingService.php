<?php

namespace App\Services;

use App\Models\LessonSession;
use App\Models\ClassGroup;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LessonSchedulingService
{
    public function validate(array $data, ?LessonSession $current = null, array $ignoreSessionIds = []): void
    {
        if (in_array(strtolower(trim($data['status'])), ['cancelled', 'canceled', 'dibatalkan'], true)) { return; }
        $start = Carbon::parse($data['planned_start_at']);
        $end = Carbon::parse($data['planned_end_at']);
        if ($current && ! $current->isCancelled() && $current->teacher_id === $data['teacher_id']
            && $current->branch_id === $data['branch_id'] && $current->program_id === $data['program_id']
            && $current->class_group_id === ($data['class_group_id'] ?? null)
            && $start->eq($current->planned_start_at) && $end->eq($current->planned_end_at)) { return; }

        // Serialize scheduling for the same Teacher across both management roles.
        $teacher = Teacher::whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
        if (! $teacher->is_active || ! $teacher->user?->is_active || $teacher->user?->role_code !== 'teacher') {
            throw ValidationException::withMessages(['teacher_id' => 'Pilih Teacher dengan profil dan akun aktif untuk jadwal baru/perubahan waktu.']);
        }
        $conflict = LessonSession::where('teacher_id', $data['teacher_id'])->notSuperseded()
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])
            ->where('planned_start_at', '<', $end->format('Y-m-d H:i:s'))->where('planned_end_at', '>', $start->format('Y-m-d H:i:s'))
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->when($ignoreSessionIds, fn ($q) => $q->whereNotIn('id', $ignoreSessionIds))
            ->when($data['rescheduled_from_session_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['planned_start_at' => 'Waktu bertabrakan dengan sesi lain milik Teacher ini. Pilih waktu atau Teacher lain.']);
        }
        if (! empty($data['class_group_id'])) {
            ClassGroup::whereKey($data['class_group_id'])->lockForUpdate()->firstOrFail();
            $groupConflict = LessonSession::where('class_group_id', $data['class_group_id'])->notSuperseded()
                ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan'])
                ->where('planned_start_at', '<', $end->format('Y-m-d H:i:s'))
                ->where('planned_end_at', '>', $start->format('Y-m-d H:i:s'))
                ->when($current, fn ($q) => $q->whereKeyNot($current->id))
                ->when($ignoreSessionIds, fn ($q) => $q->whereNotIn('id', $ignoreSessionIds))
                ->when($data['rescheduled_from_session_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))->exists();
            if ($groupConflict) {
                throw ValidationException::withMessages(['planned_start_at' => 'Kelompok kelas sudah memiliki sesi pada waktu ini. Pilih waktu lain.']);
            }
        }
        $slots = TeacherAvailability::where('teacher_id', $data['teacher_id'])->where('branch_id', $data['branch_id'])
            ->whereDate('available_on', '>=', $start->toDateString())->whereDate('available_on', '<=', $end->toDateString())->get();
        if ($slots->isEmpty()) { return; } // Manual availability is optional; an absent entry is not a blocked slot.
        $programType = str_contains(strtolower((string) Program::findOrFail($data['program_id'])->class_type), 'private') ? 'private' : 'regular';
        $windows = [];
        foreach ($slots as $slot) {
            $slotStart = Carbon::parse($slot->available_on->toDateString().' '.$slot->starts_at);
            $slotEnd = Carbon::parse($slot->available_on->toDateString().' '.$slot->ends_at);
            if (strtolower(trim($slot->availability_status)) !== 'available' && $slotStart->lt($end) && $slotEnd->gt($start)) {
                throw ValidationException::withMessages(['planned_start_at' => 'Waktu ini bertumpang tindih dengan slot Teacher yang sudah dipesan atau diblokir. Pilih waktu lain.']);
            }
            if (strtolower(trim($slot->availability_status)) === 'available' && strtolower(trim($slot->class_type)) === $programType) {
                $windows[] = [$slotStart, $slotEnd];
            }
        }
        usort($windows, fn ($a, $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());
        $covered = $start->copy();
        foreach ($windows as [$a, $b]) {
            if ($a->gt($covered)) { break; }
            if ($b->gt($covered)) { $covered = $b; }
            if ($covered->gte($end)) { return; }
        }
        throw ValidationException::withMessages(['planned_start_at' => 'Jadwal harus berada dalam slot Available Teacher untuk jenis program ini pada tanggal yang dipilih.']);
    }

}
