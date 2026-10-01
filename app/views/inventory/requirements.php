<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Material requirements</h1><p class="sf-muted mb-0">Required, reserved, issued, ordered, and still short for open jobs.</p></div>
<div class="table-responsive sf-panel">
    <table class="table sf-table mb-0">
        <thead><tr><th>Job</th><th>Customer</th><th>Product</th><th>Required</th><th>Reserved</th><th>Issued</th><th>Ordered</th><th>Received</th><th>Shortage</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/jobs/' . $row['job_id'] . '?tab=materials')) ?>"><?= e((string) $row['job_number']) ?></a></td>
                <td><?= e((string) $row['customer_label']) ?></td>
                <td><?= e((string) ($row['product_name'] ?? '—')) ?></td>
                <td><?= e((string) $row['required_qty']) ?></td>
                <td><?= e((string) $row['reserved_qty']) ?></td>
                <td><?= e((string) $row['issued_qty']) ?></td>
                <td><?= e((string) $row['ordered_qty']) ?></td>
                <td><?= e((string) $row['received_qty']) ?></td>
                <td><?= (string) $row['shortage'] === '0.0000' ? '0' : '<strong>' . e((string) $row['shortage']) . '</strong>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
