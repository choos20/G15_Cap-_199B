<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string $actor_name
 * @property string|null $actor_role
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property string $summary
 * @property array|null $context
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AuditLog extends Model
{
    protected $fillable = [
        'actor_id', 'actor_name', 'actor_role', 'action',
        'subject_type', 'subject_id', 'subject_label', 'summary', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Write an entry for the currently authenticated actor.
     *
     * Deliberately swallows its own failures: an audit write must never be the
     * reason an account deactivation or a project approval 500s. A dropped entry
     * is reported for follow-up rather than surfaced to the user mid-action.
     */
    public static function record(string $action, string $summary, ?Model $subject = null, array $context = []): void
    {
        try {
            $actor = Auth::user();

            static::create([
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name ?? 'System',
                'actor_role' => $actor?->role,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? static::labelFor($subject) : null,
                'summary' => $summary,
                'context' => $context ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Best-effort display name for a subject, so the log stays readable even if
     * the underlying row is later renamed or removed.
     */
    protected static function labelFor(Model $subject): string
    {
        return $subject->title
            ?? $subject->name
            ?? class_basename($subject) . ' #' . $subject->getKey();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
