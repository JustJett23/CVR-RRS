<?php

namespace App\Services;

use App\Models\User;

class AccountRolePath
{
    /** @var array<string, string> */
    private const ROLE_PATHS = [
        'student' => 'student',
        'org-leader' => 'org_leader',
        'professor' => 'professor',
        'admin' => 'admin',
        'creator' => 'creator',
    ];

    /** @var list<string> */
    private const LEGACY_PATHS = [
        'activity-logs', 'api', 'availability', 'dashboard', 'equipment', 'equipment-requests', 'floor-plan',
        'help', 'logout', 'notifications', 'profile', 'reports', 'reservations', 'rooms', 'schedule', 'settings',
    ];

    public static function for(User $user): string
    {
        $path = array_search($user->role, self::ROLE_PATHS, true);
        abort_unless(is_string($path), 403);

        return $path;
    }

    public static function recognizes(string $path): bool
    {
        return array_key_exists($path, self::ROLE_PATHS);
    }

    public static function isLegacyPath(string $path): bool
    {
        return in_array($path, self::LEGACY_PATHS, true);
    }
}
