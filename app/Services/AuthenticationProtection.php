<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthenticationProtection
{
    private const WINDOW = 3600;

    public function loginKey(string $identifier): string
    {
        return 'auth-protection:login:'.hash('sha256', Str::transliterate(Str::lower(trim($identifier))));
    }

    public function mfaKey(int $userId): string
    {
        return 'auth-protection:mfa:'.hash('sha256', (string) $userId);
    }

    public function failures(string $key): int
    {
        return RateLimiter::attempts($key.':failures');
    }

    public function remaining(string $key): int
    {
        return max(0, (int) Cache::get($key.':locked-until', 0) - now()->timestamp);
    }

    public function requiresCaptcha(string $key): bool
    {
        return $this->failures($key) >= 3 || Cache::has($key.':captcha');
    }

    public function failed(string $key): int
    {
        if ($this->remaining($key) > 0) {
            return $this->remaining($key);
        }

        $failures = RateLimiter::hit($key.':failures', self::WINDOW);
        if ($failures >= 3) {
            Cache::put($key.':captcha', true, self::WINDOW);
        }
        if ($failures < 5) {
            return 0;
        }

        // Cache::add is atomic on supported shared stores. Only one concurrent
        // request should start and escalate a cooldown for this failure batch.
        if (! Cache::add($key.':cooldown-starting', true, 10)) {
            return max(1, $this->remaining($key));
        }

        $strikes = RateLimiter::hit($key.':strikes', 86400);
        $seconds = [300, 900, 3600][min($strikes - 1, 2)];
        Cache::put($key.':locked-until', now()->timestamp + $seconds, $seconds);
        RateLimiter::clear($key.':failures');

        return $seconds;
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key.':failures');
        RateLimiter::clear($key.':strikes');
        Cache::forget($key.':locked-until');
        Cache::forget($key.':captcha');
        Cache::forget($key.':cooldown-starting');
    }

    public static function cooldownMessage(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        return "Too many attempts. Please wait up to {$minutes} minute".($minutes === 1 ? '' : 's').' before trying again.';
    }
}
