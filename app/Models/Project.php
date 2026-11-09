<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $created_by
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $volunteers_count
 * @property int $hours_rendered
 * @property string|null $review_comments
 * @property \Illuminate\Support\Carbon|null $notification_seen_at
 * @property-read int|null $attachments_count
 */

class Project extends Model
{
    use HasFactory;

    /**
     * Every status a project can hold, in pipeline order. Drives the browse
     * filter and any status label shown to a reviewer.
     */
    public const STATUS_LABELS = [
        'submitted' => 'Submitted',
        'endorsed_by_secretary' => 'Endorsed by Secretary',
        'endorsed_by_coordinator' => 'Endorsed by Coordinator',
        'approved' => 'Approved by CELP Head',
        'csa_cleared' => 'CSA Cleared',
        'returned_by_secretary' => 'Returned by Secretary',
        'returned_by_coordinator' => 'Returned by Coordinator',
        'returned_by_celp_head' => 'Returned by CELP Head',
        // Key is 'returned_by_csa', not '..._csa_director' — that is the value
        // csaDirectorReview() actually writes.
        'returned_by_csa' => 'Returned by CSA Director',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucwords(str_replace('_', ' ', (string) $status));
    }

    protected $fillable = [
        'title', 'description', 'created_by', 'status', 'volunteers_count', 'hours_rendered', 'review_comments', 'notification_seen_at'
    ];

    protected $casts = [
        'notification_seen_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function activityParticipants()
    {
        return $this->hasMany(ActivityParticipant::class);
    }
}
