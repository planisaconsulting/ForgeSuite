<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CommunicationRepository;
use App\Services\SettingsService;
use App\Services\WebhookService;

final class IntegrationController
{
    public function index(): void
    {
        $repo = new CommunicationRepository();
        View::render('admin/integrations', [
            'title' => 'Integrations',
            'activeNav' => 'integrations',
            'emailMode' => (string) SettingsService::get('email_delivery_mode', 'off'),
            'smtpHost' => (string) SettingsService::get('smtp_host', ''),
            'passwordStored' => $repo->hasSecret('smtp_password'),
            'webhookStored' => $repo->hasSecret('webhook_secret'),
            'lastEvent' => $repo->latestEvent(),
            'lastError' => $repo->latestFailure(),
        ]);
    }

    public function save(): void
    {
        $repo = new CommunicationRepository();
        $mode = (string) ($_POST['email_delivery_mode'] ?? 'off');
        if (!in_array($mode, ['off', 'log', 'smtp'], true)) {
            $mode = 'off';
        }
        (new \App\Repositories\SettingRepository())->put('email_delivery_mode', $mode);
        (new \App\Repositories\SettingRepository())->put('smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        SettingsService::forget();
        $password = (string) ($_POST['smtp_password'] ?? '');
        if ($password !== '') {
            $repo->putSecret('smtp_password', $password);
        }
        $webhook = (string) ($_POST['webhook_secret'] ?? '');
        if ($webhook !== '') {
            $repo->putSecret('webhook_secret', $webhook);
        }
        flash('success', 'Integration settings saved. Secrets are not shown again.');
        redirect('/admin/integrations');
    }

    public function webhook(string $provider): void
    {
        $raw = file_get_contents('php://input');
        $raw = $raw === false ? '' : $raw;
        $signature = (string) ($_SERVER['HTTP_X_SIGNFORGE_SIGNATURE'] ?? '');
        try {
            $result = (new WebhookService())->receive($provider, $raw, $signature);
        } catch (\Throwable) {
            json_response(['ok' => false], 500);
        }
        json_response($result['body'], $result['http']);
    }
}
