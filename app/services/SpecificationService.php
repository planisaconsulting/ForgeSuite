<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\SignageRepository;

/**
 * Versioned manufacturing specifications.
 * An approved specification is not edited in place. A new version is a new row.
 */
final class SpecificationService
{
    /** @var list<string> */
    public const STATUSES = ['DRAFT', 'REVIEW', 'APPROVED', 'SUPERSEDED', 'ARCHIVED'];

    /** @var list<string> */
    public const ESTIMATORS = ['VEHICLE_WRAP', 'CHANNEL_LETTER', 'LIGHTBOX', 'PYLON', 'PANEL_FRAME', 'CUSTOM'];

    public function __construct(
        private readonly SignageRepository $specs = new SignageRepository(),
        private readonly FormulaService $formulas = new FormulaService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('specifications.create')) {
            return ['errors' => ['_form' => 'You cannot create a specification.'], 'id' => null];
        }
        $built = $this->fields($input, true);
        if ($built['errors'] !== []) {
            return ['errors' => $built['errors'], 'id' => null];
        }
        $built['data']['version'] = 1;
        $built['data']['status'] = 'DRAFT';
        $built['data']['created_by'] = $userId;
        $id = 0;
        Database::transaction(function () use ($built, $userId, &$id): void {
            $id = $this->specs->insertSpecification($built['data']);
            $this->audit->record('sign_specification', $id, 'SPECIFICATION_CREATED', null, ['code' => $built['data']['code']], $userId);
        });
        BusinessEventDispatcher::emit('SPECIFICATION_CREATED', 'SPECIFICATION', $id, $userId, ['code' => $built['data']['code']]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $userId): array
    {
        if (!can('specifications.edit')) {
            return ['_form' => 'You cannot edit a specification.'];
        }
        $existing = $this->specs->specification($id);
        if ($existing === null || !in_array((string) $existing['status'], ['DRAFT', 'REVIEW'], true)) {
            return ['_form' => 'Only a draft or a specification in review can be edited. Approved history stays as it was.'];
        }
        $built = $this->fields($input, false);
        if ($built['errors'] !== []) {
            return $built['errors'];
        }
        $formulaError = $this->checkFormulas($input);
        if ($formulaError !== null) {
            return ['formula' => $formulaError];
        }
        Database::transaction(function () use ($id, $built, $input): void {
            $this->specs->updateSpecification($id, $built['data']);
            if (isset($input['replace_children'])) {
                $this->specs->deleteChildren($id);
                $this->storeChildren($id, $input);
            }
        });

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function revise(int $id, int $userId): array
    {
        if (!can('specifications.edit')) {
            return ['errors' => ['_form' => 'You cannot revise a specification.'], 'id' => null];
        }
        $existing = $this->specs->specification($id);
        if ($existing === null) {
            return ['errors' => ['_form' => 'That specification was not found.'], 'id' => null];
        }
        $copy = $existing;
        $copy['version'] = $this->specs->nextVersion((string) $existing['code']);
        $copy['status'] = 'DRAFT';
        $copy['created_by'] = $userId;
        $copy['effective_from'] = date('Y-m-d');
        $newId = 0;
        Database::transaction(function () use ($copy, $id, &$newId): void {
            $newId = $this->specs->insertSpecification($copy);
            foreach ($this->specs->materials($id) as $row) {
                $row['specification_id'] = $newId;
                $this->specs->insertMaterial($row);
            }
            foreach ($this->specs->components($id) as $row) {
                $row['specification_id'] = $newId;
                $this->specs->insertComponent($row);
            }
            foreach ($this->specs->labour($id) as $row) {
                $row['specification_id'] = $newId;
                $this->specs->insertLabour($row);
            }
            foreach ($this->specs->operations($id) as $row) {
                $row['specification_id'] = $newId;
                $this->specs->insertOperation($row);
            }
            foreach ($this->specs->rules($id) as $row) {
                $row['specification_id'] = $newId;
                $this->specs->insertRule($row);
            }
            foreach ($this->specs->notes($id) as $row) {
                $row['specification_id'] = $newId;
                $this->specs->insertNote($row);
            }
        });

        return ['errors' => [], 'id' => $newId];
    }

    /**
     * @return array<string, string>
     */
    public function approve(int $id, int $userId): array
    {
        if (!can('specifications.approve')) {
            return ['_form' => 'You cannot approve a specification.'];
        }
        $existing = $this->specs->specification($id);
        if ($existing === null) {
            return ['_form' => 'That specification was not found.'];
        }
        $gate = (new ApprovalService())->gate('SPECIFICATION', $id, 'APPROVE', $userId, [], 'Approve specification');
        if ($gate['blocked']) {
            return ['_form' => 'This specification is waiting for approval.'];
        }
        $previous = $this->specs->currentByCode((string) $existing['code']);
        Database::transaction(function () use ($id, $existing, $previous, $userId): void {
            if ($previous !== null && (int) $previous['id'] !== $id) {
                $this->specs->markSuperseded((int) $previous['id'], $id);
            }
            $this->specs->markApproved($id, $userId);
            $this->audit->record('sign_specification', $id, 'SPECIFICATION_APPROVED', ['status' => $existing['status']], ['version' => $existing['version']], $userId);
        });
        BusinessEventDispatcher::emit('SPECIFICATION_APPROVED', 'SPECIFICATION', $id, $userId, ['code' => (string) $existing['code']]);
        if ($previous !== null && (int) $previous['id'] !== $id) {
            BusinessEventDispatcher::emit('SPECIFICATION_SUPERSEDED', 'SPECIFICATION', (int) $previous['id'], $userId, ['code' => (string) $existing['code']]);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, data: array<string, mixed>}
     */
    private function fields(array $input, bool $creating): array
    {
        $errors = [];
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($creating && !preg_match('/^[A-Z0-9][A-Z0-9-]{1,38}$/', $code)) {
            $errors['code'] = 'Use a short code such as LBX-ACM-001.';
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        $type = strtoupper(trim((string) ($input['estimator_type'] ?? '')));
        if (!in_array($type, self::ESTIMATORS, true)) {
            $errors['estimator_type'] = 'Choose an estimator type.';
        }
        $allowance = trim((string) ($input['manufacturing_allowance_percent'] ?? '0'));
        if (!Decimal::isNumeric($allowance) || Decimal::cmp($allowance, '0') < 0) {
            $errors['manufacturing_allowance_percent'] = 'Allowance must be zero or more.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'data' => []];
        }
        $date = trim((string) ($input['effective_from'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        return ['errors' => [], 'data' => [
            'code' => $code,
            'name' => mb_substr($name, 0, 180),
            'category' => mb_substr(strtoupper(trim((string) ($input['category'] ?? $type))), 0, 60),
            'estimator_type' => $type,
            'description' => blank_to_null($input['description'] ?? null),
            'effective_from' => $date,
            'effective_to' => null,
            'default_recipe_id' => (int) ($input['default_recipe_id'] ?? 0) > 0 ? (int) $input['default_recipe_id'] : null,
            'default_production_route_id' => (int) ($input['default_production_route_id'] ?? 0) > 0 ? (int) $input['default_production_route_id'] : null,
            'engineering_review_required' => !empty($input['engineering_review_required']) ? 1 : 0,
            'electrical_review_required' => !empty($input['electrical_review_required']) ? 1 : 0,
            'height_review_mm' => $this->optionalNumber($input['height_review_mm'] ?? null),
            'max_width_mm' => $this->optionalNumber($input['max_width_mm'] ?? null),
            'max_height_mm' => $this->optionalNumber($input['max_height_mm'] ?? null),
            'max_post_height_mm' => $this->optionalNumber($input['max_post_height_mm'] ?? null),
            'waste_percent' => Decimal::round((string) ($input['waste_percent'] ?? '0'), 2),
            'manufacturing_allowance_percent' => Decimal::round($allowance, 2),
            'construction_type' => blank_to_null($input['construction_type'] ?? null),
            'frame_profile' => blank_to_null($input['frame_profile'] ?? null),
            'face_material' => blank_to_null($input['face_material'] ?? null),
            'return_material' => blank_to_null($input['return_material'] ?? null),
            'back_material' => blank_to_null($input['back_material'] ?? null),
            'illumination_type' => blank_to_null($input['illumination_type'] ?? null),
            'mounting_method' => blank_to_null($input['mounting_method'] ?? null),
            'finishing' => blank_to_null($input['finishing'] ?? null),
        ]];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function checkFormulas(array $input): ?string
    {
        $sample = SignMath::formulaVariables(['W' => '1000', 'H' => '1000', 'D' => '100', 'Q' => '1', 'AREA' => '1', 'PERIMETER' => '4', 'FACE_AREA' => '1', 'RETURN_DEPTH' => '100', 'VECTOR_AREA' => '1', 'VECTOR_PERIMETER' => '4', 'SIDES' => '1', 'MODULE_WATTS' => '1']);
        foreach ((array) ($input['labour'] ?? []) as $row) {
            $formula = trim((string) ($row['minutes_formula'] ?? ''));
            if ($formula === '') {
                continue;
            }
            try {
                $this->formulas->evaluate($formula, $sample);
            } catch (FormulaRejected $e) {
                return $e->getMessage();
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeChildren(int $id, array $input): void
    {
        foreach ((array) ($input['labour'] ?? []) as $row) {
            if (!is_array($row) || trim((string) ($row['description'] ?? '')) === '') {
                continue;
            }
            $this->specs->insertLabour([
                'specification_id' => $id,
                'operation_code' => strtoupper(trim((string) ($row['operation_code'] ?? 'LABOUR'))),
                'description' => mb_substr((string) $row['description'], 0, 180),
                'minutes_formula' => trim((string) ($row['minutes_formula'] ?? '0')),
                'hourly_rate' => Decimal::round((string) ($row['hourly_rate'] ?? '0'), 4),
                'installer_count' => max(1, (int) ($row['installer_count'] ?? 1)),
            ]);
        }
    }

    private function optionalNumber(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::round($text, 2);
    }
}
