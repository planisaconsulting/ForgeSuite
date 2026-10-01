<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e($release) ?></p>
    <h1>Help</h1>
</div>
<div class="row g-3">
    <?php foreach ($sections as $section): ?>
        <div class="col-md-6">
            <section class="sf-panel h-100"><div class="p-3">
                <h2 class="h5"><?= e($section['title']) ?></h2>
                <p class="mb-2"><?= e($section['body']) ?></p>
                <?php if ($section['href'] !== ''): ?>
                    <a href="<?= e(url($section['href'])) ?>"><?= e($section['link']) ?></a>
                <?php endif; ?>
            </div></section>
        </div>
    <?php endforeach; ?>
</div>
<p class="sf-muted mt-3">Printed guides for installation, backup, and go-live are in the docs folder shipped with the application. This screen does not change prices, stock, or invoices.</p>
