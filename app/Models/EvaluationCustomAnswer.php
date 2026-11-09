<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCustomAnswer extends Model
{
    protected $fillable = ['response_id', 'question_id', 'answer'];

    public function response()
    {
        return $this->belongsTo(EvaluationResponse::class, 'response_id');
    }

    public function question()
    {
        return $this->belongsTo(EvaluationCustomQuestion::class, 'question_id');
    }
}
