<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $applicant_id
 * @property string $status
 * @property string|null $secretary_comments
 * @property string|null $coordinator_comments
 */

class Proposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'applicant_id',
        'status',
        'secretary_comments',
        'coordinator_comments',
    ];

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }
}
