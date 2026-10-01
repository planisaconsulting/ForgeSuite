<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\PricingLevelRepository;

/**
 * Edits markup percentages. The calculator reads these rows on every price
 * request, so a change here is the change the next calculation uses.
 */
final class PricingLevelService
{
    public function __construct(
        private readonly PricingLevelRepository $levels = new PricingLevelRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->levels->find($id);
        if ($existing === null) {
            return ['_form' => 'That pricing level was not found.'];
        }
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        $markupRaw = str_replace(',', '.', trim((string) ($input['markup_percent'] ?? '')));
        if (!Decimal::isNumeric($markupRaw) || Decimal::cmp($markupRaw, '0') < 0 || Decimal::cmp($markupRaw, '1000') > 0) {
            $errors['markup_percent'] = 'Markup must be between 0 and 1000 percent.';
        }
        $sort = trim((string) ($input['sort_order'] ?? '0'));
        if (!preg_match('/^-?\d+$/', $sort)) {
            $errors['sort_order'] = 'Sort order must be a whole number.';
        }
        if ($errors !== []) {
            return $errors;
        }
        $data = [
            'name' => $name,
            'markup_percent' => Decimal::round($markupRaw, 2),
            'active' => posted_flag($input, 'active', 1),
            'sort_order' => (int) $sort,
        ];
        Database::transaction(function () use ($id, $data, $existing): void {
            $this->levels->update($id, $data);
            $this->audit->record('pricing_level', $id, 'updated', [
                'code' => $existing['code'],
                'name' => $existing['name'],
                'markup_percent' => $existing['markup_percent'],
                'active' => $existing['active'],
            ], $data + ['code' => $existing['code']]);
        });

        return [];
    }
}
