<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Products</h1>
        <p class="sf-muted mb-0">Materials, components, services, labour, and consumables. Costs shown are buy or internal costs.</p>
    </div>
    <?php if ($canManage): ?>
        <a class="btn btn-sf" href="<?= e(url('/products/new')) ?>">Add product</a>
    <?php endif; ?>
</div>
<?php
$basePath = '/products';
ob_start();
?>
<label class="visually-hidden" for="category_id">Category</label>
<select class="form-select" id="category_id" name="category_id">
    <option value="0">All categories</option>
    <?php foreach ($categories as $category): ?>
        <option value="<?= e((string) $category['id']) ?>" <?= (int) $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
            <?= $category['parent_id'] ? '— ' : '' ?><?= e((string) $category['name']) ?>
        </option>
    <?php endforeach; ?>
</select>
<?php
$extra = ob_get_clean();
require base_path('app/views/partials/list_tools.php');
?>
<?php if ($rows === []): ?>
    <section class="sf-panel"><div class="sf-empty"><p>No products match that search.</p></div></section>
<?php else: ?>
    <div class="table-responsive sf-panel">
        <table class="table sf-table sf-stack align-middle mb-0">
            <thead>
                <tr><th>SKU</th><th>Name</th><th>Method</th><th>Cost</th><th>Supplier</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="SKU"><?= e((string) $row['sku']) ?></td>
                        <td data-label="Name"><a href="<?= e(url('/products/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                        <td data-label="Method"><?= e(enum_label(App\Domain\PricingMethod::class, (string) $row['pricing_method'])) ?></td>
                        <td data-label="Cost"><?= e(money((string) $row['cost_price'])) ?> / <?= e(App\Domain\Units::label((string) $row['cost_unit'])) ?></td>
                        <td data-label="Supplier"><?= e((string) ($row['supplier_name'] ?? '')) ?></td>
                        <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
