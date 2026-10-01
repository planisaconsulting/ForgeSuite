<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\OperationsRepository;
use App\Repositories\PricingLevelRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RecipeRepository;
use App\Services\QuoteService;
use App\Services\RecipeService;

final class RecipeController
{
    public function index(): void
    {
        View::render('recipes/index', [
            'title' => 'Recipes',
            'activeNav' => 'recipes',
            'rows' => (new RecipeRepository())->all(trim((string) ($_GET['q'] ?? ''))),
            'canEdit' => can('recipes.edit') || can('recipes.create'),
        ]);
    }

    public function create(): void
    {
        $this->editor(null, [], []);
    }

    public function store(): void
    {
        $result = (new RecipeService())->save(null, $_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->editor(null, $_POST, $result['errors']);

            return;
        }
        flash('success', 'Recipe saved.');
        redirect('/recipes/' . $result['id']);
    }

    public function edit(string $id): void
    {
        $recipe = $this->recipe($id);
        $this->editor($recipe, $recipe, []);
    }

    public function update(string $id): void
    {
        $recipe = $this->recipe($id);
        $result = (new RecipeService())->save((int) $recipe['id'], $_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->editor($recipe, $_POST, $result['errors']);

            return;
        }
        flash('success', 'Recipe updated. A new version was stored.');
        redirect('/recipes/' . $recipe['id']);
    }

    public function duplicate(string $id): void
    {
        $result = (new RecipeService())->duplicate((int) $this->recipe($id)['id'], (int) auth_user()['id']);
        flash($result['id'] ? 'success' : 'error', $result['id'] ? 'Recipe duplicated. It stays inactive until you check it.' : 'The recipe could not be duplicated.');
        redirect($result['id'] ? '/recipes/' . $result['id'] . '/edit' : '/recipes');
    }

    public function deactivate(string $id): void
    {
        $errors = (new RecipeService())->deactivate((int) $this->recipe($id)['id'], (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Recipe deactivated.' : implode(' ', $errors));
        redirect('/recipes/' . $id);
    }

    public function testForm(): void
    {
        View::render('recipes/test', [
            'title' => 'Recipe test',
            'activeNav' => 'recipe-test',
            'recipes' => (new RecipeRepository())->all(''),
            'result' => null,
            'canCost' => can('recipes.view_cost') || can('costing.view'),
            'old' => ['recipe_id' => (int) ($_GET['recipe_id'] ?? 0), 'W' => '2400', 'H' => '1200', 'Q' => '1'],
        ]);
    }

    public function test(): void
    {
        $id = (int) ($_POST['recipe_id'] ?? 0);
        $levels = (new PricingLevelRepository())->active();
        $result = (new RecipeService())->test($id, $_POST, $levels[0] ?? ['markup_percent' => '0', 'active' => 1]);
        View::render('recipes/test', [
            'title' => 'Recipe test',
            'activeNav' => 'recipe-test',
            'recipes' => (new RecipeRepository())->all(''),
            'result' => $result,
            'canCost' => can('recipes.view_cost') || can('costing.view'),
            'old' => $_POST,
        ]);
    }

    public function configure(string $id): void
    {
        $quote = $this->quote($id);
        $recipeId = (int) ($_GET['recipe_id'] ?? $_GET['template_recipe'] ?? 0);
        $template = null;
        if ((int) ($_GET['template_id'] ?? 0) > 0) {
            $template = (new RecipeRepository())->template((int) $_GET['template_id']);
            if ($template !== null) {
                $recipeId = (int) $template['recipe_id'];
            }
        }
        View::render('recipes/configure', [
            'title' => 'Add configured sign',
            'activeNav' => 'quotes',
            'quote' => $quote,
            'recipes' => (new RecipeRepository())->all('', true),
            'templates' => (new RecipeRepository())->templates(true),
            'recipe' => $recipeId > 0 ? (new RecipeRepository())->find($recipeId) : null,
            'inputs' => $recipeId > 0 ? (new RecipeRepository())->inputs($recipeId) : [],
            'template' => $template,
            'preview' => null,
            'canCost' => can('recipes.view_cost') || can('costing.view'),
            'errors' => [],
        ]);
    }

    public function previewConfigure(string $id): void
    {
        $quote = $this->quote($id);
        $recipeId = (int) ($_POST['recipe_id'] ?? 0);
        $levels = (new PricingLevelRepository())->active();
        $levelId = (int) ($quote['pricing_level_id'] ?? 0);
        $level = $levelId > 0 ? (new PricingLevelRepository())->find($levelId) : ($levels[0] ?? ['markup_percent' => '0', 'active' => 1]);
        $result = (new RecipeService())->test($recipeId, $_POST, $level ?? ['markup_percent' => '0', 'active' => 1]);
        View::render('recipes/configure', [
            'title' => 'Add configured sign',
            'activeNav' => 'quotes',
            'quote' => $quote,
            'recipes' => (new RecipeRepository())->all('', true),
            'templates' => (new RecipeRepository())->templates(true),
            'recipe' => (new RecipeRepository())->find($recipeId),
            'inputs' => (new RecipeRepository())->inputs($recipeId),
            'template' => null,
            'preview' => $result,
            'canCost' => can('recipes.view_cost') || can('costing.view'),
            'errors' => $result['ok'] ? [] : ['_form' => (string) $result['error']],
            'old' => $_POST,
        ]);
    }

    public function addToQuote(string $id): void
    {
        $quote = $this->quote($id);
        $result = (new QuoteService())->addRecipeLine((int) $quote['id'], $_POST, (int) $quote['version_number'], (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', implode(' ', $result['errors']));
            redirect('/quotes/' . $quote['id'] . '/configure?recipe_id=' . (int) ($_POST['recipe_id'] ?? 0));
        }
        flash('success', 'Configured sign added to the quotation.');
        redirect('/quotes/' . $quote['id'] . '/edit');
    }

    /**
     * @param array<string, mixed>|null $recipe
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function editor(?array $recipe, array $old, array $errors): void
    {
        $id = (int) ($recipe['id'] ?? 0);
        View::render('recipes/editor', [
            'title' => $recipe === null ? 'New recipe' : 'Edit recipe',
            'activeNav' => 'recipes',
            'recipe' => $recipe,
            'old' => $old,
            'errors' => $errors,
            'inputs' => $id > 0 ? (new RecipeRepository())->inputs($id) : [],
            'items' => $id > 0 ? (new RecipeRepository())->items($id) : [],
            'versions' => $id > 0 ? (new RecipeRepository())->versions($id) : [],
            'categories' => (new RecipeRepository())->categories(),
            'products' => (new ProductRepository())->search('', 'active', null, 300),
            'routes' => (new OperationsRepository())->templates(),
            'recipes' => (new RecipeRepository())->all(''),
            'canCost' => can('recipes.view_cost') || can('costing.view'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function recipe(string $id): array
    {
        $row = (new RecipeRepository())->find(route_id($id));
        if ($row === null) {
            abort_not_found('That recipe was not found.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $id): array
    {
        $row = (new QuoteRepository())->find(route_id($id));
        if ($row === null) {
            abort_not_found('That quotation was not found.');
        }

        return $row;
    }
}
