<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Search</h1>
    <p class="sf-muted mb-0">Customers, opportunities, quotations, products, suppliers, and inventory codes.</p>
</div>
<form class="sf-filters" method="get" action="<?= e(url('/search')) ?>">
    <label class="visually-hidden" for="search-q">Search</label>
    <input class="form-control" id="search-q" type="search" name="q" value="<?= e($term) ?>" placeholder="Name, quote number, opportunity, SKU">
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
    <?php if ($showOpportunities): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Opportunities</h2></div>
            <?php if ($opportunities === []): ?><div class="sf-empty"><p>No opportunities.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($opportunities as $row): ?>
                        <li><a href="<?= e(url('/opportunities/' . $row['id'])) ?>"><?= e((string) $row['opportunity_number']) ?></a><small><?= e((string) $row['title']) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($showQuotes): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Quotations</h2></div>
            <?php if ($quotes === []): ?><div class="sf-empty"><p>No quotations.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($quotes as $row): ?>
                        <li><a href="<?= e(url('/quotes/' . $row['id'])) ?>"><?= e((string) $row['quote_number']) ?></a><small><?= e(customer_label($row)) ?> · <?= e((string) $row['status']) ?></small></li>
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
    <?php if (!empty($showInventory)): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Inventory</h2></div>
            <?php if ($inventory === []): ?><div class="sf-empty"><p>No rolls, sheets, or offcuts.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($inventory as $row): ?>
                        <li><a href="<?= e(url('/inventory/items/' . $row['id'])) ?>"><?= e((string) $row['inventory_code']) ?></a><small><?= e((string) $row['product_name']) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showFinance)): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Finance</h2></div>
            <?php if ($finance === []): ?><div class="sf-empty"><p>No invoices, credit notes, or payments.</p></div><?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($finance as $row):
                        $href = match ($row['kind']) {
                            'credit' => '/credit-notes/' . $row['id'],
                            'payment' => '/payments/' . $row['id'],
                            default => '/invoices/' . $row['id'],
                        };
                    ?>
                        <li><a href="<?= e(url($href)) ?>"><?= e((string) $row['reference']) ?></a><small><?= e((string) $row['kind']) ?></small></li>
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
