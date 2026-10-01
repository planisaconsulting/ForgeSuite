<?php
/** Internal job card. Costs stay off unless an authorised user asks for the costing view. */
$qr_url = $qr_url ?? '';
$generated_at = $generated_at ?? '';
$updated = $updated ?? false;
$show_cost = $show_cost ?? false;
$approved = $approved ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e((string) $job['job_number']) ?> job card</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1c1f24; font-size: 12px; padding: 24px; }
        h1 { font-size: 22px; padding: 0; }
        h2 { font-size: 13px; padding: 16px 0 6px; text-transform: uppercase; letter-spacing: 0.05em; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d5d8de; text-align: left; padding: 5px 4px; vertical-align: top; }
        .muted { color: #5c6370; }
        .box { border: 1px solid #1c1f24; padding: 8px 10px; }
        @media print { body { padding: 12mm; } .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <?php if ($updated): ?><p><strong>JOB CARD UPDATED</strong></p><?php endif; ?>
    <p class="muted"><?= e((string) $company) ?> · Internal job card<?= $generated_at !== '' ? ' · ' . e((string) $generated_at) : '' ?></p>
    <h1><?= e((string) $job['job_number']) ?></h1>
    <p><strong><?= e((string) $job['title']) ?></strong></p>
    <table>
        <tr><th>Customer</th><td><?= e(customer_label($job)) ?></td><th>Priority</th><td><?= e((string) $job['priority']) ?></td></tr>
        <tr><th>Contact</th><td><?= e((string) ($job['site_contact_name'] ?? $job['customer_phone'] ?? '')) ?> <?= e((string) ($job['site_contact_phone'] ?? '')) ?></td><th>Due</th><td><?= e((string) ($job['target_date'] ?? '—')) ?></td></tr>
        <tr><th>Delivery</th><td><?= e(enum_label(\App\Domain\DeliveryMethod::class, (string) $job['delivery_method'])) ?></td><th>PO</th><td><?= e((string) ($job['customer_po_number'] ?? '—')) ?></td></tr>
    </table>
    <?php if (!empty($job['site_address'])): ?>
        <h2>Site</h2>
        <p><?= nl2br(e((string) $job['site_address'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($job['description'])): ?>
        <h2>Description</h2>
        <p><?= nl2br(e((string) $job['description'])) ?></p>
    <?php endif; ?>
    <h2>Production items</h2>
    <table>
        <thead><tr><th>Customer description</th><th>Internal</th><th>Size (mm)</th><th>Qty</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e((string) $item['description']) ?></td>
                    <td><?= e((string) ($item['internal_description'] ?? '')) ?></td>
                    <td><?= e(trim((string) ($item['width_mm'] ?? '') . ' × ' . (string) ($item['height_mm'] ?? '') . ((string) ($item['length_mm'] ?? '') !== '' ? ' × ' . (string) $item['length_mm'] : ''), ' ×')) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td><?= e(enum_label(\App\Domain\JobItemStatus::class, (string) $item['production_status'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Materials required</h2>
    <table>
        <thead><tr><th>Material</th><th>Quantity</th><th>Unit</th></tr></thead>
        <tbody>
            <?php if ($requirements === []): ?><tr><td colspan="3">None recorded.</td></tr><?php endif; ?>
            <?php foreach ($requirements as $row): ?>
                <tr>
                    <td><?= e((string) ($row['product_name'] ?? 'Material')) ?></td>
                    <td><?= e((string) $row['final_required_quantity']) ?></td>
                    <td><?= e((string) $row['unit']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Artwork</h2>
    <?php if ($artworks === []): ?><p>No proof uploaded.</p><?php endif; ?>
    <?php foreach ($artworks as $artwork): ?>
        <p><?= e((string) $artwork['title']) ?> rev <?= e((string) $artwork['revision_number']) ?> · <?= e(enum_label(\App\Domain\ArtworkStatus::class, (string) $artwork['status'])) ?></p>
    <?php endforeach; ?>
    <?php if (!empty($job['production_notes'])): ?>
        <h2>Production notes</h2>
        <p><?= nl2br(e((string) $job['production_notes'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($job['installation_notes'])): ?>
        <h2>Installation notes</h2>
        <p><?= nl2br(e((string) $job['installation_notes'])) ?></p>
    <?php endif; ?>
    <h2>Checklist</h2>
    <?php if ($stages === []): ?>
        <div class="box">Artwork checked<br>Materials ready<br>Produced<br>Quality checked<br>Packed</div>
    <?php else: ?>
        <?php foreach ($stages as $stage): ?>
            <div class="box"><?= e((string) $stage['stage_name']) ?> · <?= e((string) $stage['status']) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($show_cost && !empty($costing)): ?>
        <h2>Internal costing</h2>
        <p>Actual cost <?= e(money((string) ($costing['actual_total_cost'] ?? '0'))) ?></p>
    <?php endif; ?>
    <?php if ($qr_url !== ''): ?>
        <h2>Scan</h2>
        <p class="box"><?= e((string) $qr_url) ?></p>
    <?php endif; ?>
</body>
</html>
