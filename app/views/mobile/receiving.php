<div class="sf-page-head">
    <h1 class="display-6">Receive</h1>
    <p>Scan the purchase order, count the goods, then confirm online. A draft on this phone does not post stock.</p>
</div>
<form class="sf-panel" method="get" action="<?= e(url('/purchasing')) ?>">
    <label class="form-label">Purchase order</label>
    <input class="form-control form-control-lg mb-3" name="po" inputmode="text">
    <label class="form-label">Quantity</label>
    <input class="form-control form-control-lg mb-3" name="qty" inputmode="decimal">
    <label class="form-label">Location</label>
    <input class="form-control form-control-lg mb-3" name="location" inputmode="text">
    <button class="btn btn-light btn-lg w-100" type="submit">Open receiving</button>
</form>
