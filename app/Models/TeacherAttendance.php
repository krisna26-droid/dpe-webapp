<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TeacherAttendance extends Model
{
    use HasFactory;

    protected $table = 'teacher_attendances';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'session_id',
        'teacher_id',
        'branch_id',
        'attendance_mode',
        'attendance_date',
        'active_visit_teacher_id',
        'exception_day_key',
        'check_in_at',
        'check_in_photo_file_id',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy_m',
        'check_in_location_status',
        'check_in_location_note',
        'check_out_at',
        'check_out_photo_file_id',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy_m',
        'check_out_location_status',
        'check_out_location_note',
        'status',
        'attendance_type',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
        'manual_note',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_in_accuracy_m' => 'decimal:2',
            'check_out_at' => 'datetime',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_out_accuracy_m' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TeacherAttendance $attendance) {
            if ($attendance->attendance_date === null) {
                $date = $attendance->session_id
                    ? LessonSession::query()->whereKey($attendance->session_id)->value('planned_start_at')
                    : null;
                $attendance->attendance_date = $date
                    ? Carbon::parse($date)->toDateString()
                    : now()->toDateString();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LessonSession::class, 'session_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function checkInPhoto(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'check_in_photo_file_id');
    }

    public function checkOutPhoto(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'check_out_photo_file_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function isManualException(): bool
    {
        return in_array($this->attendance_type, ['absent', 'leave', 'sick'], true);
    }

    public function modeLabel(): string
    {
        return match ($this->attendance_mode) {
            'branch_visit' => 'Kunjungan cabang',
            'branch_day' => 'Ketidakhadiran cabang · per hari',
            'private_session' => 'Private di rumah siswa',
            default => 'Riwayat sesi lama',
        };
    }

    public function recordDate(): ?Carbon
    {
        return $this->attendance_date ?? $this->session?->planned_start_at ?? $this->check_in_at;
    }
}
