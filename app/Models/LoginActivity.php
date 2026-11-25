<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends \Illuminate\Database\Eloquent\Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED_PASSWORD = 'failed_password';
    public const STATUS_FAILED_MFA = 'failed_mfa';
    public const STATUS_INACTIVE_ACCOUNT = 'inactive_account';
    public const STATUS_EMAIL_UNVERIFIED = 'email_unverified';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'status',
        'ip_address',
        'ip_hash',
        'location_label',
        'device_type',
        'browser',
        'operating_system',
        'mfa_used',
        'is_suspicious',
        'failure_reason',
        'suspicious_reason',
        'review_status',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        // IP addresses are sensitive security data. Keep them encrypted at rest;
        // the hash is only for repeated-attempt comparisons.
        'ip_address' => 'encrypted',
        'mfa_used' => 'boolean',
        'is_suspicious' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'success' => 'Successful',
            'failed_password' => 'Wrong password',
            'failed_mfa' => 'MFA failed',
            'inactive_account' => 'Inactive account',
            'email_unverified' => 'Email not verified',
            default => ucwords(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function statusTone(): array
    {
        return match ($this->status) {
            'success' => ['#ecfdf5', '#047857'],
            'failed_password', 'failed_mfa' => ['#fef2f2', '#b91c1c'],
            default => ['#fffbeb', '#a16207'],
        };
    }

    public function deviceLabel(): string
    {
        return trim(implode(' · ', array_filter([
            $this->device_type,
            $this->browser,
            $this->operating_system,
        ]))) ?: 'Device unavailable';
    }

    public function maskedIp(): string
    {
        $ip = (string) $this->ip_address;
        if ($ip === '') {
            return 'Unavailable';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'xxx';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4)).':…';
        }

        return 'Unavailable';
    }
}
