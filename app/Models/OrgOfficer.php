<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $position
 * @property string|null $email
 * @property string|null $avatar_path
 * @property int $sort_order
 */
class OrgOfficer extends Model
{
    protected $fillable = ['user_id', 'name', 'position', 'email', 'sort_order', 'avatar_path'];

    /** The organization this officer belongs to. */
    public function organization()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Up to two initials for the avatar circle. */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->take(2)
            ->implode('');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : null;
    }
}
