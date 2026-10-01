<?php
$showInternal = can('costing.view');
$locked = (string) $job['status'] === 'CANCELLED' && !can('jobs.reopen');
?>
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Job</h2></div>
            <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="version_number" value="<?= e((string) $job['version_number']) ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control" id="title" name="title" value="<?= e((string) $job['title']) ?>" <?= $locked ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority" <?= $locked ? 'disabled' : '' ?>>
                            <?php foreach (['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= (string) $job['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" <?= $locked ? 'disabled' : '' ?>><?= e((string) ($job['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="assigned_to">Assigned staff</label>
                        <select class="form-select" id="assigned_to" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($staff as $person): ?>
                                <?php if ((int) $person['active'] !== 1) { continue; } ?>
                                <option value="<?= e((string) $person['id']) ?>" <?= (int) ($job['assigned_to'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= e((string) $person['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="project_manager_id">Project manager</label>
                        <select class="form-select" id="project_manager_id" name="project_manager_id">
                            <option value="">None</option>
                            <?php foreach ($staff as $person): ?>
                                <?php if ((int) $person['active'] !== 1) { continue; } ?>
                                <option value="<?= e((string) $person['id']) ?>" <?= (int) ($job['project_manager_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= e((string) $person['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="target_date">Internal target date</label>
                        <input class="form-control" type="date" id="target_date" name="target_date" value="<?= e((string) ($job['target_date'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="customer_promised_date">Customer promised date</label>
                        <input class="form-control" type="date" id="customer_promised_date" name="customer_promised_date" value="<?= e((string) ($job['customer_promised_date'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="target_change_reason">Reason if a date changes</label>
                        <input class="form-control" id="target_change_reason" name="target_change_reason" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="production_due_date">Production due</label>
                        <input class="form-control" type="date" id="production_due_date" name="production_due_date" value="<?= e((string) ($job['production_due_date'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="installation_date">Installation date</label>
                        <input class="form-control" type="date" id="installation_date" name="installation_date" value="<?= e((string) ($job['installation_date'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="delivery_method">Delivery method</label>
                        <select class="form-select" id="delivery_method" name="delivery_method">
                            <?php foreach (\App\Domain\DeliveryMethod::cases() as $method): ?>
                                <option value="<?= e($method->value) ?>" <?= (string) $job['delivery_method'] === $method->value ? 'selected' : '' ?>><?= e($method->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="customer_po_number">Customer PO</label>
                        <input class="form-control" id="customer_po_number" name="customer_po_number" value="<?= e((string) ($job['customer_po_number'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="site_address">Site address</label>
                        <textarea class="form-control" id="site_address" name="site_address" rows="2"><?= e((string) ($job['site_address'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="site_contact_name">Site contact</label>
                        <input class="form-control" id="site_contact_name" name="site_contact_name" value="<?= e((string) ($job['site_contact_name'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="site_contact_phone">Site phone</label>
                        <input class="form-control" id="site_contact_phone" name="site_contact_phone" value="<?= e((string) ($job['site_contact_phone'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="customer_notes">Customer / general notes</label>
                        <textarea class="form-control" id="customer_notes" name="customer_notes" rows="2"><?= e((string) ($job['customer_notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="production_notes">Production notes</label>
                        <textarea class="form-control" id="production_notes" name="production_notes" rows="2"><?= e((string) ($job['production_notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="installation_notes">Installation notes</label>
                        <textarea class="form-control" id="installation_notes" name="installation_notes" rows="2"><?= e((string) ($job['installation_notes'] ?? '')) ?></textarea>
                    </div>
                    <?php if ($showInternal): ?>
                        <div class="col-12">
                            <label class="form-label" for="internal_notes">Internal management notes</label>
                            <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2"><?= e((string) ($job['internal_notes'] ?? '')) ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (can('jobs.edit') && !$locked): ?>
                    <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Save job</button></div>
                <?php endif; ?>
            </form>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Source</h2></div>
            <dl class="sf-dl">
                <div><dt>Customer</dt><dd><a href="<?= e(url('/customers/' . $job['customer_id'])) ?>"><?= e(customer_label($job)) ?></a></dd></div>
                <div><dt>Quote</dt><dd><a href="<?= e(url('/quotes/' . $job['quote_id'])) ?>"><?= e((string) $job['quote_number']) ?></a> revision <?= e((string) $job['quote_revision_number']) ?></dd></div>
                <div><dt>Delivery</dt><dd><?= e(enum_label(\App\Domain\DeliveryMethod::class, (string) $job['delivery_method'])) ?></dd></div>
                <div><dt>Project manager</dt><dd><?= e((string) ($job['manager_name'] ?? '—')) ?></dd></div>
                <div><dt>Assigned</dt><dd><?= e((string) ($job['assignee_name'] ?? '—')) ?></dd></div>
            </dl>
            <p class="px-3 pb-3 mb-0 sf-muted">The accepted revision stays the commercial baseline. Catalogue or quote edits after conversion do not change these figures.</p>
        </section>
        <?php if ($costing !== null): ?>
            <section class="sf-panel mb-3">
                <div class="sf-panel-head"><h2>Quoted vs actual</h2></div>
                <dl class="sf-dl">
                    <div><dt>Quoted revenue</dt><dd><?= e(money((string) $costing['quoted_revenue'])) ?></dd></div>
                    <div><dt>Quoted cost</dt><dd><?= e(money((string) $costing['quoted_cost'])) ?></dd></div>
                    <div><dt>Current actual cost</dt><dd><?= e(money((string) $costing['actual_total_cost'])) ?></dd></div>
                    <div><dt>Estimated gross profit</dt><dd><?= e(money((string) $costing['actual_profit'])) ?></dd></div>
                </dl>
                <p class="px-3 pb-3 mb-0 sf-muted">Quoted revenue is the accepted quotation, not money received.</p>
            </section>
        <?php endif; ?>
        <?php if ((string) $job['status'] === 'COMPLETED'): ?>
            <section class="sf-panel mb-3">
                <div class="sf-panel-head"><h2>Completion</h2></div>
                <dl class="sf-dl">
                    <div><dt>Started</dt><dd><?= e(format_datetime((string) $job['created_at'])) ?></dd></div>
                    <div><dt>Completed</dt><dd><?= e(format_datetime((string) ($job['completed_at'] ?? ''))) ?></dd></div>
                    <?php if ($costing !== null): ?>
                        <div><dt>Quoted value</dt><dd><?= e(money((string) $costing['quoted_revenue'])) ?></dd></div>
                        <div><dt>Actual cost</dt><dd><?= e(money((string) $costing['actual_total_cost'])) ?></dd></div>
                        <div><dt>Gross profit</dt><dd><?= e(money((string) $costing['actual_profit'])) ?></dd></div>
                        <div><dt>Margin</dt><dd><?= $costing['actual_margin_percent'] === null ? '—' : e((string) $costing['actual_margin_percent'] . '%') ?></dd></div>
                        <div><dt>Labour</dt><dd><?= e(number_format(((int) $costing['labour']['actual_minutes']) / 60, 1)) ?> hours</dd></div>
                    <?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>
        <?php if (can('jobs.change_status') || can('jobs.complete')): ?>
            <section class="sf-panel">
                <div class="sf-panel-head"><h2>Status</h2></div>
                <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/status')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version_number" value="<?= e((string) $job['version_number']) ?>">
                    <label class="form-label" for="status">Move to</label>
                    <select class="form-select mb-2" id="status" name="status">
                        <?php foreach ($choices as $choice): ?>
                            <option value="<?= e($choice) ?>"><?= e(enum_label(\App\Domain\JobStatus::class, $choice)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($choices === []): ?><p class="sf-muted">No further status is available.</p><?php endif; ?>
                    <?php if (!empty($checks['artwork_blocking'])): ?>
                        <div class="alert alert-warning sf-alert">Artwork has not been approved by the customer.</div>
                    <?php endif; ?>
                    <label class="form-label" for="notes">Note</label>
                    <input class="form-control mb-2" id="notes" name="notes">
                    <label class="form-label" for="override_reason">Override reason</label>
                    <input class="form-control" id="override_reason" name="override_reason" placeholder="Required only when a warning blocks the move">
                    <?php if ($choices !== []): ?><div class="sf-form-actions"><button class="btn btn-sf" type="submit">Update status</button></div><?php endif; ?>
                </form>
            </section>
        <?php endif; ?>
        <?php if (can('jobs.complete')): ?>
            <form class="mt-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/archive')) ?>">
                <?= csrf_field() ?>
                <?php if ((int) $job['archived'] === 1): ?>
                    <input type="hidden" name="restore" value="1">
                    <button class="btn btn-outline-light" type="submit">Restore from archive</button>
                <?php else: ?>
                    <button class="btn btn-outline-light" type="submit">Archive job</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if ($items !== []): ?>
    <section class="sf-panel mt-3">
        <div class="sf-panel-head"><h2>Production items</h2></div>
        <div class="table-responsive">
            <table class="table sf-table mb-0">
                <thead><tr><th>Description</th><th>Size</th><th>Qty</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e((string) $item['description']) ?></td>
                            <td><?= e(trim((string) ($item['width_mm'] ?? '') . ' × ' . (string) ($item['height_mm'] ?? ''), ' ×')) ?></td>
                            <td><?= e((string) $item['quantity']) ?></td>
                            <td><?= e(enum_label(\App\Domain\JobItemStatus::class, (string) $item['production_status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
