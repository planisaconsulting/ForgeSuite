<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Outbound mail is not configured. This records that a message was not sent
 * so later SMTP work has one place to plug in. It does not call mail().
 */
final class EmailService
{
    public function configured(): bool
    {
        return false;
    }

    /**
     * @return array{sent: bool, reason: string}
     */
    public function send(string $to, string $subject, string $body): array
    {
        unset($to, $subject, $body);

        return [
            'sent' => false,
            'reason' => 'Email delivery is not configured. Log the conversation on the customer instead.',
        ];
    }
}
