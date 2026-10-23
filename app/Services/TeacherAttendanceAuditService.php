<?php

namespace App\Services;

use App\Models\TeacherAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherAttendanceAuditService
{
    public function record(TeacherAttendance $attendance, string $event, ?string $actorId, ?string $reason = null): void
    {
        DB::table('teacher_attendance_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'teacher_attendance_id' => $attendance->id,
            'actor_user_id' => $actorId,
            'event_type' => $event,
            'snapshot_json' => json_encode($attendance->getAttributes(), JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'happened_at' => now(),
        ]);
    }
}
