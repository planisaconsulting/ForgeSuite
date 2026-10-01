<?php

declare(strict_types=1);

namespace App\Repositories;

final class SettingRepository extends Repository
{
    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $map = [];
        foreach ($this->rows('SELECT setting_key, setting_value FROM settings') as $row) {
            $map[(string) $row['setting_key']] = $row['setting_value'] === null
                ? null
                : (string) $row['setting_value'];
        }

        return $map;
    }

    public function put(string $key, ?string $value): void
    {
        $this->run(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
    }
}
