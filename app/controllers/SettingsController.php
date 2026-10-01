<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\SettingsService;

final class SettingsController
{
    public function edit(): void
    {
        $service = new SettingsService();
        $this->form($service->all(), []);
    }

    public function update(): void
    {
        $service = new SettingsService();
        $errors = $service->update($_POST);
        if ($errors !== []) {
            $this->form($_POST, $errors);

            return;
        }
        $tz = SettingsService::get('timezone', 'Africa/Johannesburg');
        if (is_string($tz) && in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
        }
        flash('success', 'Settings saved.');
        redirect('/settings');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors): void
    {
        View::render('settings/index', [
            'title' => 'Settings',
            'activeNav' => 'settings',
            'fields' => SettingsService::FIELDS,
            'old' => $old,
            'errors' => $errors,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
