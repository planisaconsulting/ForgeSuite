<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\OperationsRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RecipeRepository;

final class TemplateController
{
    public function index(): void
    {
        View::render('templates/index', [
            'title' => 'Signage templates',
            'activeNav' => 'templates',
            'rows' => (new RecipeRepository())->templates(false),
            'canManage' => can('templates.manage'),
        ]);
    }

    public function create(): void
    {
        $this->form([], []);
    }

    public function store(): void
    {
        $data = $this->data($_POST);
        if ($data['errors'] !== []) {
            $this->form($_POST, $data['errors']);

            return;
        }
        $id = (new RecipeRepository())->insertTemplate($data['row']);
        flash('success', 'Template saved.');
        redirect('/templates');
        unset($id);
    }

    public function edit(string $id): void
    {
        $row = (new RecipeRepository())->template(route_id($id));
        if ($row === null) {
            abort_not_found('That template was not found.');
        }
        $this->form($row, []);
    }

    public function update(string $id): void
    {
        $existing = (new RecipeRepository())->template(route_id($id));
        if ($existing === null) {
            abort_not_found('That template was not found.');
        }
        $data = $this->data($_POST);
        if ($data['errors'] !== []) {
            $this->form(array_merge($existing, $_POST), $data['errors']);

            return;
        }
        (new RecipeRepository())->updateTemplate((int) $existing['id'], $data['row']);
        flash('success', 'Template updated.');
        redirect('/templates');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors): void
    {
        View::render('templates/form', [
            'title' => empty($old['id']) ? 'New template' : 'Edit template',
            'activeNav' => 'templates',
            'old' => $old,
            'errors' => $errors,
            'recipes' => (new RecipeRepository())->all('', true),
            'products' => (new ProductRepository())->search('', 'active', null, 200),
            'routes' => (new OperationsRepository())->templates(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, row: array<string, mixed>}
     */
    private function data(array $input): array
    {
        $errors = [];
        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string) ($input['code'] ?? ''))) ?? '');
        $name = trim((string) ($input['name'] ?? ''));
        if ($code === '') {
            $errors['code'] = 'Code is required.';
        }
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        $defaults = [
            'W' => trim((string) ($input['default_w'] ?? '')),
            'H' => trim((string) ($input['default_h'] ?? '')),
            'Q' => trim((string) ($input['default_q'] ?? '1')),
        ];

        return ['errors' => $errors, 'row' => [
            'code' => $code,
            'name' => $name,
            'category' => blank_to_null($input['category'] ?? null),
            'finished_product_id' => ((int) ($input['finished_product_id'] ?? 0)) > 0 ? (int) $input['finished_product_id'] : null,
            'recipe_id' => ((int) ($input['recipe_id'] ?? 0)) > 0 ? (int) $input['recipe_id'] : null,
            'default_inputs_json' => json_encode($defaults),
            'customer_description' => blank_to_null($input['customer_description'] ?? null),
            'internal_description' => blank_to_null($input['internal_description'] ?? null),
            'notes' => blank_to_null($input['notes'] ?? null),
            'production_route_template_id' => ((int) ($input['production_route_template_id'] ?? 0)) > 0 ? (int) $input['production_route_template_id'] : null,
            'active' => !empty($input['active']) ? 1 : 0,
            'created_by' => (int) (auth_user()['id'] ?? 0),
        ]];
    }
}
