<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Announcement;

class AnnouncementAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}
