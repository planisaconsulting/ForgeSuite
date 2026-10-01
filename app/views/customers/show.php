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

<?php if (!empty($management)): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Management summary</h2></div>
    <div class="row g-3 p-3">
        <div class="col-6 col-md-3"><p class="sf-kicker">First quote</p><p class="mb-0"><?= e((string) ($management['history']['first_quote'] ?? '—')) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Last quote</p><p class="mb-0"><?= e((string) ($management['history']['last_quote'] ?? '—')) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Quotes</p><p class="mb-0"><?= e((string) ($management['history']['quotes'] ?? 0)) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Jobs</p><p class="mb-0"><?= e((string) ($management['history']['jobs'] ?? 0)) ?></p></div>
        <?php if (!empty($management['show_cost'])): ?>
            <div class="col-6 col-md-3"><p class="sf-kicker">Commercial value</p><p class="mb-0"><?= e(money($management['profit']['commercial'])) ?></p></div>
            <div class="col-6 col-md-3"><p class="sf-kicker">Actual cost</p><p class="mb-0"><?= e(money($management['profit']['actual'])) ?></p></div>
            <div class="col-6 col-md-3"><p class="sf-kicker">Gross profit</p><p class="mb-0"><?= e(money($management['profit']['profit'])) ?></p></div>
            <div class="col-6 col-md-3"><p class="sf-kicker">Gross margin</p><p class="mb-0"><?= $management['profit']['margin'] === null ? '—' : e($management['profit']['margin'] . '%') ?></p></div>
        <?php endif; ?>
        <div class="col-6 col-md-3"><p class="sf-kicker">Invoiced</p><p class="mb-0"><?= e(money((string) ($management['invoices']['invoiced'] ?? '0'))) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Payments</p><p class="mb-0"><?= e(money((string) ($management['invoices']['paid'] ?? '0'))) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Outstanding</p><p class="mb-0"><?= e(money((string) ($management['invoices']['outstanding'] ?? '0'))) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Average payment time</p><p class="mb-0"><?= isset($management['invoices']['pay_days']) && $management['invoices']['pay_days'] !== null ? e(\App\Helpers\Decimal::round((string) $management['invoices']['pay_days'], 1) . ' days from invoice date') : '—' ?></p></div>
    </div>
    <p class="px-3 sf-muted">Gross profit is commercial value minus actual job cost. It is not cash received.</p>
</section>
<?php if ($canActivity): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Communications</h2></div>
    <form class="p-3 row g-2" method="post" action="<?= e(url('/customers/' . $customer['id'] . '/communications')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-3"><select class="form-select" name="channel"><?php foreach (['PHONE','EMAIL','WHATSAPP','SMS','IN_PERSON','OTHER'] as $channel): ?><option value="<?= e($channel) ?>"><?= e($channel) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><input class="form-control" name="subject" placeholder="Subject" required></div>
        <div class="col-md-5"><input class="form-control" name="message_summary" placeholder="Summary"></div>
        <div class="col-12"><button class="btn btn-outline-light" type="submit">Log communication</button></div>
    </form>
    <ul class="sf-feed">
        <?php foreach ($management['communications'] as $note): ?>
            <li><strong><?= e((string) $note['subject']) ?></strong><small><?= e((string) $note['channel']) ?> · <?= e((string) ($note['user_name'] ?? '')) ?> · <?= e((string) $note['created_at']) ?></small><p class="mb-0"><?= e((string) ($note['message_summary'] ?? '')) ?></p></li>
        <?php endforeach; ?>
    </ul>
    <p class="px-3 sf-muted">Logging a call or WhatsApp note does not connect to WhatsApp.</p>
</section>
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($account)): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Account summary</h2></div>
    <div class="row g-3 p-3">
        <div class="col-6 col-md-4"><p class="sf-kicker">Invoiced</p><p class="mb-0"><?= e(money($account['invoiced'])) ?></p></div>
        <div class="col-6 col-md-4"><p class="sf-kicker">Credits</p><p class="mb-0"><?= e(money($account['credits'])) ?></p></div>
        <div class="col-6 col-md-4"><p class="sf-kicker">Payments</p><p class="mb-0"><?= e(money($account['payments'])) ?></p></div>
        <div class="col-6 col-md-4"><p class="sf-kicker">Outstanding</p><p class="mb-0"><?= e(money($account['outstanding'])) ?></p></div>
        <div class="col-6 col-md-4"><p class="sf-kicker">Overdue</p><p class="mb-0"><?= e(money($account['overdue'])) ?></p></div>
        <div class="col-6 col-md-4"><p class="sf-kicker">Unallocated credit</p><p class="mb-0"><?= e(money($account['unallocated'])) ?></p></div>
    </div>
    <?php if ((int) ($customer['account_on_hold'] ?? 0) === 1): ?>
        <p class="px-3">Account on hold.<?php if (can('invoices.issue')): ?> <?= e((string) ($customer['account_hold_reason'] ?? '')) ?><?php endif; ?></p>
    <?php endif; ?>
    <?php if (can('invoices.issue')): ?>
    <form class="p-3 row g-2" method="post" action="<?= e(url('/customers/' . $customer['id'] . '/account')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-3"><input class="form-control" name="credit_limit" value="<?= e((string) ($customer['credit_limit'] ?? '')) ?>" placeholder="Credit limit"></div>
        <div class="col-md-3"><select class="form-select" name="payment_term_id"><option value="">Payment terms</option><?php foreach ($paymentTerms as $term): ?><option value="<?= e((string) $term['id']) ?>" <?= (int) ($customer['payment_term_id'] ?? 0) === (int) $term['id'] ? 'selected' : '' ?>><?= e((string) $term['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><input class="form-control" name="account_hold_reason" placeholder="Hold reason"></div>
        <div class="col-md-3 form-check mt-2"><input class="form-check-input" type="checkbox" name="account_on_hold" value="1" id="hold" <?= (int) ($customer['account_on_hold'] ?? 0) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="hold">Account on hold</label></div>
        <div class="col-12"><button class="btn btn-outline-light" type="submit">Save account</button></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

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
    <div class="col-12 col-lg-4">
        <section class="sf-panel h-100">
            <div class="sf-panel-head"><h2>Opportunities</h2></div>
            <?php if (($opportunities ?? []) === []): ?><div class="sf-empty"><p>No opportunities yet.</p></div><?php endif; ?>
            <ul class="sf-feed">
                <?php foreach (($opportunities ?? []) as $opportunity): ?>
                    <li><a href="<?= e(url('/opportunities/' . $opportunity['id'])) ?>"><?= e((string) $opportunity['opportunity_number']) ?></a><small><?= e((string) $opportunity['title']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <div class="col-12 col-lg-4">
        <section class="sf-panel h-100">
            <div class="sf-panel-head"><h2>Quotes</h2></div>
            <?php if (($quotes ?? []) === []): ?><div class="sf-empty"><p>No quotations yet.</p></div><?php endif; ?>
            <ul class="sf-feed">
                <?php foreach (($quotes ?? []) as $quote): ?>
                    <li><a href="<?= e(url('/quotes/' . $quote['id'])) ?>"><?= e((string) $quote['quote_number']) ?></a><small><?= e((string) $quote['status']) ?> · <?= e(money((string) $quote['total'])) ?></small></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($canQuote)): ?><div class="p-3"><a href="<?= e(url('/quotes/new?customer_id=' . $customer['id'])) ?>">New quotation</a></div><?php endif; ?>
            <?php if (can('site_surveys.create')): ?><div class="p-3 pt-0"><a href="<?= e(url('/surveys/new?customer_id=' . $customer['id'])) ?>">New site survey</a></div><?php endif; ?>
        </section>
    </div>
    <div class="col-12 col-lg-4">
        <section class="sf-panel h-100">
            <div class="sf-panel-head"><h2>Jobs</h2></div>
            <?php if (($jobs ?? []) === []): ?><div class="sf-empty"><p>No jobs yet.</p></div><?php endif; ?>
            <ul class="sf-feed">
                <?php foreach (($jobs ?? []) as $job): ?>
                    <li><a href="<?= e(url('/jobs/' . $job['id'])) ?>"><?= e((string) $job['job_number']) ?></a><small><?= e((string) $job['status']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
<div class="row g-3 mt-1">
    <?php foreach ([
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
