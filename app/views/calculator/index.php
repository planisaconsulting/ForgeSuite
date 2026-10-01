<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Pricing calculator</h1>
    <p class="sf-muted mb-0">Choose a product, enter the size, and the server returns the cost and each markup band. Figures typed in the browser are not trusted.</p>
</div>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <form class="sf-panel sf-form" id="calculator-form" data-url="<?= e(url('/calculator/price')) ?>" autocomplete="off">
            <div class="p-3">
                <label class="form-label" for="calc-category">Category</label>
                <select class="form-select mb-3" id="calc-category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ((int) $category['active'] !== 1) { continue; } ?>
                        <option value="<?= e((string) $category['id']) ?>"><?= $category['parent_id'] ? '— ' : '' ?><?= e((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label" for="calc-product">Product <span class="sf-req">*</span></label>
                <select class="form-select mb-3" id="calc-product" name="product_id" required>
                    <option value="">Choose a product</option>
                </select>
                <div class="sf-calc-field" data-inputs="AREA SHEET">
                    <label class="form-label" for="calc-width">Width (mm)</label>
                    <input class="form-control mb-3" id="calc-width" name="width_mm" inputmode="decimal">
                </div>
                <div class="sf-calc-field" data-inputs="AREA SHEET">
                    <label class="form-label" for="calc-height">Height (mm)</label>
                    <input class="form-control mb-3" id="calc-height" name="height_mm" inputmode="decimal">
                </div>
                <div class="sf-calc-field" data-inputs="LINEAR_METRE">
                    <label class="form-label" for="calc-length">Length (mm)</label>
                    <input class="form-control mb-3" id="calc-length" name="length_mm" inputmode="decimal">
                </div>
                <div class="sf-calc-field" data-inputs="AREA LINEAR_METRE UNIT SHEET CUSTOM">
                    <label class="form-label" for="calc-qty">Quantity</label>
                    <input class="form-control mb-3" id="calc-qty" name="quantity" inputmode="decimal" value="1">
                </div>
                <div class="sf-calc-field" data-inputs="LITRE">
                    <label class="form-label" for="calc-litres">Litres</label>
                    <input class="form-control mb-3" id="calc-litres" name="litres" inputmode="decimal">
                </div>
                <div class="sf-calc-field" data-inputs="HOUR">
                    <label class="form-label" for="calc-hours">Hours</label>
                    <input class="form-control mb-3" id="calc-hours" name="hours" inputmode="decimal" placeholder="1.5">
                </div>
                <fieldset class="sf-calc-field mb-3" data-inputs="WASTE" id="calc-waste">
                    <legend class="form-label">Wastage treatment</legend>
                    <div id="calc-waste-options"></div>
                    <div class="sf-calc-field mt-2" data-inputs="MANUAL">
                        <label class="form-label" for="calc-manual-width">Manual billable width (mm)</label>
                        <input class="form-control mb-2" id="calc-manual-width" name="manual_width_mm" inputmode="decimal">
                        <label class="form-label" for="calc-manual-area">Manual billable area (m²)</label>
                        <input class="form-control mb-2" id="calc-manual-area" name="manual_area" inputmode="decimal">
                        <label class="form-label" for="calc-manual-sheets">Manual sheet count</label>
                        <input class="form-control" id="calc-manual-sheets" name="manual_sheets" inputmode="decimal">
                        <div class="form-text">Manual area replaces the whole line. Manual width uses the print length. A sheet count is the number of sheets to charge.</div>
                    </div>
                </fieldset>
                <p class="sf-muted mb-0" id="calc-hint">Select a product to see the inputs it needs.</p>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-7">
        <section class="sf-panel sf-calc-result" id="calc-result" aria-live="polite">
            <div class="sf-empty">
                <p>Cost and selling prices appear here after you enter a size. The server calculates them.</p>
            </div>
        </section>
    </div>
</div>
<script type="application/json" id="calc-catalogue"><?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="application/json" id="calc-categories"><?= json_encode(array_map(static function (array $row): array {
    return ['id' => (int) $row['id'], 'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id']];
}, $categories), JSON_THROW_ON_ERROR) ?></script>
