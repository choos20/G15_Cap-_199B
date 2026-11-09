<?php

namespace App\Models;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $project_id
 * @property string $qr_token
 * @property numeric-string $latitude
 * @property numeric-string $longitude
 * @property int $radius_meters
 * @property \Illuminate\Support\Carbon|null $opens_at
 * @property \Illuminate\Support\Carbon|null $closes_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Project|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttendanceRecord> $records
 * @property-read string $scan_url
 * @property-read string $qr_code_data_uri
 */
class AttendanceSession extends Model
{
    use HasFactory;

    public const RADIUS_METERS = 50;

    protected $fillable = [
        'project_id',
        'qr_token',
        'latitude',
        'longitude',
        'radius_meters',
        'opens_at',
        'closes_at',
        'status',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->qr_token)) {
                $session->qr_token = Str::random(40);
            }
            if (empty($session->radius_meters)) {
                $session->radius_meters = self::RADIUS_METERS;
            }
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    public function getScanUrlAttribute()
    {
        return route('attendance.scan.view', ['token' => $this->qr_token]);
    }

    public function getQrCodeDataUriAttribute(): string
    {
        $payload = $this->scan_url;

        try {
            if (extension_loaded('gd') && function_exists('gd_info')) {
                $renderer = new GDLibRenderer(240);
                $writer = new Writer($renderer);

                return 'data:image/png;base64,' . base64_encode($writer->writeString($payload));
            }
        } catch (\Throwable) {
            // Fall through to SVG generation.
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(240),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($payload);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return '';
        }
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function isActive(): bool
    {
        return $this->status === 'open' && now()->between($this->opens_at, $this->closes_at);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
