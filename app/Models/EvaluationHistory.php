<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationHistory extends Model
{
    protected $table = 'evaluation_history';

    protected $fillable = ['user_id', 'action', 'reason', 'beneficiary_count', 'implementer_count'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
