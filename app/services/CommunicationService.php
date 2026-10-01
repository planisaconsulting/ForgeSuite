<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SystemRepository;

/**
 * Customer messages stay behind this class.
 *
 * Email templates exist but are not sent until EmailService is configured.
 * WhatsApp is a prefilled link. This is not a WhatsApp Business API.
 */
final class CommunicationService
{
    public function __construct(
        private readonly EmailService $email = new EmailService(),
        private readonly SystemRepository $system = new SystemRepository(),
    ) {
    }

    /**
     * @return array{sent: bool, reason: string}
     */
    public function prepareEmail(string $templateCode, string $to, int $customerId, string $entityType, int $entityId, int $userId): array
    {
        $template = (new PortalRepository())->emailTemplate($templateCode);
        $result = ['sent' => false, 'reason' => 'Email delivery is not configured.'];
        if ($template !== null && (int) $template['active'] === 1 && $this->email->configured()) {
            $result = $this->email->send($to, (string) $template['subject'], (string) $template['body']);
        }
        $this->system->insertCommunication([
            'customer_id' => $customerId,
            'contact_id' => null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'channel' => 'EMAIL',
            'direction' => 'OUTBOUND',
            'subject' => $template['subject'] ?? $templateCode,
            'message_summary' => $result['sent'] ? 'Sent' : 'Prepared only. ' . $result['reason'],
            'status' => $result['sent'] ? 'SENT' : 'LOGGED',
            'sent_by' => $userId,
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        return $result;
    }

    public function whatsappLink(string $phone, string $message): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }
}
