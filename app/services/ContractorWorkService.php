<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\MovementType;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;
use App\Repositories\ContractorRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\OperationsRepository;

/**
 * External work orders. The contractor sees the assigned scope only.
 * Approval, payment, and job completion stay inside Sign-Forge.
 */
final class ContractorWorkService
{
    /** @var list<string> */
    public const TYPES = ['INSTALLER', 'ELECTRICIAN', 'FABRICATOR', 'PRINTER', 'RIGGER', 'PAINTER', 'BUILDER', 'TRANSPORT', 'CRANE', 'OTHER'];

    /** @var list<string> */
    public const STATUSES = ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'];

    /** @var list<string> */
    public const WORK_TYPES = ['INSTALLATION', 'FABRICATION', 'ELECTRICAL', 'PRINTING', 'PAINTING', 'CRANE', 'TRANSPORT', 'REPAIR', 'SITE_SURVEY', 'OTHER'];

    /** @var list<string> */
    public const WORK_STATUSES = [
        'DRAFT', 'SENT', 'VIEWED', 'ACCEPTED', 'DECLINED', 'SCHEDULED', 'IN_PROGRESS', 'BLOCKED',
        'SUBMITTED_COMPLETE', 'REVIEW_REQUIRED', 'APPROVED_COMPLETE', 'CANCELLED',
    ];

    /** @var list<string> */
    public const PRICING = ['FIXED', 'HOURLY', 'PER_UNIT', 'PER_M2', 'PER_KM', 'DAY_RATE', 'CUSTOM'];

    /** @var list<string> */
    public const SUPPLY = ['SIGN_FORGE', 'CONTRACTOR', 'MIXED'];

    /** @var list<string> */
    public const BLOCKS = ['SITE_ACCESS', 'MATERIAL_MISSING', 'WRONG_ITEM', 'MEASUREMENT_PROBLEM', 'WEATHER', 'CUSTOMER', 'EQUIPMENT', 'SAFETY', 'OTHER'];

    /** @var list<string> */
    public const PORTAL = [
        'contractor.work.view', 'contractor.work.accept', 'contractor.work.update',
        'contractor.photos.upload', 'contractor.documents.view', 'contractor.completion.submit',
    ];

    public function __construct(
        private readonly ContractorRepository $repo = new ContractorRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(array $input, int $userId): array
    {
        if (!can('contractor.manage')) {
            return ['errors' => ['_form' => 'You cannot manage contractors.'], 'id' => null];
        }
        $name = trim((string) ($input['company_name'] ?? ''));
        $type = strtoupper(trim((string) ($input['contractor_type'] ?? 'OTHER')));
        $status = strtoupper(trim((string) ($input['status'] ?? 'PENDING')));
        if ($name === '') {
            return ['errors' => ['company_name' => 'Enter the company name.'], 'id' => null];
        }
        if (!in_array($type, self::TYPES, true) || !in_array($status, self::STATUSES, true)) {
            return ['errors' => ['_form' => 'That contractor type or status is not valid.'], 'id' => null];
        }
        $id = $this->repo->insert([
            'supplier_id' => (int) ($input['supplier_id'] ?? 0) > 0 ? (int) $input['supplier_id'] : null,
            'company_name' => mb_substr($name, 0, 180),
            'contractor_type' => $type,
            'status' => $status,
            'contact_name' => $this->blank($input['contact_name'] ?? null),
            'email' => $this->blank($input['email'] ?? null),
            'phone' => $this->blank($input['phone'] ?? null),
            'notes' => $this->blank($input['notes'] ?? null),
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addDocument(int $contractorId, array $input, int $userId): array
    {
        if (!can('contractor.manage')) {
            return ['_form' => 'You cannot manage contractor documents.'];
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['title' => 'Name the document.'];
        }
        $this->repo->insertDocument([
            'contractor_id' => $contractorId,
            'document_type' => mb_substr(strtoupper((string) ($input['document_type'] ?? 'OTHER')), 0, 40),
            'title' => mb_substr($title, 0, 180),
            'issue_date' => $this->date($input['issue_date'] ?? null),
            'expiry_date' => $this->date($input['expiry_date'] ?? null),
            'file_path' => $this->blank($input['file_path'] ?? null),
        ]);
        $this->audit->record('contractor', $contractorId, 'CONTRACTOR_DOCUMENT', null, [], $userId);

        return [];
    }

    public function remindExpiring(int $userId, int $withinDays = 30): int
    {
        $until = date('Y-m-d', time() + ($withinDays * 86400));
        $count = 0;
        foreach ($this->repo->expiringDocuments($until) as $row) {
            (new NotificationService())->send(
                $userId,
                null,
                'SYSTEM',
                'Contractor document expiry',
                $row['company_name'] . ' — ' . $row['title'] . ' expires ' . $row['expiry_date'] . '. A stored file is not a legal check.',
                'CONTRACTOR',
                (int) $row['contractor_id'],
                'NORMAL',
                'contractor-doc:' . $row['id']
            );
            $this->repo->markReminded((int) $row['id']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function addPortalUser(int $contractorId, string $email, string $password, string $name, int $userId): array
    {
        if (!can('contractor.portal.manage')) {
            return ['errors' => ['_form' => 'You cannot manage the contractor portal.'], 'id' => null];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            return ['errors' => ['email' => 'Enter an email and a password of at least 10 characters.'], 'id' => null];
        }
        $id = $this->repo->insertUser([
            'contractor_id' => $contractorId,
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => mb_substr($name === '' ? $email : $name, 0, 120),
        ]);
        foreach (self::PORTAL as $code) {
            $this->repo->grant($id, $code);
        }
        $this->audit->record('contractor', $contractorId, 'CONTRACTOR_PORTAL_USER', null, ['user_id' => $id], $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, user: array<string, mixed>|null}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->repo->userByEmail(strtolower(trim($email)));
        if ($user === null || (int) $user['active'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
            return ['errors' => ['_form' => 'Those contractor details were not accepted.'], 'user' => null];
        }
        $contractor = $this->repo->find((int) $user['contractor_id']);
        if ($contractor === null || (string) $contractor['status'] !== 'ACTIVE') {
            return ['errors' => ['_form' => 'This contractor portal is not active.'], 'user' => null];
        }
        session_regenerate_id(true);
        $_SESSION['contractor_user_id'] = (int) $user['id'];

        return ['errors' => [], 'user' => $user];
    }

    public function logout(): void
    {
        unset($_SESSION['contractor_user_id']);
    }

    public function currentUser(): ?array
    {
        $id = (int) ($_SESSION['contractor_user_id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $user = $this->repo->user($id);
        if ($user === null || (int) $user['active'] !== 1) {
            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createWorkOrder(array $input, int $userId): array
    {
        if (!can('contractor.work_order.create')) {
            return ['errors' => ['_form' => 'You cannot create a contractor work order.'], 'id' => null];
        }
        $contractor = $this->repo->find((int) ($input['contractor_id'] ?? 0));
        if ($contractor === null || (string) $contractor['status'] !== 'ACTIVE') {
            return ['errors' => ['contractor_id' => 'Choose an active contractor.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['work_type'] ?? '')));
        $pricing = strtoupper(trim((string) ($input['pricing_method'] ?? 'FIXED')));
        $supply = strtoupper(trim((string) ($input['material_supply'] ?? 'SIGN_FORGE')));
        if (!in_array($type, self::WORK_TYPES, true) || !in_array($pricing, self::PRICING, true) || !in_array($supply, self::SUPPLY, true)) {
            return ['errors' => ['_form' => 'Check the work type, pricing, and material supply.'], 'id' => null];
        }
        $scope = trim((string) ($input['scope'] ?? ''));
        if ($scope === '' || (int) ($input['job_id'] ?? 0) < 1) {
            return ['errors' => ['scope' => 'Enter the scope and the job.'], 'id' => null];
        }
        $agreed = Decimal::isNumeric((string) ($input['agreed_cost'] ?? '0')) ? Decimal::money((string) $input['agreed_cost']) : null;
        if ($agreed === null) {
            return ['errors' => ['agreed_cost' => 'Enter the agreed cost.'], 'id' => null];
        }
        $id = $this->repo->insertWorkOrder([
            'work_order_number' => $this->numbers->contractorWorkOrder(),
            'contractor_id' => (int) $contractor['id'],
            'job_id' => (int) $input['job_id'],
            'project_id' => $this->positive($input['project_id'] ?? 0),
            'project_site_id' => $this->positive($input['project_site_id'] ?? 0),
            'service_request_id' => $this->positive($input['service_request_id'] ?? 0),
            'fulfilment_requirement_id' => $this->positive($input['fulfilment_requirement_id'] ?? 0),
            'installation_id' => $this->positive($input['installation_id'] ?? 0),
            'work_type' => $type,
            'scope' => $scope,
            'quantity' => Decimal::round((string) ($input['quantity'] ?? '1'), 4),
            'site_address' => $this->blank($input['site_address'] ?? null),
            'contact_name' => $this->blank($input['contact_name'] ?? null),
            'contact_phone' => $this->blank($input['contact_phone'] ?? null),
            'required_date' => $this->date($input['required_date'] ?? null),
            'instructions' => $this->blank($input['instructions'] ?? null),
            'pricing_method' => $pricing,
            'agreed_cost' => $agreed,
            'material_supply' => $supply,
            'internal_notes' => $this->blank($input['internal_notes'] ?? null),
            'visible_terms' => $this->blank($input['visible_terms'] ?? null),
            'created_by' => $userId,
        ]);
        foreach ((array) ($input['production_file_ids'] ?? []) as $fileId) {
            if ((int) $fileId > 0) {
                $this->repo->linkFile($id, (int) $fileId, 'DRAWING', 'Approved file');
            }
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function send(int $id, int $userId): array
    {
        if (!can('contractor.assign') && !can('contractor.work_order.create')) {
            return ['_form' => 'You cannot send a work order.'];
        }
        $order = $this->repo->workOrder($id);
        if ($order === null || (string) $order['status'] !== 'DRAFT') {
            return ['_form' => 'Only a draft work order can be sent.'];
        }
        $this->repo->setWorkStatus($id, 'SENT', ['exposed' => 1]);
        BusinessEventDispatcher::emit('CONTRACTOR_WORK_ORDER_SENT', 'CONTRACTOR_WORK', $id, $userId, []);

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function home(int $contractorUserId): array
    {
        $user = $this->repo->user($contractorUserId);
        if ($user === null || !$this->repo->allows($contractorUserId, 'contractor.work.view')) {
            return [];
        }

        return $this->repo->workForContractor((int) $user['contractor_id']);
    }

    /**
     * Assigned scope only. Commercial fields are not selected.
     *
     * @return array<string, mixed>|null
     */
    public function portalWork(int $workOrderId, int $contractorUserId): ?array
    {
        $user = $this->repo->user($contractorUserId);
        if ($user === null || !$this->repo->allows($contractorUserId, 'contractor.work.view')) {
            return null;
        }
        $row = $this->repo->workOrderForContractor($workOrderId, (int) $user['contractor_id']);
        if ($row === null) {
            return null;
        }
        if ((string) $row['status'] === 'SENT') {
            $this->repo->setWorkStatus($workOrderId, 'VIEWED');
            $row['status'] = 'VIEWED';
        }

        return $row;
    }

    public function canSeeFile(int $productionFileId, int $contractorUserId): bool
    {
        $user = $this->repo->user($contractorUserId);
        if ($user === null || !$this->repo->allows($contractorUserId, 'contractor.documents.view')) {
            return false;
        }

        return $this->repo->fileForContractor($productionFileId, (int) $user['contractor_id']) !== null;
    }

    /**
     * @return array<string, string>
     */
    public function respond(int $workOrderId, int $contractorUserId, string $action, string $message = '', string $changeKind = ''): array
    {
        $user = $this->repo->user($contractorUserId);
        if ($user === null || !$this->repo->allows($contractorUserId, 'contractor.work.accept')) {
            return ['_form' => 'You cannot respond to this work order.'];
        }
        $order = $this->repo->workOrderForContractor($workOrderId, (int) $user['contractor_id']);
        if ($order === null) {
            return ['_form' => 'That work order is not assigned to you.'];
        }
        $action = strtoupper($action);
        if (!in_array($action, ['ACCEPT', 'DECLINE', 'REQUEST_CHANGE'], true)) {
            return ['action' => 'Choose accept, decline, or request a change.'];
        }
        $status = match ($action) {
            'ACCEPT' => 'ACCEPTED',
            'DECLINE' => 'DECLINED',
            default => 'REVIEW_REQUIRED',
        };
        $this->repo->insertResponse($workOrderId, $action, $changeKind === '' ? null : strtoupper($changeKind), $message === '' ? null : mb_substr($message, 0, 500));
        $this->repo->setWorkStatus($workOrderId, $status);
        $event = match ($action) {
            'ACCEPT' => 'CONTRACTOR_WORK_ACCEPTED',
            'DECLINE' => 'CONTRACTOR_WORK_DECLINED',
            default => 'CONTRACTOR_WORK_BLOCKED',
        };
        BusinessEventDispatcher::emit($event, 'CONTRACTOR_WORK', $workOrderId, null, ['action' => strtolower($action)]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function start(int $workOrderId, int $contractorUserId, ?string $latitude, ?string $longitude): array
    {
        $order = $this->owned($workOrderId, $contractorUserId, 'contractor.work.update');
        if ($order === null) {
            return ['_form' => 'That work order is not assigned to you.'];
        }
        $this->repo->setWorkStatus($workOrderId, 'IN_PROGRESS', [
            'started_at' => date('Y-m-d H:i:s'),
            'start_latitude' => $this->coord($latitude),
            'start_longitude' => $this->coord($longitude),
        ]);
        BusinessEventDispatcher::emit('CONTRACTOR_WORK_STARTED', 'CONTRACTOR_WORK', $workOrderId, null, []);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function block(int $workOrderId, int $contractorUserId, string $reason, string $note): array
    {
        $reason = strtoupper($reason);
        if (!in_array($reason, self::BLOCKS, true)) {
            return ['reason' => 'Choose why the work is blocked.'];
        }
        if ($this->owned($workOrderId, $contractorUserId, 'contractor.work.update') === null) {
            return ['_form' => 'That work order is not assigned to you.'];
        }
        $this->repo->insertIssue([
            'work_order_id' => $workOrderId,
            'severity' => 'MAJOR',
            'description' => mb_substr($note === '' ? $reason : $note, 0, 255),
            'suggested_action' => null,
            'photo_path' => null,
            'reason' => $reason,
        ]);
        $this->repo->setWorkStatus($workOrderId, 'BLOCKED');
        BusinessEventDispatcher::emit('CONTRACTOR_WORK_BLOCKED', 'CONTRACTOR_WORK', $workOrderId, null, ['reason' => strtolower($reason)]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function submit(int $workOrderId, int $contractorUserId, array $input): array
    {
        if ($this->owned($workOrderId, $contractorUserId, 'contractor.completion.submit') === null) {
            return ['_form' => 'That work order is not assigned to you.'];
        }
        $this->repo->setWorkStatus($workOrderId, 'SUBMITTED_COMPLETE', [
            'submitted_at' => date('Y-m-d H:i:s'),
            'actual_hours' => $this->blank($input['actual_hours'] ?? null),
            'mileage_km' => $this->blank($input['mileage_km'] ?? null),
        ]);
        BusinessEventDispatcher::emit('CONTRACTOR_WORK_SUBMITTED', 'CONTRACTOR_WORK', $workOrderId, null, []);
        (new NotificationService())->send(null, null, 'SYSTEM', 'Contractor completion to review', 'A contractor submitted work for internal review. The job is not complete.', 'CONTRACTOR_WORK', $workOrderId, 'NORMAL', 'cwo-submit:' . $workOrderId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function review(int $workOrderId, int $userId, bool $approve): array
    {
        if (!can('contractor.work_order.approve')) {
            return ['_form' => 'You cannot review contractor work.'];
        }
        $order = $this->repo->workOrder($workOrderId);
        if ($order === null || !in_array((string) $order['status'], ['SUBMITTED_COMPLETE', 'REVIEW_REQUIRED'], true)) {
            return ['_form' => 'This work order is not waiting for review.'];
        }
        $status = $approve ? 'APPROVED_COMPLETE' : 'REVIEW_REQUIRED';
        $this->repo->setWorkStatus($workOrderId, $status, $approve ? ['approved_at' => date('Y-m-d H:i:s')] : []);
        if ($approve) {
            BusinessEventDispatcher::emit('CONTRACTOR_WORK_APPROVED', 'CONTRACTOR_WORK', $workOrderId, $userId, []);
        }

        return [];
    }

    /**
     * @return array{errors: array<string, string>, variance: string|null}
     */
    public function approveCost(int $workOrderId, string $actual, string $reason, int $userId): array
    {
        if (!can('contractor.cost.approve')) {
            return ['errors' => ['_form' => 'You cannot approve contractor cost.'], 'variance' => null];
        }
        if (!Decimal::isNumeric($actual) || Decimal::cmp($actual, '0') < 0) {
            return ['errors' => ['actual_cost' => 'Enter the actual cost.'], 'variance' => null];
        }
        $actualMoney = Decimal::money($actual);

        return Database::transaction(function () use ($workOrderId, $actualMoney, $reason, $userId): array {
            $order = $this->repo->lockWorkOrder($workOrderId);
            if ($order === null || (string) $order['status'] !== 'APPROVED_COMPLETE') {
                return ['errors' => ['_form' => 'Approve the work before the cost.'], 'variance' => null];
            }
            $variance = Decimal::sub($actualMoney, (string) $order['agreed_cost'], 2);
            $threshold = Decimal::money((string) SettingsService::get('contractor_variance_amount', '0'));
            if (Decimal::cmp($threshold, '0') > 0 && Decimal::cmp(ltrim($variance, '-'), $threshold) > 0 && trim($reason) === '') {
                return ['errors' => ['variance_reason' => 'Explain this cost variance.'], 'variance' => $variance];
            }
            if ($order['other_cost_id'] !== null) {
                return ['errors' => [], 'variance' => $variance];
            }
            $reference = 'CWO-' . $order['work_order_number'];
            $ops = new OperationsRepository();
            $existing = $ops->otherCostByReference((int) $order['job_id'], $reference);
            if ($existing !== null) {
                $this->repo->setWorkStatus($workOrderId, 'APPROVED_COMPLETE', [
                    'actual_cost' => $actualMoney,
                    'variance_reason' => $reason === '' ? null : mb_substr($reason, 0, 255),
                    'other_cost_id' => (int) $existing['id'],
                ]);

                return ['errors' => [], 'variance' => $variance];
            }
            $contractor = $this->repo->find((int) $order['contractor_id']);
            $otherId = $ops->insertOther([
                'job_id' => (int) $order['job_id'],
                'cost_type' => 'SUBCONTRACTOR',
                'description' => (string) $order['work_order_number'],
                'supplier_id' => $contractor['supplier_id'] ?? null,
                'quantity' => '1.0000',
                'unit_cost' => $actualMoney,
                'total_cost' => $actualMoney,
                'reference' => $reference,
                'created_by' => $userId,
            ]);
            $this->repo->setWorkStatus($workOrderId, 'APPROVED_COMPLETE', [
                'actual_cost' => $actualMoney,
                'variance_reason' => $reason === '' ? null : mb_substr($reason, 0, 255),
                'other_cost_id' => $otherId,
            ]);
            (new \App\Repositories\LogisticsRepository())->insertCost([
                'job_id' => (int) $order['job_id'],
                'project_id' => $order['project_id'],
                'shipment_id' => null,
                'source_type' => 'CONTRACTOR',
                'source_id' => $workOrderId,
                'amount' => $actualMoney,
                'customer_charge' => '0.00',
                'description' => 'Contractor ' . $order['work_order_number'],
                'other_cost_id' => $otherId,
                'created_by' => $userId,
            ]);
            (new JobCostingService())->refresh((int) $order['job_id']);
            $this->audit->record('contractor_work_order', $workOrderId, 'CONTRACTOR_COST_APPROVED', null, [
                'actual' => $actualMoney,
                'variance' => $variance,
            ], $userId);

            return ['errors' => [], 'variance' => $variance];
        });
    }

    /**
     * @return array<string, string>
     */
    public function issueMaterial(int $workOrderId, int $productId, int $fromLocationId, string $quantity, int $userId): array
    {
        if (!can('inventory.transfer')) {
            return ['_form' => 'You cannot issue stock to a contractor.'];
        }
        $order = $this->repo->workOrder($workOrderId);
        $contractor = $order === null ? null : $this->repo->find((int) $order['contractor_id']);
        if ($order === null || $contractor === null) {
            return ['_form' => 'That work order was not found.'];
        }
        if (!in_array((string) $order['material_supply'], ['SIGN_FORGE', 'MIXED'], true)) {
            return ['_form' => 'This work order does not use Sign-Forge material.'];
        }
        $locationId = $this->repo->ensureLocation((int) $contractor['id'], (string) $contractor['company_name']);
        $moved = (new StockMovementService())->transfer([
            'product_id' => $productId,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $locationId,
            'quantity' => $quantity,
            'reason' => 'Issue to contractor ' . $order['work_order_number'],
        ], $userId);
        if ($moved !== []) {
            return $moved;
        }
        $this->repo->insertMaterial([
            'work_order_id' => $workOrderId,
            'product_id' => $productId,
            'movement' => 'ISSUE',
            'quantity' => Decimal::round($quantity, 4),
            'stock_location_id' => $locationId,
            'transfer_reference' => null,
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function returnMaterial(int $workOrderId, int $productId, int $toLocationId, string $quantity, int $userId): array
    {
        $order = $this->repo->workOrder($workOrderId);
        $contractor = $order === null ? null : $this->repo->find((int) $order['contractor_id']);
        if ($order === null || $contractor === null) {
            return ['_form' => 'That work order was not found.'];
        }
        $held = $this->repo->materialBalance($workOrderId, $productId);
        if (Decimal::cmp($quantity, $held) > 0) {
            return ['quantity' => 'That is more than the contractor is holding.'];
        }
        $locationId = $this->repo->ensureLocation((int) $contractor['id'], (string) $contractor['company_name']);
        $moved = (new StockMovementService())->transfer([
            'product_id' => $productId,
            'from_location_id' => $locationId,
            'to_location_id' => $toLocationId,
            'quantity' => $quantity,
            'reason' => 'Return from contractor ' . $order['work_order_number'],
        ], $userId);
        if ($moved !== []) {
            return $moved;
        }
        $this->repo->insertMaterial([
            'work_order_id' => $workOrderId,
            'product_id' => $productId,
            'movement' => 'RETURN',
            'quantity' => Decimal::round($quantity, 4),
            'stock_location_id' => $toLocationId,
            'transfer_reference' => null,
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function consumeMaterial(int $workOrderId, int $productId, string $quantity, int $userId): array
    {
        $order = $this->repo->workOrder($workOrderId);
        $contractor = $order === null ? null : $this->repo->find((int) $order['contractor_id']);
        if ($order === null || $contractor === null) {
            return ['_form' => 'That work order was not found.'];
        }
        $held = $this->repo->materialBalance($workOrderId, $productId);
        if (Decimal::cmp($quantity, $held) > 0) {
            return ['quantity' => 'That is more than the contractor is holding.'];
        }
        $locationId = $this->repo->ensureLocation((int) $contractor['id'], (string) $contractor['company_name']);
        try {
            (new StockMovementService())->post([
                'product_id' => $productId,
                'stock_location_id' => $locationId,
                'movement_type' => MovementType::JobConsumption->value,
                'quantity' => $quantity,
                'unit' => 'unit',
                'unit_cost_snapshot' => '0',
                'job_id' => (int) $order['job_id'],
                'reference_type' => 'contractor_work_order',
                'reference_id' => $workOrderId,
                'reason' => 'Contractor consumption',
                'created_by' => $userId,
            ]);
        } catch (StockRejected $e) {
            return $e->errors;
        }
        $this->repo->insertMaterial([
            'work_order_id' => $workOrderId,
            'product_id' => $productId,
            'movement' => 'CONSUME',
            'quantity' => Decimal::round($quantity, 4),
            'stock_location_id' => $locationId,
            'transfer_reference' => null,
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function receiveOutput(int $workOrderId, array $input, int $userId): array
    {
        if (!can('contractor.work_order.approve')) {
            return ['_form' => 'You cannot receive outsourced work.'];
        }
        $received = (string) ($input['quantity_received'] ?? '0');
        $accepted = (string) ($input['quantity_accepted'] ?? '0');
        $rejected = (string) ($input['quantity_rejected'] ?? '0');
        if (!Decimal::isNumeric($received) || Decimal::cmp(Decimal::add($accepted, $rejected, 4), $received) !== 0) {
            return ['quantity' => 'Accepted and rejected quantities must add up to the quantity received.'];
        }
        $qc = strtoupper((string) ($input['qc_status'] ?? 'PENDING'));
        if (!in_array($qc, ['PENDING', 'PASS', 'FAIL'], true)) {
            return ['qc_status' => 'QC is pending, pass, or fail.'];
        }
        $this->repo->insertReceipt([
            'work_order_id' => $workOrderId,
            'quantity_received' => Decimal::round($received, 4),
            'quantity_accepted' => Decimal::round($accepted, 4),
            'quantity_rejected' => Decimal::round($rejected, 4),
            'qc_status' => $qc,
            'photo_path' => $this->blank($input['photo_path'] ?? null),
            'notes' => $this->blank($input['notes'] ?? null),
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function recordRework(int $workOrderId, array $input, int $userId): array
    {
        if (!can('contractor.work_order.approve')) {
            return ['_form' => 'You cannot record contractor rework.'];
        }
        $this->repo->insertRework([
            'work_order_id' => $workOrderId,
            'original_work_order_id' => $this->positive($input['original_work_order_id'] ?? 0),
            'reason' => mb_substr(trim((string) ($input['reason'] ?? 'Rework')), 0, 255),
            'cost' => Decimal::money((string) ($input['cost'] ?? '0')),
            'delay_days' => (int) ($input['delay_days'] ?? 0),
            'resolution' => $this->blank($input['resolution'] ?? null),
        ]);
        $this->audit->record('contractor_work_order', $workOrderId, 'CONTRACTOR_REWORK', null, [], $userId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function recordInvoice(int $workOrderId, array $input, int $userId): array
    {
        if (!can('contractor.cost.view')) {
            return ['_form' => 'You cannot record a contractor invoice.'];
        }
        $number = trim((string) ($input['invoice_number'] ?? ''));
        $amount = (string) ($input['amount'] ?? '');
        if ($number === '' || !Decimal::isNumeric($amount)) {
            return ['invoice_number' => 'Enter the invoice number and amount. This does not post accounts payable.'];
        }
        $this->repo->insertInvoice([
            'work_order_id' => $workOrderId,
            'invoice_number' => mb_substr($number, 0, 80),
            'invoice_date' => $this->date($input['invoice_date'] ?? null) ?? date('Y-m-d'),
            'amount' => Decimal::money($amount),
            'file_path' => $this->blank($input['file_path'] ?? null),
        ]);
        $this->audit->record('contractor_work_order', $workOrderId, 'CONTRACTOR_INVOICE_FILE', null, [], $userId);

        return [];
    }

    /**
     * Warranty amounts stay separate. Nothing here issues an invoice or a payment.
     *
     * @return array<string, string>
     */
    public function recordServiceSplit(int $requestId, string $customerCharge, string $internalCost, string $recovery, ?int $contractorId, ?int $originalWorkOrderId): array
    {
        if (!can('contractor.cost.view')) {
            return ['_form' => 'You cannot record service costs.'];
        }
        foreach (['customer' => $customerCharge, 'internal' => $internalCost, 'recovery' => $recovery] as $label => $amount) {
            if (!Decimal::isNumeric($amount)) {
                return [$label => 'Enter a number.'];
            }
        }
        $this->repo->setServiceSplit(
            $requestId,
            Decimal::money($customerCharge),
            Decimal::money($internalCost),
            Decimal::money($recovery),
            $contractorId,
            $originalWorkOrderId
        );

        return [];
    }

    public function noteAssetService(int $assetId, int $workOrderId, string $summary, int $userId): void
    {
        (new AssetRepository())->insertEvent([
            'asset_id' => $assetId,
            'event_type' => 'SERVICE',
            'summary' => mb_substr($summary, 0, 255),
            'related_type' => 'contractor_work_order',
            'related_id' => $workOrderId,
            'happened_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);
    }

    public function locationId(int $contractorId): int
    {
        $contractor = $this->repo->find($contractorId);
        if ($contractor === null) {
            return 0;
        }

        return $this->repo->ensureLocation($contractorId, (string) $contractor['company_name']);
    }

    public function holding(int $workOrderId, int $productId): string
    {
        return $this->repo->materialBalance($workOrderId, $productId);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function performance(int $contractorId): array
    {
        return $this->repo->performance($contractorId);
    }

    public function onHand(int $productId, ?int $locationId = null): string
    {
        return (new InventoryRepository())->onHand($productId, $locationId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function owned(int $workOrderId, int $contractorUserId, string $permission): ?array
    {
        $user = $this->repo->user($contractorUserId);
        if ($user === null || !$this->repo->allows($contractorUserId, $permission)) {
            return null;
        }

        return $this->repo->workOrderForContractor($workOrderId, (int) $user['contractor_id']);
    }

    private function positive(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function date(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $parsed = strtotime($text);

        return $parsed === false ? null : date('Y-m-d', $parsed);
    }

    private function coord(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' || !is_numeric($text) ? null : $text;
    }
}
