<?php

namespace App\Services;

use App\Models\User;

final class LoginActivityAccess
{
    /** Roles whose login records are considered administrative/staff activity. */
    public const STAFF_ROLES = [
        'celp_head',
        'csa_director',
        'celp_secretary',
        'coordinator',
    ];

    public const ALL_ROLES = [
        'celp_head',
        'csa_director',
        'celp_secretary',
        'coordinator',
        'student_org',
        'college_department',
        'participant',
    ];

    public static function canViewAudit(?User $user): bool
    {
        return $user !== null && in_array($user->role, ['celp_head', 'csa_director'], true);
    }

    /**
     * A CELP Head sees authorized staff activity. The CSA Director is the
     * system-wide security reviewer and may see all account roles.
     * A null return means no role filter should be applied.
     */
    public static function visibleRoles(?User $user): ?array
    {
        if ($user?->role === 'csa_director') {
            return null;
        }

        if ($user?->role === 'celp_head') {
            return self::STAFF_ROLES;
        }

        return [];
    }

    public static function scopeLabel(?User $user): string
    {
        return $user?->role === 'csa_director'
            ? 'System-wide login activity'
            : 'Authorized staff login activity';
    }
}
