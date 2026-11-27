<?php

namespace App\Models;

use App\Casts\BinaryFileData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'file_data',
        'file_mime',
        'file_sha256',
        'file_disk',
    ];

    protected $hidden = ['file_data'];

    protected $casts = ['file_data' => BinaryFileData::class];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
