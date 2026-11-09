<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCustomQuestion extends Model
{
    public const TYPES = [
        'rating' => 'Rating (1–5)',
        'text' => 'Written answer',
        'yes_no_maybe' => 'Yes / No / Maybe',
        'multiple_choice' => 'Multiple choice',
    ];

    protected $fillable = [
        'evaluation_id',
        'respondent_type',
        'question',
        'question_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function answers()
    {
        return $this->hasMany(EvaluationCustomAnswer::class, 'question_id');
    }
}
