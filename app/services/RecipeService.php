<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\OperationsRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RecipeRepository;

/**
 * Saves recipes, keeps a version each time the structure changes, and
 * refuses to activate a recipe whose formulas or products are not usable.
 */
final class RecipeService
{
    /** @var list<string> */
    public const TYPES = ['SIGNAGE', 'PRINT', 'FABRICATION', 'INSTALLATION', 'SERVICE', 'OTHER'];

    /** @var list<string> */
    public const COMPONENTS = ['MATERIAL', 'LABOUR', 'SERVICE', 'HARDWARE', 'SUBCONTRACT', 'OTHER'];

    /** @var list<string> */
    public const INPUTS = ['NUMBER', 'DIMENSION', 'QUANTITY', 'SELECT', 'BOOLEAN'];

    public function __construct(
        private readonly RecipeRepository $recipes = new RecipeRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly RecipeCalculationService $calculator = new RecipeCalculationService(),
        private readonly FormulaService $formulas = new FormulaService(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(?int $id, array $input, int $userId): array
    {
        $parsed = $this->parse($input, $id);
        if ($parsed['errors'] !== []) {
            return ['errors' => $parsed['errors'], 'id' => null];
        }
        $saved = 0;
        try {
        Database::transaction(function () use ($id, $parsed, $userId, &$saved): void {
            $header = $parsed['header'];
            $existing = $id !== null ? $this->recipes->find($id) : null;
            if ($existing === null) {
                $header['version_number'] = 1;
                $header['created_by'] = $userId;
                $saved = $this->recipes->insert($header);
                $this->recipes->replaceChildren($saved, $parsed['inputs'], $parsed['items']);
                $this->storeVersion($saved, 1, $userId);
                $this->audit->record('recipe', $saved, 'RECIPE_CREATED', null, ['code' => $header['code'], 'name' => $header['name']], $userId);
            } else {
                $version = ((int) $existing['version_number']) + 1;
                $header['version_number'] = $version;
                $this->recipes->update($id, $header);
                $this->recipes->replaceChildren($id, $parsed['inputs'], $parsed['items']);
                $this->storeVersion($id, $version, $userId);
                $saved = $id;
                $this->audit->record('recipe', $id, 'RECIPE_UPDATED', [
                    'name' => $existing['name'],
                    'version' => (int) $existing['version_number'],
                    'active' => (int) $existing['active'],
                ], [
                    'name' => $header['name'],
                    'version' => $version,
                    'active' => $header['active'],
                ], $userId);
                $this->audit->record('recipe', $id, 'RECIPE_VERSION_CREATED', null, ['version' => $version], $userId);
            }
            if ((int) $header['active'] === 1) {
                $problems = $this->activationProblems($saved);
                if ($problems !== []) {
                    throw new FormulaRejected(implode(' ', $problems));
                }
            }
        });
        } catch (FormulaRejected $e) {
            return ['errors' => ['_form' => $e->getMessage()], 'id' => null];
        }

        return ['errors' => [], 'id' => $saved];
    }

    /**
     * @return array<string, string>
     */
    public function deactivate(int $id, int $userId): array
    {
        $existing = $this->recipes->find($id);
        if ($existing === null) {
            return ['_form' => 'That recipe was not found.'];
        }
        Database::transaction(function () use ($id, $existing, $userId): void {
            $this->recipes->setActive($id, 0);
            $this->audit->record('recipe', $id, 'RECIPE_DEACTIVATED', ['active' => (int) $existing['active']], ['active' => 0], $userId);
        });

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function duplicate(int $id, int $userId): array
    {
        $existing = $this->recipes->find($id);
        if ($existing === null) {
            return ['errors' => ['_form' => 'That recipe was not found.'], 'id' => null];
        }
        $code = substr((string) $existing['code'], 0, 32) . '-COPY';
        $suffix = 2;
        while ($this->recipes->findByCode($code) !== null) {
            $code = substr((string) $existing['code'], 0, 28) . '-COPY' . $suffix;
            $suffix++;
        }
        $newId = 0;
        Database::transaction(function () use ($existing, $code, $userId, &$newId): void {
            $newId = $this->recipes->insert([
                'code' => $code,
                'name' => (string) $existing['name'] . ' copy',
                'description' => $existing['description'],
                'category_id' => $existing['category_id'],
                'finished_product_id' => $existing['finished_product_id'],
                'recipe_type' => $existing['recipe_type'],
                'pricing_method' => $existing['pricing_method'],
                'production_route_template_id' => $existing['production_route_template_id'],
                'active' => 0,
                'version_number' => 1,
                'created_by' => $userId,
            ]);
            $inputs = [];
            foreach ($this->recipes->inputs((int) $existing['id']) as $row) {
                $inputs[] = $row;
            }
            $items = [];
            foreach ($this->recipes->items((int) $existing['id']) as $row) {
                $row['nested_recipe_id'] = (int) $row['nested_recipe_id'] === (int) $existing['id'] ? null : $row['nested_recipe_id'];
                $items[] = $row;
            }
            $this->recipes->replaceChildren($newId, $this->normaliseCopied($inputs), $this->normaliseCopied($items));
            $this->storeVersion($newId, 1, $userId);
            $this->audit->record('recipe', $newId, 'RECIPE_CREATED', null, ['copied_from' => (int) $existing['id']], $userId);
        });

        return ['errors' => [], 'id' => $newId];
    }

    /**
     * @param array<string, mixed> $posted
     * @param array<string, mixed> $level
     * @return array<string, mixed>
     */
    public function test(int $id, array $posted, array $level): array
    {
        $recipe = $this->recipes->find($id);
        if ($recipe === null) {
            return ['ok' => false, 'error' => 'That recipe was not found.'];
        }
        $result = $this->calculator->calculate($recipe, $this->recipes->inputs($id), $this->recipes->items($id), $posted, $level);
        $result['production_route'] = $this->route((int) ($recipe['production_route_template_id'] ?? 0));
        $result['recipe_version'] = (int) $recipe['version_number'];

        return $result;
    }

    /**
     * @return list<string>
     */
    public function activationProblems(int $id): array
    {
        $recipe = $this->recipes->find($id);
        if ($recipe === null) {
            return ['That recipe was not found.'];
        }
        $problems = [];
        $inputs = $this->recipes->inputs($id);
        $sample = ['W' => '1000', 'H' => '1000', 'L' => '1000', 'D' => '10', 'Q' => '1', 'AREA_M2' => '1', 'PERIMETER_M' => '4'];
        foreach ($inputs as $input) {
            $code = strtoupper((string) $input['code']);
            if (!in_array((string) $input['input_type'], ['SELECT'], true)) {
                $sample[$code] = '1';
            }
        }
        $seen = [];
        foreach ($this->recipes->items($id) as $item) {
            if (!$this->formulas->isValid((string) $item['quantity_formula'], $sample)) {
                $problems[] = (string) $item['description'] . ' has a formula that cannot be calculated.';
            }
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0 && $this->products->find($productId) === null) {
                $problems[] = (string) $item['description'] . ' refers to a missing product.';
            }
            if (trim((string) $item['unit']) === '') {
                $problems[] = (string) $item['description'] . ' needs a unit.';
            }
            $nested = (int) ($item['nested_recipe_id'] ?? 0);
            if ($nested > 0) {
                if ($this->cycle($id, $nested, $seen)) {
                    $problems[] = 'A recipe cannot include itself.';
                }
            }
        }
        $routeId = (int) ($recipe['production_route_template_id'] ?? 0);
        if ($routeId > 0 && $this->ops->templateStages($routeId) === []) {
            $problems[] = 'The production route has no stages.';
        }

        return $problems;
    }

    /**
     * @param array<int, true> $seen
     */
    private function cycle(int $origin, int $nested, array &$seen): bool
    {
        if ($nested === $origin) {
            return true;
        }
        if (isset($seen[$nested])) {
            return false;
        }
        $seen[$nested] = true;
        foreach ($this->recipes->items($nested) as $item) {
            $next = (int) ($item['nested_recipe_id'] ?? 0);
            if ($next > 0 && $this->cycle($origin, $next, $seen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function route(int $templateId): array
    {
        if ($templateId < 1) {
            return [];
        }
        $rows = [];
        foreach ($this->ops->templateStages($templateId) as $stage) {
            $rows[] = [
                'production_stage_id' => (int) $stage['production_stage_id'],
                'name' => (string) $stage['name'],
                'sort_order' => (int) $stage['sort_order'],
            ];
        }

        return $rows;
    }

    private function storeVersion(int $id, int $version, int $userId): void
    {
        $recipe = $this->recipes->find($id);
        $this->recipes->insertVersion($id, $version, [
            'recipe' => $recipe,
            'inputs' => $this->recipes->inputs($id),
            'items' => $this->recipes->items($id),
        ], $userId);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function normaliseCopied(array $rows): array
    {
        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, header: array<string, mixed>, inputs: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    private function parse(array $input, ?int $id): array
    {
        $errors = [];
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($input['code'] ?? '')) ?? '');
        $name = trim((string) ($input['name'] ?? ''));
        if ($code === '') {
            $errors['code'] = 'Code is required.';
        }
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        $taken = $code !== '' ? $this->recipes->findByCode($code) : null;
        if ($taken !== null && (int) $taken['id'] !== (int) $id) {
            $errors['code'] = 'That code is already used.';
        }
        $type = strtoupper(trim((string) ($input['recipe_type'] ?? 'SIGNAGE')));
        if (!in_array($type, self::TYPES, true)) {
            $errors['recipe_type'] = 'Choose a recipe type.';
        }
        $finished = (int) ($input['finished_product_id'] ?? 0);
        if ($finished > 0 && $this->products->find($finished) === null) {
            $errors['finished_product_id'] = 'That finished product was not found.';
        }
        $route = (int) ($input['production_route_template_id'] ?? 0);
        $inputs = [];
        $sort = 10;
        $postedInputs = is_array($input['inputs'] ?? null) ? $input['inputs'] : [];
        foreach ($postedInputs as $row) {
            if (!is_array($row) || trim((string) ($row['code'] ?? '')) === '') {
                continue;
            }
            $inputCode = strtoupper(preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) $row['code'])) ?? '');
            $inputType = strtoupper(trim((string) ($row['input_type'] ?? 'NUMBER')));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $inputCode) || in_array($inputCode, FormulaService::FUNCTIONS, true)) {
                $errors['inputs'] = 'Input codes must be letters and numbers, and cannot be a formula function.';
                continue;
            }
            if (!in_array($inputType, self::INPUTS, true)) {
                $inputType = 'NUMBER';
            }
            $options = trim((string) ($row['options'] ?? ''));
            $inputs[] = [
                'code' => $inputCode,
                'label' => trim((string) ($row['label'] ?? $inputCode)) ?: $inputCode,
                'input_type' => $inputType,
                'unit' => blank_to_null($row['unit'] ?? null),
                'required' => !empty($row['required']) ? 1 : 0,
                'default_value' => blank_to_null($row['default_value'] ?? null),
                'min_value' => blank_to_null($row['min_value'] ?? null),
                'max_value' => blank_to_null($row['max_value'] ?? null),
                'options_json' => $options === '' ? null : json_encode(array_values(array_filter(array_map('trim', explode(',', $options))))),
                'sort_order' => $sort,
            ];
            $sort += 10;
        }
        $items = [];
        $sort = 10;
        $postedItems = is_array($input['items'] ?? null) ? $input['items'] : [];
        foreach ($postedItems as $row) {
            if (!is_array($row) || trim((string) ($row['description'] ?? '')) === '') {
                continue;
            }
            $component = strtoupper(trim((string) ($row['component_type'] ?? 'MATERIAL')));
            if (!in_array($component, self::COMPONENTS, true)) {
                $component = 'OTHER';
            }
            $productId = (int) ($row['product_id'] ?? 0);
            $nestedId = (int) ($row['nested_recipe_id'] ?? 0);
            $waste = trim((string) ($row['waste_percent_override'] ?? ''));
            $pack = trim((string) ($row['pack_size'] ?? ''));
            $condition = trim((string) ($row['condition'] ?? ''));
            $conditionJson = null;
            if ($condition !== '') {
                $bits = array_map('trim', explode(',', $condition, 3));
                if (count($bits) >= 2) {
                    $conditionJson = json_encode([
                        'input' => strtoupper($bits[0]),
                        'op' => strtoupper($bits[1] ?? 'EQ'),
                        'value' => $bits[2] ?? 'YES',
                    ], JSON_THROW_ON_ERROR);
                }
            }
            $items[] = [
                'component_type' => $component,
                'product_id' => $productId > 0 ? $productId : null,
                'nested_recipe_id' => $nestedId > 0 ? $nestedId : null,
                'description' => trim((string) $row['description']),
                'quantity_formula' => trim((string) ($row['quantity_formula'] ?? 'Q')),
                'waste_percent_override' => $waste === '' ? null : $waste,
                'unit' => trim((string) ($row['unit'] ?? 'unit')) ?: 'unit',
                'cost_calculation_method' => strtoupper(trim((string) ($row['cost_calculation_method'] ?? 'PRODUCT'))) ?: 'PRODUCT',
                'rounding_rule' => strtoupper(trim((string) ($row['rounding_rule'] ?? 'NONE'))) ?: 'NONE',
                'pack_size' => $pack !== '' && Decimal::isNumeric($pack) ? $pack : null,
                'yield_mode' => strtoupper(trim((string) ($row['yield_mode'] ?? 'NONE'))) ?: 'NONE',
                'condition_json' => $conditionJson,
                'sort_order' => $sort,
                'optional' => !empty($row['optional']) ? 1 : 0,
            ];
            $sort += 10;
        }
        if ($items === []) {
            $errors['items'] = 'Add at least one component.';
        }

        return [
            'errors' => $errors,
            'header' => [
                'code' => $code,
                'name' => $name,
                'description' => blank_to_null($input['description'] ?? null),
                'category_id' => ((int) ($input['category_id'] ?? 0)) > 0 ? (int) $input['category_id'] : null,
                'finished_product_id' => $finished > 0 ? $finished : null,
                'recipe_type' => $type,
                'pricing_method' => 'MARKUP',
                'production_route_template_id' => $route > 0 ? $route : null,
                'active' => !empty($input['active']) ? 1 : 0,
                'version_number' => 1,
                'created_by' => null,
            ],
            'inputs' => $inputs,
            'items' => $items,
        ];
    }
}
