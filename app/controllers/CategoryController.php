<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;

final class CategoryController
{
    public function index(): void
    {
        View::render('categories/index', [
            'title' => 'Categories',
            'activeNav' => 'categories',
            'rows' => (new CategoryRepository())->allWithParent(),
        ]);
    }

    public function create(): void
    {
        $this->form(null, [], ['active' => '1', 'sort_order' => '0']);
    }

    public function store(): void
    {
        $result = (new CategoryService())->save(null, $_POST);
        if ($result['errors'] !== []) {
            $this->form(null, $result['errors'], $_POST);

            return;
        }
        flash('success', 'Category created.');
        redirect('/categories');
    }

    public function edit(string $id): void
    {
        $category = $this->requireCategory($id);
        $this->form($category, [], $category);
    }

    public function update(string $id): void
    {
        $categoryId = route_id($id);
        $result = (new CategoryService())->save($categoryId, $_POST);
        if ($result['errors'] !== []) {
            $this->form((new CategoryRepository())->find($categoryId), $result['errors'], $_POST);

            return;
        }
        flash('success', 'Category updated.');
        redirect('/categories');
    }

    public function deactivate(string $id): void
    {
        $categoryId = route_id($id);
        $active = posted_flag($_POST, 'active', 0) === 1;
        if (!(new CategoryService())->setActive($categoryId, $active)) {
            abort_not_found('That category was not found.');
        }
        flash('success', $active ? 'Category activated.' : 'Category deactivated.');
        redirect('/categories');
    }

    /**
     * @param array<string, mixed>|null $category
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function form(?array $category, array $errors, array $old): void
    {
        View::render('categories/form', [
            'title' => $category === null || !isset($category['id']) ? 'New category' : 'Edit category',
            'activeNav' => 'categories',
            'category' => isset($category['id']) ? $category : null,
            'parents' => (new CategoryRepository())->allWithParent(),
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCategory(string $id): array
    {
        $category = (new CategoryRepository())->find(route_id($id));
        if ($category === null) {
            abort_not_found('That category was not found.');
        }

        return $category;
    }
}
