<?php if (can('installations.schedule')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Schedule</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/installations')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="scheduled_date">Date</label>
                <input class="form-control form-control-lg" type="date" id="scheduled_date" name="scheduled_date" value="<?= e((string) ($job['installation_date'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="scheduled_start_time">Start</label>
                <input class="form-control form-control-lg" type="time" id="scheduled_start_time" name="scheduled_start_time">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="assigned_team_id">Team</label>
                <select class="form-select form-select-lg" id="assigned_team_id" name="assigned_team_id">
                    <option value="">No team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= e((string) $team['id']) ?>"><?= e((string) $team['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="assigned_user_id">Installer</label>
                <select class="form-select form-select-lg" id="assigned_user_id" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($staff as $person): ?>
                        <?php if ((int) $person['active'] !== 1) { continue; } ?>
                        <option value="<?= e((string) $person['id']) ?>"><?= e((string) $person['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="site_address">Address</label>
                <textarea class="form-control" id="site_address" name="site_address" rows="2"><?= e((string) ($job['site_address'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="site_contact_name">Contact</label>
                <input class="form-control form-control-lg" id="site_contact_name" name="site_contact_name" value="<?= e((string) ($job['site_contact_name'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="site_contact_phone">Phone</label>
                <input class="form-control form-control-lg" id="site_contact_phone" name="site_contact_phone" value="<?= e((string) ($job['site_contact_phone'] ?? '')) ?>">
            </div>
        </div>
        <div class="sf-form-actions"><button class="btn btn-sf btn-lg" type="submit">Schedule installation</button></div>
    </form>
</section>
<?php endif; ?>
<?php if ($installations === []): ?>
    <section class="sf-panel"><p class="p-3 mb-0">No installation visit yet. Collection, delivery, and courier jobs do not need one before completion.</p></section>
<?php endif; ?>
<?php foreach ($installations as $row): ?>
    <section class="sf-panel mb-3">
        <div class="sf-panel-head"><h2><?= e((string) ($row['scheduled_date'] ?? 'Unscheduled')) ?> · <?= e(enum_label(\App\Domain\InstallationStatus::class, (string) $row['status'])) ?></h2></div>
        <div class="p-3">
            <p class="mb-1"><strong>Address</strong><br><?= nl2br(e((string) ($row['site_address'] ?? 'No address yet'))) ?></p>
            <p class="mb-1"><strong>Contact</strong><br><?= e((string) ($row['site_contact_name'] ?? '—')) ?> <?= e((string) ($row['site_contact_phone'] ?? '')) ?></p>
            <?php if (!empty($row['site_contact_phone'])): ?>
                <p><a class="btn btn-outline-light btn-lg" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $row['site_contact_phone']) ?? '') ?>">Call site</a></p>
            <?php endif; ?>
            <p class="mb-1">Team <?= e((string) ($row['team_name'] ?? '—')) ?> · <?= e((string) ($row['assignee_name'] ?? 'Unassigned')) ?></p>
            <?php if (!empty($row['installation_notes'])): ?><p><?= nl2br(e((string) $row['installation_notes'])) ?></p><?php endif; ?>
            <h3 class="h6 mt-3">Checklist</h3>
            <?php foreach ($checklists[(int) $row['id']] ?? [] as $check): ?>
                <form method="post" action="<?= e(url('/jobs/' . $job['id'] . '/installations/' . $row['id'] . '/checklist/' . $check['id'])) ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <label class="d-flex gap-2 align-items-center">
                        <input class="form-check-input sf-check" type="checkbox" name="checked" <?= (int) $check['checked'] === 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span><?= e((string) $check['label']) ?></span>
                    </label>
                </form>
            <?php endforeach; ?>
            <?php if (can('installations.complete') || can('attachments.manage')): ?>
                <form class="mt-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/installations/' . $row['id'] . '/photo')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="form-label" for="photo-<?= e((string) $row['id']) ?>">Completion photo</label>
                    <input class="form-control form-control-lg" id="photo-<?= e((string) $row['id']) ?>" type="file" name="file" accept="image/*" capture="environment">
                    <input class="form-control mt-2" name="notes" placeholder="Photo note">
                    <button class="btn btn-outline-light btn-lg mt-2" type="submit">Upload photo</button>
                </form>
            <?php endif; ?>
            <?php foreach ($photos[(int) $row['id']] ?? [] as $photo): ?>
                <?php if ((string) $photo['purpose'] !== 'COMPLETION_PHOTO') { continue; } ?>
                <p class="mt-2 mb-0"><a href="<?= e(url('/attachments/' . $photo['id'])) ?>"><?= e((string) $photo['original_filename']) ?></a><?php if (!empty($photo['notes'])): ?> · <?= e((string) $photo['notes']) ?><?php endif; ?></p>
            <?php endforeach; ?>
            <?php if (can('installations.complete') || can('installations.schedule')): ?>
                <form class="row g-2 mt-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/installations/' . $row['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version_number" value="<?= e((string) $row['version_number']) ?>">
                    <input type="hidden" name="scheduled_date" value="<?= e((string) ($row['scheduled_date'] ?? '')) ?>">
                    <input type="hidden" name="assigned_team_id" value="<?= e((string) ($row['assigned_team_id'] ?? '')) ?>">
                    <input type="hidden" name="assigned_user_id" value="<?= e((string) ($row['assigned_user_id'] ?? '')) ?>">
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <select class="form-select form-select-lg" name="status">
                            <?php foreach (\App\Domain\InstallationStatus::cases() as $status): ?>
                                <option value="<?= e($status->value) ?>" <?= (string) $row['status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer name on the record</label>
                        <input class="form-control form-control-lg" name="customer_signoff_name" value="<?= e((string) ($row['customer_signoff_name'] ?? '')) ?>" placeholder="Typed name, not a signature">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sign-off notes</label>
                        <input class="form-control form-control-lg" name="signoff_notes" value="<?= e((string) ($row['signoff_notes'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Completion notes</label>
                        <textarea class="form-control" name="completion_notes" rows="2"><?= e((string) ($row['completion_notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-light btn-lg" type="submit" onclick="this.form.status.value='ON_SITE'">On site</button>
                        <button class="btn btn-sf btn-lg" type="submit">Save installation</button>
                    </div>
                </form>
                <p class="sf-muted mt-2 mb-0">The customer name is a written record of who was on site. It is not an electronic signature.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
