<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $session_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $scanned_at
 * @property string|null $scanned_lat
 * @property string|null $scanned_lng
 * @property string|null $distance_meters
 * @property string $status
 * @property string $attendance_source
 * @property string|null $check_in_status
 * @property string|null $check_out_status
 * @property \Illuminate\Support\Carbon|null $check_in_at
 * @property \Illuminate\Support\Carbon|null $check_out_at
 * @property string|null $check_in_lat
 * @property string|null $check_in_lng
 * @property string|null $check_out_lat
 * @property string|null $check_out_lng
 * @property string|null $check_in_distance_meters
 * @property string|null $check_out_distance_meters
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AttendanceSession|null $session
 * @property-read User|null $user
 */
class AttendanceRecord extends Model
{
    use HasFactory;

    public const MANUAL_CSV_SOURCE = 'manual_csv';

    public const CSV_SOURCES = ['offline_csv', self::MANUAL_CSV_SOURCE];

    protected $fillable = [
        'session_id',
        'user_id',
        'scanned_at',
        'scanned_lat',
        'scanned_lng',
        'distance_meters',
        'status',
        'attendance_source',
        'check_in_status',
        'check_out_status',
        'check_in_at',
        'check_out_at',
        'check_in_lat',
        'check_in_lng',
        'check_out_lat',
        'check_out_lng',
        'check_in_distance_meters',
        'check_out_distance_meters',
        'note',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
