<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\PricingLevelRepository;
use App\Services\PricingLevelService;

final class PricingLevelController
{
    public function index(): void
    {
        View::render('pricing/index', [
            'title' => 'Pricing levels',
            'activeNav' => 'pricing',
            'rows' => (new PricingLevelRepository())->all(),
            'canManage' => can('pricing.manage'),
        ]);
    }

    public function edit(string $id): void
    {
        $level = $this->requireLevel($id);
        $this->form($level, [], $level);
    }

    public function update(string $id): void
    {
        $levelId = route_id($id);
        $errors = (new PricingLevelService())->update($levelId, $_POST);
        if ($errors !== []) {
            $level = (new PricingLevelRepository())->find($levelId);
            if ($level === null) {
                abort_not_found('That pricing level was not found.');
            }
            $this->form($level, $errors, $_POST);

            return;
        }
        flash('success', 'Pricing level updated. The calculator will use this markup on the next price.');
        redirect('/pricing-levels');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireLevel(string $id): array
    {
        $level = (new PricingLevelRepository())->find(route_id($id));
        if ($level === null) {
            abort_not_found('That pricing level was not found.');
        }

        return $level;
    }

    /**
     * @param array<string, mixed> $level
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function form(array $level, array $errors, array $old): void
    {
        View::render('pricing/form', [
            'title' => 'Edit ' . $level['code'],
            'activeNav' => 'pricing',
            'level' => $level,
            'errors' => $errors,
            'old' => $old,
        ]);
    }
}
