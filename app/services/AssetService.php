<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;

/**
 * Customer assets are the physical items now at a site.
 * Creating one never copies the job's commercial value into project totals.
 */
final class AssetService
{
    public const STATUSES = [
        'PLANNED', 'MANUFACTURED', 'AWAITING_INSTALLATION', 'ACTIVE', 'SERVICE_DUE',
        'UNDER_REPAIR', 'OUT_OF_SERVICE', 'REPLACED', 'REMOVED', 'DECOMMISSIONED', 'LOST', 'ARCHIVED',
    ];

    public const HEALTH = ['GOOD', 'SERVICE_DUE', 'ISSUE_REPORTED', 'UNDER_REPAIR', 'OUT_OF_SERVICE', 'UNKNOWN'];

    public function __construct(
        private readonly AssetRepository $assets = new AssetRepository(),
        private readonly NumberingService $numbers = new NumberingService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, warnings: list<string>}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('assets.create')) {
            return ['errors' => ['_form' => 'You cannot create an asset.'], 'id' => null, 'warnings' => []];
        }
        $built = $this->fields($input, true);
        if ($built['errors'] !== []) {
            return ['errors' => $built['errors'], 'id' => null, 'warnings' => []];
        }
        $warnings = $this->duplicateWarnings(
            (int) $built['data']['customer_id'],
            $built['data']['project_site_id'],
            $built['data']['customer_asset_reference'],
            $built['data']['serial_number'],
            $built['data']['original_job_item_id'],
            null
        );
        $id = 0;
        Database::transaction(function () use ($built, $userId, &$id): void {
            $data = $built['data'];
            $data['asset_number'] = $this->numbers->asset();
            $data['tracking_token'] = $this->token();
            $data['created_by'] = $userId;
            $id = $this->assets->insert($data);
            $this->assets->insertEvent([
                'asset_id' => $id,
                'event_type' => 'CREATED',
                'summary' => 'Asset created (' . $data['source'] . ')',
                'related_type' => 'customer_asset',
                'related_id' => $id,
                'happened_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);
            if ($data['installation_date'] !== null) {
                $this->assets->insertEvent([
                    'asset_id' => $id,
                    'event_type' => 'INSTALLED',
                    'summary' => 'Installed',
                    'related_type' => 'job',
                    'related_id' => $data['original_job_id'],
                    'happened_at' => $data['installation_date'] . ' 00:00:00',
                    'created_by' => $userId,
                ]);
            }
        });
        BusinessEventDispatcher::emit('ASSET_CREATED', 'ASSET', $id, $userId, [
            'source' => (string) $built['data']['source'],
        ]);
        if ($built['data']['installation_date'] !== null) {
            BusinessEventDispatcher::emit('ASSET_INSTALLED', 'ASSET', $id, $userId, []);
        }

        return ['errors' => [], 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestionsForJob(int $jobId): array
    {
        return $this->assets->eligibleItems($jobId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, ids: list<int>, warnings: list<string>}
     */
    public function createFromJobItem(int $jobItemId, array $input, int $userId): array
    {
        $item = $this->assets->jobItem($jobItemId);
        if ($item === null) {
            return ['errors' => ['_form' => 'That job item was not found.'], 'ids' => [], 'warnings' => []];
        }
        if ((int) ($item['creates_customer_asset'] ?? 0) !== 1 && empty($input['force'])) {
            return ['errors' => ['_form' => 'This product is not set to create a customer asset.'], 'ids' => [], 'warnings' => []];
        }
        if ((new \App\Repositories\ProductionControlRepository())->openFulfilmentForItem($jobItemId) > 0) {
            return ['errors' => ['_form' => 'Fulfilment is not complete. An asset is not created before that is recorded.'], 'ids' => [], 'warnings' => []];
        }
        $mode = strtoupper(trim((string) ($input['track_mode'] ?? 'GROUP')));
        if (!in_array($mode, ['GROUP', 'INDIVIDUAL'], true)) {
            $mode = 'GROUP';
        }
        $quantity = Decimal::round((string) ($input['quantity'] ?? $item['quantity']), 4);
        if (Decimal::cmp($quantity, '0') <= 0) {
            return ['errors' => ['quantity' => 'Quantity must be greater than zero.'], 'ids' => [], 'warnings' => []];
        }
        $copies = 1;
        $each = $quantity;
        if ($mode === 'INDIVIDUAL') {
            if (Decimal::cmp($quantity, (string) (int) $quantity) !== 0) {
                return ['errors' => ['quantity' => 'Individual assets need a whole quantity.'], 'ids' => [], 'warnings' => []];
            }
            $copies = (int) $quantity;
            if ($copies > 100) {
                return ['errors' => ['_form' => 'That is too many individual assets. Use one grouped asset, or create fewer than 100.'], 'ids' => [], 'warnings' => []];
            }
            $each = '1.0000';
        }
        $existing = $this->assets->forJobItem($jobItemId);
        $warnings = $existing === [] ? [] : ['An asset already exists for this job item. Nothing was merged.'];
        $ids = [];
        for ($n = 0; $n < $copies; $n++) {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '') {
                $name = (string) ($item['description'] ?: $item['product_name'] ?: $item['job_title']);
            }
            if ($copies > 1) {
                $name .= ' ' . ($n + 1);
            }
            $created = $this->create([
                'customer_id' => (int) $item['customer_id'],
                'project_id' => (int) ($item['project_id'] ?? 0),
                'project_site_id' => (int) ($item['project_site_id'] ?? 0),
                'original_job_id' => (int) $item['job_id'],
                'original_job_item_id' => $jobItemId,
                'asset_type_id' => (int) ($input['asset_type_id'] ?? 0),
                'name' => $name,
                'description' => (string) ($input['description'] ?? $item['description'] ?? ''),
                'source' => 'SIGN_FORGE',
                'status' => (string) ($input['status'] ?? 'ACTIVE'),
                'quantity' => $each,
                'track_mode' => $mode,
                'installation_date' => (string) ($input['installation_date'] ?? $item['job_installation_date'] ?? ''),
                'location_description' => (string) ($input['location_description'] ?? ''),
            ], $userId);
            if ($created['errors'] !== []) {
                return ['errors' => $created['errors'], 'ids' => $ids, 'warnings' => array_merge($warnings, $created['warnings'])];
            }
            $ids[] = (int) $created['id'];
            $warnings = array_merge($warnings, $created['warnings']);
            $technical = (new \App\Repositories\SignageRepository())->jobTechnical((int) $item['job_id']);
            if ($technical !== null) {
                (new \App\Repositories\SignageRepository())->stampAsset(
                    (int) $created['id'],
                    isset($technical['specification_id']) ? (int) $technical['specification_id'] : null,
                    json_encode([
                        'specification_code' => $technical['specification_code'] ?? null,
                        'specification_version' => $technical['specification_version'] ?? null,
                        'components' => $technical['components'] ?? [],
                        'geometry' => $technical['geometry'] ?? [],
                    ], JSON_THROW_ON_ERROR)
                );
            }
        }

        return ['errors' => [], 'ids' => $ids, 'warnings' => $warnings];
    }

    /**
     * @param list<int> $itemIds
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, ids: list<int>, warnings: list<string>}
     */
    public function bulkFromItems(array $itemIds, array $input, int $userId): array
    {
        $ids = [];
        $warnings = [];
        foreach ($itemIds as $itemId) {
            $made = $this->createFromJobItem((int) $itemId, $input, $userId);
            if ($made['errors'] !== []) {
                return ['errors' => $made['errors'], 'ids' => $ids, 'warnings' => array_merge($warnings, $made['warnings'])];
            }
            $ids = array_merge($ids, $made['ids']);
            $warnings = array_merge($warnings, $made['warnings']);
        }

        return ['errors' => [], 'ids' => $ids, 'warnings' => $warnings];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $version, int $userId): array
    {
        if (!can('assets.edit')) {
            return ['_form' => 'You cannot edit this asset.'];
        }
        $asset = $this->assets->find($id);
        if ($asset === null) {
            return ['_form' => 'That asset was not found.'];
        }
        $built = $this->fields(array_merge($asset, $input), false);
        if ($built['errors'] !== []) {
            return $built['errors'];
        }
        $changed = 0;
        Database::transaction(function () use ($id, $built, $version, $asset, $userId, &$changed): void {
            $changed = $this->assets->update($id, $built['data'], $version);
            if ($changed !== 1) {
                return;
            }
            if ((string) $asset['status'] !== (string) $built['data']['status']) {
                $this->assets->insertEvent([
                    'asset_id' => $id,
                    'event_type' => 'STATUS',
                    'summary' => 'Status ' . $asset['status'] . ' to ' . $built['data']['status'],
                    'related_type' => null,
                    'related_id' => null,
                    'happened_at' => date('Y-m-d H:i:s'),
                    'created_by' => $userId,
                ]);
            }
        });
        if ($changed !== 1) {
            return ['_form' => 'This asset was saved by someone else. Reload it and try again.'];
        }
        if ((string) $asset['status'] !== (string) $built['data']['status']) {
            BusinessEventDispatcher::emit('ASSET_STATUS_CHANGED', 'ASSET', $id, $userId, [
                'status' => (string) $built['data']['status'],
            ]);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function replace(int $id, array $input, int $userId): array
    {
        if (!can('assets.edit')) {
            return ['errors' => ['_form' => 'You cannot replace an asset.'], 'id' => null];
        }
        $old = $this->assets->find($id);
        if ($old === null || $old['archived_at'] !== null) {
            return ['errors' => ['_form' => 'That asset was not found.'], 'id' => null];
        }
        $gate = (new ApprovalService())->gate('ASSET', $id, 'ASSET_REPLACEMENT', $userId, [], 'Replace asset');
        if ($gate['blocked']) {
            return ['errors' => ['_form' => 'Replacement needs approval before it can continue.'], 'id' => null];
        }
        $created = $this->create([
            'customer_id' => (int) $old['customer_id'],
            'project_id' => (int) ($old['project_id'] ?? 0),
            'project_site_id' => (int) ($old['project_site_id'] ?? 0),
            'asset_type_id' => (int) ($input['asset_type_id'] ?? $old['asset_type_id']),
            'name' => trim((string) ($input['name'] ?? '')) !== '' ? $input['name'] : ((string) $old['name'] . ' replacement'),
            'description' => (string) ($input['description'] ?? 'Replacement for ' . $old['asset_number']),
            'source' => (string) $old['source'],
            'status' => 'ACTIVE',
            'quantity' => (string) ($input['quantity'] ?? $old['quantity']),
            'track_mode' => (string) $old['track_mode'],
            'installation_date' => (string) ($input['installation_date'] ?? date('Y-m-d')),
            'location_description' => (string) ($old['location_description'] ?? ''),
            'replaces_asset_id' => $id,
        ], $userId);
        if ($created['id'] === null) {
            return ['errors' => $created['errors'], 'id' => null];
        }
        $newId = (int) $created['id'];
        $this->assets->setStatus($id, 'REPLACED', 'OUT_OF_SERVICE');
        $this->assets->linkReplacement($id, $newId);
        $this->assets->insertEvent([
            'asset_id' => $id,
            'event_type' => 'REPLACED',
            'summary' => 'Replaced. History kept.',
            'related_type' => 'customer_asset',
            'related_id' => $newId,
            'happened_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);
        BusinessEventDispatcher::emit('ASSET_REPLACED', 'ASSET', $id, $userId, ['replacement_id' => $newId]);

        return ['errors' => [], 'id' => $newId];
    }

    /**
     * @return array<string, string>
     */
    public function remove(int $id, array $input, int $userId): array
    {
        if (!can('assets.edit')) {
            return ['_form' => 'You cannot remove an asset.'];
        }
        $asset = $this->assets->find($id);
        if ($asset === null) {
            return ['_form' => 'That asset was not found.'];
        }
        $reason = trim((string) ($input['removal_reason'] ?? ''));
        $date = trim((string) ($input['removed_at'] ?? ''));
        if ($reason === '') {
            return ['removal_reason' => 'A removal needs a reason.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['removed_at' => 'Removal date is required.'];
        }
        $this->assets->markRemoved($id, $date, mb_substr($reason, 0, 255), blank_to_null($input['disposition'] ?? null));
        $this->assets->insertEvent([
            'asset_id' => $id,
            'event_type' => 'REMOVED',
            'summary' => 'Removed: ' . $reason,
            'related_type' => 'job',
            'related_id' => (int) ($input['job_id'] ?? 0) > 0 ? (int) $input['job_id'] : null,
            'happened_at' => $date . ' 00:00:00',
            'created_by' => $userId,
        ]);
        BusinessEventDispatcher::emit('ASSET_REMOVED', 'ASSET', $id, $userId, []);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function transfer(int $id, array $input, int $userId): array
    {
        if (!can('assets.edit')) {
            return ['_form' => 'You cannot move an asset.'];
        }
        $asset = $this->assets->find($id);
        if ($asset === null) {
            return ['_form' => 'That asset was not found.'];
        }
        $customerId = (int) ($input['customer_id'] ?? $asset['customer_id']);
        if ($customerId < 1) {
            return ['customer_id' => 'Choose a customer.'];
        }
        $siteId = (int) ($input['project_site_id'] ?? 0);
        $location = blank_to_null($input['location_description'] ?? null);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ['reason' => 'Record why the asset moved.'];
        }
        Database::transaction(function () use ($asset, $id, $customerId, $siteId, $location, $reason, $input, $userId): void {
            $this->assets->insertLocation([
                'asset_id' => $id,
                'from_site_id' => $asset['project_site_id'] !== null ? (int) $asset['project_site_id'] : null,
                'to_site_id' => $siteId > 0 ? $siteId : null,
                'from_location' => $asset['location_description'],
                'to_location' => $location,
                'reason' => mb_substr($reason, 0, 180),
                'job_id' => (int) ($input['job_id'] ?? 0) > 0 ? (int) $input['job_id'] : null,
                'changed_by' => $userId,
            ]);
            $this->assets->move($id, $customerId, $siteId > 0 ? $siteId : null, $location);
            $this->assets->insertEvent([
                'asset_id' => $id,
                'event_type' => 'MOVED',
                'summary' => $reason,
                'related_type' => null,
                'related_id' => null,
                'happened_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function archive(int $id): array
    {
        if (!can('assets.archive')) {
            return ['_form' => 'You cannot archive an asset.'];
        }
        $this->assets->archive($id);

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publicCard(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $asset = $this->assets->findByToken($token);
        if ($asset === null) {
            return null;
        }

        return [
            'asset_number' => (string) $asset['asset_number'],
            'name' => (string) $asset['name'],
            'site' => (string) ($asset['site_name'] ?: $asset['location_description'] ?: ''),
        ];
    }

    public function labelSvg(int $id): ?string
    {
        $asset = $this->assets->find($id);
        if ($asset === null) {
            return null;
        }
        $payload = 'SFASSET:' . (string) $asset['tracking_token'];

        return QrSvg::svg($payload);
    }

    /**
     * @return array{imported: int, skipped: int, failed: int, warnings: list<string>, errors: list<string>}
     */
    public function importCsv(string $csv, int $userId): array
    {
        if (!can('assets.import')) {
            return ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'warnings' => [], 'errors' => ['You cannot import assets.']];
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        if ($lines === [] || count($lines) < 2) {
            return ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'warnings' => [], 'errors' => ['The file needs a header and one row.']];
        }
        $header = str_getcsv((string) array_shift($lines));
        $header = array_map(static fn ($cell): string => strtolower(trim((string) $cell)), $header);
        $required = ['customer', 'asset_name', 'type'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                return ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'warnings' => [], 'errors' => ['Missing column ' . $column . '.']];
            }
        }
        $pending = [];
        $errors = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = trim((string) ($cells[$i] ?? ''));
            }
            $lineNo = $index + 2;
            $customer = $this->assets->customerByName($row['customer']);
            if ($customer === null) {
                $errors[] = 'Line ' . $lineNo . ': customer was not found.';
                continue;
            }
            $type = null;
            foreach ($this->assets->types() as $candidate) {
                if (strcasecmp((string) $candidate['code'], $row['type']) === 0 || strcasecmp((string) $candidate['name'], $row['type']) === 0) {
                    $type = $candidate;
                    break;
                }
            }
            if ($type === null) {
                $errors[] = 'Line ' . $lineNo . ': asset type was not found.';
                continue;
            }
            if ($row['asset_name'] === '') {
                $errors[] = 'Line ' . $lineNo . ': asset name is required.';
                continue;
            }
            $siteId = 0;
            $projectId = 0;
            if (($row['site'] ?? '') !== '') {
                $site = $this->assets->siteForCustomer((int) $customer['id'], $row['site']);
                if ($site === null) {
                    $errors[] = 'Line ' . $lineNo . ': site was not found for that customer.';
                    continue;
                }
                $siteId = (int) $site['id'];
                $projectId = (int) $site['project_id'];
            }
            $pending[] = [
                'customer_id' => (int) $customer['id'],
                'project_id' => $projectId,
                'project_site_id' => $siteId,
                'asset_type_id' => (int) $type['id'],
                'name' => $row['asset_name'],
                'customer_asset_reference' => $row['reference'] ?? '',
                'installation_date' => $row['install_date'] ?? '',
                'location_description' => $row['location'] ?? '',
                'source' => 'THIRD_PARTY',
                'description' => 'Imported. Dates and condition are user-supplied where the original job is unknown.',
                'status' => 'ACTIVE',
                'warranty_months' => (int) ($row['warranty'] ?? 0),
            ];
        }
        if ($errors !== []) {
            return ['imported' => 0, 'skipped' => 0, 'failed' => count($errors), 'warnings' => [], 'errors' => $errors];
        }
        $imported = 0;
        $skipped = 0;
        $warnings = [];
        foreach ($pending as $row) {
            $months = (int) $row['warranty_months'];
            unset($row['warranty_months']);
            $dupes = $this->duplicateWarnings(
                (int) $row['customer_id'],
                $row['project_site_id'] > 0 ? (int) $row['project_site_id'] : null,
                $row['customer_asset_reference'] !== '' ? $row['customer_asset_reference'] : null,
                null,
                null,
                null
            );
            if ($dupes !== []) {
                $skipped++;
                $warnings[] = $row['name'] . ' skipped: ' . $dupes[0];
                continue;
            }
            $created = $this->create($row, $userId);
            if ($created['id'] === null) {
                return ['imported' => $imported, 'skipped' => $skipped, 'failed' => 1, 'warnings' => $warnings, 'errors' => array_values($created['errors'])];
            }
            if ($months > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row['installation_date'])) {
                (new WarrantyService())->add((int) $created['id'], [
                    'warranty_type' => 'CUSTOM',
                    'start_date' => $row['installation_date'],
                    'months' => $months,
                    'provider_type' => 'CUSTOM',
                    'provider_name' => 'Imported terms',
                    'terms' => 'Imported warranty length. Confirm the original certificate.',
                ], $userId);
            }
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'failed' => 0, 'warnings' => $warnings, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $asset
     */
    public function healthFor(array $asset, bool $openRequest): string
    {
        $status = (string) $asset['status'];
        if (in_array($status, ['OUT_OF_SERVICE', 'REMOVED', 'DECOMMISSIONED', 'LOST'], true)) {
            return 'OUT_OF_SERVICE';
        }
        if ($status === 'UNDER_REPAIR') {
            return 'UNDER_REPAIR';
        }
        if ($openRequest) {
            return 'ISSUE_REPORTED';
        }
        $next = (string) ($asset['next_service_date'] ?? '');
        if ($next !== '' && $next <= date('Y-m-d')) {
            return 'SERVICE_DUE';
        }
        if ($status === 'ACTIVE') {
            return 'GOOD';
        }

        return 'UNKNOWN';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, data: array<string, mixed>}
     */
    private function fields(array $input, bool $creating): array
    {
        $errors = [];
        $customerId = (int) ($input['customer_id'] ?? 0);
        if ($customerId < 1) {
            $errors['customer_id'] = 'Choose a customer.';
        }
        $typeId = (int) ($input['asset_type_id'] ?? 0);
        if ($this->assets->type($typeId) === null) {
            $errors['asset_type_id'] = 'Choose an asset type.';
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        $status = strtoupper(trim((string) ($input['status'] ?? 'PLANNED')));
        if (!in_array($status, self::STATUSES, true) || $status === 'ARCHIVED') {
            $errors['status'] = 'Choose a status.';
        }
        $source = strtoupper(trim((string) ($input['source'] ?? 'SIGN_FORGE')));
        if (!in_array($source, ['SIGN_FORGE', 'THIRD_PARTY', 'UNKNOWN'], true)) {
            $source = 'SIGN_FORGE';
        }
        $mode = strtoupper(trim((string) ($input['track_mode'] ?? 'INDIVIDUAL')));
        if (!in_array($mode, ['GROUP', 'INDIVIDUAL'], true)) {
            $mode = 'INDIVIDUAL';
        }
        $quantity = trim((string) ($input['quantity'] ?? '1'));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            $errors['quantity'] = 'Quantity must be greater than zero.';
            $quantity = '1';
        }
        $health = strtoupper(trim((string) ($input['health'] ?? '')));
        if (!in_array($health, self::HEALTH, true)) {
            $health = $status === 'ACTIVE' ? 'GOOD' : 'UNKNOWN';
        }
        foreach (['installation_date', 'commissioned_date', 'next_service_date'] as $dateField) {
            $value = trim((string) ($input[$dateField] ?? ''));
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[$dateField] = 'Use a date.';
            }
        }
        $lat = $this->coordinate($input['latitude'] ?? null, -90, 90);
        $lng = $this->coordinate($input['longitude'] ?? null, -180, 180);
        if ($lat === false || $lng === false) {
            $errors['latitude'] = 'Latitude and longitude must be coordinates.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'data' => []];
        }
        $captured = null;
        if ($lat !== null && $lng !== null) {
            $captured = date('Y-m-d H:i:s');
        }
        $year = (int) ($input['vehicle_year'] ?? 0);

        return ['errors' => [], 'data' => [
            'customer_id' => $customerId,
            'project_id' => (int) ($input['project_id'] ?? 0) > 0 ? (int) $input['project_id'] : null,
            'project_site_id' => (int) ($input['project_site_id'] ?? 0) > 0 ? (int) $input['project_site_id'] : null,
            'original_job_id' => (int) ($input['original_job_id'] ?? 0) > 0 ? (int) $input['original_job_id'] : null,
            'original_job_item_id' => (int) ($input['original_job_item_id'] ?? 0) > 0 ? (int) $input['original_job_item_id'] : null,
            'asset_type_id' => $typeId,
            'name' => mb_substr($name, 0, 180),
            'description' => blank_to_null($input['description'] ?? null),
            'manufacturer' => blank_to_null($input['manufacturer'] ?? null),
            'model' => blank_to_null($input['model'] ?? null),
            'serial_number' => blank_to_null($input['serial_number'] ?? null),
            'customer_asset_reference' => blank_to_null($input['customer_asset_reference'] ?? null),
            'source' => $source,
            'status' => $status,
            'health' => $health,
            'quantity' => Decimal::round($quantity, 4),
            'track_mode' => $mode,
            'installation_date' => $this->dateOrNull($input['installation_date'] ?? null),
            'commissioned_date' => $this->dateOrNull($input['commissioned_date'] ?? null),
            'expected_service_interval_days' => (int) ($input['expected_service_interval_days'] ?? 0) > 0 ? (int) $input['expected_service_interval_days'] : null,
            'next_service_date' => $this->dateOrNull($input['next_service_date'] ?? null),
            'latitude' => $lat,
            'longitude' => $lng,
            'gps_accuracy_m' => $this->optionalMoney($input['gps_accuracy_m'] ?? null),
            'gps_captured_at' => $captured,
            'location_description' => blank_to_null($input['location_description'] ?? null),
            'fleet_number' => blank_to_null($input['fleet_number'] ?? null),
            'registration' => blank_to_null($input['registration'] ?? null),
            'vehicle_make' => blank_to_null($input['vehicle_make'] ?? null),
            'vehicle_model' => blank_to_null($input['vehicle_model'] ?? null),
            'vehicle_year' => $year > 1900 ? $year : null,
            'original_commercial_value' => $this->optionalMoney($input['original_commercial_value'] ?? null),
            'original_internal_cost' => $this->optionalMoney($input['original_internal_cost'] ?? null),
            'replaces_asset_id' => (int) ($input['replaces_asset_id'] ?? 0) > 0 ? (int) $input['replaces_asset_id'] : null,
        ]];
    }

    /**
     * @return list<string>
     */
    private function duplicateWarnings(int $customerId, ?int $siteId, ?string $reference, ?string $serial, ?int $jobItemId, ?int $ignore): array
    {
        $ids = $this->assets->duplicateIds($customerId, $siteId, $reference, $serial, $jobItemId, $ignore);
        if ($ids === []) {
            return [];
        }

        return ['Possible duplicate asset id ' . $ids[0] . '. Nothing was merged.'];
    }

    private function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function coordinate(mixed $value, float $min, float $max): float|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return false;
        }
        $number = (float) $value;
        if ($number < $min || $number > $max) {
            return false;
        }

        return $number;
    }

    private function optionalMoney(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || !Decimal::isNumeric($value)) {
            return null;
        }

        return Decimal::money($value);
    }
}
