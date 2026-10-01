<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Test a recipe</h1><p class="sf-muted">No quotation is created.</p></div>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/recipes/test')) ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-12"><select class="form-select form-select-lg" name="recipe_id" required><option value="">Recipe</option><?php foreach ($recipes as $recipe): ?><option value="<?= e((string) $recipe['id']) ?>" <?= (int) ($old['recipe_id'] ?? 0) === (int) $recipe['id'] ? 'selected' : '' ?>><?= e((string) $recipe['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-4"><label class="form-label">W mm</label><input class="form-control form-control-lg" name="W" value="<?= e((string) ($old['W'] ?? '2400')) ?>"></div>
        <div class="col-4"><label class="form-label">H mm</label><input class="form-control form-control-lg" name="H" value="<?= e((string) ($old['H'] ?? '1200')) ?>"></div>
        <div class="col-4"><label class="form-label">Q</label><input class="form-control form-control-lg" name="Q" value="<?= e((string) ($old['Q'] ?? '1')) ?>"></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Calculate</button></div>
    </div>
</form>
<?php if (is_array($result)): ?>
    <?php if (!$result['ok']): ?><div class="alert alert-danger"><?= e((string) $result['error']) ?></div><?php else: ?>
        <section class="sf-panel mb-3"><div class="p-3">
            <p>Each face <?= e((string) $result['face_area_each']) ?> m² · total face <?= e((string) $result['face_area_total']) ?> m²</p>
            <?php if ($canCost): ?><p>Cost <?= e(money((string) $result['total_cost'])) ?> · sell <?= e(money((string) $result['selling_price'])) ?> · GP <?= e(money((string) $result['gross_profit'])) ?> · margin <?= e((string) ($result['gross_margin'] ?? '—')) ?>%</p><?php else: ?><p>Selling price <?= e(money((string) $result['selling_price'])) ?></p><?php endif; ?>
        </div>
        <table class="table sf-table mb-0"><thead><tr><th>Component</th><th>Qty</th><th>Unit</th><?php if ($canCost): ?><th>Cost</th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($result['components'] as $row): ?><tr><td><?= e((string) $row['description']) ?></td><td><?= e((string) $row['costed_quantity']) ?></td><td><?= e((string) $row['unit']) ?></td><?php if ($canCost): ?><td><?= e(money((string) $row['total_cost'])) ?></td><?php endif; ?></tr><?php endforeach; ?>
        </tbody></table>
        <?php if ($result['production_route'] !== []): ?><div class="p-3">Route: <?php foreach ($result['production_route'] as $stage): ?><?= e((string) $stage['name']) ?> · <?php endforeach; ?></div><?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
