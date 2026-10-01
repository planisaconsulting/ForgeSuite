<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SystemRepository;

/**
 * Database backups live under storage/backups, which the web server denies.
 * A failed run does not delete an older successful file.
 */
final class BackupService
{
    public function __construct(private readonly SystemRepository $system = new SystemRepository())
    {
    }

    public function directory(): string
    {
        $dir = base_path('storage/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $deny = $dir . '/.htaccess';
        if (!is_file($deny)) {
            file_put_contents($deny, "Require all denied\n");
        }

        return $dir;
    }

    /**
     * @return array{ok: bool, id: int, message: string}
     */
    public function create(?int $userId): array
    {
        $name = 'signforge-' . date('Ymd-His') . '.sql';
        $id = $this->system->insertBackup([
            'backup_type' => 'DATABASE',
            'filename' => $name,
            'created_by' => $userId,
            'notes' => 'Started',
        ]);
        $path = $this->directory() . '/' . $name;
        try {
            $this->write($path);
            $size = is_file($path) ? (int) filesize($path) : 0;
            if ($size < 1) {
                throw new \RuntimeException('The backup file is empty.');
            }
            $this->system->finishBackup($id, 'SUCCESS', $size, 'Database dump completed.');
            $this->prune();

            return ['ok' => true, 'id' => $id, 'message' => 'Backup completed.'];
        } catch (\Throwable $e) {
            if (is_file($path) && (int) filesize($path) < 1) {
                unlink($path);
            }
            $this->system->finishBackup($id, 'FAILED', is_file($path) ? (int) filesize($path) : 0, mb_substr($e->getMessage(), 0, 240));

            return ['ok' => false, 'id' => $id, 'message' => 'Backup failed. The previous successful file was kept.'];
        }
    }

    public function pathFor(array $backup): ?string
    {
        $name = basename((string) $backup['filename']);
        $path = $this->directory() . '/' . $name;
        $real = realpath($path);
        $root = realpath($this->directory());
        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        if (!is_file($real) || (int) ($backup['file_size'] ?? 0) < 1 && (string) $backup['status'] !== 'SUCCESS') {
            return is_file($real) ? $real : null;
        }

        return $real;
    }

    /**
     * Drop older files only after a newer successful backup exists.
     */
    public function prune(): void
    {
        $latest = $this->system->latestSuccessfulBackup();
        if ($latest === null) {
            return;
        }
        $daily = max(1, (int) (SettingsService::get('backup_keep_daily', '7') ?? '7'));
        $weekly = max(1, (int) (SettingsService::get('backup_keep_weekly', '4') ?? '4'));
        $monthly = max(1, (int) (SettingsService::get('backup_keep_monthly', '6') ?? '6'));
        $success = [];
        foreach ($this->system->backups() as $row) {
            if ((string) $row['status'] !== 'SUCCESS') {
                continue;
            }
            $success[] = $row;
        }
        $keep = [];
        $dailyN = 0;
        $weeklyN = 0;
        $monthlyN = 0;
        $seenWeek = [];
        $seenMonth = [];
        foreach ($success as $row) {
            $stamp = strtotime((string) $row['started_at']) ?: time();
            if ($dailyN < $daily) {
                $keep[(int) $row['id']] = true;
                $dailyN++;
            }
            $week = date('o-W', $stamp);
            if ($weeklyN < $weekly && !isset($seenWeek[$week])) {
                $keep[(int) $row['id']] = true;
                $seenWeek[$week] = true;
                $weeklyN++;
            }
            $month = date('Y-m', $stamp);
            if ($monthlyN < $monthly && !isset($seenMonth[$month])) {
                $keep[(int) $row['id']] = true;
                $seenMonth[$month] = true;
                $monthlyN++;
            }
        }
        $keep[(int) $latest['id']] = true;
        foreach ($success as $row) {
            if (isset($keep[(int) $row['id']])) {
                continue;
            }
            $path = $this->pathFor($row);
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }
    }

    private function write(string $path): void
    {
        if ($this->mysqldump($path)) {
            return;
        }
        $this->phpDump($path);
    }

    private function mysqldump(string $path): bool
    {
        $binary = trim((string) shell_exec('command -v mysqldump 2>/dev/null'));
        if ($binary === '' || !is_file($binary)) {
            return false;
        }
        $command = escapeshellarg($binary)
            . ' --single-transaction --skip-lock-tables --no-tablespaces'
            . ' -h ' . escapeshellarg((string) config('db.host'))
            . ' -P ' . escapeshellarg((string) config('db.port', 3306))
            . ' -u ' . escapeshellarg((string) config('db.user'))
            . ' ' . escapeshellarg((string) config('db.name'));
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['file', $path, 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = ['MYSQL_PWD' => (string) config('db.pass')];
        $process = proc_open($command, $descriptor, $pipes, null, $env);
        if (!is_resource($process)) {
            return false;
        }
        fclose($pipes[0]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new \RuntimeException(trim((string) $error) !== '' ? trim((string) $error) : 'mysqldump failed.');
        }

        return true;
    }

    private function phpDump(string $path): void
    {
        $pdo = \App\Helpers\Database::connection();
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not write the backup file.');
        }
        fwrite($handle, "-- Sign-Forge PHP dump. mysqldump was not available.\nSET NAMES utf8mb4;\n");
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as $tableRow) {
            $table = (string) $tableRow[0];
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(\PDO::FETCH_ASSOC);
            $statement = (string) ($create['Create Table'] ?? '');
            fwrite($handle, "\nDROP TABLE IF EXISTS `{$table}`;\n{$statement};\n");
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '`');
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
                }
                fwrite($handle, 'INSERT INTO `' . $table . '` VALUES (' . implode(',', $values) . ");\n");
            }
        }
        fclose($handle);
    }
}
