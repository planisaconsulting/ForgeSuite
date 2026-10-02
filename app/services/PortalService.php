<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ArtworkStatus;
use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\FinanceRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\PortalRepository;
use App\Repositories\QuoteRepository;

/**
 * Customer actions. Ownership is checked on every call from the portal user's
 * customer id. Internal cost, markup, and production problems are not returned.
 */
final class PortalService
{
    public const STATEMENT_VERSION = '1';

    public function __construct(
        private readonly PortalRepository $portal = new PortalRepository(),
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly RateLimiter $limits = new RateLimiter(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function dashboard(array $user): array
    {
        $customerId = (int) $user['customer_id'];

        return [
            'quotes' => $this->portal->quotes($customerId),
            'jobs' => $this->labelledJobs($customerId),
            'artworks' => $this->portal->artworks($customerId),
            'invoices' => $this->portal->invoices($customerId),
            'documents' => $this->portal->documents($customerId),
            'signed_documents' => (new \App\Repositories\WorkshopRepository())->customerDocuments($customerId),
            'balance' => $this->portal->balance($customerId),
            'customer' => $this->portal->customerLabel($customerId),
            'assets' => (new \App\Repositories\AssetRepository())->forCustomer($customerId, 8),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function quote(array $user, int $quoteId): ?array
    {
        $quote = $this->portal->quote((int) $user['customer_id'], $quoteId);
        if ($quote === null) {
            return null;
        }
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'QUOTE_VIEWED', 'quote', $quoteId, $this->ip());

        return $quote;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, string>
     */
    public function acceptQuote(array $user, int $quoteId, array $input): array
    {
        if (!$this->limits->allow('approve:' . (int) $user['id'], 20, 3600)) {
            return ['_form' => 'Too many attempts. Wait and try again.'];
        }
        if (empty($input['accept'])) {
            return ['accept' => 'Tick I accept this quotation before you continue.'];
        }
        $quote = $this->portal->quote((int) $user['customer_id'], $quoteId);
        if ($quote === null) {
            return ['_form' => 'That quotation is not available.'];
        }
        if (!in_array((string) $quote['status'], ['READY', 'SENT', 'VIEWED'], true)) {
            return ['_form' => 'This quotation can no longer be accepted here.'];
        }
        if ($this->portal->hasAction((int) $user['customer_id'], 'QUOTE_ACCEPTED', 'quote', $quoteId, (int) $quote['revision_number'])) {
            return ['_form' => 'This revision has already been accepted.'];
        }
        $statement = (string) SettingsService::get('quote_acceptance_statement', 'I accept this quotation.');
        $name = $this->portal->customerLabel((int) $user['customer_id']);
        Database::transaction(function () use ($user, $quote, $quoteId, $input, $statement, $name): void {
            $full = $this->quotes->find($quoteId);
            if ($full === null || (int) $full['customer_id'] !== (int) $user['customer_id']) {
                throw new \RuntimeException('Quote ownership failed.');
            }
            (new QuoteService())->captureRevision($full, 'Portal acceptance revision ' . $full['revision_number'], null);
            $this->quotes->markAccepted($quoteId, [
                'accepted_by_name' => $name,
                'user_id' => null,
                'acceptance_method' => 'PORTAL',
                'acceptance_reference' => blank_to_null($input['customer_po'] ?? null),
                'acceptance_notes' => blank_to_null($input['notes'] ?? null),
            ]);
            $this->quotes->insertHistory($quoteId, (string) $quote['status'], 'ACCEPTED', null, 'Portal acceptance revision ' . $quote['revision_number']);
            $this->portal->insertAction([
                'customer_id' => (int) $user['customer_id'],
                'portal_user_id' => (int) $user['id'],
                'action' => 'QUOTE_ACCEPTED',
                'entity_type' => 'quote',
                'entity_id' => $quoteId,
                'revision_number' => (int) $quote['revision_number'],
                'statement_version' => self::STATEMENT_VERSION,
                'statement_text' => $statement,
                'ip_address' => $this->ip(),
                'payload_json' => json_encode([
                    'customer_po' => blank_to_null($input['customer_po'] ?? null),
                    'notes' => blank_to_null($input['notes'] ?? null),
                ]),
            ]);
            $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'QUOTE_ACCEPTED', 'quote', $quoteId, $this->ip());
            $this->notify($full['assigned_to'] ?? null, 'Quote accepted', $quote['quote_number'] . ' was accepted in the portal.', 'quote', $quoteId, 'quote-accept-' . $quoteId . '-' . $quote['revision_number']);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function declineQuote(array $user, int $quoteId, array $input): array
    {
        $quote = $this->portal->quote((int) $user['customer_id'], $quoteId);
        if ($quote === null) {
            return ['_form' => 'That quotation is not available.'];
        }
        if (!in_array((string) $quote['status'], ['READY', 'SENT', 'VIEWED'], true)) {
            return ['_form' => 'This quotation can no longer be declined here.'];
        }
        $reason = strtoupper(trim((string) ($input['reason'] ?? '')));
        $allowed = ['', 'PRICE', 'TIMING', 'SCOPE', 'ALTERNATIVE_SUPPLIER', 'PROJECT_CANCELLED', 'OTHER'];
        if (!in_array($reason, $allowed, true)) {
            $reason = 'OTHER';
        }
        $full = $this->quotes->find($quoteId);
        $this->quotes->updateStatus($quoteId, ['status' => 'DECLINED', 'user_id' => null]);
        $this->quotes->insertHistory($quoteId, (string) $quote['status'], 'DECLINED', null, $reason === '' ? 'Declined in the portal' : 'Declined in the portal: ' . $reason);
        $this->portal->insertAction([
            'customer_id' => (int) $user['customer_id'],
            'portal_user_id' => (int) $user['id'],
            'action' => 'QUOTE_DECLINED',
            'entity_type' => 'quote',
            'entity_id' => $quoteId,
            'revision_number' => (int) $quote['revision_number'],
            'statement_version' => null,
            'statement_text' => null,
            'ip_address' => $this->ip(),
            'payload_json' => json_encode(['reason' => $reason, 'notes' => blank_to_null($input['notes'] ?? null)]),
        ]);
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'QUOTE_DECLINED', 'quote', $quoteId, $this->ip());
        $this->notify($full['assigned_to'] ?? null, 'Quote declined', (string) $quote['quote_number'] . ' was declined in the portal.', 'quote', $quoteId, 'quote-decline-' . $quoteId);

        return [];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function requestQuoteChange(array $user, int $quoteId, array $input): array
    {
        $quote = $this->portal->quote((int) $user['customer_id'], $quoteId);
        if ($quote === null) {
            return ['_form' => 'That quotation is not available.'];
        }
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '') {
            return ['message' => 'Describe the change you need.'];
        }
        $full = $this->quotes->find($quoteId);
        $this->portal->insertMessage((int) $user['customer_id'], (int) $user['id'], 'quote', $quoteId, $message);
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'QUOTE_CHANGE_REQUESTED', 'quote', $quoteId, $this->ip());
        $this->notify($full['assigned_to'] ?? null, 'Quote change requested', (string) $quote['quote_number'] . ': ' . mb_substr($message, 0, 180), 'quote', $quoteId, 'quote-change-' . $quoteId . '-' . date('YmdHis'));
        $remindUser = (int) ($full['assigned_to'] ?? 0) > 0 ? (int) $full['assigned_to'] : (int) ($full['created_by'] ?? 0);
        if ($remindUser > 0) {
            (new NotificationRepository())->insertReminder([
                'user_id' => $remindUser,
                'entity_type' => 'quote',
                'entity_id' => $quoteId,
                'title' => 'Customer asked for quote changes',
                'description' => mb_substr($message, 0, 500),
                'remind_at' => date('Y-m-d H:i:s'),
                'dedupe_key' => 'portal-quote-change-' . $quoteId . '-' . sha1($message),
            ]);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function artwork(array $user, int $artworkId): ?array
    {
        $row = $this->portal->artwork((int) $user['customer_id'], $artworkId);
        if ($row === null) {
            return null;
        }
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'ARTWORK_VIEWED', 'artwork', $artworkId, $this->ip());
        BusinessEventDispatcher::emit('ARTWORK_VIEWED', 'ARTWORK', $artworkId, null, ['portal' => '1']);

        return $row;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function approveArtwork(array $user, int $artworkId, array $input): array
    {
        if (!$this->limits->allow('art:' . (int) $user['id'], 20, 3600)) {
            return ['_form' => 'Too many attempts. Wait and try again.'];
        }
        if (empty($input['approve'])) {
            return ['approve' => 'Tick the approval statement before you continue.'];
        }
        $art = $this->portal->artwork((int) $user['customer_id'], $artworkId);
        if ($art === null) {
            return ['_form' => 'That artwork is not available.'];
        }
        if ((string) $art['status'] === ArtworkStatus::Superseded->value) {
            return ['_form' => 'Approve the current revision. This one is historical.'];
        }
        if ((string) $art['status'] === ArtworkStatus::Approved->value) {
            return ['_form' => 'This revision is already approved.'];
        }
        $statement = (string) SettingsService::get('artwork_approval_statement', 'I approve this artwork revision.');
        $name = $this->portal->customerLabel((int) $user['customer_id']);
        Database::transaction(function () use ($user, $art, $artworkId, $statement, $name): void {
            $this->ops->insertApproval([
                'job_artwork_id' => $artworkId,
                'approval_status' => ArtworkStatus::Approved->value,
                'customer_name' => $name,
                'approval_method' => 'PORTAL',
                'reference' => 'statement ' . self::STATEMENT_VERSION,
                'notes' => $statement,
                'approved_at' => date('Y-m-d H:i:s'),
                'recorded_by' => null,
            ]);
            $this->ops->markArtworkApproved($artworkId, $name);
            $this->portal->insertAction([
                'customer_id' => (int) $user['customer_id'],
                'portal_user_id' => (int) $user['id'],
                'action' => 'ARTWORK_APPROVED',
                'entity_type' => 'artwork',
                'entity_id' => $artworkId,
                'revision_number' => (int) $art['revision_number'],
                'statement_version' => self::STATEMENT_VERSION,
                'statement_text' => $statement,
                'ip_address' => $this->ip(),
                'payload_json' => null,
            ]);
            $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'ARTWORK_APPROVED', 'artwork', $artworkId, $this->ip());
            $job = $this->portal->job((int) $user['customer_id'], (int) $art['job_id']);
            $this->notify(null, 'Artwork approved', (string) ($job['job_number'] ?? 'Job') . ' artwork revision ' . $art['revision_number'] . ' was approved.', 'job', (int) $art['job_id'], 'art-ok-' . $artworkId);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function requestArtworkChange(array $user, int $artworkId, array $input): array
    {
        $art = $this->portal->artwork((int) $user['customer_id'], $artworkId);
        if ($art === null) {
            return ['_form' => 'That artwork is not available.'];
        }
        if ((string) $art['status'] === ArtworkStatus::Superseded->value || (string) $art['status'] === ArtworkStatus::Approved->value) {
            return ['_form' => 'Ask for changes on the current proof.'];
        }
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '') {
            return ['message' => 'Describe the changes.'];
        }
        $version = (int) $art['version_number'];
        if ($this->ops->setArtworkStatus($artworkId, ArtworkStatus::ChangesRequested->value, $version) < 1) {
            return ['_form' => 'This proof changed while you were looking at it. Reload and try again.'];
        }
        $this->portal->insertMessage((int) $user['customer_id'], (int) $user['id'], 'artwork', $artworkId, $message);
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'ARTWORK_CHANGES', 'artwork', $artworkId, $this->ip());
        $assignee = (int) ($art['uploaded_by'] ?? 0);
        $this->notify($assignee > 0 ? $assignee : null, 'Artwork changes requested', mb_substr($message, 0, 180), 'job', (int) $art['job_id'], 'art-change-' . $artworkId . '-' . date('YmdHis'));

        return [];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function job(array $user, int $jobId): ?array
    {
        $job = $this->portal->job((int) $user['customer_id'], $jobId);
        if ($job === null) {
            return null;
        }
        $job['customer_status'] = $this->portal->labelForStatus((string) $job['status']);
        $job['visible_schedule'] = (new \App\Repositories\PlanningRepository())->customerVisibleEntries($jobId);
        $job['timeline'] = $this->timeline($job);

        return $job;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function quotePdf(array $user, int $quoteId): ?string
    {
        $owned = $this->portal->quote((int) $user['customer_id'], $quoteId);
        if ($owned === null) {
            return null;
        }
        $quote = $this->quotes->find($quoteId);
        if ($quote === null) {
            return null;
        }
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'QUOTE_DOWNLOADED', 'quote', $quoteId, $this->ip());
        $html = View::capture('quotes/pdf_shell', [
            'quote' => $quote,
            'items' => $this->quotes->items($quoteId),
            'sections' => $this->quotes->sections($quoteId),
            'print' => false,
            'company' => [
                'name' => SettingsService::get('company_name', 'Sign-Forge Signs'),
                'trading_name' => SettingsService::get('trading_name', ''),
                'registration' => SettingsService::get('company_registration', ''),
                'vat' => SettingsService::get('vat_number', ''),
                'address' => SettingsService::get('address', ''),
                'telephone' => SettingsService::get('telephone', ''),
                'email' => SettingsService::get('email', ''),
                'website' => SettingsService::get('website', ''),
                'symbol' => SettingsService::symbol(),
            ],
        ]);

        return (new QuotePdf())->render($html);
    }

    /**
     * @param array<string, mixed> $job
     * @return list<array{label: string, at: string}>
     */
    private function timeline(array $job): array
    {
        $events = [];
        if (!empty($job['quote_id'])) {
            $quote = $this->quotes->find((int) $job['quote_id']);
            if ($quote !== null && !empty($quote['accepted_at'])) {
                $events[] = ['label' => 'Quote accepted', 'at' => (string) $quote['accepted_at']];
            }
        }
        $arts = $this->ops->artworks((int) $job['id']);
        foreach ($arts as $art) {
            if ((string) $art['status'] === 'APPROVED' && !empty($art['customer_approved_at'])) {
                $events[] = ['label' => 'Artwork approved', 'at' => (string) $art['customer_approved_at']];
            } elseif (in_array((string) $art['status'], ['SENT_FOR_APPROVAL', 'CHANGES_REQUESTED', 'APPROVED'], true)) {
                $events[] = ['label' => 'Artwork sent', 'at' => (string) $art['uploaded_at']];
            }
        }
        if (!empty($job['customer_promised_date'])) {
            $events[] = ['label' => 'Expected completion', 'at' => (string) $job['customer_promised_date']];
        }
        foreach ($job['visible_schedule'] ?? [] as $entry) {
            $events[] = ['label' => 'Scheduled', 'at' => (string) $entry['start_datetime']];
        }
        if ((string) $job['status'] === 'COMPLETED') {
            $events[] = ['label' => 'Completed', 'at' => ''];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function labelledJobs(int $customerId): array
    {
        $rows = [];
        foreach ($this->portal->jobs($customerId) as $job) {
            $job['customer_status'] = $this->portal->labelForStatus((string) $job['status']);
            $rows[] = $job;
        }

        return $rows;
    }

    private function notify(mixed $userId, string $title, string $message, string $entity, int $entityId, string $dedupe): void
    {
        $id = (int) $userId;
        $this->notifications->send(
            $id > 0 ? $id : null,
            $id > 0 ? null : null,
            'SYSTEM',
            $title,
            $message,
            $entity,
            $entityId,
            'NORMAL',
            $dedupe
        );
    }

    private function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}
