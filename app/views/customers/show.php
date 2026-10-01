<?php
require base_path('app/views/partials/flashes.php');
$contactValue = static function (string $key, string $default = '') use ($contactOld): string {
    return old_value($contactOld, $key, $default);
};
$activityValue = static function (string $key, string $default = '') use ($activityOld): string {
    return old_value($activityOld, $key, $default);
};
?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e(enum_label(App\Domain\CustomerType::class, (string) $customer['customer_type'])) ?></p>
        <h1><?= e(customer_label($customer)) ?></h1>
        <p class="sf-muted mb-0"><?= (int) $customer['active'] === 1 ? 'Active' : 'Inactive' ?></p>
    </div>
    <?php if ($canManage): ?>
        <div class="sf-action-row">
            <a class="btn btn-sf" href="<?= e(url('/customers/' . $customer['id'] . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('/customers/' . $customer['id'] . '/active')) ?>" onsubmit="return confirm('<?= (int) $customer['active'] === 1 ? 'Deactivate this customer? The record is kept.' : 'Activate this customer?' ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="active" value="<?= (int) $customer['active'] === 1 ? '0' : '1' ?>">
                <button class="btn btn-outline-light" type="submit"><?= (int) $customer['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Customer</h2></div>
            <dl class="sf-dl">
                <div><dt>Company</dt><dd><?= e((string) ($customer['company_name'] ?? '—')) ?></dd></div>
                <div><dt>Person</dt><dd><?= e(trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? ''))) ?: '—') ?></dd></div>
                <div><dt>Email</dt><dd><?= e((string) ($customer['email'] ?? '—')) ?></dd></div>
                <div><dt>Phone</dt><dd><?= e((string) ($customer['phone'] ?? '—')) ?></dd></div>
                <div><dt>Mobile</dt><dd><?= e((string) ($customer['mobile'] ?? '—')) ?></dd></div>
                <div><dt>Website</dt><dd><?= e((string) ($customer['website'] ?? '—')) ?></dd></div>
                <div><dt>VAT number</dt><dd><?= e((string) ($customer['vat_number'] ?? '—')) ?></dd></div>
                <div><dt>Registration</dt><dd><?= e((string) ($customer['registration_number'] ?? '—')) ?></dd></div>
            </dl>
        </section>
        <section class="sf-panel mt-3">
            <div class="sf-panel-head"><h2>Addresses</h2></div>
            <div class="row g-3 p-3">
                <div class="col-md-6">
                    <p class="sf-kicker">Billing</p>
                    <p><?= nl_text((string) ($customer['billing_address'] ?? '')) ?: '—' ?></p>
                </div>
                <div class="col-md-6">
                    <p class="sf-kicker">Physical</p>
                    <p><?= nl_text((string) ($customer['physical_address'] ?? '')) ?: '—' ?></p>
                </div>
            </div>
        </section>
        <section class="sf-panel mt-3">
            <div class="sf-panel-head"><h2>Notes</h2></div>
            <div class="p-3"><?= nl_text((string) ($customer['notes'] ?? '')) ?: '<p class="sf-muted mb-0">No notes.</p>' ?></div>
        </section>
    </div>
    <div class="col-12 col-lg-5">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Contacts</h2></div>
            <?php if ($contacts === []): ?>
                <div class="sf-empty"><p>No contacts yet. A business can have an owner, accounts, and a site manager.</p></div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <article class="sf-subcard">
                        <div class="d-flex justify-content-between gap-2">
                            <strong><?= e((string) $contact['name']) ?></strong>
                            <?php if ((int) $contact['primary_contact'] === 1): ?><span class="badge text-bg-warning">Primary</span><?php endif; ?>
                        </div>
                        <p class="mb-1 sf-muted"><?= e((string) ($contact['position'] ?? '')) ?></p>
                        <p class="mb-0"><?= e((string) ($contact['email'] ?? '')) ?> <?= e((string) ($contact['phone'] ?? '')) ?> <?= e((string) ($contact['mobile'] ?? '')) ?></p>
                        <?php if ((int) $contact['active'] !== 1): ?><p class="mb-0">Inactive</p><?php endif; ?>
                        <?php if ($canManage): ?>
                            <details class="mt-2">
                                <summary>Edit contact</summary>
                                <form method="post" action="<?= e(url('/customers/' . $customer['id'] . '/contacts/' . $contact['id'])) ?>" class="mt-2">
                                    <?= csrf_field() ?>
                                    <input class="form-control mb-2" name="name" value="<?= e((string) $contact['name']) ?>" required>
                                    <input class="form-control mb-2" name="position" value="<?= e((string) ($contact['position'] ?? '')) ?>" placeholder="Position">
                                    <input class="form-control mb-2" name="email" value="<?= e((string) ($contact['email'] ?? '')) ?>" placeholder="Email">
                                    <input class="form-control mb-2" name="phone" value="<?= e((string) ($contact['phone'] ?? '')) ?>" placeholder="Phone">
                                    <input class="form-control mb-2" name="mobile" value="<?= e((string) ($contact['mobile'] ?? '')) ?>" placeholder="Mobile">
                                    <textarea class="form-control mb-2" name="notes" rows="2" placeholder="Notes"><?= e((string) ($contact['notes'] ?? '')) ?></textarea>
                                    <input type="hidden" name="primary_contact" value="0">
                                    <label class="form-check mb-1"><input class="form-check-input" type="checkbox" name="primary_contact" value="1" <?= (int) $contact['primary_contact'] === 1 ? 'checked' : '' ?>> Primary</label>
                                    <input type="hidden" name="active" value="0">
                                    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="active" value="1" <?= (int) $contact['active'] === 1 ? 'checked' : '' ?>> Active</label>
                                    <button class="btn btn-sm btn-sf" type="submit">Save contact</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form class="p-3 border-top border-secondary-subtle" method="post" action="<?= e(url('/customers/' . $customer['id'] . '/contacts')) ?>">
                    <?= csrf_field() ?>
                    <p class="sf-kicker">Add contact</p>
                    <?php if (!empty($contactErrors['_form'])): ?><div class="alert alert-danger sf-alert"><?= e($contactErrors['_form']) ?></div><?php endif; ?>
                    <label class="form-label" for="contact_name">Name <span class="sf-req">*</span></label>
                    <input class="form-control mb-2" id="contact_name" name="name" value="<?= e($contactValue('name')) ?>">
                    <?= field_error($contactErrors, 'name') ?>
                    <input class="form-control mb-2" name="position" value="<?= e($contactValue('position')) ?>" placeholder="Position">
                    <input class="form-control mb-2" name="email" value="<?= e($contactValue('email')) ?>" placeholder="Email">
                    <?= field_error($contactErrors, 'email') ?>
                    <input class="form-control mb-2" name="phone" value="<?= e($contactValue('phone')) ?>" placeholder="Phone">
                    <input class="form-control mb-2" name="mobile" value="<?= e($contactValue('mobile')) ?>" placeholder="Mobile">
                    <textarea class="form-control mb-2" name="notes" rows="2" placeholder="Notes"><?= e($contactValue('notes')) ?></textarea>
                    <input type="hidden" name="primary_contact" value="0">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="primary_contact" value="1" <?= is_checked($contactValue('primary_contact', '0')) ?>> Primary contact</label>
                    <input type="hidden" name="active" value="0">
                    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="active" value="1" <?= is_checked($contactValue('active', '1')) ?>> Active</label>
                    <button class="btn btn-sf" type="submit">Add contact</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Activity</h2></div>
    <?php if ($activities === []): ?>
        <div class="sf-empty"><p>No calls, emails, or notes yet.</p></div>
    <?php else: ?>
        <ol class="sf-timeline">
            <?php foreach ($activities as $activity): ?>
                <li>
                    <p class="sf-kicker mb-1"><?= e(enum_label(App\Domain\ActivityType::class, (string) $activity['activity_type'])) ?> · <?= e(format_date((string) $activity['activity_date'])) ?></p>
                    <strong><?= e((string) $activity['subject']) ?></strong>
                    <?php if ((int) $activity['completed'] === 1): ?><span class="badge text-bg-success">Done</span><?php endif; ?>
                    <p class="mb-1"><?= nl_text((string) ($activity['description'] ?? '')) ?></p>
                    <small class="sf-muted">
                        <?= e((string) ($activity['user_name'] ?? 'Unknown')) ?>
                        <?php if (!empty($activity['follow_up_date'])): ?> · Follow up <?= e(format_date((string) $activity['follow_up_date'])) ?><?php endif; ?>
                    </small>
                    <?php if ($canActivity): ?>
                        <form method="post" action="<?= e(url('/activities/' . $activity['id'] . '/complete')) ?>" class="mt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="customer">
                            <input type="hidden" name="completed" value="<?= (int) $activity['completed'] === 1 ? '0' : '1' ?>">
                            <button class="btn btn-sm btn-outline-light" type="submit"><?= (int) $activity['completed'] === 1 ? 'Reopen' : 'Mark complete' ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if ($canActivity): ?>
        <form class="p-3 border-top border-secondary-subtle" method="post" action="<?= e(url('/customers/' . $customer['id'] . '/activities')) ?>">
            <?= csrf_field() ?>
            <p class="sf-kicker">Record an interaction</p>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label" for="activity_type">Type</label>
                    <select class="form-select" id="activity_type" name="activity_type">
                        <?php foreach (App\Domain\ActivityType::cases() as $case): ?>
                            <option value="<?= e($case->value) ?>" <?= $activityValue('activity_type', 'NOTE') === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="activity_date">Date <span class="sf-req">*</span></label>
                    <input class="form-control" id="activity_date" type="date" name="activity_date" value="<?= e($activityValue('activity_date', date('Y-m-d'))) ?>">
                    <?= field_error($activityErrors, 'activity_date') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="follow_up_date">Follow-up</label>
                    <input class="form-control" id="follow_up_date" type="date" name="follow_up_date" value="<?= e($activityValue('follow_up_date')) ?>">
                    <?= field_error($activityErrors, 'follow_up_date') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="subject">Subject <span class="sf-req">*</span></label>
                    <input class="form-control" id="subject" name="subject" value="<?= e($activityValue('subject')) ?>">
                    <?= field_error($activityErrors, 'subject') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= e($activityValue('description')) ?></textarea>
                </div>
                <div class="col-12">
                    <input type="hidden" name="completed" value="0">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="completed" value="1" <?= is_checked($activityValue('completed', '0')) ?>> Already completed</label>
                </div>
            </div>
            <button class="btn btn-sf mt-2" type="submit">Save activity</button>
        </form>
    <?php endif; ?>
</section>

<div class="row g-3 mt-1">
    <?php foreach ([
        'Quotes' => 'Quotations for this customer will be listed here.',
        'Jobs' => 'Jobs will be listed here after a quote is accepted.',
        'Invoices' => 'Invoices will be listed here.',
        'Payments' => 'Payments will be listed here.',
        'Files' => 'Artwork and site files will be listed here.',
    ] as $label => $note): ?>
        <div class="col-12 col-md-6 col-xl">
            <section class="sf-future h-100">
                <p class="sf-kicker"><?= e($label) ?></p>
                <p class="mb-0"><?= e($note) ?></p>
            </section>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($audit !== []): ?>
    <section class="sf-panel mt-3">
        <div class="sf-panel-head"><h2>Record history</h2></div>
        <?php require base_path('app/views/partials/audit_list.php'); ?>
    </section>
<?php endif; ?>
