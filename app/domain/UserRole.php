<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Role codes stored in roles.code.
 *
 * The database is the list of roles. This enum documents the six that
 * Phase 1 seeds. A later role can be inserted without a schema change;
 * access still depends on role_permissions, and ADMIN is allowed everything.
 */
enum UserRole: string
{
    case Admin = 'ADMIN';
    case Sales = 'SALES';
    case Design = 'DESIGN';
    case Production = 'PRODUCTION';
    case Accounts = 'ACCOUNTS';
    case Installer = 'INSTALLER';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
            self::Design => 'Design',
            self::Production => 'Production',
            self::Accounts => 'Accounts',
            self::Installer => 'Installer',
        };
    }
}
