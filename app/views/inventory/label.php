<div class="sf-panel p-4" style="max-width: 420px">
    <p class="sf-kicker mb-1">Sign-Forge</p>
    <h1 class="h3"><?= e((string) $item['inventory_code']) ?></h1>
    <p><?= e((string) $item['product_name']) ?></p>
    <p><?= e(trim((string) ($item['width_mm'] ?? '') . ' × ' . ((string) ($item['height_mm'] ?? $item['length_mm'] ?? '')), ' ×')) ?> mm</p>
    <p>Received <?= e((string) ($item['received_date'] ?? '')) ?></p>
    <p class="sf-muted"><?= e(url('/inventory/code/' . rawurlencode((string) $item['inventory_code']))) ?></p>
    <button class="btn btn-sf btn-lg" type="button" onclick="window.print()">Print</button>
</div>
