<div class="sf-page-head"><h1>More</h1></div>
<div class="d-grid gap-2">
    <?php if (can('customers.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/customers')) ?>">Customers</a><?php endif; ?>
    <?php if (can('leads.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/leads')) ?>">Leads</a><?php endif; ?>
    <?php if (can('quotes.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/quotes')) ?>">Quotes</a><?php endif; ?>
    <?php if (can('jobs.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/jobs')) ?>">Jobs</a><?php endif; ?>
    <?php if (can('site_surveys.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/surveys')) ?>">Surveys</a><?php endif; ?>
    <?php if (can('installations.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/m')) ?>">Installations</a><?php endif; ?>
    <?php if (can('dispatch.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/dispatch')) ?>">Deliveries</a><?php endif; ?>
    <?php if (can('approvals.view')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/approvals')) ?>">Approvals</a><?php endif; ?>
    <?php if (can('offline.use')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/m/sync')) ?>">Sync centre</a><?php endif; ?>
    <?php if (can('device.view_own')): ?><a class="btn btn-outline-light sf-touch" href="<?= e(url('/m/devices')) ?>">Devices</a><?php endif; ?>
    <button class="btn btn-sf sf-touch" type="button" id="sf-install">Install app</button>
</div>
<form class="mt-3" id="sf-report">
    <label class="form-label" for="sf-report-code">Report a problem</label>
    <input class="form-control mb-2" id="sf-report-code" maxlength="40" placeholder="Error code">
    <button class="btn btn-outline-light sf-touch" type="submit">Send report</button>
    <p id="sf-report-result" class="sf-muted mt-2"></p>
</form>
<p class="sf-muted mt-3">App <?= e($version) ?> · service worker <?= e($sw) ?> · schema <?= e($schema) ?></p>
<p class="sf-muted">Add Sign-Forge to the home screen from the browser menu if the install button stays quiet. iPhone uses Share, then Add to Home Screen. Background sync and some camera features differ between Android Chrome and iPhone Safari.</p>
