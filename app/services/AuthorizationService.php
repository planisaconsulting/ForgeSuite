<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RoleRepository;

/**
 * Decides whether the signed-in role may perform an action.
 *
 * ADMIN is allowed everything, even if a permission row is missing, so an
 * incomplete seed cannot lock the owner out. Other roles only receive the
 * codes stored in role_permissions.
 */
final class AuthorizationService
{
    /** @var array<int, list<string>> */
    private static array $cache = [];

    /**
     * @param array<string, mixed>|null $user
     */
    public static function allows(?array $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }
        if (($user['role_code'] ?? '') === 'ADMIN') {
            return true;
        }
        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId < 1) {
            return false;
        }
        if (!isset(self::$cache[$roleId])) {
            self::$cache[$roleId] = (new RoleRepository())->permissionCodes($roleId);
        }

        return in_array($permission, self::$cache[$roleId], true);
    }
}
