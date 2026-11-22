<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\MfaService;
use App\Services\AuthenticationProtection;
use App\Services\LoginActivityRecorder;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function showEnrollment(Request $request): View|RedirectResponse
    {
        $user = $this->enrollmentUser($request);

        if (MfaService::isEnabled($user)) {
            return MfaService::isProtectedUser($user)
                ? redirect()->route('mfa.challenge')
                : redirect()->route('account.security');
        }

        $secret = $request->session()->get('mfa_setup_secret');
        if (! is_string($secret) || $secret === '') {
            $secret = TotpService::generateSecret();
            $request->session()->put('mfa_setup_secret', $secret);
        }

        return view('mfa.enroll', [
            'user' => $user,
            'manualKey' => TotpService::formatSecret($secret),
            'qrCode' => TotpService::qrCodeDataUri(TotpService::provisioningUri($user, $secret)),
        ]);
    }

    public function enroll(Request $request): View|RedirectResponse
    {
        $user = $this->enrollmentUser($request);

        if (MfaService::isEnabled($user)) {
            return MfaService::isProtectedUser($user)
                ? redirect()->route('mfa.challenge')
                : redirect()->route('account.security');
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $secret = $request->session()->get('mfa_setup_secret');
        if (! is_string($secret) || $secret === '') {
            return redirect()->route('mfa.enroll')
                ->withErrors(['code' => 'Your enrollment session expired. Please scan a new QR code.']);
        }

        if (! TotpService::verify($secret, (string) $request->input('code'))) {
            app(LoginActivityRecorder::class)->recordFailure(
                $request,
                $user,
                LoginActivityRecorder::FAILED_MFA,
                'enrollment_code'
            );

            return back()->withErrors(['code' => 'That code is incorrect or expired. Check SafeAuth and try again.']);
        }

        $recoveryCodes = MfaService::enable($user, $secret);
        $isProtected = MfaService::isProtectedUser($user);

        $request->session()->regenerate();
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified_user_id', $user->id);
        if ($isProtected) {
            app(LoginActivityRecorder::class)->recordSuccess($request, $user, true);
        }
        AuditLog::record('account.mfa_enabled', 'Multi-factor authentication enabled.', $user);

        return view('mfa.recovery_codes', [
            'user' => $user->fresh(),
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_if(
            MfaService::isProtectedUser($user),
            403,
            'MFA is required for this role and cannot be disabled here.'
        );

        if (! $user->mfa_enabled_at || ! filled($user->mfa_secret)) {
            return redirect()->route('account.security');
        }

        $request->validate([
            'mfa_current_password' => ['required', 'string'],
            'mfa_code' => ['required', 'string', 'max:32'],
        ]);

        if (! Hash::check((string) $request->input('mfa_current_password'), (string) $user->password)) {
            return back()->withErrors(['mfa_current_password' => 'Current password is incorrect.']);
        }

        $protection = app(AuthenticationProtection::class);
        $protectionKey = $protection->mfaKey($user->id);
        if ($remaining = $protection->remaining($protectionKey)) {
            return back()->withErrors(['mfa_code' => AuthenticationProtection::cooldownMessage($remaining)]);
        }

        $submittedCode = trim((string) $request->input('mfa_code'));
        $validCode = TotpService::verify((string) $user->mfa_secret, $submittedCode)
            || MfaService::consumeRecoveryCode($user, $submittedCode);

        if (! $validCode) {
            $remaining = $protection->failed($protectionKey);
            return back()->withErrors(['mfa_code' => $remaining ? AuthenticationProtection::cooldownMessage($remaining) : 'Enter a current authenticator code or an unused recovery code.']);
        }

        $protection->clear($protectionKey);
        MfaService::disable($user);
        $request->session()->forget(['mfa_verified_user_id', 'mfa_setup_secret']);
        AuditLog::record('account.mfa_disabled', 'Multi-factor authentication disabled.', $user);

        return redirect()
            ->to(route('account.security') . '#mfa')
            ->with('status', 'MFA has been disabled for this account.');
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $user = $this->mfaUser($request);

        if (! MfaService::isEnabled($user)) {
            return MfaService::isProtectedUser($user)
                ? redirect()->route('mfa.enroll')
                : redirect()->route('account.security');
        }

        if ((int) $request->session()->get('mfa_verified_user_id') === (int) $user->id) {
            return redirect()->route(dashboard_route_for($user));
        }

        return view('mfa.challenge', ['user' => $user]);
    }

    public function challenge(Request $request): RedirectResponse
    {
        $user = $this->mfaUser($request);

        if (! MfaService::isEnabled($user)) {
            return MfaService::isProtectedUser($user)
                ? redirect()->route('mfa.enroll')
                : redirect()->route('account.security');
        }

        $protection = app(AuthenticationProtection::class);
        $protectionKey = $protection->mfaKey($user->id);
        if ($remaining = $protection->remaining($protectionKey)) {
            return back()->withErrors(['code' => AuthenticationProtection::cooldownMessage($remaining)]);
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! TotpService::verify((string) $user->mfa_secret, (string) $request->input('code'))) {
            $remaining = $protection->failed($protectionKey);
            app(LoginActivityRecorder::class)->recordFailure(
                $request,
                $user,
                LoginActivityRecorder::FAILED_MFA,
                'challenge_code'
            );

            return back()->withErrors(['code' => $remaining ? AuthenticationProtection::cooldownMessage($remaining) : 'That code is incorrect or expired. Check SafeAuth and try again.']);
        }

        $protection->clear($protectionKey);
        $this->markVerified($request, $user);

        return redirect()->route(dashboard_route_for($user));
    }

    public function recovery(Request $request): RedirectResponse
    {
        $user = $this->mfaUser($request);

        if (! MfaService::isEnabled($user)) {
            return MfaService::isProtectedUser($user)
                ? redirect()->route('mfa.enroll')
                : redirect()->route('account.security');
        }

        $protection = app(AuthenticationProtection::class);
        $protectionKey = $protection->mfaKey($user->id);
        if ($remaining = $protection->remaining($protectionKey)) {
            return back()->withErrors(['recovery_code' => AuthenticationProtection::cooldownMessage($remaining)]);
        }

        $request->validate([
            'recovery_code' => ['required', 'string', 'max:32'],
        ]);

        if (! MfaService::consumeRecoveryCode($user, (string) $request->input('recovery_code'))) {
            $remaining = $protection->failed($protectionKey);
            app(LoginActivityRecorder::class)->recordFailure(
                $request,
                $user,
                LoginActivityRecorder::FAILED_MFA,
                'recovery_code'
            );

            return back()->withErrors(['recovery_code' => $remaining ? AuthenticationProtection::cooldownMessage($remaining) : 'That recovery code is invalid or has already been used.']);
        }

        $protection->clear($protectionKey);
        $this->markVerified($request, $user);

        return redirect()->route(dashboard_route_for($user));
    }

    private function mfaUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && MfaService::requiresVerification($user), 403);

        return $user;
    }

    private function enrollmentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function markVerified(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified_user_id', $user->id);
        app(LoginActivityRecorder::class)->recordSuccess($request, $user, true);
    }
}
