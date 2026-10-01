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
        return (new WhatsAppService())->prepare($phone, $message)['url'];
    }

    /**
     * @param array<string, scalar|null> $vars
     * @return array{sent: bool, reason: string, preview: array<string, string>}
     */
    public function sendQuote(int $quoteId, string $to, int $templateId, int $userId, bool $confirm): array
    {
        $quote = (new \App\Repositories\QuoteRepository())->find($quoteId);
        if ($quote === null) {
            return ['sent' => false, 'reason' => 'That quotation was not found.', 'preview' => []];
        }
        $contactId = (int) ($quote['contact_id'] ?? 0);
        if (!(new ConsentService())->allowsTransactional($contactId > 0 ? $contactId : null, 'EMAIL')) {
            return ['sent' => false, 'reason' => 'Transactional email is turned off for that contact.', 'preview' => []];
        }
        $template = (new \App\Repositories\CommunicationRepository())->template($templateId);
        $vars = $this->quoteVars($quote);
        $engine = new TemplateEngine();
        $subject = $engine->render((string) ($template['subject_template'] ?? 'Quotation {{quote_number}}'), $vars);
        $body = $engine->render((string) ($template['body_template'] ?? 'Quotation {{quote_number}} totals {{quote_total}}.'), $vars);
        $preview = ['to' => $to, 'subject' => $subject, 'body' => $body];
        if (!$confirm) {
            return ['sent' => false, 'reason' => 'Preview only.', 'preview' => $preview];
        }
        $attachment = (new QuoteDocument())->pdfFile($quote);
        $result = $this->email->send($to, $subject, $body, [$attachment]);
        $sent = $result['sent'] === true;
        $this->system->insertCommunication([
            'customer_id' => $quote['customer_id'],
            'contact_id' => $quote['contact_id'],
            'lead_id' => $quote['lead_id'] ?? null,
            'opportunity_id' => $quote['opportunity_id'],
            'quote_id' => $quoteId,
            'entity_type' => 'quote',
            'entity_id' => $quoteId,
            'channel' => 'EMAIL',
            'direction' => 'OUTBOUND',
            'subject' => $subject,
            'message_summary' => $sent ? 'Sent ' . $to : 'Failed. ' . $result['reason'],
            'message_body' => $body,
            'status' => $sent ? 'SENT' : 'FAILED',
            'failure_reason' => $sent ? null : $result['reason'],
            'sent_by' => $userId,
            'template_id' => $templateId > 0 ? $templateId : null,
            'external_reference' => $result['provider_reference'] ?? null,
        ]);
        $this->auditSafe($quoteId, $sent ? 'EMAIL_SENT' : 'EMAIL_FAILED', $userId, $result['reason']);
        if ($sent && in_array((string) $quote['status'], ['DRAFT', 'READY'], true)) {
            (new QuoteService())->changeStatus($quoteId, 'SENT', (int) $quote['version_number'], $userId, 'Sent by email');
        }

        return ['sent' => $sent, 'reason' => $result['reason'], 'preview' => $preview];
    }

    /**
     * @param array<string, mixed> $quote
     * @return array<string, string>
     */
    public function quoteVars(array $quote): array
    {
        $symbol = SettingsService::symbol();

        return [
            'customer_name' => (string) ($quote['company_name'] ?? ''),
            'contact_name' => (string) ($quote['contact_name'] ?? $quote['company_name'] ?? ''),
            'quote_number' => (string) $quote['quote_number'],
            'quote_total' => $symbol . (string) $quote['total'],
            'quote_expiry' => (string) ($quote['expiry_date'] ?? ''),
            'job_number' => '',
            'invoice_number' => '',
            'invoice_balance' => '',
            'invoice_due_date' => '',
            'portal_link' => rtrim((string) config('app.url', ''), '/') . '/portal',
            'company_name' => (string) SettingsService::get('company_name', ''),
        ];
    }

    private function auditSafe(int $quoteId, string $action, int $userId, string $reason): void
    {
        (new AuditService())->record('quote', $quoteId, $action, null, [
            'reason' => $reason,
        ], $userId);
    }
}
