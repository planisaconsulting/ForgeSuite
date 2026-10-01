<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\CategoryRepository;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(?int $id, array $input): array
    {
        $existing = $id === null ? null : $this->categories->find($id);
        if ($id !== null && $existing === null) {
            return ['errors' => ['_form' => 'That category was not found.'], 'id' => null];
        }
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif ($this->categories->nameTaken($name, $id)) {
            $errors['name'] = 'A category with that name already exists.';
        }
        $parentId = (int) ($input['parent_id'] ?? 0);
        $parent = $parentId > 0 ? $parentId : null;
        if ($parent !== null) {
            if ($this->categories->find($parent) === null) {
                $errors['parent_id'] = 'That parent category was not found.';
            } elseif ($id !== null && ($parent === $id || in_array($parent, $this->categories->descendantIds($id), true))) {
                $errors['parent_id'] = 'A category cannot sit inside itself.';
            }
        }
        $sort = trim((string) ($input['sort_order'] ?? '0'));
        if ($sort === '' || !preg_match('/^-?\d+$/', $sort)) {
            $errors['sort_order'] = 'Sort order must be a whole number.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        $data = [
            'parent_id' => $parent,
            'name' => $name,
            'description' => blank_to_null($input['description'] ?? null),
            'active' => posted_flag($input, 'active', 1),
            'sort_order' => (int) $sort,
        ];
        $saved = $id ?? 0;
        Database::transaction(function () use ($id, $data, $existing, &$saved): void {
            if ($id === null) {
                $saved = $this->categories->insert($data);
                $this->audit->record('product_category', $saved, 'created', null, $data);
            } else {
                $saved = $id;
                $this->categories->update($id, $data);
                $this->audit->record('product_category', $id, 'updated', $existing, $data);
            }
        });

        return ['errors' => [], 'id' => $saved];
    }

    public function setActive(int $id, bool $active): bool
    {
        if ($this->categories->find($id) === null) {
            return false;
        }
        Database::transaction(function () use ($id, $active): void {
            $this->categories->setActive($id, $active ? 1 : 0);
            $this->audit->record('product_category', $id, $active ? 'activated' : 'deactivated', null, ['active' => $active ? 1 : 0]);
        });

        return true;
    }
}
