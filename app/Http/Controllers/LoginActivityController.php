<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Services\LoginActivityAccess;
use App\Services\MfaService;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoginActivityController extends Controller
{
    public function personal(Request $request): View
    {
        $user = $request->user();

        $activities = $user->loginActivities()
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $lastSuccessful = $user->loginActivities()
            ->where('status', LoginActivity::STATUS_SUCCESS)
            ->latest('created_at')
            ->first();

        $recent = $user->loginActivities()
            ->where('created_at', '>=', now()->subDays(30));
        $successfulCount = (clone $recent)->where('status', LoginActivity::STATUS_SUCCESS)->count();
        $failedCount = (clone $recent)->whereIn('status', [
            LoginActivity::STATUS_FAILED_PASSWORD,
            LoginActivity::STATUS_FAILED_MFA,
        ])->count();
        $suspiciousCount = (clone $recent)
            ->where('is_suspicious', true)
            ->whereNull('review_status')
            ->count();

        return view('account.security', [
            'activities' => $activities,
            'lastSuccessful' => $lastSuccessful,
            'successfulCount' => $successfulCount,
            'failedCount' => $failedCount,
            'suspiciousCount' => $suspiciousCount,
            'canViewAudit' => LoginActivityAccess::canViewAudit($user),
            'mfaRequired' => MfaService::isProtectedUser($user),
            'mfaEnabled' => MfaService::isEnabled($user),
            'mfaRecoveryCodesRemaining' => MfaService::remainingRecoveryCodes($user),
        ]);
    }

    public function review(Request $request, LoginActivity $activity)
    {
        $user = $request->user();

        abort_unless((int) $activity->user_id === (int) $user->id, 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['recognized', 'secure'])],
            'current_password' => ['nullable', 'string', 'max:'.PasswordPolicy::MAX_LENGTH],
        ]);

        if ($validated['decision'] === 'recognized') {
            $activity->forceFill([
                'review_status' => 'recognized',
                'reviewed_at' => now(),
                'reviewed_by' => $user->id,
            ])->save();

            return redirect()->route('account.security')->with(
                'status',
                'Activity marked as recognized. Your security settings were not changed.'
            );
        }

        if (! filled($validated['current_password'] ?? null)) {
            return back()->withErrors([
                'security_review' => 'Enter your current password to secure the account.',
            ]);
        }

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return back()->withErrors([
                'security_review' => 'Current password is incorrect. No security changes were made.',
            ]);
        }

        if (config('session.driver') !== 'database') {
            return back()->withErrors([
                'security_review' => 'Secure-session controls are unavailable. Please contact an administrator.',
            ]);
        }

        Auth::logoutOtherDevices($validated['current_password']);

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '<>', $request->session()->getId())
            ->delete();

        $activity->forceFill([
            'review_status' => 'secured',
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
        ])->save();

        return redirect()->route('account.security')->with(
            'status',
            'Your account was secured. Other active sessions were signed out. Consider changing your password and verifying MFA.'
        );
    }

    public function audit(Request $request): View
    {
        $user = $request->user();
        abort_unless(LoginActivityAccess::canViewAudit($user), 403);

        $visibleRoles = LoginActivityAccess::visibleRoles($user);
        $term = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $role = (string) $request->input('role', '');
        $suspiciousOnly = $request->boolean('suspicious');
        $from = $this->validDate($request->input('from'));
        $to = $this->validDate($request->input('to'));

        $query = LoginActivity::query();

        if ($visibleRoles !== null) {
            $query->whereIn('user_role', $visibleRoles);
        }

        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';
            $query->where(function ($builder) use ($like) {
                foreach (['user_name', 'user_email', 'location_label', 'browser', 'operating_system', 'status'] as $column) {
                    $builder->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                }
            });
        }

        if (in_array($status, [
            LoginActivity::STATUS_SUCCESS,
            LoginActivity::STATUS_FAILED_PASSWORD,
            LoginActivity::STATUS_FAILED_MFA,
            LoginActivity::STATUS_INACTIVE_ACCOUNT,
            LoginActivity::STATUS_EMAIL_UNVERIFIED,
        ], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        if ($role !== '' && ($visibleRoles === null || in_array($role, $visibleRoles, true))) {
            $query->where('user_role', $role);
        } else {
            $role = '';
        }

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($suspiciousOnly) {
            $query->where('is_suspicious', true);
        }

        $successCount = (clone $query)->where('status', LoginActivity::STATUS_SUCCESS)->count();
        $failedCount = (clone $query)->whereIn('status', [
            LoginActivity::STATUS_FAILED_PASSWORD,
            LoginActivity::STATUS_FAILED_MFA,
        ])->count();
        $suspiciousCount = (clone $query)->where('is_suspicious', true)->count();

        $activities = $query
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('security.login_audit', [
            'activities' => $activities,
            'term' => $term,
            'status' => $status,
            'role' => $role,
            'from' => $from,
            'to' => $to,
            'suspiciousOnly' => $suspiciousOnly,
            'visibleRoles' => $visibleRoles,
            'scopeLabel' => LoginActivityAccess::scopeLabel($user),
            'successCount' => $successCount,
            'failedCount' => $failedCount,
            'suspiciousCount' => $suspiciousCount,
        ]);
    }

    private function validDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
