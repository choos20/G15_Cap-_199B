<?php

namespace App\Http\Middleware;

use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps required or enrolled users inside the MFA enrollment/challenge flow
 * until the current login session has successfully completed MFA.
 */
class EnsureMfaVerified
{
    private const ALLOWED_ROUTES = [
        'login',
        'login.submit',
        'logout',
        'session.activity',
        'mfa.enroll',
        'mfa.enroll.verify',
        'mfa.challenge',
        'mfa.challenge.verify',
        'mfa.recovery.verify',
    ];

    private const PASSWORD_SETUP_ROUTES = [
        'account.password',
        'account.password.update',
    ];

    /**
     * Personal security pages remain available while MFA enrollment is
     * pending. The administrative audit route is intentionally not included.
     */
    private const PERSONAL_SECURITY_ROUTES = [
        'account.security',
        'account.login_activity',
        // The controller requires the current password plus a TOTP or
        // recovery code before an optional MFA enrollment can be removed.
        'account.mfa.disable',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if (! MfaService::requiresVerification($user)) {
            return $next($request);
        }

        if (in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if (in_array($routeName, self::PERSONAL_SECURITY_ROUTES, true)) {
            return $next($request);
        }

        // Temporary passwords must be changed before MFA enrollment starts.
        if ($user->must_change_password && in_array($routeName, self::PASSWORD_SETUP_ROUTES, true)) {
            return $next($request);
        }

        if (! $user->mfa_enabled_at) {
            return redirect()->route('mfa.enroll');
        }

        if ((int) $request->session()->get('mfa_verified_user_id') === (int) $user->id) {
            return $next($request);
        }

        return redirect()->route('mfa.challenge');
    }
}
