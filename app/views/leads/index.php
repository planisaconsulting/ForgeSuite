<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head d-flex flex-wrap justify-content-between gap-2">
    <div><h1>Lead inbox</h1><p class="sf-muted mb-0">Enquiries stay here until someone links them to a customer. Nothing is merged on name alone.</p></div>
    <?php if (can('leads.create')): ?><a class="btn btn-sf" href="<?= e(url('/leads/new')) ?>">New lead</a><?php endif; ?>
</div>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/leads')) ?>">
    <div class="col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, company, phone, email, number"></div>
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All open views</option>
            <?php foreach (['NEW' => 'New', 'UNASSIGNED' => 'Unassigned', 'UNCONTACTED' => 'Needs contact', 'FOLLOWUP' => 'Follow-up', 'OVERDUE' => 'Overdue', 'CONTACTED' => 'Contacted', 'QUALIFIED' => 'Qualified', 'CONVERTED' => 'Converted', 'LOST' => 'Lost', 'SPAM' => 'Spam'] as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-light w-100" type="submit">Filter</button></div>
</form>
<div class="row g-3">
<?php if ($rows === []): ?>
    <div class="col-12"><section class="sf-panel p-3">No leads in this view.</section></div>
<?php endif; ?>
<?php foreach ($rows as $lead): ?>
    <div class="col-md-6 col-xl-4">
        <a class="sf-panel p-3 d-block text-decoration-none text-reset h-100" href="<?= e(url('/leads/' . $lead['id'])) ?>">
            <div class="d-flex justify-content-between gap-2"><strong><?= e((string) ($lead['company_name'] ?: $lead['name'])) ?></strong><span class="badge text-bg-secondary"><?= e((string) $lead['status']) ?></span></div>
            <div class="sf-muted small"><?= e((string) $lead['name']) ?> · <?= e((string) $lead['source']) ?></div>
            <p class="mt-2 mb-1"><?= e(mb_substr((string) $lead['message'], 0, 140)) ?></p>
            <div class="small sf-muted"><?= e((string) $lead['lead_number']) ?> · <?= e((string) $lead['created_at']) ?><?php if (!empty($lead['assignee_name'])): ?> · <?= e((string) $lead['assignee_name']) ?><?php endif; ?></div>
        </a>
    </div>
<?php endforeach; ?>
</div>
<?php if (count($rows) === 40): ?><a class="btn btn-outline-light mt-3" href="<?= e(url('/leads?status=' . urlencode($status) . '&q=' . urlencode($q) . '&page=' . ($page + 1))) ?>">Next</a><?php endif; ?>
