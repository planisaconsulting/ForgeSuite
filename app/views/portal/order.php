<div class="sf-page-head">
    <h1>Order signage</h1>
    <p class="mb-0">This sends an order for review. It does not start production or create an invoice.</p>
</div>
<?php if (($errors ?? []) !== []): ?><div class="alert alert-danger"><?= e((string) reset($errors)) ?></div><?php endif; ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/portal/orders')) ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="catalogue_item_id">Catalogue item</label>
    <input class="form-control form-control-lg mb-3" id="catalogue_item_id" name="catalogue_item_id" inputmode="numeric" required>
    <label class="form-label" for="quantity">Quantity</label>
    <input class="form-control form-control-lg mb-3" id="quantity" name="quantity" inputmode="decimal" required>
    <label class="form-label" for="customer_po">Your purchase order</label>
    <input class="form-control form-control-lg mb-3" id="customer_po" name="customer_po">
    <label class="form-label" for="cost_centre">Cost centre</label>
    <input class="form-control form-control-lg mb-3" id="cost_centre" name="cost_centre">
    <label class="form-label" for="requested_date">Requested date</label>
    <input class="form-control form-control-lg mb-3" id="requested_date" name="requested_date" type="date">
    <p class="sf-muted">A requested date is not a confirmed date.</p>
    <label class="form-label" for="notes">Notes</label>
    <textarea class="form-control mb-3" id="notes" name="notes" rows="3"></textarea>
    <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(8))) ?>">
    <button class="btn btn-light btn-lg w-100" type="submit">Submit order</button>
</form>
