<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CommunicationRepository;

/**
 * Outbound email. mail() is not used.
 *
 * off: nothing is delivered.
 * log: the message is written to storage/mail without SMTP secrets.
 * smtp: a direct SMTP conversation. A failure stays a failure.
 */
final class EmailService
{
    public function __construct(private readonly CommunicationRepository $secrets = new CommunicationRepository())
    {
    }

    public function configured(): bool
    {
        $mode = (string) SettingsService::get('email_delivery_mode', 'off');
        if ($mode === 'log') {
            return true;
        }
        if ($mode !== 'smtp') {
            return false;
        }

        return trim((string) SettingsService::get('smtp_host', '')) !== ''
            && trim((string) SettingsService::get('smtp_from_email', '')) !== '';
    }

    public function mode(): string
    {
        $mode = (string) SettingsService::get('email_delivery_mode', 'off');

        return in_array($mode, ['off', 'log', 'smtp'], true) ? $mode : 'off';
    }

    /**
     * @param list<string> $attachments
     * @return array{sent: bool, reason: string, provider_reference: ?string}
     */
    public function send(string $to, string $subject, string $body, array $attachments = []): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'reason' => 'That recipient address is not valid.', 'provider_reference' => null];
        }
        if (!$this->configured()) {
            return [
                'sent' => false,
                'reason' => 'Email delivery is not configured.',
                'provider_reference' => null,
            ];
        }
        foreach ($attachments as $path) {
            if (!$this->allowedAttachment($path)) {
                return ['sent' => false, 'reason' => 'An attachment is not an allowed file.', 'provider_reference' => null];
            }
        }
        if ($this->mode() === 'log') {
            return $this->log($to, $subject, $body, $attachments);
        }

        return $this->smtp($to, $subject, $body, $attachments);
    }

    /**
     * @param list<string> $attachments
     * @return array{sent: bool, reason: string, provider_reference: ?string}
     */
    private function log(string $to, string $subject, string $body, array $attachments): array
    {
        $dir = base_path('storage/mail');
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return ['sent' => false, 'reason' => 'The local outbox could not be created.', 'provider_reference' => null];
        }
        $reference = 'log-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $line = json_encode([
            'reference' => $reference,
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'attachments' => array_map('basename', $attachments),
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
        if ($line === false || file_put_contents($dir . '/outbox.jsonl', $line . "\n", FILE_APPEND) === false) {
            return ['sent' => false, 'reason' => 'The local outbox could not be written.', 'provider_reference' => null];
        }

        return [
            'sent' => true,
            'reason' => 'Recorded in the local outbox. This is not an external mailbox delivery.',
            'provider_reference' => $reference,
        ];
    }

    /**
     * @param list<string> $attachments
     * @return array{sent: bool, reason: string, provider_reference: ?string}
     */
    private function smtp(string $to, string $subject, string $body, array $attachments): array
    {
        $host = trim((string) SettingsService::get('smtp_host', ''));
        $port = (int) SettingsService::get('smtp_port', '587');
        $encryption = strtolower(trim((string) SettingsService::get('smtp_encryption', 'tls')));
        $username = (string) SettingsService::get('smtp_username', '');
        $password = (string) ($this->secrets->secret('smtp_password') ?? '');
        $from = trim((string) SettingsService::get('smtp_from_email', ''));
        $fromName = trim((string) SettingsService::get('smtp_from_name', ''));
        $reply = trim((string) SettingsService::get('smtp_reply_to', ''));
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $error, 15);
        if ($socket === false) {
            return ['sent' => false, 'reason' => 'The mail server could not be reached.', 'provider_reference' => null];
        }
        stream_set_timeout($socket, 15);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO signforge', [250]);
            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS failed');
                }
                $this->command($socket, 'EHLO signforge', [250]);
            }
            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $mime = $this->mime($from, $fromName, $to, $reply, $subject, $body, $attachments);
            fwrite($socket, $mime . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } catch (\Throwable) {
            fclose($socket);

            return ['sent' => false, 'reason' => 'The mail server did not accept the message.', 'provider_reference' => null];
        }
        fclose($socket);

        return ['sent' => true, 'reason' => '', 'provider_reference' => null];
    }

    /**
     * @param resource $socket
     * @param list<int> $ok
     */
    private function command($socket, string $command, array $ok): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $ok);
    }

    /**
     * @param resource $socket
     * @param list<int> $ok
     */
    private function expect($socket, array $ok): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new \RuntimeException('SMTP refused the step.');
        }
    }

    /**
     * @param list<string> $attachments
     */
    private function mime(string $from, string $fromName, string $to, string $reply, string $subject, string $body, array $attachments): string
    {
        $fromHeader = $fromName !== '' ? $this->encode($fromName) . ' <' . $from . '>' : $from;
        $headers = [
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: ' . $this->encode($subject),
            'Date: ' . date(DATE_RFC2822),
            'MIME-Version: 1.0',
        ];
        if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $reply;
        }
        if ($attachments === []) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';

            return implode("\r\n", $headers) . "\r\n\r\n" . $this->dot($body);
        }
        $boundary = 'sf_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $parts = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $this->dot($body) . "\r\n";
        foreach ($attachments as $path) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }
            $name = basename($path);
            $parts .= '--' . $boundary . "\r\n";
            $parts .= 'Content-Type: application/octet-stream; name="' . $name . "\"\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n";
            $parts .= 'Content-Disposition: attachment; filename="' . $name . "\"\r\n\r\n";
            $parts .= chunk_split(base64_encode($raw)) . "\r\n";
        }
        $parts .= '--' . $boundary . '--';

        return implode("\r\n", $headers) . "\r\n\r\n" . $parts;
    }

    private function encode(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function dot(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);

        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private function allowedAttachment(string $path): bool
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return false;
        }
        $root = realpath(base_path('storage'));
        if ($root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return false;
        }
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)) {
            return false;
        }

        return filesize($real) !== false && filesize($real) <= 8 * 1024 * 1024;
    }
}
