<?php $rfq = $opened['rfq']; $invite = $opened['invitation']; ?>
<div class="sf-page-head">
    <h1><?= e((string) $rfq['rfq_number']) ?></h1>
    <p><?= e((string) $rfq['title']) ?> · <?= e((string) $invite['supplier_name']) ?></p>
</div>
<section class="sf-panel mb-3">
    <p>Reply with the price and quantity you can supply. This page does not show other suppliers.</p>
    <?php foreach ($opened['items'] as $item): ?>
        <form method="post" class="mb-3">
            <p><strong><?= e((string) $item['description']) ?></strong> · <?= e((string) $item['quantity']) ?> <?= e((string) $item['unit']) ?></p>
            <input type="hidden" name="rfq_item_id" value="<?= (int) $item['id'] ?>">
            <input type="hidden" name="product_id" value="<?= (int) ($item['product_id'] ?? 0) ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Your description</label><input class="form-control form-control-lg" name="offered_description" value="<?= e((string) $item['description']) ?>"></div>
                <div class="col-md-2"><label class="form-label">Available</label><input class="form-control form-control-lg" name="available_quantity" inputmode="decimal"></div>
                <div class="col-md-2"><label class="form-label">Unit price</label><input class="form-control form-control-lg" name="unit_price" inputmode="decimal"></div>
                <div class="col-md-2"><label class="form-label">Lead days</label><input class="form-control form-control-lg" name="lead_time_days" inputmode="numeric"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-light btn-lg w-100" type="submit">Submit</button></div>
            </div>
        </form>
    <?php endforeach; ?>
</section>
<section class="sf-panel">
    <h2 class="h5">Your quotations</h2>
    <ul><?php foreach ($opened['own_quotes'] as $quote): ?><li><?= e((string) $quote['quote_number']) ?> · <?= e((string) $quote['status']) ?> · <?= e((string) $quote['total']) ?></li><?php endforeach; ?></ul>
</section>
