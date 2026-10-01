<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Refresh current prices</h1>
    <p class="sf-muted mb-0"><?= e((string) $quote['quote_number']) ?> still uses the saved costs until you confirm. Confirming keeps this revision and prices the next one from the catalogue.</p>
</div>
<div class="sf-table-wrap mb-3">
    <table class="table sf-table">
        <thead><tr><th>Product</th><th>Quoted cost</th><th>Current cost</th><th>Difference</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="4">No catalogue lines to compare.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e((string) $row['name']) ?></td>
                <td><?= e(money((string) $row['saved_cost'])) ?></td>
                <td><?= $row['current_cost'] === null ? 'Not in the catalogue' : e(money((string) $row['current_cost'])) ?></td>
                <td><?php if (!empty($row['changed'])): ?><?= e(money((string) $row['difference'])) ?><?= $row['percent'] !== null ? ' / ' . e((string) $row['percent']) . '%' : '' ?><?php else: ?>No change<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/refresh')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
    <button class="btn btn-sf" type="submit">Use current prices on a new revision</button>
    <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/edit')) ?>">Keep saved prices</a>
</form>
