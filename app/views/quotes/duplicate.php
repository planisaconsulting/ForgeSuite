<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Duplicate <?= e((string) $quote['quote_number']) ?></h1>
    <p class="sf-muted mb-0">This creates a new quotation number in draft. The original is left as it is.</p>
</div>
<form class="sf-panel sf-form" method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/duplicate')) ?>">
    <?= csrf_field() ?>
    <label class="form-check"><input class="form-check-input" type="radio" name="price_mode" value="KEEP" checked> Keep the saved prices</label>
    <label class="form-check"><input class="form-check-input" type="radio" name="price_mode" value="CURRENT"> Use current catalogue prices</label>
    <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Create the copy</button></div>
</form>
