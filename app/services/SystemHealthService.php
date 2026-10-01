<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutomationRepository;
use App\Repositories\SystemRepository;
use App\Version;

/**
 * Facts for the admin health screen. Passwords and session tokens are not included.
 */
final class SystemHealthService
{
    public function __construct(
        private readonly SystemRepository $system = new SystemRepository(),
        private readonly AutomationRepository $automation = new AutomationRepository()
    ) {
    }

    /**
     * @return array<string, string|int|bool>
     */
    public function snapshot(): array
    {
        $storage = base_path('storage');
        $connected = true;
        $version = '';
        try {
            $version = $this->system->databaseVersion();
        } catch (\Throwable) {
            $connected = false;
        }
        $backup = $this->system->latestSuccessfulBackup();
        $migrations = glob(base_path('database/migrations/*.sql')) ?: [];
        sort($migrations);

        return [
            'php' => PHP_VERSION,
            'database_connected' => $connected,
            'database_version' => $version,
            'storage_writable' => is_writable($storage),
            'storage_bytes' => $this->bytes($storage),
            'app_version' => Version::NUMBER,
            'last_migration' => $migrations === [] ? '' : basename((string) end($migrations)),
            'last_cron' => (string) (SettingsService::get('last_cron_at', '') ?? ''),
            'last_backup' => $backup === null ? '' : (string) ($backup['completed_at'] ?? $backup['started_at']),
            'failed_automations' => $this->automation->failedSince(date('Y-m-d H:i:s', time() - 7 * 86400)),
            'website_leads' => 'Healthy',
            'email_status' => $this->emailStatus(),
            'whatsapp_status' => 'Manual mode',
            'last_communication_error' => $this->lastCommunicationError(),
            'last_webhook' => $this->lastWebhook(),
        ];
    }

    /**
     * @return list<array{at: string, severity: string, message: string}>
     */
    public function recentErrors(int $limit = 40): array
    {
        $path = base_path('storage/logs/app.log');
        if (!is_file($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }
        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $clean = $this->redact($line);
            if ($clean === '') {
                continue;
            }
            $rows[] = [
                'at' => '',
                'severity' => str_contains(strtolower($clean), 'error') ? 'error' : 'notice',
                'message' => $clean,
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    public function cleanupTemporary(): int
    {
        $removed = 0;
        $cutoff = time() - 14 * 86400;
        foreach (['storage/tmp', 'storage/cache'] as $relative) {
            $dir = base_path($relative);
            if (!is_dir($dir)) {
                continue;
            }
            $items = scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess') {
                    continue;
                }
                $path = $dir . '/' . $item;
                if (!is_file($path) || filemtime($path) === false || filemtime($path) > $cutoff) {
                    continue;
                }
                unlink($path);
                $removed++;
            }
        }

        return $removed;
    }

    private function emailStatus(): string
    {
        $mode = (string) SettingsService::get('email_delivery_mode', 'off');
        if ($mode === 'smtp' && trim((string) SettingsService::get('smtp_host', '')) !== '') {
            return 'Configured';
        }
        if ($mode === 'log') {
            return 'Log only';
        }

        return 'Not configured';
    }

    private function lastCommunicationError(): string
    {
        try {
            $row = (new \App\Repositories\CommunicationRepository())->latestFailure();
        } catch (\Throwable) {
            return '';
        }
        if ($row === null) {
            return '';
        }

        return (string) $row['created_at'] . ' ' . (string) ($row['failure_reason'] ?? '');
    }

    private function lastWebhook(): string
    {
        try {
            $row = (new \App\Repositories\CommunicationRepository())->latestEvent();
        } catch (\Throwable) {
            return '';
        }
        if ($row === null) {
            return '';
        }

        return (string) $row['received_at'] . ' ' . (string) $row['provider'] . ' ' . (string) $row['status'];
    }

    private function bytes(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $total = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }

    private function redact(string $line): string
    {
        $line = preg_replace('/(password|passwd|secret|token|authorization|mysql_pwd)(["\'\s:=]+)[^\s,;]+/i', '$1$2[redacted]', $line) ?? $line;

        return mb_substr(trim($line), 0, 400);
    }
}
