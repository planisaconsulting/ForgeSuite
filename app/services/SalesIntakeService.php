<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InventoryRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SalesIntakeRepository;

/**
 * Intake proposes structure. EstimateService and QuoteService calculate money.
 * A person confirms the customer, the requirements, and the product first.
 */
final class SalesIntakeService
{
    /** @var list<string> */
    public const SOURCES = [
        'EMAIL', 'WHATSAPP', 'CUSTOMER_PORTAL', 'WEBSITE', 'PHONE_NOTE', 'WALK_IN', 'FILE_UPLOAD', 'MANUAL', 'OTHER',
    ];

    /** @var list<string> */
    public const INTENTS = [
        'NEW_QUOTE', 'REORDER', 'SERVICE', 'PROJECT', 'ASSET_REPLACEMENT', 'GENERAL_ENQUIRY', 'COMPLAINT', 'UNKNOWN',
    ];

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'exe', 'sh', 'js', 'html', 'htm', 'svg', 'bat', 'cmd'];

    public function __construct(
        private readonly SalesIntakeRepository $repo = new SalesIntakeRepository(),
        private readonly IntakeTextParser $parser = new IntakeTextParser(),
        private readonly IntakeSchema $schema = new IntakeSchema(),
        private readonly IntakeRequirementValidator $validator = new IntakeRequirementValidator(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, existing: bool}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('sales_intake.create')) {
            return ['errors' => ['_form' => 'You cannot capture an enquiry.'], 'id' => null, 'existing' => false];
        }
        $source = strtoupper(trim((string) ($input['source_type'] ?? 'MANUAL')));
        if (!in_array($source, self::SOURCES, true)) {
            return ['errors' => ['source_type' => 'Choose a source.'], 'id' => null, 'existing' => false];
        }
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '' && !empty($input['structured'])) {
            $message = 'Structured enquiry.';
        }
        if ($message === '') {
            return ['errors' => ['message' => 'Enter the enquiry.'], 'id' => null, 'existing' => false];
        }
        $messageId = trim((string) ($input['external_message_id'] ?? ''));
        if ($messageId !== '') {
            $thread = $this->repo->findByMessage($messageId);
            if ($thread !== null) {
                return ['errors' => [], 'id' => (int) $thread['id'], 'existing' => true];
            }
        }
        if (preg_match('/SFIN-\d{4}-\d{4}/', $message . ' ' . ($input['subject'] ?? ''), $token) === 1) {
            $thread = $this->repo->findByNumber($token[0]);
            if ($thread !== null) {
                return ['errors' => [], 'id' => (int) $thread['id'], 'existing' => true];
            }
        }
        $received = trim((string) ($input['received_at'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $received)) {
            $received = date('Y-m-d H:i:s');
        }
        $id = 0;
        $number = '';
        \App\Helpers\Database::transaction(function () use ($input, $userId, $source, $message, $messageId, $received, &$id, &$number): void {
            $number = $this->numbers->salesIntake();
            $id = $this->repo->insertIntake([
                'intake_number' => $number,
                'source_type' => $source,
                'source_reference_id' => $this->optionalId($input['source_reference_id'] ?? null),
                'communication_id' => $this->optionalId($input['communication_id'] ?? null),
                'external_message_id' => $messageId !== '' ? $messageId : null,
                'customer_id' => null,
                'contact_id' => $this->optionalId($input['contact_id'] ?? null),
                'lead_id' => $this->optionalId($input['lead_id'] ?? null),
                'opportunity_id' => $this->optionalId($input['opportunity_id'] ?? null),
                'status' => 'NEW',
                'intent_type' => 'UNKNOWN',
                'language_code' => null,
                'assigned_user_id' => $this->optionalId($input['assigned_user_id'] ?? null),
                'sender_name' => $this->blank($input['sender_name'] ?? null),
                'sender_email' => $this->blank($input['sender_email'] ?? null),
                'sender_phone' => $this->blank($input['sender_phone'] ?? null),
                'subject' => $this->blank($input['subject'] ?? null),
                'original_message' => $message,
                'received_at' => $received,
                'created_by' => $userId,
            ]);
        });
        $this->audit->record('sales_intake', $id, 'SALES_INTAKE_CREATED', null, ['number' => $number], $userId);
        BusinessEventDispatcher::emit('SALES_INTAKE_CREATED', 'SALES_INTAKE', $id, $userId, ['source' => strtolower($source)]);
        (new ReviewQueueService())->add('NEW_INTAKE', 'SALES_INTAKE', $id, 'Review new enquiry', 'intake', null, $this->optionalId($input['assigned_user_id'] ?? null));
        if (!empty($input['structured']) && is_array($input['structured'])) {
            $this->storeStructured($id, $input['structured'], $userId);
        }
        $flags = new FeatureFlagService();
        if ($flags->enabled('AI_INTAKE_ANALYSIS') && can('sales_intake.ai_analyse') && empty($input['structured'])) {
            $this->analyse($id, $userId);
        }

        return ['errors' => [], 'id' => $id, 'existing' => false];
    }

    /**
     * @return array{errors: array<string, string>, analysis: array<string, mixed>|null, cached: bool}
     */
    public function analyse(int $id, int $userId, ?string $externalJson = null): array
    {
        if (!can('sales_intake.ai_analyse')) {
            return ['errors' => ['_form' => 'You cannot analyse this enquiry.'], 'analysis' => null, 'cached' => false];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'analysis' => null, 'cached' => false];
        }
        $hash = hash('sha256', $externalJson !== null ? 'ext:' . $externalJson : 'msg:' . (string) $intake['original_message']);
        $existing = $this->repo->analysisByHash($id, $hash);
        if ($existing !== null) {
            return ['errors' => [], 'analysis' => $existing, 'cached' => true];
        }
        $max = max(1, (int) SettingsService::get('intake_max_analyses', '3'));
        if ($this->repo->analysisCount($id) >= $max) {
            return ['errors' => ['_form' => 'This enquiry has reached the analysis limit.'], 'analysis' => null, 'cached' => false];
        }
        BusinessEventDispatcher::emit('SALES_INTAKE_ANALYSIS_STARTED', 'SALES_INTAKE', $id, $userId, []);
        if ($externalJson !== null) {
            return $this->storeExternal($intake, $externalJson, $hash, $userId);
        }
        $parsed = $this->parser->parse((string) $intake['original_message'], (string) $intake['received_at'], $this->repo->terms());
        $provider = $this->providerDraft($intake, $userId);
        $status = $provider['called'] && $provider['status'] === 'UNAVAILABLE' ? 'MANUAL' : 'PROPOSED';
        $this->applyParsed($intake, $parsed, $hash, $provider, $status, $userId);

        return ['errors' => [], 'analysis' => $this->repo->analysisByHash($id, $hash), 'cached' => false];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function confirmCustomer(int $id, int $customerId, int $userId): array
    {
        if (!can('sales_intake.confirm_customer')) {
            return ['_form' => 'You cannot confirm this customer.'];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['_form' => 'You cannot open this intake.'];
        }
        $row = $this->customerExists($customerId);
        if ($row === null) {
            return ['customer_id' => 'That customer was not found.'];
        }
        $this->repo->updateIntake($id, ['customer_id' => $customerId, 'match_state' => 'EXACT']);
        $this->audit->record('sales_intake', $id, 'CUSTOMER_MATCH_CONFIRMED', null, ['customer_id' => $customerId], $userId);
        BusinessEventDispatcher::emit('CUSTOMER_MATCH_CONFIRMED', 'SALES_INTAKE', $id, $userId, ['customer' => $customerId]);
        $this->refreshNext($id);

        return [];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function confirmField(int $id, string $key, string $value, int $userId): array
    {
        if (!can('sales_intake.confirm_requirements')) {
            return ['_form' => 'You cannot confirm requirements.'];
        }
        if ($this->guard($id, $userId) === null) {
            return ['_form' => 'You cannot open this intake.'];
        }
        $key = substr(preg_replace('/[^a-z0-9_]/', '', strtolower($key)) ?? '', 0, 80);
        if ($key === '') {
            return ['field' => 'Choose a field.'];
        }
        $existing = $this->repo->field($id, $key);
        $value = trim($value);
        if ($existing !== null && in_array((string) $existing['review_status'], ['CONFIRMED', 'CORRECTED'], true) && (string) $existing['confirmed_value'] !== $value) {
            $this->repo->updateField((int) $existing['id'], ['conflict_value' => $value]);
            $this->repo->updateIntake($id, ['status' => 'REVIEW_REQUIRED']);

            return ['field' => 'That confirmed value conflicts with the new value.'];
        }
        $status = $existing !== null && (string) ($existing['proposed_value'] ?? '') !== $value ? 'CORRECTED' : 'CONFIRMED';
        if ($existing === null) {
            $this->repo->insertField([
                'intake_id' => $id,
                'field_key' => $key,
                'proposed_value' => $value,
                'confirmed_value' => $value,
                'conflict_value' => null,
                'original_text' => null,
                'extraction_method' => 'MANUAL',
                'evidence' => 'HUMAN_CONFIRMED',
                'review_status' => 'CONFIRMED',
                'is_approximate' => 0,
                'dimension_source' => 'MANUAL',
            ]);
        } else {
            $this->repo->updateField((int) $existing['id'], [
                'confirmed_value' => $value,
                'conflict_value' => null,
                'review_status' => $status,
                'evidence' => 'HUMAN_CONFIRMED',
            ]);
        }
        $this->copyFieldToItem($id, $key, $value);

        return [];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function confirmRequirements(int $id, int $userId): array
    {
        if (!can('sales_intake.confirm_requirements')) {
            return ['_form' => 'You cannot confirm requirements.'];
        }
        if ($this->guard($id, $userId) === null) {
            return ['_form' => 'You cannot open this intake.'];
        }
        foreach ($this->repo->fields($id) as $field) {
            if ((string) $field['review_status'] === 'PROPOSED' && (string) ($field['proposed_value'] ?? '') !== '') {
                $this->repo->updateField((int) $field['id'], [
                    'confirmed_value' => $field['proposed_value'],
                    'review_status' => 'CONFIRMED',
                    'evidence' => 'HUMAN_CONFIRMED',
                ]);
            }
        }
        foreach ($this->repo->items($id) as $item) {
            $this->repo->updateItem((int) $item['id'], ['review_status' => 'CONFIRMED']);
        }
        $this->repo->updateIntake($id, [
            'requirements_confirmed_at' => date('Y-m-d H:i:s'),
            'status' => 'READY_TO_ESTIMATE',
        ]);
        BusinessEventDispatcher::emit('REQUIREMENTS_CONFIRMED', 'SALES_INTAKE', $id, $userId, []);
        $this->refreshNext($id);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, matches: list<array<string, mixed>>}
     */
    public function matchProducts(int $id, int $userId): array
    {
        if (!can('sales_intake.match_product')) {
            return ['errors' => ['_form' => 'You cannot match a product.'], 'matches' => []];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'matches' => []];
        }
        $this->repo->clearMatches($id);
        $items = $this->repo->items($id);
        $customerId = (int) ($intake['customer_id'] ?? 0);
        $catalogue = $customerId > 0 ? $this->repo->catalogueItems($customerId) : [];
        $rank = 1;
        $any = false;
        foreach ($items as $item) {
            $placed = $this->placeMatch($id, $item, $catalogue, $rank);
            $any = $any || $placed;
            $rank++;
        }
        if ($items === [] && (string) $intake['estimator_kind'] === 'VEHICLE_WRAP') {
            $this->repo->insertMatch([
                'intake_id' => $id,
                'line_no' => 1,
                'product_id' => null,
                'catalogue_item_id' => null,
                'specification_id' => null,
                'recipe_id' => null,
                'match_state' => 'REVIEW_REQUIRED',
                'match_rank' => 1,
                'why_text' => 'Open the wrap estimator after the vehicle is confirmed.',
                'missing_json' => null,
                'warnings_json' => null,
                'review_status' => 'PROPOSED',
            ]);
        }
        if (!$any && $items !== []) {
            $description = trim((string) ($items[0]['material_text'] ?? $items[0]['description'] ?? 'Custom sign'));
            $this->repo->insertReviewRequest([
                'intake_id' => $id,
                'description' => mb_substr($description, 0, 255),
                'status' => 'PENDING',
                'created_by' => $userId,
            ]);
        }
        BusinessEventDispatcher::emit('PRODUCT_MATCH_PROPOSED', 'SALES_INTAKE', $id, $userId, []);

        return ['errors' => [], 'matches' => $this->repo->matches($id)];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function confirmProduct(int $id, int $matchId, int $userId): array
    {
        if (!can('sales_intake.match_product')) {
            return ['_form' => 'You cannot match a product.'];
        }
        if ($this->guard($id, $userId) === null) {
            return ['_form' => 'You cannot open this intake.'];
        }
        $match = $this->repo->match($matchId);
        if ($match === null || (int) $match['intake_id'] !== $id) {
            return ['match' => 'That match does not belong to this enquiry.'];
        }
        if ((int) ($match['product_id'] ?? 0) > 0 && $this->repo->product((int) $match['product_id']) === null) {
            return ['match' => 'That product does not exist.'];
        }
        $blocked = $this->configurationBlock($match);
        if ($blocked !== null) {
            return ['match' => $blocked];
        }
        $this->repo->confirmMatch($matchId);
        BusinessEventDispatcher::emit('PRODUCT_MATCH_CONFIRMED', 'SALES_INTAKE', $id, $userId, ['match' => $matchId]);

        return [];
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createEstimate(int $id, int $userId, array $proposal = []): array
    {
        if (!can('sales_intake.create_estimate')) {
            return ['errors' => ['_form' => 'You cannot create this estimate.'], 'id' => null];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'id' => null];
        }
        if (in_array((string) $intake['intent_type'], ['SERVICE', 'COMPLAINT'], true)) {
            return ['errors' => ['_form' => 'This enquiry is not a new sales estimate.'], 'id' => null];
        }
        $readiness = $this->readiness($intake);
        if ($readiness !== 'READY') {
            return ['errors' => ['_form' => 'The estimate is not ready. ' . $readiness], 'id' => null];
        }
        $ignored = [];
        foreach (['sell_price', 'unit_cost', 'margin', 'vat', 'discount', 'markup', 'cost', 'price', 'subtotal_cost'] as $money) {
            if (array_key_exists($money, $proposal)) {
                $ignored[] = $money;
            }
        }
        if ($ignored !== []) {
            $this->audit->record('sales_intake', $id, 'AI_MONEY_IGNORED', null, ['fields' => $ignored], $userId);
        }
        $match = null;
        foreach ($this->repo->matches($id) as $row) {
            if ((string) $row['review_status'] === 'CONFIRMED') {
                $match = $row;
                break;
            }
        }
        if ($match === null || (int) ($match['product_id'] ?? 0) < 1) {
            return ['errors' => ['_form' => 'Confirm a product before estimating.'], 'id' => null];
        }
        $block = $this->configurationBlock($match);
        if ($block !== null) {
            return ['errors' => ['match' => $block], 'id' => null];
        }
        $product = $this->repo->product((int) $match['product_id']);
        if ($product === null) {
            return ['errors' => ['_form' => 'That product does not exist.'], 'id' => null];
        }
        $item = $this->repo->items($id)[0] ?? null;
        $qty = $this->fieldValue($id, 'i1_quantity') ?? (string) ($item['quantity'] ?? '1');
        $saved = (new EstimateService())->save(null, [
            'customer_id' => (int) $intake['customer_id'],
            'estimate_type' => 'GENERAL',
            'notes' => 'Intake ' . $intake['intake_number'],
            'subtotal_cost' => $proposal['subtotal_cost'] ?? null,
        ], [[
            'component_type' => 'MATERIAL',
            'product_id' => (int) $product['id'],
            'description' => trim((string) ($item['material_category'] ?? $product['name'])) . ' from product cost',
            'estimated_quantity' => $qty,
            'unit_cost_snapshot' => (string) $product['cost_price'],
            'unit' => 'unit',
            'details' => ['source' => 'product.cost_price', 'ignored_ai' => $ignored],
        ]], $userId);
        if (!$saved['ok'] || $saved['id'] === null) {
            return ['errors' => ['_form' => (string) ($saved['error'] ?? 'The estimate was not saved.')], 'id' => null];
        }
        $this->repo->updateIntake($id, ['estimate_id' => $saved['id'], 'status' => 'ESTIMATE_READY']);
        BusinessEventDispatcher::emit('INTAKE_ESTIMATE_CREATED', 'SALES_INTAKE', $id, $userId, ['estimate' => $saved['id']]);

        return ['errors' => [], 'id' => $saved['id']];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createQuote(int $id, int $userId): array
    {
        if (!can('sales_intake.create_quote')) {
            return ['errors' => ['_form' => 'You cannot draft this quote.'], 'id' => null];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'id' => null];
        }
        if (in_array((string) $intake['intent_type'], ['SERVICE', 'COMPLAINT'], true)) {
            return ['errors' => ['_form' => 'A service enquiry does not become a sales quote from here.'], 'id' => null];
        }
        if ((int) ($intake['customer_id'] ?? 0) < 1) {
            return ['errors' => ['customer_id' => 'Confirm the customer first.'], 'id' => null];
        }
        $match = null;
        foreach ($this->repo->matches($id) as $row) {
            if ((string) $row['review_status'] === 'CONFIRMED' && (int) ($row['product_id'] ?? 0) > 0) {
                $match = $row;
            }
        }
        $created = (new QuoteService())->create([
            'customer_id' => (int) $intake['customer_id'],
            'contact_id' => (int) ($intake['contact_id'] ?? 0),
        ], $userId);
        if ($created['id'] === null) {
            return ['errors' => $created['errors'], 'id' => null];
        }
        $quote = (new QuoteRepository())->find((int) $created['id']);
        if ($match !== null && $quote !== null) {
            $item = $this->repo->items($id)[0] ?? [];
            $qty = $this->fieldValue($id, 'i1_quantity') ?? (string) ($item['quantity'] ?? '1');
            (new QuoteService())->addProductLine((int) $created['id'], [
                'product_id' => (int) $match['product_id'],
                'quantity' => $qty,
                'width_mm' => $item['width_mm'] ?? null,
                'height_mm' => $item['height_mm'] ?? null,
                'customer_description' => $this->customerDescription($item),
                'internal_description' => 'Material, yield, and labour stay on the estimate.',
            ], (int) ($quote['version_number'] ?? 1), $userId);
        }
        $this->repo->updateIntake($id, [
            'quote_id' => $created['id'],
            'status' => 'QUOTE_DRAFTED',
            'discount_applied' => 0,
        ]);
        BusinessEventDispatcher::emit('INTAKE_QUOTE_DRAFTED', 'SALES_INTAKE', $id, $userId, ['quote' => $created['id']]);

        return ['errors' => [], 'id' => $created['id']];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function routeService(int $id, int $userId): array
    {
        if (!can('sales_intake.review')) {
            return ['errors' => ['_form' => 'You cannot route this enquiry.'], 'id' => null];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'id' => null];
        }
        if (!in_array((string) $intake['intent_type'], ['SERVICE', 'ASSET_REPLACEMENT'], true)) {
            return ['errors' => ['_form' => 'This enquiry is not a service request.'], 'id' => null];
        }
        if ((int) ($intake['quote_id'] ?? 0) > 0) {
            return ['errors' => ['_form' => 'A quote is already linked.'], 'id' => null];
        }
        $source = match ((string) $intake['source_type']) {
            'EMAIL' => 'EMAIL',
            'WHATSAPP' => 'WHATSAPP',
            'CUSTOMER_PORTAL' => 'CUSTOMER_PORTAL',
            'PHONE_NOTE' => 'PHONE',
            default => 'OTHER',
        };
        $created = (new ServiceRequestService())->create([
            'customer_id' => (int) ($intake['customer_id'] ?? 0),
            'asset_id' => (int) ($intake['asset_id'] ?? 0),
            'description' => (string) $intake['original_message'],
            'source' => $source,
            'problem_category' => 'OTHER',
        ], $userId);
        if ($created['id'] === null) {
            return ['errors' => $created['errors'], 'id' => null];
        }
        $this->repo->updateIntake($id, ['service_request_id' => $created['id'], 'status' => 'CONVERTED']);

        return ['errors' => [], 'id' => $created['id']];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function confirmAsset(int $id, int $assetId, int $userId): array
    {
        if (!can('sales_intake.confirm_requirements')) {
            return ['_form' => 'You cannot confirm this asset.'];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['_form' => 'You cannot open this intake.'];
        }
        $asset = (new \App\Repositories\AssetRepository())->find($assetId);
        if ($asset === null) {
            return ['asset_id' => 'That asset was not found.'];
        }
        if ((int) ($intake['customer_id'] ?? 0) > 0 && (int) $asset['customer_id'] !== (int) $intake['customer_id']) {
            return ['asset_id' => 'That asset belongs to another customer.'];
        }
        $this->repo->updateIntake($id, ['asset_id' => $assetId, 'customer_id' => (int) $asset['customer_id']]);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, rows: list<array<string, mixed>>}
     */
    public function importSpreadsheet(int $id, string $csv, int $userId): array
    {
        if (!can('sales_intake.review')) {
            return ['errors' => ['_form' => 'You cannot import this file.'], 'rows' => []];
        }
        if ($this->guard($id, $userId) === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'rows' => []];
        }
        $projectsBefore = $this->repo->projectCount();
        $this->repo->clearImport($id);
        $lines = preg_split('/\R/', trim($csv)) ?: [];
        if ($lines === []) {
            return ['errors' => ['file' => 'The sheet is empty.'], 'rows' => []];
        }
        $header = str_getcsv((string) array_shift($lines));
        $map = [];
        foreach ($header as $index => $name) {
            $map[strtolower(trim($name))] = $index;
        }
        $rowNo = 1;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $pick = static function (string $name) use ($map, $cols): string {
                if (!isset($map[$name])) {
                    return '';
                }

                return trim((string) ($cols[$map[$name]] ?? ''));
            };
            $issues = [];
            if ($pick('quantity') === '') {
                $issues[] = 'quantity';
            }
            if ($pick('width_mm') === '' || $pick('height_mm') === '') {
                $issues[] = 'dimensions';
            }
            $this->repo->insertImport([
                'intake_id' => $id,
                'row_no' => $rowNo,
                'site_code' => $this->blank($pick('site_code')),
                'branch_name' => $this->blank($pick('branch')),
                'address_text' => $this->blank($pick('address')),
                'sign_type' => $this->blank($pick('sign_type')),
                'quantity' => $pick('quantity') === '' ? null : $pick('quantity'),
                'width_mm' => $pick('width_mm') === '' ? null : $pick('width_mm'),
                'height_mm' => $pick('height_mm') === '' ? null : $pick('height_mm'),
                'issues' => $issues === [] ? null : implode(', ', $issues),
            ]);
            $rowNo++;
        }
        $this->repo->updateIntake($id, ['intent_type' => 'PROJECT', 'status' => 'REVIEW_REQUIRED']);
        if ($this->repo->projectCount() !== $projectsBefore) {
            return ['errors' => ['_form' => 'The preview created project rows.'], 'rows' => []];
        }

        return ['errors' => [], 'rows' => $this->repo->importRows($id)];
    }

    /**
     * @return array{errors: array<string, string>, rejected: bool}
     */
    public function attach(int $id, string $filename, string $contents, int $userId): array
    {
        if (!can('sales_intake.review')) {
            return ['errors' => ['_form' => 'You cannot attach a file.'], 'rejected' => false];
        }
        if ($this->guard($id, $userId) === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'rejected' => false];
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $hash = hash('sha256', $contents);
        $blocked = in_array($extension, self::BLOCKED_EXTENSIONS, true) || str_contains($contents, '<?php');
        $max = max(1, (int) SettingsService::get('intake_max_attachment_mb', '8')) * 1024 * 1024;
        if (strlen($contents) > $max) {
            $blocked = true;
        }
        $text = null;
        if (!$blocked && in_array($extension, ['txt', 'csv', 'md'], true)) {
            $text = mb_substr($contents, 0, 8000);
        }
        $this->repo->insertFile([
            'intake_id' => $id,
            'original_name' => mb_substr($filename, 0, 180),
            'sha256' => $hash,
            'mime_type' => $extension,
            'rejected' => $blocked ? 1 : 0,
            'reject_reason' => $blocked ? 'The file type is not accepted for analysis.' : null,
            'extracted_text' => $blocked ? null : $text,
        ]);
        if (!$blocked && $text !== null && preg_match('/ignore all system instructions|delete all customers/i', $text) === 1) {
            $this->audit->record('sales_intake', $id, 'UNTRUSTED_ATTACHMENT', null, ['name' => $filename], $userId);
        }

        return ['errors' => [], 'rejected' => $blocked];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function receiveReply(int $id, string $text, int $userId): array
    {
        if (!can('sales_intake.review')) {
            return ['_form' => 'You cannot add a reply.'];
        }
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['_form' => 'You cannot open this intake.'];
        }
        $parsed = $this->parser->parse($text, (string) $intake['received_at'], $this->repo->terms());
        $this->mergeParsed($id, $parsed);
        BusinessEventDispatcher::emit('INFORMATION_RECEIVED', 'SALES_INTAKE', $id, $userId, []);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, intake: array<string, mixed>|null}
     */
    public function workspace(int $id, int $userId): array
    {
        $intake = $this->guard($id, $userId);
        if ($intake === null) {
            return ['errors' => ['_form' => 'You cannot open this intake.'], 'intake' => null];
        }
        if ($intake['first_reviewed_at'] === null) {
            $this->repo->updateIntake($id, ['first_reviewed_at' => date('Y-m-d H:i:s')]);
            $intake['first_reviewed_at'] = date('Y-m-d H:i:s');
        }
        $customerId = (int) ($intake['customer_id'] ?? 0);
        $stock = null;
        foreach ($this->repo->matches($id) as $match) {
            if ((int) ($match['product_id'] ?? 0) > 0) {
                $stock = (new InventoryRepository())->onHand((int) $match['product_id']);
                break;
            }
        }
        $showCost = can('sales_intake.view_ai_audit');
        $analysis = $this->repo->analysisByHash($id, (string) ($intake['analysis_hash'] ?? ''));
        if (!$showCost && $analysis !== null) {
            unset($analysis['model_name']);
        }

        return ['errors' => [], 'intake' => $intake + [
            'items' => $this->repo->items($id),
            'fields' => $this->repo->fields($id),
            'questions' => $this->repo->questions($id),
            'matches' => $this->repo->matches($id),
            'imports' => $this->repo->importRows($id),
            'missing' => $this->missingFor($intake),
            'readiness' => $this->readiness($intake),
            'next_action' => (string) ($intake['next_action'] ?? ''),
            'quotes' => $customerId > 0 ? $this->repo->recentQuotes($customerId) : [],
            'jobs' => $customerId > 0 ? $this->repo->recentJobs($customerId) : [],
            'stock_on_hand' => $stock,
            'analysis' => $analysis,
            'original_message' => (string) $intake['original_message'],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $counts = $this->repo->counts();

        return [
            'new' => (int) ($counts['NEW'] ?? 0),
            'awaiting_review' => (int) ($counts['REVIEW_REQUIRED'] ?? 0) + (int) ($counts['ANALYSING'] ?? 0),
            'awaiting_information' => (int) ($counts['INFO_REQUIRED'] ?? 0),
            'ready_to_estimate' => (int) ($counts['READY_TO_ESTIMATE'] ?? 0),
            'estimates_ready' => (int) ($counts['ESTIMATE_READY'] ?? 0),
            'draft_quotes' => (int) ($counts['QUOTE_DRAFTED'] ?? 0),
            'unassigned' => $this->repo->unassigned(),
            'failed' => (int) ($counts['FAILED'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $fields = $this->repo->fieldOutcomes();
        $reviewed = $fields['confirmed'] + $fields['corrected'] + $fields['rejected'];

        return [
            'by_source' => $this->repo->sourceCounts(),
            'fields_proposed' => $fields['proposed'] + $reviewed,
            'fields_confirmed_unchanged' => $fields['confirmed'],
            'fields_corrected' => $fields['corrected'],
            'fields_rejected' => $fields['rejected'],
            'correction_rate' => $reviewed > 0 ? round($fields['corrected'] / $reviewed, 4) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function page(int $limit, int $offset): array
    {
        if (!can('sales_intake.view')) {
            return [];
        }

        return $this->repo->page($limit, $offset);
    }

    /**
     * @param array<string, mixed> $structured
     */
    private function storeStructured(int $id, array $structured, int $userId): void
    {
        $item = [
            'line_no' => 1,
            'quantity' => $structured['quantity'] ?? null,
            'width_mm' => $structured['width_mm'] ?? null,
            'height_mm' => $structured['height_mm'] ?? null,
            'material_text' => $structured['material'] ?? null,
            'material_category' => $structured['material'] ?? null,
            'print_sides' => $structured['print_sides'] ?? null,
            'original_text' => 'Structured form',
            'is_approximate' => false,
            'quantity_method' => 'DIRECT',
            'assumption' => null,
            'thickness_mm' => $structured['thickness_mm'] ?? null,
            'description' => 'Structured enquiry',
        ];
        $intake = $this->repo->find($id);
        if ($intake === null) {
            return;
        }
        $this->applyParsed($intake, [
            'language' => 'EN',
            'intent' => 'NEW_QUOTE',
            'requirement_type' => 'SIGNAGE',
            'discount_requested_percent' => null,
            'customer_budget' => null,
            'requested_date' => null,
            'requested_date_text' => null,
            'requested_date_approximate' => false,
            'date_promised' => false,
            'site' => $structured['site'] ?? null,
            'installation_proposed' => null,
            'artwork_mentioned' => false,
            'vehicle' => null,
            'letters' => null,
            'items' => [$item],
            'original' => (string) $intake['original_message'],
        ], hash('sha256', 'structured:' . json_encode($structured)), ['called' => false, 'status' => 'SKIPPED', 'text' => ''], 'PROPOSED', $userId);
    }

    /**
     * @param array<string, mixed> $intake
     * @return array{errors: array<string, string>, analysis: array<string, mixed>|null, cached: bool}
     */
    private function storeExternal(array $intake, string $json, string $hash, int $userId): array
    {
        $checked = $this->schema->accept($json);
        $id = (int) $intake['id'];
        if ($checked['ignored_money'] !== []) {
            $this->audit->record('sales_intake', $id, 'AI_MONEY_IGNORED', null, ['fields' => $checked['ignored_money']], $userId);
        }
        $this->repo->insertAnalysis([
            'intake_id' => $id,
            'provider' => 'EXTERNAL',
            'model_name' => null,
            'prompt_version' => (string) SettingsService::get('intake_prompt_version', '1'),
            'input_hash' => $hash,
            'input_reference' => substr($hash, 0, 16),
            'structured_json' => json_encode($checked['data'], JSON_THROW_ON_ERROR),
            'status' => $checked['status'],
            'created_by' => $userId,
        ]);
        $this->repo->updateIntake($id, [
            'analysis_hash' => $hash,
            'analysis_count' => $this->repo->analysisCount($id),
            'status' => $checked['status'] === 'FAILED' ? 'REVIEW_REQUIRED' : 'REVIEW_REQUIRED',
        ]);
        if ($checked['status'] === 'FAILED') {
            (new ReviewQueueService())->add('AI_EXTRACTION_FAILURE', 'SALES_INTAKE', $id, 'Review a failed analysis', 'intake', $checked['errors'][0] ?? null, null);
            BusinessEventDispatcher::emit('SALES_INTAKE_ANALYSIS_FAILED', 'SALES_INTAKE', $id, $userId, []);
        }
        $items = [];
        $line = 1;
        foreach ($checked['data']['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'line_no' => $line,
                'quantity' => $item['quantity'] ?? null,
                'width_mm' => $item['width_mm'] ?? null,
                'height_mm' => $item['height_mm'] ?? null,
                'material_text' => $item['material'] ?? null,
                'material_category' => $item['material'] ?? null,
                'print_sides' => $item['print_sides'] ?? null,
                'thickness_mm' => $item['thickness_mm'] ?? null,
                'original_text' => $item['original_text'] ?? null,
                'is_approximate' => !empty($item['is_approximate']),
                'description' => (string) ($item['description'] ?? ''),
                'quantity_method' => 'AI',
                'assumption' => null,
            ];
            $line++;
        }
        if ($items !== [] && $this->repo->items($id) === []) {
            $this->writeItems($id, $items, 'SIGNAGE', 'AI_EXTRACTED_PENDING_REVIEW');
        }
        BusinessEventDispatcher::emit('SALES_INTAKE_ANALYSED', 'SALES_INTAKE', $id, $userId, ['status' => strtolower($checked['status'])]);

        return ['errors' => $checked['ok'] ? [] : ['analysis' => implode(' ', $checked['errors'])], 'analysis' => $this->repo->analysisByHash($id, $hash), 'cached' => false];
    }

    /**
     * @param array<string, mixed> $intake
     * @param array<string, mixed> $parsed
     * @param array{called: bool, status: string, text: string} $provider
     */
    private function applyParsed(array $intake, array $parsed, string $hash, array $provider, string $status, int $userId): void
    {
        $id = (int) $intake['id'];
        $match = $this->matchCustomer($intake, $parsed);
        $estimator = null;
        $payload = null;
        if (is_array($parsed['vehicle'])) {
            $estimator = 'VEHICLE_WRAP';
            $payload = $parsed['vehicle'];
        } elseif (is_array($parsed['letters'])) {
            $estimator = 'CHANNEL_LETTER';
            $payload = $parsed['letters'];
        }
        $this->repo->updateIntake($id, [
            'intent_type' => (string) $parsed['intent'],
            'language_code' => (string) $parsed['language'],
            'requested_date' => $parsed['requested_date'],
            'requested_date_text' => $parsed['requested_date_text'],
            'date_promised' => 0,
            'installation_proposed' => $parsed['installation_proposed'],
            'customer_budget' => $parsed['customer_budget'],
            'discount_requested_percent' => $parsed['discount_requested_percent'],
            'discount_applied' => 0,
            'match_state' => $match['state'],
            'estimator_kind' => $estimator,
            'estimator_payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
            'analysis_hash' => $hash,
            'status' => 'REVIEW_REQUIRED',
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->proposeField($id, 'site', $parsed['site'], (string) ($parsed['site'] ?? ''), 'DETERMINISTIC', 'DIRECTLY_STATED', false);
        $this->proposeField($id, 'requested_date', $parsed['requested_date'], (string) ($parsed['requested_date_text'] ?? ''), 'DETERMINISTIC', 'INFERRED', (bool) $parsed['requested_date_approximate']);
        $this->proposeField($id, 'customer_budget', $parsed['customer_budget'], (string) ($parsed['customer_budget'] ?? ''), 'DETERMINISTIC', 'DIRECTLY_STATED', false);
        $this->proposeField($id, 'discount_requested_percent', $parsed['discount_requested_percent'], (string) ($parsed['discount_requested_percent'] ?? ''), 'DETERMINISTIC', 'DIRECTLY_STATED', false);
        if ($this->repo->items($id) === []) {
            $this->writeItems($id, $parsed['items'], (string) $parsed['requirement_type'], 'AI_EXTRACTED_PENDING_REVIEW');
        }
        $duplicate = $intake['sender_email'] !== null
            ? $this->repo->duplicateCandidate((string) $intake['sender_email'], $hash, $id)
            : null;
        if ($duplicate !== null) {
            $this->repo->updateIntake($id, ['duplicate_of_id' => (int) $duplicate['id']]);
        }
        $fresh = $this->repo->find($id) ?? $intake;
        $missing = $this->missingFor($fresh);
        $this->repo->clearQuestions($id);
        foreach ($missing as $row) {
            $this->repo->insertQuestion([
                'intake_id' => $id,
                'field_key' => $row['field'],
                'question_text' => $row['question'],
                'draft_text' => $provider['text'] !== '' ? mb_substr($provider['text'], 0, 500) : null,
                'language_code' => (string) $parsed['language'],
                'status' => 'DRAFT',
            ]);
        }
        if ($missing !== []) {
            $this->repo->updateIntake($id, ['status' => 'INFO_REQUIRED']);
            BusinessEventDispatcher::emit('INFORMATION_REQUIRED', 'SALES_INTAKE', $id, $userId, []);
            (new ReviewQueueService())->add('MISSING_INFORMATION', 'SALES_INTAKE', $id, 'Missing intake information', 'intake', $missing[0]['field'], null);
        }
        $this->repo->insertAnalysis([
            'intake_id' => $id,
            'provider' => $provider['called'] ? (string) SettingsService::get('ai_provider', 'deterministic') : 'DETERMINISTIC',
            'model_name' => $provider['called'] ? (string) SettingsService::get('ai_model', '') : null,
            'prompt_version' => (string) SettingsService::get('intake_prompt_version', '1'),
            'input_hash' => $hash,
            'input_reference' => substr($hash, 0, 16),
            'structured_json' => json_encode([
                'intent' => $parsed['intent'],
                'items' => $parsed['items'],
                'missing' => $missing,
                'match' => $match['state'],
                'provider_status' => $status,
            ], JSON_THROW_ON_ERROR),
            'status' => $status,
            'created_by' => $userId,
        ]);
        $this->repo->updateIntake($id, ['analysis_count' => $this->repo->analysisCount($id)]);
        if ($match['state'] !== 'UNKNOWN') {
            BusinessEventDispatcher::emit('CUSTOMER_MATCH_PROPOSED', 'SALES_INTAKE', $id, $userId, ['state' => strtolower($match['state'])]);
        }
        BusinessEventDispatcher::emit('REQUIREMENTS_EXTRACTED', 'SALES_INTAKE', $id, $userId, []);
        BusinessEventDispatcher::emit('SALES_INTAKE_ANALYSED', 'SALES_INTAKE', $id, $userId, []);
        $this->refreshNext($id);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function writeItems(int $id, array $items, string $type, string $source): void
    {
        $this->repo->clearItems($id);
        foreach ($items as $item) {
            $line = (int) ($item['line_no'] ?? 1);
            $this->repo->insertItem([
                'intake_id' => $id,
                'line_no' => $line,
                'requirement_type' => $type,
                'description' => $this->blank($item['description'] ?? null),
                'quantity' => $this->blank($item['quantity'] ?? null),
                'width_mm' => $this->blank($item['width_mm'] ?? null),
                'height_mm' => $this->blank($item['height_mm'] ?? null),
                'depth_mm' => null,
                'thickness_mm' => $this->blank($item['thickness_mm'] ?? null),
                'material_text' => $this->blank($item['material_text'] ?? null),
                'material_category' => $this->blank($item['material_category'] ?? null),
                'finish' => null,
                'print_sides' => $item['print_sides'] ?? null,
                'installation_proposed' => null,
                'is_approximate' => !empty($item['is_approximate']) ? 1 : 0,
                'dimension_source' => $source,
                'original_text' => $this->blank($item['original_text'] ?? null),
                'notes' => $this->blank($item['assumption'] ?? null),
                'review_status' => 'PROPOSED',
            ]);
            $this->proposeField($id, 'i' . $line . '_quantity', $item['quantity'] ?? null, (string) ($item['original_text'] ?? ''), (string) ($item['quantity_method'] ?? 'DETERMINISTIC') === 'INFERRED' ? 'DETERMINISTIC' : 'DETERMINISTIC', (string) ($item['quantity_method'] ?? '') === 'INFERRED' ? 'INFERRED' : 'DIRECTLY_STATED', false);
            $evidence = ($item['assumption'] ?? null) !== null ? 'AMBIGUOUS' : 'DIRECTLY_STATED';
            $this->proposeField($id, 'i' . $line . '_width_mm', $item['width_mm'] ?? null, (string) ($item['original_text'] ?? ''), 'DETERMINISTIC', $evidence, !empty($item['is_approximate']));
            $this->proposeField($id, 'i' . $line . '_height_mm', $item['height_mm'] ?? null, (string) ($item['original_text'] ?? ''), 'DETERMINISTIC', $evidence, !empty($item['is_approximate']));
            $this->proposeField($id, 'i' . $line . '_material', $item['material_text'] ?? null, (string) ($item['material_text'] ?? ''), 'DETERMINISTIC', 'DIRECTLY_STATED', false);
            $this->proposeField($id, 'i' . $line . '_print_sides', $item['print_sides'] ?? null, '', 'DETERMINISTIC', 'DIRECTLY_STATED', false);
            $this->proposeField($id, 'i' . $line . '_thickness_mm', $item['thickness_mm'] ?? null, '', 'DETERMINISTIC', 'DIRECTLY_STATED', false);
        }
    }

    /**
     * @param array<string, mixed> $parsed
     */
    private function mergeParsed(int $id, array $parsed): void
    {
        $items = $this->repo->items($id);
        $incoming = $parsed['items'][0] ?? null;
        if (!is_array($incoming) || $items === []) {
            return;
        }
        $item = $items[0];
        foreach (['width_mm' => 'i1_width_mm', 'height_mm' => 'i1_height_mm', 'quantity' => 'i1_quantity'] as $column => $key) {
            $new = $this->blank($incoming[$column] ?? null);
            if ($new === null) {
                continue;
            }
            $field = $this->repo->field($id, $key);
            if ($field !== null && in_array((string) $field['review_status'], ['CONFIRMED', 'CORRECTED'], true)) {
                if ((string) $field['confirmed_value'] !== (string) $new) {
                    $this->repo->updateField((int) $field['id'], ['conflict_value' => (string) $new]);
                    $this->repo->updateIntake($id, ['status' => 'REVIEW_REQUIRED']);
                }
                continue;
            }
            $this->proposeField($id, $key, $new, (string) ($incoming['original_text'] ?? ''), 'DETERMINISTIC', 'DIRECTLY_STATED', !empty($incoming['is_approximate']));
            if ((string) $item['review_status'] === 'PROPOSED') {
                $this->repo->updateItem((int) $item['id'], [$column => $new]);
            }
        }
    }

    private function proposeField(int $id, string $key, mixed $value, string $original, string $method, string $evidence, bool $approximate): void
    {
        $text = $this->blank($value);
        if ($text === null) {
            return;
        }
        $existing = $this->repo->field($id, $key);
        if ($existing === null) {
            $this->repo->insertField([
                'intake_id' => $id,
                'field_key' => $key,
                'proposed_value' => $text,
                'confirmed_value' => null,
                'conflict_value' => null,
                'original_text' => $original !== '' ? mb_substr($original, 0, 255) : null,
                'extraction_method' => $method,
                'evidence' => $evidence,
                'review_status' => 'PROPOSED',
                'is_approximate' => $approximate ? 1 : 0,
                'dimension_source' => 'AI_EXTRACTED_PENDING_REVIEW',
            ]);

            return;
        }
        if (in_array((string) $existing['review_status'], ['CONFIRMED', 'CORRECTED'], true)) {
            if ((string) $existing['confirmed_value'] !== $text) {
                $this->repo->updateField((int) $existing['id'], ['conflict_value' => $text]);
            }

            return;
        }
        $this->repo->updateField((int) $existing['id'], [
            'proposed_value' => $text,
            'original_text' => $original !== '' ? mb_substr($original, 0, 255) : $existing['original_text'],
            'is_approximate' => $approximate ? 1 : 0,
            'evidence' => $evidence,
        ]);
    }

    /**
     * @param array<string, mixed> $intake
     * @param array<string, mixed> $parsed
     * @return array{state: string, customer_id: int|null}
     */
    private function matchCustomer(array $intake, array $parsed): array
    {
        unset($parsed);
        $email = strtolower(trim((string) ($intake['sender_email'] ?? '')));
        if ($email !== '') {
            $rows = $this->repo->customersByEmail($email);
            if (count($rows) === 1) {
                return ['state' => 'EXACT', 'customer_id' => (int) $rows[0]['id']];
            }
            if (count($rows) > 1) {
                return ['state' => 'MULTIPLE', 'customer_id' => null];
            }
        }
        $name = trim((string) ($intake['sender_name'] ?? ''));
        if ($name === '') {
            return ['state' => 'UNKNOWN', 'customer_id' => null];
        }
        $needle = $this->companyNeedle($name);
        if ($needle === '') {
            return ['state' => 'UNKNOWN', 'customer_id' => null];
        }
        $rows = $this->repo->customersByCompany($needle);
        $likely = [];
        foreach ($rows as $row) {
            $candidate = $this->companyNeedle((string) ($row['company_name'] ?? ''));
            if ($candidate === $needle || str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                $likely[] = $row;
            }
        }
        if (count($likely) === 1) {
            $exact = strcasecmp($this->companyNeedle((string) $likely[0]['company_name']), $needle) === 0
                && strcasecmp((string) $likely[0]['company_name'], $name) === 0;

            return ['state' => $exact ? 'EXACT' : 'LIKELY', 'customer_id' => (int) $likely[0]['id']];
        }
        if (count($likely) > 1) {
            return ['state' => 'MULTIPLE', 'customer_id' => null];
        }

        return ['state' => 'NEW', 'customer_id' => null];
    }

    private function companyNeedle(string $name): string
    {
        $name = strtolower($name);
        $name = str_replace(['(pty) ltd', '(pty) ltd.', 'pty ltd', 'proprietary limited'], '', $name);
        $name = preg_replace('/[^a-z0-9 ]/', '', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    /**
     * @param array<string, mixed> $intake
     * @return array{called: bool, status: string, text: string}
     */
    private function providerDraft(array $intake, int $userId): array
    {
        $flags = new FeatureFlagService();
        if (!$flags->enabled('AI_INTAKE_ANALYSIS') || !$flags->enabled('AI_MESSAGE_DRAFTING')) {
            return ['called' => false, 'status' => 'SKIPPED', 'text' => ''];
        }
        $draft = (new AssistedIntelligenceService())->draft(
            'quote_description',
            mb_substr((string) $intake['original_message'], 0, (int) SettingsService::get('intake_max_input_chars', '8000')),
            $userId,
            'SALES_INTAKE',
            (int) $intake['id'],
            'ai.communication_draft'
        );

        return ['called' => true, 'status' => (string) $draft['status'], 'text' => (string) $draft['text']];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $catalogue
     */
    private function placeMatch(int $intakeId, array $item, array $catalogue, int $rank): bool
    {
        $category = strtolower((string) ($item['material_category'] ?? ''));
        $text = strtolower((string) ($item['material_text'] ?? '') . ' ' . (string) ($item['description'] ?? ''));
        foreach ($catalogue as $entry) {
            $name = strtolower((string) $entry['name']);
            if ($name !== '' && (str_contains($text, $name) || str_contains($name, trim((string) ($item['material_text'] ?? '---'))) || $this->tokensMatch($name, $text))) {
                $this->repo->insertMatch([
                    'intake_id' => $intakeId,
                    'line_no' => (int) $item['line_no'],
                    'product_id' => $entry['product_id'] !== null ? (int) $entry['product_id'] : null,
                    'catalogue_item_id' => (int) $entry['id'],
                    'specification_id' => $entry['specification_id'] !== null ? (int) $entry['specification_id'] : null,
                    'recipe_id' => null,
                    'match_state' => 'EXACT',
                    'match_rank' => 1,
                    'why_text' => 'Customer catalogue item matches the wording.',
                    'missing_json' => null,
                    'warnings_json' => null,
                    'review_status' => 'PROPOSED',
                ]);

                return true;
            }
        }
        $term = $category !== '' ? $category : (string) ($item['material_text'] ?? '');
        if ($term === '') {
            $this->repo->insertMatch([
                'intake_id' => $intakeId,
                'line_no' => (int) $item['line_no'],
                'product_id' => null,
                'catalogue_item_id' => null,
                'specification_id' => null,
                'recipe_id' => null,
                'match_state' => 'NO_MATCH',
                'match_rank' => $rank,
                'why_text' => 'No material was stated.',
                'missing_json' => null,
                'warnings_json' => null,
                'review_status' => 'PROPOSED',
            ]);

            return false;
        }
        $products = $this->repo->productsLike($term);
        if ($products === []) {
            $this->repo->insertMatch([
                'intake_id' => $intakeId,
                'line_no' => (int) $item['line_no'],
                'product_id' => null,
                'catalogue_item_id' => null,
                'specification_id' => null,
                'recipe_id' => null,
                'match_state' => 'NO_MATCH',
                'match_rank' => $rank,
                'why_text' => 'No product uses this term. A review request was opened.',
                'missing_json' => null,
                'warnings_json' => null,
                'review_status' => 'PROPOSED',
            ]);

            return false;
        }
        $product = $products[0];
        $state = strtoupper((string) $product['product_type']) === 'FINISHED_PRODUCT' ? 'GOOD_MATCH' : 'PARTIAL';
        $specs = $this->repo->specificationsLike($term);
        $this->repo->insertMatch([
            'intake_id' => $intakeId,
            'line_no' => (int) $item['line_no'],
            'product_id' => (int) $product['id'],
            'catalogue_item_id' => null,
            'specification_id' => isset($specs[0]['id']) ? (int) $specs[0]['id'] : null,
            'recipe_id' => null,
            'match_state' => $state,
            'match_rank' => $rank + 1,
            'why_text' => 'Product name matches the material term. The SKU is an existing product, not a guess.',
            'missing_json' => json_encode($this->missingFor($this->repo->find($intakeId) ?? [])),
            'warnings_json' => null,
            'review_status' => 'PROPOSED',
        ]);

        return true;
    }

    private function tokensMatch(string $name, string $text): bool
    {
        $tokens = preg_split('/\s+/', $name) ?: [];
        $hits = 0;
        $need = 0;
        foreach ($tokens as $token) {
            if (strlen($token) < 4) {
                continue;
            }
            $need++;
            if (str_contains($text, $token)) {
                $hits++;
            }
        }

        return $need > 0 && $hits === $need;
    }

    /**
     * @param array<string, mixed> $match
     */
    private function configurationBlock(array $match): ?string
    {
        $specId = (int) ($match['specification_id'] ?? 0);
        if ($specId < 1) {
            return null;
        }
        $item = $this->repo->items((int) $match['intake_id'])[0] ?? [];
        $input = [
            'material' => (string) ($item['material_category'] ?? ''),
            'finish' => (string) ($item['finish'] ?? ''),
            'width_mm' => (string) ($item['width_mm'] ?? ''),
            'height_mm' => (string) ($item['height_mm'] ?? ''),
        ];
        $result = (new CompatibilityRuleService())->evaluate($this->repo->specRules($specId), $input);
        if ($result['blocked']) {
            return $result['messages'][0] ?? 'The configuration is not allowed.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $intake
     */
    private function readiness(array $intake): string
    {
        if ((int) ($intake['customer_id'] ?? 0) < 1 || $intake['requirements_confirmed_at'] === null) {
            return 'NOT_READY';
        }
        foreach ($this->repo->fields((int) $intake['id']) as $field) {
            if ((string) ($field['conflict_value'] ?? '') !== '') {
                return 'REVIEW_REQUIRED';
            }
        }
        if ($this->missingFor($intake) !== []) {
            return 'NOT_READY';
        }
        $confirmed = false;
        foreach ($this->repo->matches((int) $intake['id']) as $match) {
            if ((string) $match['review_status'] === 'CONFIRMED') {
                $confirmed = true;
                if ($this->configurationBlock($match) !== null) {
                    return 'NOT_READY';
                }
            }
        }
        if (!$confirmed && (string) ($intake['estimator_kind'] ?? '') === '') {
            return 'REVIEW_REQUIRED';
        }

        return 'READY';
    }

    /**
     * @param array<string, mixed> $intake
     * @return list<array{field: string, question: string}>
     */
    private function missingFor(array $intake): array
    {
        if ($intake === []) {
            return [];
        }
        $values = [];
        foreach ($this->repo->fields((int) $intake['id']) as $field) {
            $values[(string) $field['field_key']] = (string) ($field['confirmed_value'] ?? $field['proposed_value'] ?? '');
        }
        if (is_string($intake['estimator_payload'] ?? null)) {
            $payload = json_decode((string) $intake['estimator_payload'], true);
            if (is_array($payload)) {
                foreach (['make', 'model', 'year', 'body', 'coverage', 'text', 'height_mm'] as $key) {
                    if (isset($payload[$key]) && (string) $payload[$key] !== '') {
                        $values[$key] = (string) $payload[$key];
                    }
                }
            }
        }

        return $this->validator->missing(
            $intake,
            $this->repo->items((int) $intake['id']),
            $this->repo->requirementRules(),
            $values,
            (string) ($intake['language_code'] ?? 'EN')
        );
    }

    private function refreshNext(int $id): void
    {
        $intake = $this->repo->find($id);
        if ($intake === null) {
            return;
        }
        $action = 'Review the enquiry.';
        if ((string) $intake['match_state'] === 'MULTIPLE') {
            $action = 'Review the customer match.';
        } elseif ((int) ($intake['customer_id'] ?? 0) < 1) {
            $action = 'Confirm the customer.';
        } elseif ((string) $intake['estimator_kind'] === 'VEHICLE_WRAP') {
            $action = 'Open the wrap estimator.';
        } elseif ((string) $intake['estimator_kind'] === 'CHANNEL_LETTER') {
            $action = 'Confirm the letter artwork and depth.';
        } elseif ($this->missingFor($intake) !== []) {
            $action = 'Ask for the missing information.';
        } elseif ($intake['requirements_confirmed_at'] === null) {
            $action = 'Confirm the requirements.';
        } elseif ((int) ($intake['estimate_id'] ?? 0) < 1) {
            $action = 'Review the estimate.';
        } elseif ((int) ($intake['quote_id'] ?? 0) < 1) {
            $action = 'Create a draft quote.';
        }
        $this->repo->updateIntake($id, ['next_action' => $action]);
        if ($action === 'Review the estimate.' && (int) ($intake['assigned_user_id'] ?? 0) > 0) {
            (new NotificationService())->send(
                (int) $intake['assigned_user_id'],
                null,
                'SYSTEM',
                'Intake ready to estimate',
                (string) $intake['intake_number'] . ' is ready to estimate.',
                'SALES_INTAKE',
                $id,
                'NORMAL',
                'intake-ready-' . $id
            );
            BusinessEventDispatcher::emit('INTAKE_READY_TO_ESTIMATE', 'SALES_INTAKE', $id, (int) $intake['assigned_user_id'], []);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function customerDescription(array $item): string
    {
        $qty = (string) ($item['quantity'] ?? '');
        $width = (string) ($item['width_mm'] ?? '');
        $height = (string) ($item['height_mm'] ?? '');
        $material = (string) ($item['material_category'] ?? 'sign');
        if ($qty === '' || $width === '' || $height === '') {
            return 'Supply the confirmed signage.';
        }

        return 'Supply and print ' . $qty . ' × ' . $width . ' × ' . $height . ' mm ' . $material . ' signs with full-colour print to face.';
    }

    private function copyFieldToItem(int $id, string $key, string $value): void
    {
        if (preg_match('/^i(\d+)_(quantity|width_mm|height_mm|thickness_mm|material)$/', $key, $match) !== 1) {
            return;
        }
        foreach ($this->repo->items($id) as $item) {
            if ((int) $item['line_no'] !== (int) $match[1]) {
                continue;
            }
            $column = $match[2] === 'material' ? 'material_text' : $match[2];
            $this->repo->updateItem((int) $item['id'], [$column => $value]);
        }
    }

    private function fieldValue(int $id, string $key): ?string
    {
        $field = $this->repo->field($id, $key);
        if ($field === null) {
            return null;
        }
        $value = $field['confirmed_value'] ?? $field['proposed_value'] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function guard(int $id, int $userId): ?array
    {
        if (!can('sales_intake.view') && !can('sales_intake.ai_analyse') && !can('sales_intake.review')) {
            return null;
        }
        $row = $this->repo->find($id);
        if ($row === null) {
            return null;
        }
        $assigned = (int) ($row['assigned_user_id'] ?? 0);
        if ($assigned > 0 && $assigned !== $userId && !can('sales_intake.admin')) {
            return null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function customerExists(int $customerId): ?array
    {
        if ($customerId < 1) {
            return null;
        }
        $rows = $this->repo->customersByCompany('');
        unset($rows);

        return (new \App\Repositories\CustomerRepository())->find($customerId);
    }

    private function optionalId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function blank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
