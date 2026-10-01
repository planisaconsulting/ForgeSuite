<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $quote['quote_number']) ?> · Revision <?= e((string) $quote['revision_number']) ?></p>
        <h1><?= e(customer_label($quote)) ?></h1>
        <p class="mb-0" id="save-state" data-state="saved">Saved</p>
    </div>
    <div class="sf-action-row">
        <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'])) ?>">View</a>
        <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/pdf')) ?>">PDF</a>
    </div>
</div>
<?php if ($expiry === 'EXPIRED'): ?><div class="alert alert-warning sf-alert">This quotation date has passed. It is still stored.</div><?php elseif ($expiry === 'EXPIRING'): ?><div class="alert alert-warning sf-alert">Expiring soon.</div><?php endif; ?>

<form class="sf-panel sf-form mb-3" id="quote-header" method="post" action="<?= e(url('/quotes/' . $quote['id'])) ?>" data-autosave="<?= e(url('/quotes/' . $quote['id'] . '/autosave')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="version_number" id="version_number" value="<?= e((string) $quote['version_number']) ?>">
    <div class="sf-panel-head"><h2>Customer and quote</h2></div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="contact_id">Contact</label>
            <select class="form-select" id="contact_id" name="contact_id">
                <option value="">None</option>
                <?php foreach ($contacts as $contact): ?>
                    <option value="<?= e((string) $contact['id']) ?>" <?= (int) $quote['contact_id'] === (int) $contact['id'] ? 'selected' : '' ?>><?= e((string) $contact['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="quote_date">Quote date</label>
            <input class="form-control" type="date" id="quote_date" name="quote_date" value="<?= e((string) $quote['quote_date']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="expiry_date">Expiry</label>
            <input class="form-control" type="date" id="expiry_date" name="expiry_date" value="<?= e((string) ($quote['expiry_date'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="assigned_to">Salesperson</label>
            <select class="form-select" id="assigned_to" name="assigned_to">
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (int) $quote['assigned_to'] === (int) $user['id'] ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pricing_level_id">Pricing level</label>
            <select class="form-select" id="pricing_level_id" name="pricing_level_id">
                <?php foreach ($levels as $level): ?>
                    <option value="<?= e((string) $level['id']) ?>" <?= (int) $quote['pricing_level_id'] === (int) $level['id'] ? 'selected' : '' ?>><?= e((string) $level['code']) ?> · <?= e((string) $level['markup_percent']) ?>% markup</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vat_mode">VAT</label>
            <select class="form-select" id="vat_mode" name="vat_mode">
                <?php foreach ($modes as $mode): ?>
                    <option value="<?= e($mode->value) ?>" <?= $quote['vat_mode'] === $mode->value ? 'selected' : '' ?>><?= e($mode->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Rate on this quotation: <?= e((string) $quote['vat_rate']) ?>%. Changing the company rate later will not alter it.</div>
        </div>
        <?php if ($canDiscount): ?>
            <div class="col-md-4">
                <label class="form-label" for="discount_type">Quote discount</label>
                <select class="form-select" id="discount_type" name="discount_type">
                    <?php foreach ($discounts as $type): ?>
                        <option value="<?= e($type->value) ?>" <?= $quote['discount_type'] === $type->value ? 'selected' : '' ?>><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="discount_value">Discount value</label>
                <input class="form-control" id="discount_value" name="discount_value" value="<?= e((string) $quote['discount_value']) ?>">
            </div>
        <?php endif; ?>
        <div class="col-md-4">
            <label class="form-label" for="deposit_type">Deposit</label>
            <select class="form-select" id="deposit_type" name="deposit_type">
                <?php foreach (['NONE' => 'None', 'PERCENTAGE' => 'Percentage', 'FIXED_AMOUNT' => 'Fixed amount'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $quote['deposit_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="deposit_value">Deposit value</label>
            <input class="form-control" id="deposit_value" name="deposit_value" value="<?= e((string) $quote['deposit_value']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="customer_notes">Customer notes</label>
            <textarea class="form-control" id="customer_notes" name="customer_notes" rows="3"><?= e((string) ($quote['customer_notes'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="internal_notes">Internal notes</label>
            <textarea class="form-control" id="internal_notes" name="internal_notes" rows="3"><?= e((string) ($quote['internal_notes'] ?? '')) ?></textarea>
            <div class="form-text">Internal notes stay off the customer PDF.</div>
        </div>
        <div class="col-12">
            <label class="form-label" for="terms">Terms</label>
            <textarea class="form-control" id="terms" name="terms" rows="5"><?= e((string) ($quote['terms'] ?? '')) ?></textarea>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save draft</button>
        <span class="sf-muted">Drafts also save themselves after you pause.</span>
    </div>
</form>

<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Quote items</h2></div>
    <div id="quote-lines">
        <?php foreach ($items as $item): ?>
            <article class="sf-quote-line" draggable="true" data-line-id="<?= e((string) $item['id']) ?>">
                <header>
                    <strong><?= e((string) $item['product_name_snapshot']) ?></strong>
                    <span><?= e(quote_line_size($item)) ?></span>
                    <span><?= e(money((string) $item['line_total'])) ?></span>
                </header>
                <p class="mb-1"><?= e((string) $item['customer_description']) ?></p>
                <?php if ((int) $item['is_optional'] === 1 && (int) $item['include_optional'] !== 1): ?>
                    <p class="sf-kicker">Optional · not in the total</p>
                <?php endif; ?>
                <?php if ($canCost): ?>
                    <p class="sf-muted mb-1">Cost <?= e(money((string) $item['total_cost'])) ?> · markup <?= e((string) $item['markup_percent_snapshot']) ?>% · sell <?= e(money((string) $item['final_sell_price'])) ?> · profit <?= e(money((string) \App\Helpers\Decimal::sub((string) $item['line_total'], (string) $item['total_cost']))) ?>
                        <?php if ((int) $item['price_overridden'] === 1): ?> · override, calculated <?= e(money((string) $item['calculated_price'])) ?><?php endif; ?>
                    </p>
                <?php endif; ?>
                <div class="sf-action-row">
                    <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/lines/' . $item['id'] . '/duplicate')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><button class="btn btn-sm btn-outline-light" type="submit">Duplicate</button></form>
                    <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/lines/' . $item['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this line?');"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><button class="btn btn-sm btn-outline-light" type="submit">Remove</button></form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
            <div class="sf-empty"><p>No lines yet. Add a catalogue product or a custom item.</p></div>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/reorder')) ?>" id="reorder-form">
        <?= csrf_field() ?>
        <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
        <div id="reorder-ids"></div>
    </form>
</section>

<div class="sf-action-row mb-3">
    <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/configure')) ?>">Add configured sign</a>
    <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/configure')) ?>">Add from template</a>
</div>
<section class="sf-panel sf-form mb-3">
    <div class="sf-panel-head"><h2>Add a standard product</h2></div>
    <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/lines')) ?>" id="line-form">
        <?= csrf_field() ?>
        <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="product_id">Product</label>
                <select class="form-select" id="product_id" name="product_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 sf-line-field" data-inputs="AREA SHEET"><label class="form-label">Width mm</label><input class="form-control" name="width_mm" inputmode="decimal"></div>
            <div class="col-md-3 sf-line-field" data-inputs="AREA SHEET"><label class="form-label">Height mm</label><input class="form-control" name="height_mm" inputmode="decimal"></div>
            <div class="col-md-3 sf-line-field" data-inputs="LINEAR_METRE"><label class="form-label">Length mm</label><input class="form-control" name="length_mm" inputmode="decimal"></div>
            <div class="col-md-3 sf-line-field" data-inputs="AREA LINEAR_METRE UNIT SHEET CUSTOM"><label class="form-label">Quantity</label><input class="form-control" name="quantity" value="1" inputmode="decimal"></div>
            <div class="col-md-3 sf-line-field" data-inputs="LITRE"><label class="form-label">Litres</label><input class="form-control" name="litres" inputmode="decimal"></div>
            <div class="col-md-3 sf-line-field" data-inputs="HOUR"><label class="form-label">Hours</label><input class="form-control" name="hours" inputmode="decimal"></div>
            <div class="col-md-6 sf-line-field" data-inputs="WASTE">
                <label class="form-label">Wastage</label>
                <select class="form-select" name="waste_mode" id="waste_mode">
                    <option value="ACTUAL">Charge actual material</option>
                    <option value="CONSUMED_WIDTH">Charge consumed roll width</option>
                    <option value="FULL_SHEET">Charge full sheet</option>
                    <option value="MANUAL">Manual</option>
                </select>
            </div>
            <div class="col-md-4 sf-line-field" data-inputs="MANUAL"><label class="form-label">Manual width mm</label><input class="form-control" name="manual_width_mm"></div>
            <div class="col-md-4 sf-line-field" data-inputs="MANUAL"><label class="form-label">Manual area m²</label><input class="form-control" name="manual_area"></div>
            <div class="col-md-4 sf-line-field" data-inputs="MANUAL"><label class="form-label">Manual sheets</label><input class="form-control" name="manual_sheets"></div>
            <div class="col-12"><label class="form-label">Customer description</label><textarea class="form-control" name="customer_description" rows="2" placeholder="What the customer will read. Leave blank to use the product name."></textarea></div>
            <div class="col-12"><label class="form-label">Internal description</label><textarea class="form-control" name="internal_description" rows="2" placeholder="Workshop note. This never prints on the quotation."></textarea></div>
            <div class="col-md-4 form-check"><input class="form-check-input" type="checkbox" name="is_optional" value="1" id="is_optional"><label for="is_optional">Optional item</label></div>
            <div class="col-md-4 form-check"><input class="form-check-input" type="checkbox" name="include_optional" value="1" id="include_optional"><label for="include_optional">Include optional item in the total</label></div>
            <?php if ($canOverride): ?>
                <div class="col-md-4"><label class="form-label">Override sell price</label><input class="form-control" name="final_sell_price" placeholder="Leave blank to use the markup"></div>
                <div class="col-md-8"><label class="form-label">Override reason</label><input class="form-control" name="override_reason"></div>
            <?php endif; ?>
            <?php if ($sections !== []): ?>
                <div class="col-md-4">
                    <label class="form-label">Section</label>
                    <select class="form-select" name="section_id">
                        <option value="">None</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= e((string) $section['id']) ?>"><?= e((string) $section['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Add product</button></div>
    </form>
</section>

<section class="sf-panel sf-form mb-3">
    <div class="sf-panel-head"><h2>Custom line</h2></div>
    <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/lines/custom')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Description <span class="sf-req">*</span></label><input class="form-control" name="customer_description" required></div>
            <div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" name="quantity" value="1"></div>
            <?php if ($canCost): ?>
                <div class="col-md-2"><label class="form-label">Unit cost</label><input class="form-control" name="unit_cost" value="0"></div>
            <?php else: ?>
                <input type="hidden" name="unit_cost" value="0">
            <?php endif; ?>
            <?php if ($canOverride): ?>
                <div class="col-md-2"><label class="form-label">Sell price</label><input class="form-control" name="final_sell_price" placeholder="Optional"></div>
            <?php endif; ?>
        </div>
        <div class="sf-form-actions"><button class="btn btn-outline-light" type="submit">Add custom line</button></div>
    </form>
</section>

<form class="sf-panel sf-form mb-3" method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/sections')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
    <div class="sf-panel-head"><h2>Section</h2></div>
    <div class="row g-2">
        <div class="col-md-6"><input class="form-control" name="title" placeholder="Signage, installation, electrical, optional extras"></div>
        <div class="col-md-3"><button class="btn btn-outline-light" type="submit">Add section</button></div>
    </div>
</form>

<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Totals</h2></div>
    <dl class="sf-dl">
        <div><dt>Subtotal</dt><dd><?= e(money((string) $quote['subtotal'])) ?></dd></div>
        <div><dt>Discount</dt><dd><?= e(money((string) $quote['discount_amount'])) ?></dd></div>
        <div><dt>VAT</dt><dd><?= e(money((string) $quote['vat_amount'])) ?></dd></div>
        <div><dt>Total</dt><dd><?= e(money((string) $quote['total'])) ?></dd></div>
        <div><dt>Deposit required</dt><dd><?= e(money((string) $quote['deposit_amount'])) ?></dd></div>
    </dl>
    <?php if ($canCost): ?>
        <div class="sf-panel-head"><h2>Internal costing</h2></div>
        <dl class="sf-dl">
            <div><dt>Revenue ex VAT</dt><dd><?= e(money((string) $summary['revenue'])) ?></dd></div>
            <div><dt>Cost</dt><dd><?= e(money((string) $summary['cost'])) ?></dd></div>
            <div><dt>Gross profit</dt><dd><?= e(money((string) $summary['gross_profit'])) ?></dd></div>
            <div><dt>Gross margin</dt><dd><?= $summary['gross_margin_percent'] === null ? '—' : e((string) $summary['gross_margin_percent'] . '%') ?></dd></div>
        </dl>
        <p class="px-3 sf-muted">Not shown on the customer PDF. Quoted value is not paid revenue.</p>
    <?php endif; ?>
</section>

<div class="sf-sticky-actions">
    <button class="btn btn-sf" type="submit" form="quote-header">Save draft</button>
    <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'])) ?>">Preview</a>
    <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/pdf')) ?>">PDF</a>
    <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
        <input type="hidden" name="status" value="READY">
        <button class="btn btn-outline-light" type="submit">Mark ready</button>
    </form>
</div>
<script type="application/json" id="quote-catalogue"><?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
