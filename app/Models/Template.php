<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @property int $id
 * @property string $original_name
 * @property string $file_path
 * @property int $uploaded_by
 */

class Template extends Model
{
    use HasFactory;

    public const SOURCES = [
        'celp' => 'CELP',
        'csa' => 'CSA',
    ];

    protected $fillable = ['original_name', 'file_path', 'file_data', 'file_mime', 'uploaded_by', 'source'];

    public static function sourceForRole(string $role): ?string
    {
        return match ($role) {
            'csa_director' => 'csa',
            'celp_head', 'coordinator', 'celp_secretary' => 'celp',
            default => null,
        };
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? 'Source unavailable';
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->role === 'celp_head' || (int) $this->uploaded_by === (int) $user->id;
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
