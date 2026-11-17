<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MfaService;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function showEnrollment(Request $request): View|RedirectResponse
    {
        $user = $this->protectedUser($request);

        if ($user->mfa_enabled_at) {
            return redirect()->route('mfa.challenge');
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
        $user = $this->protectedUser($request);

        if ($user->mfa_enabled_at) {
            return redirect()->route('mfa.challenge');
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
            return back()->withErrors(['code' => 'That code is incorrect or expired. Check SafeAuth and try again.']);
        }

        $recoveryCodes = MfaService::enable($user, $secret);

        $request->session()->regenerate();
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified_user_id', $user->id);

        return view('mfa.recovery_codes', [
            'user' => $user->fresh(),
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $user = $this->protectedUser($request);

        if (! $user->mfa_enabled_at || ! filled($user->mfa_secret)) {
            return redirect()->route('mfa.enroll');
        }

        if ((int) $request->session()->get('mfa_verified_user_id') === (int) $user->id) {
            return redirect()->route(dashboard_route_for($user));
        }

        return view('mfa.challenge', ['user' => $user]);
    }

    public function challenge(Request $request): RedirectResponse
    {
        $user = $this->protectedUser($request);

        if (! $user->mfa_enabled_at || ! filled($user->mfa_secret)) {
            return redirect()->route('mfa.enroll');
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! TotpService::verify((string) $user->mfa_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is incorrect or expired. Check SafeAuth and try again.']);
        }

        $this->markVerified($request, $user);

        return redirect()->route(dashboard_route_for($user));
    }

    public function recovery(Request $request): RedirectResponse
    {
        $user = $this->protectedUser($request);

        if (! $user->mfa_enabled_at) {
            return redirect()->route('mfa.enroll');
        }

        $request->validate([
            'recovery_code' => ['required', 'string', 'max:32'],
        ]);

        if (! MfaService::consumeRecoveryCode($user, (string) $request->input('recovery_code'))) {
            return back()->withErrors(['recovery_code' => 'That recovery code is invalid or has already been used.']);
        }

        $this->markVerified($request, $user);

        return redirect()->route(dashboard_route_for($user));
    }

    private function protectedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && MfaService::isProtectedUser($user), 403);

        return $user;
    }

    private function markVerified(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified_user_id', $user->id);
    }
}
