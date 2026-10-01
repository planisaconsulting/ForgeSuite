<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Search</h1>
    <p class="sf-muted mb-0">Customers, products, and suppliers. Quotes, jobs, and invoices will join this search later.</p>
</div>
<form class="sf-filters" method="get" action="<?= e(url('/search')) ?>">
    <label class="visually-hidden" for="search-q">Search</label>
    <input class="form-control" id="search-q" type="search" name="q" value="<?= e($term) ?>" placeholder="Name, email, SKU, supplier">
    <button class="btn btn-sf" type="submit">Search</button>
</form>
<?php if ($term === ''): ?>
    <section class="sf-panel"><div class="sf-empty"><p>Type at least a few letters.</p></div></section>
<?php else: ?>
    <?php if ($showCustomers): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Customers</h2></div>
            <?php if ($customers === []): ?><div class="sf-empty"><p>No customers.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($customers as $row): ?>
                        <li><a href="<?= e(url('/customers/' . $row['id'])) ?>"><?= e(customer_label($row)) ?></a><small><?= e((string) ($row['email'] ?? '')) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($showProducts): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Products</h2></div>
            <?php if ($products === []): ?><div class="sf-empty"><p>No products.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($products as $row): ?>
                        <li><a href="<?= e(url('/products/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a><small><?= e((string) $row['sku']) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($showSuppliers): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Suppliers</h2></div>
            <?php if ($suppliers === []): ?><div class="sf-empty"><p>No suppliers.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($suppliers as $row): ?>
                        <li><a href="<?= e(url('/suppliers/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a><small><?= e((string) ($row['email'] ?? '')) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
