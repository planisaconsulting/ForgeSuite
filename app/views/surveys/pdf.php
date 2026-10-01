<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222} h1{font-size:18px} table{width:100%;border-collapse:collapse} td,th{border-bottom:1px solid #ccc;padding:4px;text-align:left}</style></head><body>
<p><?= e((string) $company) ?></p>
<h1>Site survey <?= e((string) $survey['survey_number']) ?></h1>
<p><?= e(customer_label($survey)) ?><br><?= e((string) $survey['site_name']) ?><br><?= e((string) ($survey['address_line_1'] ?? '')) ?> <?= e((string) ($survey['city'] ?? '')) ?></p>
<p>Surveyor <?= e((string) ($survey['surveyor_name'] ?? '—')) ?> · <?= e((string) ($survey['survey_date'] ?? '')) ?> · <?= e((string) $survey['status']) ?></p>
<h2>Measurements</h2>
<table><thead><tr><th>Reference</th><th>Type</th><th>W</th><th>H</th><th>Qty</th><th>Notes</th></tr></thead><tbody>
<?php foreach ($measurements as $row): ?><tr><td><?= e((string) $row['reference']) ?></td><td><?= e((string) $row['measurement_type']) ?></td><td><?= e((string) ($row['width_mm'] ?? '')) ?></td><td><?= e((string) ($row['height_mm'] ?? '')) ?></td><td><?= e((string) $row['quantity']) ?></td><td><?= e((string) ($row['notes'] ?? '')) ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2>Installation</h2>
<p><?= e((string) ($survey['environment'] ?? '')) ?> <?= e((string) ($survey['surface_type'] ?? '')) ?></p>
<p><?= nl2br(e((string) ($survey['installation_notes'] ?? ''))) ?></p>
<p><?= nl2br(e((string) ($survey['access_notes'] ?? ''))) ?></p>
<p><?= nl2br(e((string) ($survey['electrical_notes'] ?? ''))) ?></p>
<p><?= nl2br(e((string) ($survey['general_notes'] ?? ''))) ?></p>
<h2>Photos</h2>
<ul><?php foreach ($photos as $photo): ?><li><?= e((string) ($photo['photo_tag'] ?? 'PHOTO')) ?> — <?= e((string) $photo['original_filename']) ?> <?= e((string) ($photo['notes'] ?? '')) ?></li><?php endforeach; ?></ul>
</body></html>
