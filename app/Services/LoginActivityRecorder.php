<?php

namespace App\Services;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class LoginActivityRecorder
{
    public const SUCCESS = 'success';
    public const FAILED_PASSWORD = 'failed_password';
    public const FAILED_MFA = 'failed_mfa';
    public const INACTIVE_ACCOUNT = 'inactive_account';
    public const EMAIL_UNVERIFIED = 'email_unverified';

    public function recordSuccess(Request $request, User $user, bool $mfaUsed = false): void
    {
        $this->record($request, $user, self::SUCCESS, $mfaUsed);
    }

    public function recordPasswordFailure(Request $request, string $identifier): void
    {
        try {
            if (! $this->available()) {
                return;
            }

            $normalized = mb_strtolower(trim($identifier));
            $user = filter_var($normalized, FILTER_VALIDATE_EMAIL)
                ? User::query()->whereRaw('LOWER(email) = ?', [$normalized])->first()
                : User::query()->whereRaw('LOWER(username) = ?', [$normalized])->first();

            $this->record($request, $user, self::FAILED_PASSWORD, false, 'password');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function recordFailure(
        Request $request,
        ?User $user,
        string $status,
        ?string $reason = null
    ): void {
        $this->record($request, $user, $status, false, $reason);
    }

    public function recordBlocked(Request $request, User $user, string $status, string $reason): void
    {
        $this->record($request, $user, $status, false, $reason);
    }

    private function record(
        Request $request,
        ?User $user,
        string $status,
        bool $mfaUsed,
        ?string $reason = null
    ): void {
        try {
            if (! $this->available()) {
                return;
            }

            $ip = $request->ip();
            $ipHash = $ip !== null && $ip !== ''
                ? hash_hmac('sha256', $ip, (string) config('app.key'))
                : null;
            $recentFailures = $ipHash
                ? LoginActivity::query()
                    ->where('ip_hash', $ipHash)
                    ->whereIn('status', [self::FAILED_PASSWORD, self::FAILED_MFA])
                    ->where('created_at', '>=', now()->subMinutes(15))
                    ->count()
                : 0;
            $suspicious = $status === self::FAILED_MFA || $recentFailures >= 3;

            LoginActivity::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'user_role' => $user?->role,
                'status' => $status,
                'ip_address' => $ip,
                'ip_hash' => $ipHash,
                'location_label' => $this->locationLabel($request),
                'device_type' => $this->deviceType($request->userAgent()),
                'browser' => $this->browser($request->userAgent()),
                'operating_system' => $this->operatingSystem($request->userAgent()),
                'mfa_used' => $mfaUsed,
                'is_suspicious' => $suspicious,
                'failure_reason' => $reason,
                'suspicious_reason' => $suspicious
                    ? ($status === self::FAILED_MFA ? 'MFA verification failed' : 'Repeated failed sign-in attempts')
                    : null,
            ]);
        } catch (\Throwable $e) {
            // Security telemetry must never turn a valid login into a 500.
            report($e);
        }
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('login_activities');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function locationLabel(Request $request): string
    {
        // These headers are populated only by a trusted edge proxy such as
        // Cloudflare. We deliberately do not call a public geo API during
        // login: it would slow authentication and leak every IP to a third
        // party. The captured IP remains available to authorized reviewers.
        $city = trim((string) $request->header('CF-IPCity', ''));
        $country = trim((string) $request->header('CF-IPCountry', ''));

        if ($city !== '' && $country !== '') {
            return $city.', '.$country;
        }

        if ($country !== '') {
            return $country;
        }

        return 'Approximate location unavailable';
    }

    private function deviceType(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'Tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    private function browser(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    private function operatingSystem(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        return match (true) {
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
    }
}
