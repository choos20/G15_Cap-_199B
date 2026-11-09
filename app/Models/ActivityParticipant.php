<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $participation_type
 * @property string|null $participant_name
 * @property string|null $participant_id_number
 * @property string|null $roster_code
 * @property int|null $attendance_session_id
 * @property string|null $attendance_check_in
 * @property string|null $attendance_check_out
 * @property string|null $attendance_status
 * @property \Illuminate\Support\Carbon|null $attendance_recorded_at
 * @property-read string $display_name
 * @property-read string|null $display_id
 * @property-read string|null $roster_id
 */
class ActivityParticipant extends Model
{
    protected $table = 'project_participant_enrollments';

    public const ATTENDANCE_REQUIRED = 'attendance_required';

    public const PARTICIPATION_ONLY = 'participation_only';

    public const TYPES = [
        self::ATTENDANCE_REQUIRED,
        self::PARTICIPATION_ONLY,
    ];

    protected $fillable = [
        'project_id',
        'user_id',
        'added_by',
        'participation_type',
        'participant_name',
        'participant_id_number',
        'roster_code',
        'attendance_session_id',
        'attendance_check_in',
        'attendance_check_out',
        'attendance_status',
        'attendance_recorded_at',
    ];

    protected $casts = [
        'attendance_recorded_at' => 'datetime',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->participant_name ?? 'Unknown participant';
    }

    public function getDisplayIdAttribute(): ?string
    {
        return $this->user?->id_number ?? $this->participant_id_number ?? $this->roster_code;
    }

    public function getRosterIdAttribute(): ?string
    {
        return $this->user?->id_number
            ?? $this->participant_id_number
            ?? $this->roster_code;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public static function newRosterCode(): string
    {
        return 'GUEST-'.Str::upper(Str::random(10));
    }
}
