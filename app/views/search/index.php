<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Search</h1>
    <p class="sf-muted mb-0">Customers, contacts, opportunities, quotations, jobs, products, purchase orders, invoices, and documents.</p>
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
    <?php if (!empty($showContacts)): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Contacts</h2></div>
            <?php if ($contacts === []): ?><div class="sf-empty"><p>No contacts.</p></div><?php else: ?>
                <ul class="sf-feed"><?php foreach ($contacts as $row): ?><li><a href="<?= e(url('/customers/' . $row['customer_id'])) ?>"><?= e((string) $row['name']) ?></a><small><?= e(customer_label($row)) ?></small></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showJobs)): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Jobs</h2></div>
            <?php if ($jobs === []): ?><div class="sf-empty"><p>No jobs.</p></div><?php else: ?>
                <ul class="sf-feed"><?php foreach ($jobs as $row): ?><li><a href="<?= e(url('/jobs/' . $row['id'])) ?>"><?= e((string) $row['job_number']) ?></a><small><?= e((string) $row['title']) ?></small></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showOrders)): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Purchase orders</h2></div>
            <?php if ($orders === []): ?><div class="sf-empty"><p>No purchase orders.</p></div><?php else: ?>
                <ul class="sf-feed"><?php foreach ($orders as $row): ?><li><a href="<?= e(url('/purchasing/orders/' . $row['id'])) ?>"><?= e((string) $row['po_number']) ?></a><small><?= e((string) $row['supplier_name']) ?></small></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showDocuments)): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Documents</h2></div>
            <?php if ($documents === []): ?><div class="sf-empty"><p>No documents.</p></div><?php else: ?>
                <ul class="sf-feed"><?php foreach (array_slice($documents, 0, 20) as $row): ?><li><a href="<?= e(url('/attachments/' . $row['id'])) ?>"><?= e((string) $row['original_filename']) ?></a><small><?= e((string) $row['entity_type']) ?></small></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showSurveys)): ?>
        <section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Site surveys</h2></div>
            <?php if ($surveys === []): ?><div class="sf-empty"><p>No surveys.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($surveys as $row): ?><li><a href="<?= e(url('/surveys/' . $row['id'])) ?>"><?= e((string) $row['survey_number']) ?></a><small><?= e((string) $row['site_name']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showRecipes)): ?>
        <section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Recipes</h2></div>
            <?php if ($recipes === []): ?><div class="sf-empty"><p>No recipes.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($recipes as $row): ?><li><a href="<?= e(url('/recipes/' . $row['id'] . '/edit')) ?>"><?= e((string) $row['name']) ?></a><small><?= e((string) $row['code']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showPortal)): ?>
        <section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Portal contacts</h2></div>
            <?php if ($portalUsers === []): ?><div class="sf-empty"><p>No portal contacts.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($portalUsers as $row): ?><li><a href="<?= e(url('/admin/portal')) ?>"><?= e((string) $row['email']) ?></a><small><?= e((string) ($row['company_name'] ?? '')) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if (!empty($showTemplates)): ?>
        <section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Signage templates</h2></div>
            <?php $matched = array_values(array_filter($templates, static fn (array $row): bool => $term === '' || stripos((string) $row['name'], $term) !== false || stripos((string) $row['code'], $term) !== false)); ?>
            <?php if ($matched === []): ?><div class="sf-empty"><p>No templates.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($matched as $row): ?><li><a href="<?= e(url('/templates/' . $row['id'] . '/edit')) ?>"><?= e((string) $row['name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
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
