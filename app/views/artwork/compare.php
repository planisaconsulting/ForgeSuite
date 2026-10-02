<div class="sf-page-head">
    <h1>Compare <?= e((string) $artwork['artwork_number']) ?></h1>
</div>
<section class="sf-panel p-3">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <h2 class="h5">Earlier proof</h2>
            <?php if ($left === null): ?><p>Choose a revision.</p><?php else: ?><p><?= e((string) $left['original_filename']) ?></p><?php endif; ?>
        </div>
        <div class="col-12 col-md-6">
            <h2 class="h5">Later proof</h2>
            <?php if ($right === null): ?><p>Choose a revision.</p><?php else: ?><p><?= e((string) $right['original_filename']) ?></p><?php endif; ?>
        </div>
    </div>
    <p class="mt-3">The designer’s change summary is the explanation of what changed. A pixel overlay is not a reading of the design.</p>
    <label class="form-label" for="overlay">Overlay opacity</label>
    <input id="overlay" type="range" min="0" max="100" value="40">
</section>
