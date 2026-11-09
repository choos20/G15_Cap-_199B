<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolunteerApplicationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'volunteer_application_id',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    public function application()
    {
        return $this->belongsTo(VolunteerApplication::class, 'volunteer_application_id');
    }
}
