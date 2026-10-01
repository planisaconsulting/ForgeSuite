<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Service requests</h1>
    <p class="sf-muted mb-0">Age is the time since the request was reported. Older requests stay on the list.</p>
</div>
<div class="row g-2 mb-3">
    <?php foreach ([
        'new_count' => 'New',
        'triage' => 'Awaiting triage',
        'awaiting_quote' => 'Awaiting quote',
        'ready' => 'Ready to schedule',
        'in_progress' => 'In progress',
        'overdue' => 'Open over 14 days',
        'warranty_open' => 'Warranty candidate',
        'completed_month' => 'Completed this month',
    ] as $key => $label): ?>
        <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker"><?= e($label) ?></p><h2><?= (int) ($cards[$key] ?? 0) ?></h2></section></div>
    <?php endforeach; ?>
</div>
<form class="sf-panel mb-3" method="get"><div class="row g-2">
    <div class="col-md-6"><input class="form-control" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Request, customer, asset, or serial"></div>
    <div class="col-md-3"><input class="form-control" name="status" value="<?= e((string) $filters['status']) ?>" placeholder="Status"></div>
    <div class="col-md-2"><button class="btn btn-sf" type="submit">Search</button></div>
</div></form>
<section class="sf-panel">
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Request</th><th>Customer</th><th>Asset</th><th>Problem</th><th>Priority</th><th>Status</th><th>Age</th><th>Warranty</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/service/' . $row['id'])) ?>"><?= e((string) $row['request_number']) ?></a></td>
                <td><?= e((string) $row['company_name']) ?></td>
                <td><?= e((string) ($row['asset_number'] ?? '')) ?></td>
                <td><?= e((string) $row['problem_category']) ?></td>
                <td><?= e((string) $row['priority']) ?></td>
                <td><?= e((string) $row['status']) ?></td>
                <td><?= (int) $row['age_days'] ?>d</td>
                <td><?= (int) $row['warranty_candidate'] === 1 ? 'Candidate' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if ($rows === []): ?><p class="p-3 mb-0">No service requests in this queue.</p><?php endif; ?>
</section>
<p class="mt-3"><a href="<?= e(url('/service/maintenance')) ?>">Maintenance</a> · <a href="<?= e(url('/service/warranties')) ?>">Warranties</a> · <a href="<?= e(url('/service/reports/warranty-cost')) ?>">Warranty cost</a></p>
