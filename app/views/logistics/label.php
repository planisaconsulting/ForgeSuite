<section class="sf-panel p-4">
    <p class="text-secondary mb-1">Package label</p>
    <h1><?= e((string) $label['package_code']) ?></h1>
    <p><?= e((string) $label['shipment_number']) ?></p>
    <p><?= e((string) $label['customer']) ?></p>
    <p><?= e((string) $label['destination']) ?></p>
    <p>Package <?= e((string) $label['sequence']) ?></p>
    <?php if ($label['fragile']): ?><p><strong>Fragile</strong></p><?php endif; ?>
    <?php if ($label['handling'] !== ''): ?><p><?= e((string) $label['handling']) ?></p><?php endif; ?>
    <div><?= $label['barcode'] ?></div>
    <p class="mt-3 mb-0"><?= e((string) $label['package_code']) ?></p>
</section>
