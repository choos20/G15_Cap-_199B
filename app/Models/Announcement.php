<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property int|null $project_id
 * @property int $created_by
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $event_location
 * @property \Illuminate\Support\Carbon|null $event_date
 * @property-read int|null $approved_volunteers
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'project_id',
        'created_by',
        'status',
        'slots_needed',
        'application_deadline',
        'published_at',
        'event_date',
        'event_location',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'application_deadline' => 'datetime',
        'published_at' => 'datetime',
        'event_date' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments()
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    public function applications()
    {
        return $this->hasMany(VolunteerApplication::class);
    }

    /**
     * The beneficiary evaluation batch for this activity, if one has been
     * opened. Created lazily the first time the initiator encodes a form.
     */
    public function evaluation()
    {
        return $this->hasOne(Evaluation::class);
    }

    public function approvedCount(): int
    {
        return (int) ($this->getAttribute('approved_volunteers')
            ?? $this->applications()->where('status', 'approved')->count());
    }

    public function remainingSlots(): ?int
    {
        if ($this->slots_needed === null) {
            return null;
        }

        return max(0, $this->slots_needed - $this->approvedCount());
    }

    public function isFull(): bool
    {
        return $this->slots_needed !== null && $this->remainingSlots() <= 0;
    }

    public function eventHasPassed(): bool
    {
        $eventDate = $this->getRawOriginal('event_date')
            ?? $this->getAttributes()['event_date']
            ?? null;

        return $eventDate !== null
            && Carbon::parse($eventDate, 'Asia/Manila')->lte(now());
    }

    public function participantApplicationStatus(): string
    {
        $today = now()->startOfDay();
        $approvedCount = $this->getAttribute('approved_volunteers')
            ?? $this->applications()->where('status', 'approved')->count();

        if (
            ($this->application_deadline && $this->application_deadline->lt(now()))
            || ($this->event_date && $this->event_date->copy()->startOfDay()->lt($today))
            || ($this->slots_needed !== null && $approvedCount >= $this->slots_needed)
        ) {
            return 'closed';
        }

        if (
            $this->event_date
            && $this->event_date->toDateString() === $today->toDateString()
            && $this->event_date->lte(now())
        ) {
            return 'ongoing';
        }

        return 'open';
    }

    /**
     * For volunteer recruitment posts (slots_needed set): why this isn't
     * currently showing up in the participant activity feed, or null if it
     * is (or if it isn't a recruitment post at all, e.g. a general notice).
     */
    public function participantVisibilityIssue(): ?string
    {
        if ($this->slots_needed === null || $this->project_id === null) {
            return null;
        }

        if ($this->status !== 'published') {
            return 'Still a draft — publish it to make it visible to volunteers.';
        }

        if (! $this->project || ! in_array($this->project->status, ['approved', 'csa_cleared'])) {
            $projectTitle = $this->project?->title ?? 'the linked activity';

            return "Not visible yet — \"{$projectTitle}\" hasn't finished CELP/CSA approval.";
        }

        if ($this->application_deadline && $this->application_deadline->isPast()) {
            return 'Not visible — the application deadline has already passed.';
        }

        return null;
    }
}
