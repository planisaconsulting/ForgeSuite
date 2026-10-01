<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Offcuts</h1><p class="sf-muted mb-0">Search by the minimum piece you need. Rotation is optional. Nesting is not calculated.</p></div>
<form class="sf-filters mb-3" method="get">
    <select class="form-select form-select-lg" name="product_id" required>
        <option value="">Product</option>
        <?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>" <?= $productId === (int) $product['id'] ? 'selected' : '' ?>><?= e((string) $product['name']) ?></option><?php endforeach; ?>
    </select>
    <input class="form-control form-control-lg" name="min_width" inputmode="decimal" placeholder="Min width mm" value="<?= e($minWidth) ?>">
    <input class="form-control form-control-lg" name="min_height" inputmode="decimal" placeholder="Min height mm" value="<?= e($minHeight) ?>">
    <label class="form-check"><input class="form-check-input" type="checkbox" name="rotate" value="1"> Allow rotation</label>
    <button class="btn btn-sf btn-lg" type="submit">Find offcuts</button>
</form>
<div class="sf-panel mb-3">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No matching offcuts.</p></div><?php else: ?>
        <div class="table-responsive"><table class="table sf-table mb-0"><thead><tr><th>Code</th><th>Size</th><th>Location</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/inventory/items/' . $row['id'])) ?>"><?= e((string) $row['inventory_code']) ?></a></td>
                <td><?= e((string) $row['width_mm']) ?> × <?= e((string) ($row['height_mm'] ?? $row['length_mm'])) ?> mm<?= !empty($row['fits_rotated']) ? ' · rotated' : '' ?></td>
                <td><?= e((string) $row['location_name']) ?></td>
                <td><?= e((string) $row['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<?php if (can('inventory.consume') || can('inventory.receive')): ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Create usable offcut</h2></div>
    <form class="p-3 row g-3" method="post" action="<?= e(url('/inventory/offcuts')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-4"><select class="form-select form-select-lg" name="product_id" required><option value="">Product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><select class="form-select form-select-lg" name="stock_location_id"><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="width_mm" placeholder="Width mm" required></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="height_mm" placeholder="Height mm" required></div>
        <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Create offcut</button></div>
    </form>
</section>
<?php endif; ?>
