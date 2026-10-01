<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Staff roles stored in users.role.
 *
 * The database column is plain text so a future role does not need a
 * schema change. Add the new case here and check it when saving a user.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
            self::Production => 'Production',
        };
    }
}
