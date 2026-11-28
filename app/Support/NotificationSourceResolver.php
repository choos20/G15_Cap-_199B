<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

/**
 * Resolves the safe display source for database notifications.
 *
 * Notification rows intentionally do not depend on a live sender record:
 * users and organizations may later be deactivated or removed. New rows carry
 * an actor id when available, while older rows can still fall back to their
 * related project creator or to the neutral ProjectLink identity.
 */
final class NotificationSourceResolver
{
    /**
     * @param iterable<int, mixed> $notifications
     * @return array<string, array{label:string, detail:?string, initials:string, avatar_url:?string, avatar_type:string, fallback:bool}>
     */
    public static function for(iterable $notifications): array
    {
        $items = collect($notifications)->values();
        if ($items->isEmpty()) {
            return [];
        }

        $actorIds = $items
            ->map(fn ($notification) => self::integerId(data_get($notification->data ?? [], 'actor_id')))
            ->filter()
            ->unique()
            ->values();

        $projectIds = $items
            ->map(fn ($notification) => self::integerId(data_get($notification->data ?? [], 'project_id')))
            ->filter()
            ->unique()
            ->values();

        $actors = $actorIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $actorIds)->get()->keyBy('id');

        $projects = $projectIds->isEmpty()
            ? collect()
            : Project::query()->with('creator')->whereIn('id', $projectIds)->get()->keyBy('id');

        return $items->mapWithKeys(function ($notification) use ($actors, $projects): array {
            $data = is_array($notification->data ?? null) ? $notification->data : [];
            $actorId = self::integerId($data['actor_id'] ?? null);

            if ($actorId !== null) {
                $actor = $actors->get($actorId);

                return [
                    (string) $notification->getKey() => $actor instanceof User
                        && self::isAvailable($actor)
                        ? self::userSource($actor)
                        : self::fallbackSource('Former account'),
                ];
            }

            $projectId = self::integerId($data['project_id'] ?? null);
            $creator = $projectId !== null ? $projects->get($projectId)?->creator : null;

            return [
                (string) $notification->getKey() => $creator instanceof User
                    && self::isAvailable($creator)
                    ? self::userSource($creator, 'Activity organization')
                    : self::fallbackSource($projectId !== null ? 'Former organization' : 'ProjectLink'),
            ];
        })->all();
    }

    private static function integerId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    private static function isAvailable(User $user): bool
    {
        return $user->getAttribute('is_active') !== false;
    }

    /** @return array{label:string, detail:?string, initials:string, avatar_url:?string, fallback:bool} */
    private static function userSource(User $user, ?string $context = null): array
    {
        $isOrganization = in_array($user->role, ['student_org', 'college_department'], true);
        $displayName = $isOrganization
            ? ($user->organization_name ?: $user->name)
            : $user->name;

        return [
            'label' => $context ?: $displayName,
            'detail' => $context ? $displayName : role_label($user->role),
            'initials' => self::initialsFor($displayName),
            'avatar_url' => $isOrganization ? $user->orgLogoUrl() : $user->profilePhotoUrl(),
            'avatar_type' => $isOrganization ? 'organization' : 'person',
            'fallback' => false,
        ];
    }

    private static function initialsFor(?string $displayName): string
    {
        return collect(preg_split('/\s+/', trim((string) $displayName)))
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('') ?: 'PL';
    }

    /** @return array{label:string, detail:?string, initials:string, avatar_url:?string, avatar_type:string, fallback:bool} */
    private static function fallbackSource(string $label): array
    {
        return [
            'label' => $label,
            'detail' => null,
            'initials' => 'PL',
            'avatar_url' => null,
            'avatar_type' => 'fallback',
            'fallback' => true,
        ];
    }
}
