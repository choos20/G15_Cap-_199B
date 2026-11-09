<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The evaluation batch for one completed activity.
 *
 * @property int         $id
 * @property int         $announcement_id
 * @property int|null    $expected_beneficiaries
 * @property \Carbon\Carbon|null $submitted_at
 * @property int|null    $submitted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $responses_count
 */
class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'expected_beneficiaries',
        'submitted_at',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function responses()
    {
        return $this->hasMany(EvaluationResponse::class);
    }

    public function customQuestions()
    {
        return $this->hasMany(EvaluationCustomQuestion::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Status shown on the list. Derived rather than stored so it can never
     * drift out of step with the responses actually on file.
     */
    public function status(): string
    {
        if ($this->isSubmitted()) {
            return 'submitted';
        }

        return ($this->responses_count ?? $this->responses()->count()) > 0
            ? 'encoding'
            : 'not_started';
    }

    public const STATUS_LABELS = [
        'not_started' => 'Not started',
        'encoding' => 'Encoding in progress',
        'submitted' => 'Submitted',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status()];
    }

    /**
     * Mean score across every criterion on every form in this batch, or null
     * when nothing has been encoded yet.
     */
    public function overallAverage(?string $respondentType = null): ?float
    {
        $avg = EvaluationResponseRating::query()
            ->whereIn('response_id', $this->responses()
                ->when($respondentType, fn ($query) => $query->where('respondent_type', $respondentType))
                ->select('id'))
            ->avg('score');

        return $avg === null ? null : round((float) $avg, 2);
    }

    /**
     * Mean score per criterion, keyed by criterion id. Covers every criterion
     * that has been scored here, including ones since retired.
     *
     * @return array<int, float>
     */
    public function averagesByCriterion(?string $respondentType = null): array
    {
        return EvaluationResponseRating::query()
            ->whereIn('response_id', $this->responses()
                ->when($respondentType, fn ($query) => $query->where('respondent_type', $respondentType))
                ->select('id'))
            ->selectRaw('criterion_id, AVG(score) as avg_score')
            ->groupBy('criterion_id')
            ->pluck('avg_score', 'criterion_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /** Response counts split by how the form arrived. */
    public function countsBySource(): array
    {
        return $this->responses()
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->all();
    }
}
