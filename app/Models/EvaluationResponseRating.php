<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One criterion's score on one form.
 *
 * @property int $id
 * @property int $response_id
 * @property int $criterion_id
 * @property int $score
 */
class EvaluationResponseRating extends Model
{
    use HasFactory;

    protected $fillable = ['response_id', 'criterion_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function response()
    {
        return $this->belongsTo(EvaluationResponse::class, 'response_id');
    }

    public function criterion()
    {
        return $this->belongsTo(EvaluationCriterion::class, 'criterion_id');
    }
}
