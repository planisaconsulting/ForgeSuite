<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\AssetRepository;
use App\Repositories\QuoteRepository;

/**
 * Service requests sit in front of the existing quote and job.
 * A service job keeps the SFJ number. It is not a second job engine.
 */
final class ServiceRequestService
{
    public const STATUSES = [
        'NEW', 'TRIAGE', 'AWAITING_CUSTOMER', 'AWAITING_ASSESSMENT', 'AWAITING_QUOTE',
        'AWAITING_APPROVAL', 'READY_TO_SCHEDULE', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'CANCELLED',
    ];

    public const SOURCES = ['PHONE', 'EMAIL', 'WHATSAPP', 'CUSTOMER_PORTAL', 'QR', 'INTERNAL', 'INSPECTION', 'OTHER'];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    public const CLASSIFICATIONS = ['PAID', 'WARRANTY', 'GOODWILL', 'INTERNAL', 'MAINTENANCE_CONTRACT'];

    public function __construct(
        private readonly AssetRepository $assets = new AssetRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly QuoteService $quotes = new QuoteService(),
        private readonly QuoteRepository $quoteRows = new QuoteRepository(),
        private readonly WarrantyService $warranties = new WarrantyService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId, bool $public = false): array
    {
        if (!$public && !can('service_requests.create')) {
            return ['errors' => ['_form' => 'You cannot create a service request.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        $assetId = (int) ($input['asset_id'] ?? 0);
        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if ($assetId > 0 && $asset === null) {
            return ['errors' => ['asset_id' => 'That asset was not found.'], 'id' => null];
        }
        if ($asset !== null) {
            if ($customerId > 0 && (int) $asset['customer_id'] !== $customerId) {
                return ['errors' => ['asset_id' => 'That asset belongs to another customer.'], 'id' => null];
            }
            $customerId = (int) $asset['customer_id'];
        }
        if ($customerId < 1) {
            return ['errors' => ['customer_id' => 'Choose a customer.'], 'id' => null];
        }
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return ['errors' => ['description' => 'Describe the problem.'], 'id' => null];
        }
        $source = strtoupper(trim((string) ($input['source'] ?? 'PHONE')));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'OTHER';
        }
        $priority = strtoupper(trim((string) ($input['priority'] ?? 'NORMAL')));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'NORMAL';
        }
        $category = strtoupper(trim((string) ($input['problem_category'] ?? 'OTHER')));
        $known = false;
        foreach ($this->assets->problemCategories() as $row) {
            if ((string) $row['code'] === $category) {
                $known = true;
            }
        }
        if (!$known) {
            $category = 'OTHER';
        }
        $reported = trim((string) ($input['reported_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reported)) {
            $reported = date('Y-m-d');
        }
        $candidate = $asset !== null && $this->warranties->anyLikelyActive((int) $asset['id'], $reported) ? 1 : 0;
        $id = $this->assets->insertRequest([
            'request_number' => $this->numbers->serviceRequest(),
            'customer_id' => $customerId,
            'project_site_id' => $asset !== null && $asset['project_site_id'] !== null ? (int) $asset['project_site_id'] : ((int) ($input['project_site_id'] ?? 0) > 0 ? (int) $input['project_site_id'] : null),
            'asset_id' => $asset !== null ? (int) $asset['id'] : null,
            'asset_component_id' => (int) ($input['asset_component_id'] ?? 0) > 0 ? (int) $input['asset_component_id'] : null,
            'reported_by' => blank_to_null($input['reported_by'] ?? null),
            'contact_detail' => blank_to_null($input['contact_detail'] ?? null),
            'source' => $source,
            'problem_category' => $category,
            'description' => $description,
            'priority' => $priority,
            'status' => 'NEW',
            'classification' => null,
            'warranty_candidate' => $candidate,
            'assigned_user_id' => null,
            'reported_at' => $reported . ' 09:00:00',
            'created_by' => $userId > 0 ? $userId : null,
        ]);
        if ($asset !== null) {
            $this->assets->insertEvent([
                'asset_id' => (int) $asset['id'],
                'event_type' => 'SERVICE_REQUEST',
                'summary' => 'Service request opened',
                'related_type' => 'service_request',
                'related_id' => $id,
                'happened_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        }
        BusinessEventDispatcher::emit('SERVICE_REQUEST_CREATED', 'SERVICE_REQUEST', $id, $userId > 0 ? $userId : null, [
            'priority' => $priority,
            'warranty_candidate' => $candidate,
        ]);
        if ($priority === 'URGENT') {
            $this->notify('Urgent service request', 'A service request was logged as urgent.', $id);
        } else {
            $this->notify('New service request', 'A service request was logged.', $id);
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $version, int $userId): array
    {
        if (!can('service_requests.manage') && !can('service_requests.assign')) {
            return ['_form' => 'You cannot update this service request.'];
        }
        $request = $this->assets->request($id);
        if ($request === null) {
            return ['_form' => 'That service request was not found.'];
        }
        $status = strtoupper(trim((string) ($input['status'] ?? $request['status'])));
        if (!in_array($status, self::STATUSES, true)) {
            return ['status' => 'Choose a status.'];
        }
        $priority = strtoupper(trim((string) ($input['priority'] ?? $request['priority'])));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = (string) $request['priority'];
        }
        $classification = strtoupper(trim((string) ($input['classification'] ?? (string) ($request['classification'] ?? ''))));
        if ($classification === '') {
            $classification = null;
        } elseif (!in_array($classification, self::CLASSIFICATIONS, true)) {
            return ['classification' => 'Choose a service classification.'];
        }
        if ($classification === 'GOODWILL' && trim((string) ($input['service_reason'] ?? '')) === '' && trim((string) ($request['work_performed'] ?? '')) === '') {
            return ['service_reason' => 'Goodwill needs a reason. It is not a warranty.'];
        }
        $assigned = (int) ($input['assigned_user_id'] ?? $request['assigned_user_id'] ?? 0);
        $first = $request['first_response_at'];
        if ($first === null && $status !== 'NEW') {
            $first = date('Y-m-d H:i:s');
        }
        $assessed = $request['assessed_at'];
        if ($assessed === null && in_array($status, ['AWAITING_QUOTE', 'AWAITING_APPROVAL', 'READY_TO_SCHEDULE', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'], true)) {
            $assessed = date('Y-m-d H:i:s');
        }
        $resolved = $request['resolved_at'];
        if ($resolved === null && in_array($status, ['RESOLVED', 'CLOSED'], true)) {
            $resolved = date('Y-m-d H:i:s');
        }
        $changed = $this->assets->updateRequest($id, [
            'status' => $status,
            'priority' => $priority,
            'classification' => $classification,
            'assigned_user_id' => $assigned > 0 ? $assigned : null,
            'first_response_at' => $first,
            'assessed_at' => $assessed,
            'resolved_at' => $resolved,
            'work_performed' => blank_to_null($input['work_performed'] ?? $request['work_performed']),
            'outstanding_issue' => blank_to_null($input['outstanding_issue'] ?? $request['outstanding_issue']),
            'recommendations' => blank_to_null($input['recommendations'] ?? $request['recommendations']),
            'customer_signoff_name' => $request['customer_signoff_name'],
            'signed_at' => $request['signed_at'],
        ], $version);
        if ($changed !== 1) {
            return ['_form' => 'This request was saved by someone else. Reload it and try again.'];
        }
        if ((string) $request['status'] !== $status) {
            BusinessEventDispatcher::emit('SERVICE_REQUEST_STATUS_CHANGED', 'SERVICE_REQUEST', $id, $userId, ['status' => $status]);
        }
        if ((int) ($request['assigned_user_id'] ?? 0) !== $assigned && $assigned > 0) {
            BusinessEventDispatcher::emit('SERVICE_REQUEST_ASSIGNED', 'SERVICE_REQUEST', $id, $userId, ['user_id' => $assigned]);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createQuote(int $requestId, array $input, int $userId): array
    {
        if (!can('service_requests.manage') && !can('quotes.create')) {
            return ['errors' => ['_form' => 'You cannot create a service quote.'], 'id' => null];
        }
        $request = $this->assets->request($requestId);
        if ($request === null) {
            return ['errors' => ['_form' => 'That service request was not found.'], 'id' => null];
        }
        $created = $this->quotes->create([
            'customer_id' => (int) $request['customer_id'],
            'customer_notes' => 'Service request ' . $request['request_number'] . '. ' . (string) $request['description'],
            'internal_notes' => 'Linked to asset ' . (string) ($request['asset_number'] ?? ''),
        ], $userId);
        if ($created['id'] === null) {
            return ['errors' => $created['errors'], 'id' => null];
        }
        $quoteId = (int) $created['id'];
        $this->assets->linkQuote($quoteId, $requestId, $request['asset_id'] !== null ? (int) $request['asset_id'] : null);
        $charge = trim((string) ($input['charge'] ?? ''));
        if ($charge !== '' && Decimal::isNumeric($charge) && Decimal::cmp($charge, '0') > 0) {
            $quote = $this->quoteRows->find($quoteId);
            $line = $this->quotes->addCustomLine($quoteId, [
                'customer_description' => trim((string) ($input['description'] ?? 'Service repair')),
                'quantity' => '1',
                'unit_cost' => '0',
                'final_sell_price' => Decimal::money($charge),
                'override_reason' => 'Service charge',
            ], (int) $quote['version_number'], $userId);
            if ($line['errors'] !== []) {
                return ['errors' => ['charge' => implode(' ', $line['errors'])], 'id' => null];
            }
        }
        $this->update($requestId, ['status' => 'AWAITING_QUOTE'], (int) $request['version'], $userId);

        return ['errors' => [], 'id' => $quoteId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function acceptQuote(int $quoteId, array $input, int $userId): array
    {
        if (!can('service_jobs.manage')) {
            return ['errors' => ['_form' => 'You cannot open a service job.'], 'id' => null];
        }
        $quote = $this->quoteRows->find($quoteId);
        if ($quote === null || $quote['service_request_id'] === null) {
            return ['errors' => ['_form' => 'That is not a service quote.'], 'id' => null];
        }
        $request = $this->assets->request((int) $quote['service_request_id']);
        if ($request === null) {
            return ['errors' => ['_form' => 'The service request was not found.'], 'id' => null];
        }
        $classification = strtoupper(trim((string) ($input['classification'] ?? 'PAID')));
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            $classification = 'PAID';
        }
        $reason = trim((string) ($input['service_reason'] ?? ''));
        if ($classification === 'GOODWILL') {
            if ($reason === '') {
                return ['errors' => ['service_reason' => 'Goodwill needs a reason. It is not a warranty.'], 'id' => null];
            }
            $gate = (new ApprovalService())->gate('SERVICE_REQUEST', (int) $request['id'], 'GOODWILL', $userId, [], $reason);
            if ($gate['blocked']) {
                return ['errors' => ['_form' => 'Goodwill needs approval before the job can open.'], 'id' => null];
            }
        }
        if ((string) $quote['status'] === 'DRAFT') {
            $ready = $this->quotes->changeStatus($quoteId, 'READY', (int) $quote['version_number'], $userId, 'Service quote');
            if ($ready !== []) {
                return ['errors' => $ready, 'id' => null];
            }
            $quote = $this->quoteRows->find($quoteId);
        }
        if (in_array((string) $quote['status'], ['READY', 'SENT', 'VIEWED'], true)) {
            $accepted = $this->quotes->accept($quoteId, [
                'accepted_by_name' => trim((string) ($input['accepted_by_name'] ?? 'Customer')),
                'acceptance_method' => strtoupper(trim((string) ($input['acceptance_method'] ?? 'EMAIL'))),
            ], (int) $quote['version_number'], $userId);
            if ($accepted !== []) {
                return ['errors' => $accepted, 'id' => null];
            }
            $quote = $this->quoteRows->find($quoteId);
        }
        $jobType = match ($classification) {
            'WARRANTY' => 'WARRANTY',
            'MAINTENANCE_CONTRACT' => 'MAINTENANCE',
            default => strtoupper(trim((string) ($input['job_type'] ?? 'SERVICE'))),
        };
        if (!in_array($jobType, ['SERVICE', 'WARRANTY', 'MAINTENANCE', 'INSPECTION', 'REPAIR', 'REMOVAL', 'REPLACEMENT'], true)) {
            $jobType = 'SERVICE';
        }
        $converted = $this->quotes->convert($quoteId, [
            'title' => trim((string) ($input['title'] ?? ('Service ' . $request['request_number']))),
            'priority' => (string) $request['priority'],
            'description' => (string) $request['description'],
        ], (int) $quote['version_number'], $userId);
        if ($converted['id'] === null) {
            return ['errors' => $converted['errors'], 'id' => null];
        }
        $jobId = (int) $converted['id'];
        $asset = $request['asset_id'] !== null ? $this->assets->find((int) $request['asset_id']) : null;
        $this->assets->stampJob($jobId, [
            'job_type' => $jobType,
            'service_request_id' => (int) $request['id'],
            'customer_asset_id' => $asset !== null ? (int) $asset['id'] : null,
            'asset_component_id' => $request['asset_component_id'] !== null ? (int) $request['asset_component_id'] : null,
            'warranty_claim_id' => (int) ($input['warranty_claim_id'] ?? 0) > 0 ? (int) $input['warranty_claim_id'] : null,
            'classification' => $classification,
            'service_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
            'project_id' => $asset !== null ? $asset['project_id'] : null,
            'project_site_id' => $asset !== null ? $asset['project_site_id'] : null,
        ]);
        $this->update((int) $request['id'], [
            'status' => 'IN_PROGRESS',
            'classification' => $classification,
        ], (int) $this->assets->request((int) $request['id'])['version'], $userId);
        BusinessEventDispatcher::emit('SERVICE_QUOTE_ACCEPTED', 'QUOTE', $quoteId, $userId, ['job_id' => $jobId]);
        BusinessEventDispatcher::emit('SERVICE_JOB_STARTED', 'JOB', $jobId, $userId, ['job_type' => $jobType]);

        return ['errors' => [], 'id' => $jobId];
    }

    /**
     * Open a service job when there is no customer charge, such as warranty work.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function openJob(int $requestId, array $input, int $userId): array
    {
        $quote = $this->createQuote($requestId, ['charge' => (string) ($input['charge'] ?? ''), 'description' => (string) ($input['description'] ?? 'Service')], $userId);
        if ($quote['id'] === null) {
            return $quote;
        }

        return $this->acceptQuote((int) $quote['id'], $input, $userId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function signOff(int $requestId, array $input, int $version, int $userId): array
    {
        if (!can('service_requests.manage') && !can('inspections.perform')) {
            return ['_form' => 'You cannot sign off this service.'];
        }
        $request = $this->assets->request($requestId);
        if ($request === null) {
            return ['_form' => 'That service request was not found.'];
        }
        $name = trim((string) ($input['customer_signoff_name'] ?? ''));
        if ($name === '') {
            return ['customer_signoff_name' => 'Who signed for the customer?'];
        }
        $changed = $this->assets->updateRequest($requestId, [
            'status' => 'RESOLVED',
            'priority' => (string) $request['priority'],
            'classification' => $request['classification'],
            'assigned_user_id' => $request['assigned_user_id'],
            'first_response_at' => $request['first_response_at'] ?? date('Y-m-d H:i:s'),
            'assessed_at' => $request['assessed_at'] ?? date('Y-m-d H:i:s'),
            'resolved_at' => date('Y-m-d H:i:s'),
            'work_performed' => blank_to_null($input['work_performed'] ?? $request['work_performed']),
            'outstanding_issue' => blank_to_null($input['outstanding_issue'] ?? null),
            'recommendations' => blank_to_null($input['recommendations'] ?? null),
            'customer_signoff_name' => mb_substr($name, 0, 120),
            'signed_at' => date('Y-m-d H:i:s'),
        ], $version);
        if ($changed !== 1) {
            return ['_form' => 'This request changed. Reload it and try again.'];
        }
        $job = $this->assets->rowsForReport(
            'SELECT id FROM jobs WHERE service_request_id = ? ORDER BY id DESC LIMIT 1',
            [$requestId]
        );
        if ($job !== []) {
            BusinessEventDispatcher::emit('SERVICE_JOB_COMPLETED', 'JOB', (int) $job[0]['id'], $userId, []);
        }
        if ($request['asset_id'] !== null) {
            $this->assets->insertEvent([
                'asset_id' => (int) $request['asset_id'],
                'event_type' => 'SIGN_OFF',
                'summary' => 'Customer signed: ' . $name,
                'related_type' => 'service_request',
                'related_id' => $requestId,
                'happened_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);
        }

        return [];
    }

    /**
     * Customer-safe service report. Internal cost is omitted.
     *
     * @return array<string, mixed>|null
     */
    public function report(int $requestId, bool $costs): ?array
    {
        $request = $this->assets->request($requestId);
        if ($request === null) {
            return null;
        }
        $asset = $request['asset_id'] !== null ? $this->assets->find((int) $request['asset_id']) : null;
        $jobs = $asset !== null ? $this->assets->serviceJobs((int) $asset['id']) : [];
        $linked = [];
        foreach ($jobs as $job) {
            if ((int) ($job['id'] ?? 0) > 0) {
                $full = $this->assets->rowsForReport('SELECT * FROM jobs WHERE id = ?', [(int) $job['id']]);
                if ($full !== [] && (int) ($full[0]['service_request_id'] ?? 0) === $requestId) {
                    $linked = $full[0];
                }
            }
        }
        $parts = [];
        if ($linked !== []) {
            $parts = $this->assets->rowsForReport(
                'SELECT description, quantity, status FROM asset_components WHERE asset_id = ? AND status = \'ACTIVE\' ORDER BY id DESC LIMIT 20',
                [(int) $request['asset_id']]
            );
        }
        $safe = [
            'request_number' => (string) $request['request_number'],
            'job_number' => (string) ($linked['job_number'] ?? ''),
            'asset_number' => (string) ($request['asset_number'] ?? ''),
            'asset_name' => (string) ($request['asset_name'] ?? ''),
            'site' => (string) ($asset['site_name'] ?? $asset['location_description'] ?? ''),
            'problem' => (string) $request['description'],
            'work_performed' => (string) ($request['work_performed'] ?? ''),
            'outstanding_issue' => (string) ($request['outstanding_issue'] ?? ''),
            'recommendations' => (string) ($request['recommendations'] ?? ''),
            'signed_by' => (string) ($request['customer_signoff_name'] ?? ''),
            'signed_at' => (string) ($request['signed_at'] ?? ''),
            'next_service_date' => (string) ($asset['next_service_date'] ?? ''),
            'parts' => $parts,
        ];
        if ($costs && AssetAccess::canSeeCosts() && $linked !== []) {
            $safe['quoted_revenue'] = (string) $linked['quoted_revenue_snapshot'];
            $safe['actual_cost'] = (string) $linked['actual_total_cost'];
        }

        return $safe;
    }

    /**
     * @return array{first_response_hours: ?string, resolution_hours: ?string, target_hours: ?int}
     */
    public function responseTimes(int $requestId): array
    {
        $request = $this->assets->request($requestId);
        if ($request === null) {
            return ['first_response_hours' => null, 'resolution_hours' => null, 'target_hours' => null];
        }
        $agreements = $this->assets->agreementsForCustomer((int) $request['customer_id']);
        $target = null;
        foreach ($agreements as $agreement) {
            if ((string) $agreement['status'] === 'ACTIVE' && $agreement['response_target_hours'] !== null) {
                $target = (int) $agreement['response_target_hours'];
            }
        }

        return [
            'first_response_hours' => $this->hours((string) $request['reported_at'], $request['first_response_at']),
            'resolution_hours' => $this->hours((string) $request['reported_at'], $request['resolved_at']),
            'target_hours' => $target,
        ];
    }

    private function hours(string $from, mixed $to): ?string
    {
        if ($to === null || $to === '') {
            return null;
        }
        $seconds = strtotime((string) $to) - strtotime($from);
        if ($seconds < 0) {
            return null;
        }

        return Decimal::div((string) $seconds, '3600', 2);
    }

    private function notify(string $title, string $message, int $requestId): void
    {
        $role = $this->assets->rowsForReport("SELECT id FROM roles WHERE code = 'MANAGEMENT' LIMIT 1");
        (new NotificationService())->send(
            null,
            (int) ($role[0]['id'] ?? 0) > 0 ? (int) $role[0]['id'] : null,
            'SERVICE_REQUEST',
            $title,
            $message,
            'service_request',
            $requestId,
            'HIGH',
            'service-request:' . $requestId . ':' . $title
        );
    }
}
