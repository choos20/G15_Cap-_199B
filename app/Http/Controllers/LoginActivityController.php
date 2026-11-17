<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Services\LoginActivityAccess;
use Illuminate\Http\Request;
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
        $suspiciousCount = (clone $recent)->where('is_suspicious', true)->count();

        return view('account.security', [
            'activities' => $activities,
            'lastSuccessful' => $lastSuccessful,
            'successfulCount' => $successfulCount,
            'failedCount' => $failedCount,
            'suspiciousCount' => $suspiciousCount,
            'canViewAudit' => LoginActivityAccess::canViewAudit($user),
        ]);
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
