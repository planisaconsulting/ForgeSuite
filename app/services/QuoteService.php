<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DiscountType;
use App\Domain\OpportunityStatus;
use App\Domain\QuoteStatus;
use App\Domain\VatMode;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\JobRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\PricingLevelRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;

/**
 * Quotations: create, price, revise, accept, and hand off to a job.
 *
 * Catalogue lines are priced by PricingService. The numbers stored on the
 * line are a snapshot. Opening the quote again does not read the current
 * product cost unless someone confirms Refresh current prices.
 */
final class QuoteService
{
    public function __construct(
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly QuoteTotals $totals = new QuoteTotals(),
        private readonly QuoteLineFactory $lines = new QuoteLineFactory(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly PricingLevelRepository $levels = new PricingLevelRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly ContactRepository $contacts = new ContactRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly OpportunityRepository $opportunities = new OpportunityRepository(),
        private readonly JobRepository $jobs = new JobRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        $errors = $this->validateIdentity($input);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        $header = $this->headerData($input, $userId, true);
        $id = 0;
        Database::transaction(function () use ($header, $userId, &$id): void {
            $header['quote_number'] = $this->numbers->quote();
            $id = $this->quotes->insert($header);
            $this->quotes->insertHistory($id, null, QuoteStatus::Draft->value, $userId, 'Quotation created');
            $this->audit->record('quote', $id, 'QUOTE_CREATED', null, [
                'quote_number' => $header['quote_number'],
                'customer_id' => $header['customer_id'],
            ], $userId);
            $this->markOpportunityQuoted($header['opportunity_id']);
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function save(int $id, array $input, int $version, int $userId, bool $audit = true): array
    {
        try {
            Database::transaction(function () use ($id, $input, $version, $userId, $audit): void {
                $quote = $this->lockedDraft($id, $version);
                $requestedDiscount = DiscountType::normalise((string) ($input['discount_type'] ?? 'NONE'));
                $this->assertDiscount($requestedDiscount, 'NONE');
                $header = $this->headerData($input, $userId, false, $quote);
                $items = $this->quotes->items($id);
                $probe = array_merge($quote, $header);
                $summary = $this->totals->summarise($items, $probe);
                $this->assertNotBelowCost($summary);
                $this->quotes->updateHeader($id, $header);
                $this->quotes->updateTotals($id, $summary);
                if ($audit) {
                    $this->audit->record('quote', $id, 'QUOTE_EDITED', null, [
                        'quote_number' => $quote['quote_number'],
                    ], $userId);
                }
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, id: int|null}
     */
    public function addProductLine(int $quoteId, array $input, int $version, int $userId): array
    {
        $product = $this->products->find((int) ($input['product_id'] ?? 0));
        if ($product === null || (int) $product['active'] !== 1) {
            return ['errors' => ['Choose an active product.'], 'id' => null];
        }

        return $this->storeLine($quoteId, $version, $userId, function (array $quote) use ($product, $input, $userId): array {
            $built = $this->lines->fromProduct($product, $input, $this->level($quote), $userId);

            return $this->guardLine($built, $input);
        }, 'Line added');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, id: int|null}
     */
    public function addCustomLine(int $quoteId, array $input, int $version, int $userId): array
    {
        return $this->storeLine($quoteId, $version, $userId, function (array $quote) use ($input, $userId): array {
            $built = $this->lines->custom($input, $this->level($quote), $userId);

            return $this->guardLine($built, $input);
        }, 'Custom line added');
    }

    /**
     * Add a configured sign from a recipe. The calculation is stored on the
     * line snapshot so a later recipe edit does not change this quotation.
     *
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, id: int|null}
     */
    public function addRecipeLine(int $quoteId, array $input, int $version, int $userId): array
    {
        $quote = $this->quotes->find($quoteId);
        if ($quote === null) {
            return ['errors' => ['That quotation was not found.'], 'id' => null];
        }
        $recipeId = (int) ($input['recipe_id'] ?? 0);
        $recipes = new \App\Repositories\RecipeRepository();
        $recipe = $recipes->find($recipeId);
        if ($recipe === null || (int) $recipe['active'] !== 1) {
            return ['errors' => ['Choose an active recipe.'], 'id' => null];
        }
        $calc = (new RecipeCalculationService())->calculate(
            $recipe,
            $recipes->inputs($recipeId),
            $recipes->items($recipeId),
            $input,
            $this->level($quote)
        );
        if (!$calc['ok']) {
            return ['errors' => [(string) $calc['error']], 'id' => null];
        }
        $route = (new RecipeService())->route((int) ($recipe['production_route_template_id'] ?? 0));
        $calc['production_route'] = $route;
        $line = $this->recipeQuoteLine($recipe, $calc, $input, $this->level($quote));
        $stored = $this->storeLine($quoteId, $version, $userId, static function () use ($line): array {
            return ['errors' => [], 'line' => $line];
        }, 'Configured sign added');
        if ($stored['id'] !== null) {
            $recipes->insertSnapshot((int) $stored['id'], [
                'recipe_id' => $recipeId,
                'recipe_version' => (int) $recipe['version_number'],
                'inputs' => $calc['inputs'],
                'components' => $calc['components'],
                'cost' => [
                    'total_cost' => $calc['total_cost'],
                    'selling_price' => $calc['selling_price'],
                    'gross_profit' => $calc['gross_profit'],
                    'gross_margin' => $calc['gross_margin'],
                    'markup_percent' => $calc['markup_percent'],
                    'face_area_each' => $calc['face_area_each'],
                    'face_area_total' => $calc['face_area_total'],
                ],
                'route' => $route,
            ]);
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $recipe
     * @param array<string, mixed> $calc
     * @param array<string, mixed> $input
     * @param array<string, mixed> $level
     * @return array<string, mixed>
     */
    private function recipeQuoteLine(array $recipe, array $calc, array $input, array $level): array
    {
        $quantity = (string) ($calc['inputs']['Q'] ?? '1');
        $sell = (string) $calc['selling_price'];
        $cost = (string) $calc['total_cost'];
        $description = trim((string) ($input['customer_description'] ?? ''));
        if ($description === '') {
            $description = (string) $recipe['name'];
            $width = (string) ($calc['inputs']['W'] ?? '');
            $height = (string) ($calc['inputs']['H'] ?? '');
            if ($width !== '' && $height !== '' && $width !== '0' && $height !== '0') {
                $description .= ' ' . Decimal::round($width, 0) . ' x ' . Decimal::round($height, 0) . ' mm';
            }
        }
        $unitCost = Decimal::cmp($quantity, '0') === 0 ? $cost : Decimal::div($cost, $quantity, 4);

        return [
            'product_id' => !empty($recipe['finished_product_id']) ? (int) $recipe['finished_product_id'] : null,
            'is_custom_item' => 0,
            'is_optional' => 0,
            'include_optional' => 0,
            'product_name_snapshot' => (string) $recipe['name'],
            'product_description_snapshot' => blank_to_null($recipe['description'] ?? null),
            'sku_snapshot' => (string) $recipe['code'],
            'product_type_snapshot' => 'FINISHED_PRODUCT',
            'pricing_method_snapshot' => 'RECIPE',
            'width_mm' => $calc['inputs']['W'] ?? null,
            'height_mm' => $calc['inputs']['H'] ?? null,
            'length_mm' => $calc['inputs']['L'] ?? null,
            'quantity' => Decimal::qty($quantity),
            'actual_quantity' => (string) $calc['face_area_total'],
            'billable_quantity' => Decimal::qty($quantity),
            'actual_area' => (string) $calc['face_area_total'],
            'billable_area' => (string) $calc['face_area_total'],
            'waste_area' => '0',
            'waste_mode' => 'ACTUAL',
            'standard_waste_percent_snapshot' => '0',
            'cost_unit_snapshot' => 'unit',
            'unit_cost_snapshot' => Decimal::round($unitCost, 4),
            'base_cost' => $cost,
            'waste_cost' => '0.00',
            'total_cost' => $cost,
            'pricing_level_id' => isset($level['id']) ? (int) $level['id'] : null,
            'markup_percent_snapshot' => Decimal::round((string) ($calc['markup_percent'] ?? '0'), 2),
            'calculated_price' => $sell,
            'final_sell_price' => $sell,
            'unit_sell_price' => Decimal::cmp($quantity, '0') === 0 ? $sell : Decimal::money(Decimal::div($sell, $quantity)),
            'price_overridden' => 0,
            'override_reason' => null,
            'overridden_by' => null,
            'overridden_at' => null,
            'line_discount_type' => 'NONE',
            'line_discount_value' => '0',
            'discount_amount' => '0.00',
            'line_subtotal' => $sell,
            'line_total' => $sell,
            'customer_description' => $description,
            'internal_description' => blank_to_null($input['internal_description'] ?? null),
            'measure_snapshot' => json_encode([
                'recipe_id' => (int) $recipe['id'],
                'recipe_version' => (int) $recipe['version_number'],
                'face_area_each' => $calc['face_area_each'],
                'face_area_total' => $calc['face_area_total'],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, id: int|null}
     */
    public function updateLine(int $quoteId, int $lineId, array $input, int $version, int $userId): array
    {
        return $this->storeLine($quoteId, $version, $userId, function (array $quote) use ($quoteId, $lineId, $input, $userId): array {
            $existing = $this->quotes->item($lineId);
            if ($existing === null || (int) $existing['quote_id'] !== $quoteId) {
                return ['errors' => ['That line is not on this quotation.'], 'line' => null, 'replace' => $lineId];
            }
            if ((int) $existing['is_custom_item'] === 1) {
                $built = $this->lines->custom($input, $this->level($quote), $userId);
            } else {
                $product = $this->lines->productFromLine($existing);
                $merged = array_merge($this->lines->inputFromLine($existing), $input);
                $built = $this->lines->fromProduct($product, $merged, $this->level($quote), $userId);
            }
            $built['replace'] = $lineId;
            $built['section_id'] = $existing['section_id'];

            return $this->guardLine($built, $input);
        }, 'Line updated');
    }

    /**
     * @return array<string, string>
     */
    public function duplicateLine(int $quoteId, int $lineId, int $version, int $userId): array
    {
        try {
            Database::transaction(function () use ($quoteId, $lineId, $version, $userId): void {
                $this->lockedDraft($quoteId, $version);
                $existing = $this->quotes->item($lineId);
                if ($existing === null || (int) $existing['quote_id'] !== $quoteId) {
                    throw new QuoteRejected(['_form' => 'That line is not on this quotation.']);
                }
                $newId = $this->quotes->insertItem($quoteId, $existing, $this->quotes->nextSort($quoteId));
                (new \App\Repositories\RecipeRepository())->copySnapshot($lineId, $newId);
                $this->retotal($quoteId);
                $this->quotes->touch($quoteId, $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function removeLine(int $quoteId, int $lineId, int $version, int $userId): array
    {
        try {
            Database::transaction(function () use ($quoteId, $lineId, $version, $userId): void {
                $this->lockedDraft($quoteId, $version);
                $this->quotes->deleteItem($lineId, $quoteId);
                $this->retotal($quoteId);
                $this->quotes->touch($quoteId, $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param list<int> $ids
     * @return array<string, string>
     */
    public function reorder(int $quoteId, array $ids, int $version, int $userId): array
    {
        try {
            Database::transaction(function () use ($quoteId, $ids, $version, $userId): void {
                $this->lockedDraft($quoteId, $version);
                $this->quotes->reorder($quoteId, $ids);
                $this->quotes->touch($quoteId, $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function addSection(int $quoteId, string $title, int $version, int $userId): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['errors' => ['title' => 'Section name is required.'], 'id' => null];
        }
        $id = 0;
        try {
            Database::transaction(function () use ($quoteId, $title, $version, $userId, &$id): void {
                $this->lockedDraft($quoteId, $version);
                $id = $this->quotes->insertSection($quoteId, $title, $this->quotes->nextSort($quoteId));
                $this->quotes->touch($quoteId, $userId);
            });
        } catch (QuoteRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewRefresh(int $quoteId): array
    {
        $quote = $this->quotes->find($quoteId);
        if ($quote === null) {
            return [];
        }
        $rows = [];
        foreach ($this->quotes->items($quoteId) as $line) {
            if ((int) $line['is_custom_item'] === 1 || empty($line['product_id'])) {
                continue;
            }
            if ((new \App\Repositories\RecipeRepository())->snapshotForItem((int) $line['id']) !== null) {
                continue;
            }
            $product = $this->products->find((int) $line['product_id']);
            if ($product === null) {
                $rows[] = [
                    'name' => $line['product_name_snapshot'],
                    'saved_cost' => $line['unit_cost_snapshot'],
                    'current_cost' => null,
                    'missing' => true,
                ];
                continue;
            }
            $fresh = $this->lines->fromProduct(
                $product,
                $this->lines->inputFromLine($line),
                $this->level($quote),
                null
            );
            $current = $fresh['line']['unit_cost_snapshot'] ?? null;
            $saved = (string) $line['unit_cost_snapshot'];
            $diff = $current === null ? '0' : Decimal::sub((string) $current, $saved);
            $percent = Decimal::cmp($saved, '0') === 0
                ? null
                : Decimal::round(Decimal::mul(Decimal::div($diff, $saved), '100'), 2);
            $rows[] = [
                'name' => $line['product_name_snapshot'],
                'saved_cost' => $saved,
                'current_cost' => $current,
                'difference' => Decimal::money($diff),
                'percent' => $percent,
                'changed' => $current !== null && Decimal::cmp($saved, (string) $current) !== 0,
                'missing' => false,
                'saved_sell' => $line['calculated_price'],
                'current_sell' => $fresh['line']['calculated_price'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function applyRefresh(int $quoteId, int $version, int $userId): array
    {
        try {
            Database::transaction(function () use ($quoteId, $version, $userId): void {
                $quote = $this->lockedDraft($quoteId, $version);
                $this->freeze($quote, 'Saved prices before a refresh', $userId);
                $level = $this->level($quote);
                foreach ($this->quotes->items($quoteId) as $line) {
                    if ((int) $line['is_custom_item'] === 1 || empty($line['product_id'])) {
                        continue;
                    }
                    $product = $this->products->find((int) $line['product_id']);
                    if ($product === null) {
                        continue;
                    }
                    $input = $this->lines->inputFromLine($line);
                    $input['final_sell_price'] = '';
                    $built = $this->lines->fromProduct($product, $input, $level, $userId);
                    if ($built['line'] === null) {
                        continue;
                    }
                    $built['line']['section_id'] = $line['section_id'];
                    $this->quotes->deleteItem((int) $line['id'], $quoteId);
                    $this->quotes->insertItem($quoteId, $built['line'], (int) $line['sort_order']);
                }
                $summary = $this->retotal($quoteId);
                $this->assertNotBelowCost($summary);
                $this->quotes->bumpRevision($quoteId, ((int) $quote['revision_number']) + 1, QuoteStatus::Draft->value, $userId);
                $this->quotes->insertHistory(
                    $quoteId,
                    (string) $quote['status'],
                    QuoteStatus::Draft->value,
                    $userId,
                    'Prices refreshed onto revision ' . (((int) $quote['revision_number']) + 1)
                );
                $this->audit->record('quote', $quoteId, 'QUOTE_PRICE_REFRESHED', null, [
                    'quote_number' => $quote['quote_number'],
                ], $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function createRevision(int $quoteId, int $version, int $userId, string $summary): array
    {
        try {
            Database::transaction(function () use ($quoteId, $version, $userId, $summary): void {
                $quote = $this->locked($quoteId, $version);
                if ((string) $quote['status'] === QuoteStatus::Converted->value || $this->jobs->findByQuote($quoteId) !== null) {
                    throw new QuoteRejected(['_form' => 'This quotation already has a job. Duplicate it if you need a new price.']);
                }
                $this->freeze($quote, $summary !== '' ? $summary : 'Revision ' . $quote['revision_number'] . ' kept', $userId);
                $next = ((int) $quote['revision_number']) + 1;
                $this->quotes->bumpRevision($quoteId, $next, QuoteStatus::Draft->value, $userId);
                $this->quotes->insertHistory((int) $quote['id'], (string) $quote['status'], QuoteStatus::Draft->value, $userId, 'Revision ' . $next);
                $this->audit->record('quote', $quoteId, 'QUOTE_REVISION_CREATED', null, [
                    'revision' => $next,
                    'quote_number' => $quote['quote_number'],
                ], $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function duplicate(int $quoteId, string $priceMode, int $userId): array
    {
        $source = $this->quotes->find($quoteId);
        if ($source === null) {
            return ['errors' => ['_form' => 'That quotation was not found.'], 'id' => null];
        }
        $newId = 0;
        Database::transaction(function () use ($source, $priceMode, $userId, &$newId): void {
            $copy = $source;
            $copy['quote_number'] = $this->numbers->quote();
            $copy['created_by'] = $userId;
            $newId = $this->quotes->insert([
                'quote_number' => $copy['quote_number'],
                'opportunity_id' => $source['opportunity_id'],
                'customer_id' => $source['customer_id'],
                'contact_id' => $source['contact_id'],
                'quote_date' => date('Y-m-d'),
                'expiry_date' => $this->defaultExpiry(),
                'pricing_level_id' => $source['pricing_level_id'],
                'vat_mode' => $source['vat_mode'],
                'vat_rate' => $source['vat_rate'],
                'customer_notes' => $source['customer_notes'],
                'internal_notes' => $source['internal_notes'],
                'terms' => $source['terms'],
                'assigned_to' => $source['assigned_to'],
                'created_by' => $userId,
            ]);
            $this->quotes->updateHeader($newId, [
                'contact_id' => $source['contact_id'],
                'quote_date' => date('Y-m-d'),
                'expiry_date' => $this->defaultExpiry(),
                'pricing_level_id' => $source['pricing_level_id'],
                'discount_type' => $source['discount_type'],
                'discount_value' => $source['discount_value'],
                'vat_mode' => $source['vat_mode'],
                'vat_rate' => $source['vat_rate'],
                'deposit_type' => $source['deposit_type'],
                'deposit_value' => $source['deposit_value'],
                'customer_notes' => $source['customer_notes'],
                'internal_notes' => $source['internal_notes'],
                'terms' => $source['terms'],
                'assigned_to' => $source['assigned_to'],
                'updated_by' => $userId,
            ]);
            $sectionMap = [];
            foreach ($this->quotes->sections((int) $source['id']) as $section) {
                $sectionMap[(int) $section['id']] = $this->quotes->insertSection(
                    $newId,
                    (string) $section['title'],
                    (int) $section['sort_order']
                );
            }
            $level = $this->level($source);
            foreach ($this->quotes->items((int) $source['id']) as $line) {
                $stored = $line;
                if ($priceMode === 'CURRENT' && (int) $line['is_custom_item'] !== 1 && !empty($line['product_id'])) {
                    $product = $this->products->find((int) $line['product_id']);
                    if ($product !== null) {
                        $input = $this->lines->inputFromLine($line);
                        $input['final_sell_price'] = '';
                        $built = $this->lines->fromProduct($product, $input, $level, $userId);
                        if ($built['line'] !== null) {
                            $stored = $built['line'];
                        }
                    }
                }
                $oldSection = $line['section_id'] ?? null;
                $stored['section_id'] = $oldSection !== null ? ($sectionMap[(int) $oldSection] ?? null) : null;
                $this->quotes->insertItem($newId, $stored, (int) $line['sort_order']);
            }
            $this->retotal($newId);
            $this->quotes->insertHistory($newId, null, QuoteStatus::Draft->value, $userId, 'Duplicated from ' . $source['quote_number']);
            $this->audit->record('quote', $newId, 'QUOTE_CREATED', null, [
                'duplicated_from' => $source['quote_number'],
                'prices' => $priceMode === 'CURRENT' ? 'current' : 'saved',
            ], $userId);
        });

        return ['errors' => [], 'id' => $newId];
    }

    /**
     * @return array<string, string>
     */
    public function changeStatus(int $quoteId, string $status, int $version, int $userId, string $notes = ''): array
    {
        $status = strtoupper($status);
        if (!in_array($status, QuoteStatus::values(), true)) {
            return ['_form' => 'That status is not valid.'];
        }
        try {
            Database::transaction(function () use ($quoteId, $status, $version, $userId, $notes): void {
                $quote = $this->locked($quoteId, $version);
                $current = (string) $quote['status'];
                $allowed = match ($status) {
                    'READY' => $current === 'DRAFT',
                    'DRAFT' => $current === 'READY',
                    'SENT' => in_array($current, ['DRAFT', 'READY'], true),
                    'VIEWED' => in_array($current, ['SENT', 'READY'], true),
                    'DECLINED' => in_array($current, ['READY', 'SENT', 'VIEWED'], true),
                    default => false,
                };
                if (!$allowed) {
                    throw new QuoteRejected(['_form' => 'That status change is not available from ' . $current . '.']);
                }
                if ($status === 'SENT') {
                    $this->freeze($quote, 'Sent as revision ' . $quote['revision_number'], $userId);
                }
                $this->quotes->updateStatus($quoteId, ['status' => $status, 'user_id' => $userId]);
                $this->quotes->insertHistory($quoteId, $current, $status, $userId, $notes !== '' ? $notes : null);
                $action = match ($status) {
                    'SENT' => 'QUOTE_SENT',
                    'DECLINED' => 'QUOTE_DECLINED',
                    default => 'QUOTE_EDITED',
                };
                $this->audit->record('quote', $quoteId, $action, ['status' => $current], ['status' => $status], $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        if ($status === 'SENT') {
            try {
                (new AutomationService())->fire('QUOTE_SENT', 'quote', $quoteId, $userId);
            } catch (\Throwable $e) {
                error_log('Quote follow-up automation failed: ' . $e->getMessage());
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function accept(int $quoteId, array $input, int $version, int $userId): array
    {
        $name = trim((string) ($input['accepted_by_name'] ?? ''));
        $method = strtoupper(trim((string) ($input['acceptance_method'] ?? '')));
        if ($name === '') {
            return ['accepted_by_name' => 'Who accepted the quotation?'];
        }
        if (!in_array($method, \App\Domain\AcceptanceMethod::values(), true)) {
            return ['acceptance_method' => 'Choose how the acceptance was received.'];
        }
        try {
            Database::transaction(function () use ($quoteId, $input, $version, $userId, $name, $method): void {
                $quote = $this->locked($quoteId, $version);
                if (!in_array((string) $quote['status'], ['READY', 'SENT', 'VIEWED'], true)) {
                    throw new QuoteRejected(['_form' => 'Only a ready or sent quotation can be accepted.']);
                }
                $this->freeze($quote, 'Accepted revision ' . $quote['revision_number'], $userId);
                $this->quotes->markAccepted($quoteId, [
                    'accepted_by_name' => $name,
                    'user_id' => $userId,
                    'acceptance_method' => $method,
                    'acceptance_reference' => blank_to_null($input['acceptance_reference'] ?? null),
                    'acceptance_notes' => blank_to_null($input['acceptance_notes'] ?? null),
                ]);
                $this->quotes->insertHistory($quoteId, (string) $quote['status'], 'ACCEPTED', $userId, $name . ' via ' . $method);
                $this->audit->record('quote', $quoteId, 'QUOTE_ACCEPTED', null, [
                    'accepted_by_name' => $name,
                    'acceptance_method' => $method,
                ], $userId);
            });
        } catch (QuoteRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function convert(int $quoteId, array $input, int $version, int $userId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['errors' => ['title' => 'Job title is required.'], 'id' => null];
        }
        $priority = strtoupper(trim((string) ($input['priority'] ?? 'NORMAL')));
        if (!in_array($priority, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true)) {
            $priority = 'NORMAL';
        }
        $target = trim((string) ($input['target_date'] ?? ''));
        if ($target !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $target)) {
            return ['errors' => ['target_date' => 'Target date must be a date.'], 'id' => null];
        }
        foreach (['production_due_date' => 'Production due date', 'installation_date' => 'Installation date'] as $field => $label) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return ['errors' => [$field => $label . ' must be a date.'], 'id' => null];
            }
        }
        $jobId = 0;
        $jobs = new JobService();
        try {
            Database::transaction(function () use ($quoteId, $input, $version, $userId, $title, $priority, $target, $jobs, &$jobId): void {
                $quote = $this->locked($quoteId, $version);
                if ((string) $quote['status'] !== 'ACCEPTED') {
                    throw new QuoteRejected(['_form' => 'Accept the quotation before converting it to a job.']);
                }
                if ($this->jobs->findByQuote($quoteId) !== null) {
                    throw new QuoteRejected(['_form' => 'This quotation already has a job.']);
                }
                $jobId = $this->jobs->insert([
                    'job_number' => $this->numbers->job(),
                    'customer_id' => (int) $quote['customer_id'],
                    'quote_id' => $quoteId,
                    'quote_revision_number' => (int) $quote['revision_number'],
                    'title' => $title,
                    'priority' => $priority,
                    'assigned_to' => $quote['assigned_to'],
                    'target_date' => $target === '' ? null : $target,
                    'created_by' => $userId,
                ]);
                $jobs->seedFromQuote($jobId, $quote, $input, $userId);
                $this->quotes->updateStatus($quoteId, ['status' => 'CONVERTED', 'user_id' => $userId]);
                $this->quotes->insertHistory($quoteId, 'ACCEPTED', 'CONVERTED', $userId, 'Job created');
                if (!empty($quote['opportunity_id'])) {
                    $this->opportunities->setStatus((int) $quote['opportunity_id'], OpportunityStatus::Won->value, null, null);
                }
                $this->audit->record('quote', $quoteId, 'QUOTE_CONVERTED', null, [
                    'job_id' => $jobId,
                    'revision' => (int) $quote['revision_number'],
                ], $userId);
            });
        } catch (QuoteRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $jobId];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function revisionSnapshot(int $quoteId, int $number): ?array
    {
        $row = $this->quotes->revision($quoteId, $number);
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) $row['snapshot_json'], true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function expiryState(?string $date, string $status): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        if (!in_array($status, ['DRAFT', 'READY', 'SENT', 'VIEWED'], true)) {
            return null;
        }
        $today = date('Y-m-d');
        if ($date < $today) {
            return 'EXPIRED';
        }
        if ($date <= date('Y-m-d', strtotime('+7 days'))) {
            return 'EXPIRING';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validateIdentity(array $input): array
    {
        $errors = [];
        $customerId = (int) ($input['customer_id'] ?? 0);
        $customer = $customerId > 0 ? $this->customers->find($customerId) : null;
        if ($customer === null) {
            $errors['customer_id'] = 'Choose a customer.';
        }
        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId > 0 && ($customer === null || $this->contacts->findForCustomer($customerId, $contactId) === null)) {
            $errors['contact_id'] = 'That contact does not belong to this customer.';
        }
        $opportunityId = (int) ($input['opportunity_id'] ?? 0);
        if ($opportunityId > 0) {
            $opportunity = $this->opportunities->find($opportunityId);
            if ($opportunity === null || (int) $opportunity['customer_id'] !== $customerId) {
                $errors['opportunity_id'] = 'That opportunity does not belong to this customer.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function headerData(array $input, int $userId, bool $creating, ?array $existing = null): array
    {
        $date = trim((string) ($input['quote_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $creating ? date('Y-m-d') : (string) ($existing['quote_date'] ?? date('Y-m-d'));
        }
        $expiry = trim((string) ($input['expiry_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            $expiry = $creating ? $this->defaultExpiry() : ($existing['expiry_date'] ?? null);
        }
        $levelId = (int) ($input['pricing_level_id'] ?? 0);
        $level = $levelId > 0 ? $this->levels->find($levelId) : null;
        if ($level === null) {
            $levelId = $creating ? (int) ($this->levels->active()[0]['id'] ?? 0) : (int) ($existing['pricing_level_id'] ?? 0);
        }
        $assigned = (int) ($input['assigned_to'] ?? 0);
        if ($assigned > 0 && $this->users->find($assigned) === null) {
            $assigned = 0;
        }
        $contactId = (int) ($input['contact_id'] ?? 0);
        $customerId = $creating ? (int) $input['customer_id'] : (int) ($existing['customer_id'] ?? 0);
        if ($contactId > 0 && $this->contacts->findForCustomer($customerId, $contactId) === null) {
            $contactId = 0;
        }
        $rate = $creating
            ? (string) SettingsService::get('default_vat_percent', '15')
            : (string) ($existing['vat_rate'] ?? '0');
        $discountType = DiscountType::normalise((string) ($input['discount_type'] ?? 'NONE'));
        $discountValue = $this->nonNegative((string) ($input['discount_value'] ?? '0'));
        if (!can('quotes.discount')) {
            if ($creating || $existing === null) {
                $discountType = 'NONE';
                $discountValue = '0';
            } else {
                $discountType = (string) ($existing['discount_type'] ?? 'NONE');
                $discountValue = (string) ($existing['discount_value'] ?? '0');
            }
        }

        $data = [
            'contact_id' => $contactId > 0 ? $contactId : null,
            'quote_date' => $date,
            'expiry_date' => $expiry,
            'pricing_level_id' => $levelId > 0 ? $levelId : null,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'vat_mode' => VatMode::normalise((string) ($input['vat_mode'] ?? 'EXCLUSIVE')),
            'vat_rate' => Decimal::round($rate, 4),
            'deposit_type' => $this->depositType((string) ($input['deposit_type'] ?? 'NONE')),
            'deposit_value' => $this->nonNegative((string) ($input['deposit_value'] ?? '0')),
            'customer_notes' => blank_to_null($input['customer_notes'] ?? null),
            'internal_notes' => blank_to_null($input['internal_notes'] ?? null),
            'terms' => $creating
                ? (blank_to_null($input['terms'] ?? null) ?? SettingsService::get('default_quote_terms', ''))
                : blank_to_null($input['terms'] ?? ($existing['terms'] ?? null)),
            'assigned_to' => $assigned > 0 ? $assigned : ($creating ? $userId : ($existing['assigned_to'] ?? null)),
            'updated_by' => $userId,
        ];
        if ($creating) {
            $data['customer_id'] = $customerId;
            $data['opportunity_id'] = ((int) ($input['opportunity_id'] ?? 0)) > 0 ? (int) $input['opportunity_id'] : null;
            $data['created_by'] = $userId;
        }

        return $data;
    }

    /**
     * @param callable(array<string, mixed>): array{errors: list<string>, line: array<string, mixed>|null, replace?: int} $build
     * @return array{errors: list<string>, id: int|null}
     */
    private function storeLine(int $quoteId, int $version, int $userId, callable $build, string $note): array
    {
        $lineId = null;
        try {
            Database::transaction(function () use ($quoteId, $version, $userId, $build, $note, &$lineId): void {
                $quote = $this->lockedDraft($quoteId, $version);
                $built = $build($quote);
                if ($built['line'] === null) {
                    throw new QuoteRejected(['_form' => implode(' ', $built['errors'])]);
                }
                $line = $built['line'];
                if (!empty($built['replace'])) {
                    $previous = $this->quotes->item((int) $built['replace']);
                    $sort = (int) ($previous['sort_order'] ?? $this->quotes->nextSort($quoteId));
                    if (!array_key_exists('section_id', $line)) {
                        $line['section_id'] = $previous['section_id'] ?? null;
                    }
                    $this->quotes->deleteItem((int) $built['replace'], $quoteId);
                    $lineId = $this->quotes->insertItem($quoteId, $line, $sort);
                } else {
                    $line['section_id'] = $this->sectionOrNull($quoteId, (int) ($line['section_id'] ?? 0));
                    $lineId = $this->quotes->insertItem($quoteId, $line, $this->quotes->nextSort($quoteId));
                }
                $summary = $this->retotal($quoteId);
                $this->assertNotBelowCost($summary);
                $this->quotes->touch($quoteId, $userId);
                if ((int) ($line['price_overridden'] ?? 0) === 1) {
                    $this->audit->record('quote', $quoteId, 'QUOTE_PRICE_OVERRIDDEN', null, [
                        'line' => $line['product_name_snapshot'],
                        'calculated_price' => $line['calculated_price'],
                        'final_sell_price' => $line['final_sell_price'],
                    ], $userId);
                } else {
                    $this->audit->record('quote', $quoteId, 'QUOTE_EDITED', null, ['note' => $note], $userId);
                }
            });
        } catch (QuoteRejected $e) {
            return ['errors' => array_values($e->errors), 'id' => null];
        }

        return ['errors' => [], 'id' => $lineId];
    }

    /**
     * @param array{errors: list<string>, line: array<string, mixed>|null} $built
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, line: array<string, mixed>|null}
     */
    private function guardLine(array $built, array $input): array
    {
        if ($built['line'] === null) {
            return $built;
        }
        $line = $built['line'];
        $this->assertDiscount('NONE', (string) $line['line_discount_type']);
        if ((int) $line['price_overridden'] === 1 && !can('quotes.price_override')) {
            throw new QuoteRejected(['final_sell_price' => 'You cannot override the calculated selling price.']);
        }
        if ((int) $line['price_overridden'] === 1 && Decimal::cmp((string) $line['line_total'], (string) $line['total_cost']) < 0 && !can('quotes.discount_below_cost')) {
            throw new QuoteRejected(['final_sell_price' => 'That price is below cost. An administrator can allow it.']);
        }
        if (!empty($input['section_id'])) {
            $line['section_id'] = (int) $input['section_id'];
        }
        $built['line'] = $line;

        return $built;
    }

    /**
     * @return array<string, mixed>
     */
    private function locked(int $id, int $version): array
    {
        $quote = $this->quotes->lock($id);
        if ($quote === null) {
            throw new QuoteRejected(['_form' => 'That quotation was not found.']);
        }
        if ((int) $quote['version_number'] !== $version) {
            throw new QuoteConflictException('This quotation was modified by another user. Reload before saving.');
        }

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedDraft(int $id, int $version): array
    {
        $quote = $this->locked($id, $version);
        if ((string) $quote['status'] !== QuoteStatus::Draft->value) {
            throw new QuoteRejected(['_form' => 'This quotation is locked. Create a revision to change it.']);
        }

        return $quote;
    }

    /**
     * @param array<string, mixed> $quote
     */
    public function captureRevision(array $quote, string $summary, ?int $userId = null): void
    {
        $this->freeze($quote, $summary, $userId);
    }

    private function freeze(array $quote, string $summary, ?int $userId): void
    {
        $number = (int) $quote['revision_number'];
        if ($this->quotes->revision((int) $quote['id'], $number) !== null) {
            return;
        }
        $payload = [
            'quote' => $this->quotes->find((int) $quote['id']),
            'items' => $this->quotes->items((int) $quote['id']),
            'sections' => $this->quotes->sections((int) $quote['id']),
        ];
        $this->quotes->insertRevision(
            (int) $quote['id'],
            $number,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $summary,
            $userId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function retotal(int $quoteId): array
    {
        $quote = $this->quotes->find($quoteId);
        $summary = $this->totals->summarise($this->quotes->items($quoteId), $quote ?? []);
        $this->quotes->updateTotals($quoteId, $summary);

        return $summary;
    }

    /**
     * @param array<string, mixed> $quote
     * @return array<string, mixed>
     */
    private function level(array $quote): array
    {
        $level = !empty($quote['pricing_level_id']) ? $this->levels->find((int) $quote['pricing_level_id']) : null;
        if ($level === null) {
            $level = $this->levels->active()[0] ?? [
                'id' => null,
                'code' => 'Q1',
                'name' => 'Q1',
                'markup_percent' => '0',
                'active' => 1,
            ];
        }
        $level['active'] = 1;

        return $level;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function assertNotBelowCost(array $summary): void
    {
        if (!empty($summary['below_cost']) && !can('quotes.discount_below_cost')) {
            throw new QuoteRejected(['discount_type' => 'This discount takes the quotation below cost. An administrator can allow it.']);
        }
    }

    private function assertDiscount(string $quoteType, string $lineType): void
    {
        if (($quoteType !== 'NONE' || $lineType !== 'NONE') && !can('quotes.discount')) {
            throw new QuoteRejected(['discount_type' => 'You cannot apply a discount.']);
        }
    }

    private function markOpportunityQuoted(?int $opportunityId): void
    {
        if ($opportunityId === null || $opportunityId < 1) {
            return;
        }
        $opportunity = $this->opportunities->find($opportunityId);
        if ($opportunity === null) {
            return;
        }
        if (in_array((string) $opportunity['status'], ['NEW', 'CONTACTED', 'QUALIFIED', 'ON_HOLD'], true)) {
            $this->opportunities->setStatus($opportunityId, OpportunityStatus::Quoted->value, null, null);
        }
    }

    private function sectionOrNull(int $quoteId, int $sectionId): ?int
    {
        if ($sectionId < 1) {
            return null;
        }
        foreach ($this->quotes->sections($quoteId) as $section) {
            if ((int) $section['id'] === $sectionId) {
                return $sectionId;
            }
        }

        return null;
    }

    private function defaultExpiry(): string
    {
        $days = (int) SettingsService::get('default_quote_validity_days', '14');
        if ($days < 1) {
            $days = 14;
        }

        return date('Y-m-d', strtotime('+' . $days . ' days'));
    }

    private function depositType(string $value): string
    {
        $value = strtoupper(trim($value));

        return in_array($value, ['PERCENTAGE', 'FIXED_AMOUNT'], true) ? $value : 'NONE';
    }

    private function nonNegative(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return '0';
        }

        return Decimal::round($value, 4);
    }
}
