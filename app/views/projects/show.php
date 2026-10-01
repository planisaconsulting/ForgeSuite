<?php
require base_path('app/views/partials/flashes.php');
$tabs = [
    'overview' => 'Overview',
    'sites' => 'Sites',
    'jobs' => 'Jobs',
    'milestones' => 'Milestones',
    'gantt' => 'Gantt',
    'calendar' => 'Calendar',
    'financials' => 'Financials',
    'documents' => 'Documents',
    'contacts' => 'Contacts',
    'risks' => 'Risks & issues',
    'activity' => 'Activity',
];
$base = '/projects/' . $project['id'];
?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $project['project_number']) ?> · <?= e(customer_label($project)) ?></p>
    <h1><?= e((string) $project['name']) ?></h1>
    <p class="mb-0">
        <span class="sf-badge"><?= e(str_replace('_', ' ', (string) $project['status'])) ?></span>
        <span class="sf-badge"><?= e(str_replace('_', ' ', (string) $health['health'])) ?></span>
        <?= e((string) ($project['manager_name'] ?? 'No project manager')) ?>
        · Original <?= e((string) ($project['original_target_date'] ?: '—')) ?>
        · Current <?= e((string) ($project['current_target_date'] ?: '—')) ?>
    </p>
</div>
<ul class="nav nav-pills flex-nowrap overflow-auto gap-2 mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <?php if ($key === 'financials' && !$showMoney) { continue; } ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= e(url($base . '?tab=' . $key)) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
    <div class="row g-3 mb-3">
        <?php
        $cards = [
            ['Sites', (int) $counts['sites'] . ' / ' . (int) $counts['sites_complete'] . ' complete'],
            ['Jobs', (int) $counts['jobs']],
            ['Late sites', (int) $counts['sites_late']],
            ['In production', (int) $counts['jobs_production']],
        ];
        if ($showMoney) {
            $cards[] = ['Commercial value', money($financials['commercial_value'])];
            $cards[] = ['Actual cost', money($financials['actual_cost'])];
            $cards[] = ['Gross profit', money($financials['gross_profit'])];
            $cards[] = ['Margin', $financials['margin_percent'] === null ? '—' : $financials['margin_percent'] . '%'];
        }
        $cards[] = ['Progress', $progress['percent'] === null ? 'Not weighted' : $progress['percent'] . '%'];
        ?>
        <?php foreach ($cards as $card): ?>
            <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker"><?= e($card[0]) ?></p><h2 class="h4"><?= e((string) $card[1]) ?></h2></section></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <section class="sf-panel">
                <h2>Why this health</h2>
                <ul><?php foreach ($health['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul>
                <p><?= e((string) ($project['description'] ?? '')) ?></p>
                <p>Next milestone: <?= e((string) ($next['name'] ?? 'None scheduled')) ?><?php if (!empty($next['current_due_date'])): ?> · <?= e((string) $next['current_due_date']) ?><?php endif; ?></p>
                <p class="sf-muted"><?= e($progress['explanation']) ?></p>
            </section>
            <?php if ($canEdit): ?>
            <section class="sf-panel mt-3">
                <h2>Move the current target</h2>
                <p class="sf-muted">The original target stays <?= e((string) ($project['original_target_date'] ?: 'unset')) ?>.</p>
                <form method="post" action="<?= e(url($base . '/date')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) $project['version'] ?>">
                    <div class="col-md-4"><input class="form-control" type="date" name="current_target_date" value="<?= e((string) ($project['current_target_date'] ?? '')) ?>" required></div>
                    <div class="col-md-4">
                        <select class="form-select" name="reason_code">
                            <?php foreach ($reasons as $reason): ?><option value="<?= e((string) $reason['code']) ?>"><?= e((string) $reason['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><input class="form-control" name="notes" placeholder="Note"></div>
                    <div class="col-12"><button class="btn btn-outline-light" type="submit">Save current target</button></div>
                </form>
            </section>
            <?php endif; ?>
        </div>
        <div class="col-lg-5">
            <section class="sf-panel">
                <h2>Sites needing attention</h2>
                <?php if ($attention_sites === []): ?><p>None.</p><?php endif; ?>
                <ul class="sf-feed">
                    <?php foreach ($attention_sites as $site): ?>
                        <li><a href="<?= e(url('/project-sites/' . $site['id'])) ?>"><?= e((string) $site['site_code']) ?></a> <?= e((string) $site['status']) ?> <small><?= e((string) ($site['current_target_date'] ?? '')) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="sf-panel mt-3">
                <h2>Snags</h2>
                <p><?= (int) $snags['open_snags'] ?> open · <?= (int) $snags['critical_snags'] ?> critical</p>
                <?php if ($canComplete): ?>
                    <h3 class="h6 mt-3">Closeout</h3>
                    <?php foreach ($closeout['blockers'] as $line): ?><p class="text-warning mb-1"><?= e($line) ?></p><?php endforeach; ?>
                    <?php foreach ($closeout['warnings'] as $line): ?><p class="sf-muted mb-1"><?= e($line) ?></p><?php endforeach; ?>
                    <form method="post" action="<?= e(url($base . '/complete')) ?>">
                        <?= csrf_field() ?>
                        <textarea class="form-control mb-2" name="notes" rows="2" placeholder="Completion notes"></textarea>
                        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="acknowledge" value="1"> I have read the warnings. This does not close invoices.</label>
                        <button class="btn btn-sf" type="submit">Mark operationally complete</button>
                    </form>
                <?php endif; ?>
                <?php if ($canHandover): ?><p class="mt-3"><a href="<?= e(url($base . '/handover')) ?>">Customer handover pack</a></p><?php endif; ?>
            </section>
        </div>
    </div>
<?php elseif ($tab === 'sites'): ?>
    <section class="sf-panel">
        <div class="table-responsive">
            <table class="table table-dark table-sm">
                <thead><tr><th>Code</th><th>Site</th><th>Group</th><th>Wave</th><th>Status</th><th>Target</th><th>Jobs</th></tr></thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><a href="<?= e(url('/project-sites/' . $site['id'])) ?>"><?= e((string) $site['site_code']) ?></a></td>
                            <td><?= e((string) $site['site_name']) ?><br><small><?= e((string) ($site['city'] ?? '')) ?></small></td>
                            <td><?= e((string) ($site['group_label'] ?? '')) ?></td>
                            <td><?= e((string) ($site['wave_name'] ?? '')) ?></td>
                            <td><?= e(str_replace('_', ' ', (string) $site['status'])) ?></td>
                            <td><?= e((string) ($site['current_target_date'] ?? '')) ?></td>
                            <td><?= (int) $site['job_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($sites === []): ?><p>No sites yet.</p><?php endif; ?>
    </section>
    <?php if ($canSites): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/sites')) ?>">
        <?= csrf_field() ?>
        <h2>Add a site</h2>
        <div class="row g-2">
            <div class="col-md-2"><input class="form-control" name="site_code" placeholder="JHB-01" required></div>
            <div class="col-md-4"><input class="form-control" name="site_name" placeholder="Site name" required></div>
            <div class="col-md-3"><input class="form-control" name="city" placeholder="City"></div>
            <div class="col-md-3"><input class="form-control" type="date" name="target_date"></div>
            <div class="col-md-4"><input class="form-control" name="address_line_1" placeholder="Street"></div>
            <div class="col-md-3"><input class="form-control" name="group_label" placeholder="Group, such as Gauteng"></div>
            <div class="col-md-2"><input class="form-control" name="group_kind" placeholder="REGION"></div>
            <div class="col-md-3">
                <select class="form-select" name="wave_id"><option value="">No wave</option>
                    <?php foreach ($waves as $wave): ?><option value="<?= (int) $wave['id'] ?>"><?= e((string) $wave['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn btn-sf mt-2" type="submit">Add site</button>
        <?php if ($canImport): ?><a class="btn btn-outline-light mt-2" href="<?= e(url($base . '/import')) ?>">Import CSV</a><?php endif; ?>
    </form>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/waves')) ?>">
        <?= csrf_field() ?>
        <h2>Add a wave</h2>
        <div class="row g-2">
            <div class="col-md-5"><input class="form-control" name="name" placeholder="Wave 1 — pilot" required></div>
            <div class="col-md-2"><input class="form-control" name="sequence" value="1"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="planned_start"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="planned_end"></div>
            <div class="col-md-1"><button class="btn btn-outline-light" type="submit">Add</button></div>
        </div>
    </form>
    <?php endif; ?>
    <?php if ($waves !== []): ?>
    <section class="sf-panel mt-3">
        <h2>Waves</h2>
        <ul><?php foreach ($waves as $wave): ?><li><?= e((string) $wave['name']) ?> · <?= (int) $wave['complete_count'] ?>/<?= (int) $wave['site_count'] ?> sites complete</li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
<?php elseif ($tab === 'jobs'): ?>
    <section class="sf-panel">
        <?php if ($jobs === []): ?><p>No jobs linked. One-off jobs elsewhere are unchanged.</p><?php endif; ?>
        <ul class="sf-feed">
            <?php foreach ($jobs as $job): ?>
                <li><a href="<?= e(url('/jobs/' . $job['id'])) ?>"><?= e((string) $job['job_number']) ?></a> <?= e((string) $job['title']) ?> · <?= e((string) $job['status']) ?> · <?= e((string) $job['rollout_state']) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php if ($canJobs && $sites !== []): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/jobs')) ?>">
        <?= csrf_field() ?>
        <h2>Create draft jobs</h2>
        <p class="sf-muted">Each selected site gets one job in New status. Stock is not consumed and no invoice is raised.</p>
        <input class="form-control mb-2" name="title" placeholder="Standard external branding package" required>
        <?php foreach ($sites as $site): ?>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="site_ids[]" value="<?= (int) $site['id'] ?>"> <?= e((string) $site['site_code']) ?> <?= e((string) $site['site_name']) ?></label>
        <?php endforeach; ?>
        <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirm" value="1" required> I confirm these stay as drafts.</label>
        <button class="btn btn-sf mt-2" type="submit">Create draft jobs</button>
    </form>
    <?php endif; ?>
    <section class="sf-panel mt-3">
        <h2>Production</h2>
        <?php if ($production === []): ?><p>No linked jobs.</p><?php endif; ?>
        <ul><?php foreach ($production as $row): ?><li><?= e((string) $row['status']) ?>: <?= (int) $row['n'] ?></li><?php endforeach; ?></ul>
        <h2>Installation</h2>
        <?php if ($installations === []): ?><p>No installation records on the linked jobs.</p><?php endif; ?>
        <ul><?php foreach ($installations as $row): ?><li><?= e((string) $row['status']) ?>: <?= (int) $row['n'] ?></li><?php endforeach; ?></ul>
    </section>
<?php elseif ($tab === 'milestones'): ?>
    <section class="sf-panel">
        <?php foreach ($milestones as $milestone): ?>
            <div class="d-flex justify-content-between gap-2 border-bottom border-secondary py-2">
                <div>
                    <strong><?= e((string) $milestone['name']) ?></strong>
                    <div class="small"><?= e((string) $milestone['milestone_type']) ?> · weight <?= e((string) ($milestone['weight'] ?? '0')) ?> · <?= e((string) ($milestone['current_due_date'] ?? 'no date')) ?></div>
                </div>
                <?php if ($canMilestones): ?>
                <form method="post" action="<?= e(url($base . '/milestones/' . $milestone['id'])) ?>" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) $milestone['version'] ?>">
                    <select class="form-select form-select-sm" name="status">
                        <?php foreach (['NOT_STARTED','IN_PROGRESS','BLOCKED','COMPLETE','CANCELLED'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $milestone['status'] === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-light" type="submit">Save</button>
                </form>
                <?php else: ?>
                    <span><?= e((string) $milestone['status']) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($milestones === []): ?><p>No milestones yet.</p><?php endif; ?>
    </section>
    <?php if ($canMilestones): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/milestones')) ?>">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="name" placeholder="Milestone" required></div>
            <div class="col-md-3"><input class="form-control" name="milestone_type" placeholder="CUSTOM"></div>
            <div class="col-md-2"><input class="form-control" name="weight" placeholder="Weight"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="current_due_date"></div>
            <div class="col-md-1"><button class="btn btn-outline-light" type="submit">Add</button></div>
        </div>
    </form>
    <?php endif; ?>
<?php elseif ($tab === 'gantt'): ?>
    <p class="sf-muted">Scale: <a href="<?= e(url($base . '?tab=gantt&scale=DAY')) ?>">Day</a> · <a href="<?= e(url($base . '?tab=gantt&scale=WEEK')) ?>">Week</a> · <a href="<?= e(url($base . '?tab=gantt&scale=MONTH')) ?>">Month</a>. Production tasks are not loaded (<?= (int) $gantt['task_rows'] ?> task rows).</p>
    <section class="sf-panel">
        <?php foreach ($gantt['waves'] as $wave): ?>
            <p><strong><?= e((string) $wave['name']) ?></strong> <?= e((string) ($wave['planned_start'] ?? '')) ?> – <?= e((string) ($wave['planned_end'] ?? '')) ?></p>
        <?php endforeach; ?>
        <?php foreach ($gantt['milestones'] as $milestone): ?>
            <div class="d-flex justify-content-between"><span><?= e((string) $milestone['name']) ?></span><span><?= e((string) ($milestone['current_due_date'] ?? '')) ?> · <?= e($scale) ?></span></div>
        <?php endforeach; ?>
        <?php foreach (array_slice($gantt['sites'], 0, 40) as $site): ?>
            <div class="d-flex justify-content-between small"><a href="<?= e(url('/project-sites/' . $site['id'])) ?>"><?= e((string) $site['site_code']) ?></a><span><?= e((string) ($site['current_target_date'] ?? '')) ?></span></div>
        <?php endforeach; ?>
    </section>
<?php elseif ($tab === 'calendar'): ?>
    <section class="sf-panel">
        <?php if ($calendar === []): ?><p>Nothing dated in the next two months.</p><?php endif; ?>
        <ul><?php foreach ($calendar as $event): ?><li><?= e((string) $event['event_date']) ?> · <?= e((string) $event['kind']) ?> · <?= e((string) $event['label']) ?></li><?php endforeach; ?></ul>
    </section>
    <section class="sf-panel mt-3">
        <h2>Site map</h2>
        <p class="sf-muted">Business addresses only. Staff locations are not shown.</p>
        <?php if ($map === []): ?><p>No coordinates on the sites yet.</p><?php endif; ?>
        <ul>
            <?php foreach ($map as $pin): ?>
                <li><a href="<?= e('geo:' . $pin['latitude'] . ',' . $pin['longitude']) ?>"><?= e((string) $pin['site_code']) ?></a> <?= e(str_replace('_', ' ', (string) $pin['status'])) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php elseif ($tab === 'financials' && $showMoney): ?>
    <div class="row g-3 mb-3">
        <?php foreach ([
            'Commercial value' => $financials['commercial_value'],
            'Estimated cost' => $financials['estimated_cost'],
            'Actual cost' => $financials['actual_cost'],
            'Gross profit' => $financials['gross_profit'],
            'Invoiced' => $financials['invoiced'],
            'Cash collected' => $financials['paid'],
            'Outstanding' => $financials['outstanding'],
            'Budget' => $financials['budget'],
        ] as $label => $amount): ?>
            <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker"><?= e($label) ?></p><h2 class="h5"><?= e(money((string) $amount)) ?></h2></section></div>
        <?php endforeach; ?>
    </div>
    <section class="sf-panel">
        <p>Margin <?= $financials['margin_percent'] === null ? '—' : e((string) $financials['margin_percent']) . '%' ?>. Margin is gross profit divided by commercial value. Cash collected is not revenue.</p>
        <p>Budget variance (budget minus actual cost): <?= e(money((string) $financials['budget_variance'])) ?>.</p>
        <h2>Commercial lines</h2>
        <ul>
            <?php foreach ($financials['lines'] as $line): ?>
                <li><?= e((string) $line['label']) ?> · <?= e(money((string) $line['amount'])) ?> · <?= (int) $line['counts_as_value'] === 1 ? 'counts' : 'allocation only' ?><?= (int) $line['superseded'] === 1 ? ' · superseded' : '' ?></li>
            <?php endforeach; ?>
        </ul>
        <h2>Material demand</h2>
        <?php if ($materials === []): ?><p>No material requirements on the linked jobs.</p><?php endif; ?>
        <ul><?php foreach ($materials as $row): ?><li><?= e((string) ($row['product_name'] ?? 'Item')) ?>: <?= e((string) $row['required_qty']) ?> <?= e((string) $row['unit']) ?></li><?php endforeach; ?></ul>
        <p>Open purchase requests: <?= (int) $purchasing['open_requests'] ?> of <?= (int) $purchasing['requests'] ?>.</p>
    </section>
    <?php if ($canBudget): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/budget')) ?>">
        <?= csrf_field() ?>
        <h2>Planning budget</h2>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="category" placeholder="MATERIALS" required></div>
            <div class="col-md-4"><input class="form-control" name="amount" placeholder="0.00" required></div>
            <div class="col-md-4"><button class="btn btn-outline-light" type="submit">Save budget line</button></div>
        </div>
    </form>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/costs')) ?>">
        <?= csrf_field() ?>
        <h2>Project-level cost</h2>
        <p class="sf-muted">Use this only when the cost cannot sit on one job.</p>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="description" placeholder="Project management travel" required></div>
            <div class="col-md-3"><input class="form-control" name="amount" required></div>
            <div class="col-md-3"><input class="form-control" type="date" name="cost_date" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-md-2"><button class="btn btn-outline-light" type="submit">Add</button></div>
        </div>
    </form>
    <?php endif; ?>
    <?php if ($canChanges): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/changes')) ?>">
        <?= csrf_field() ?>
        <h2>Project change</h2>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="title" placeholder="Add five branches" required></div>
            <div class="col-md-2"><input class="form-control" name="commercial_impact" placeholder="Commercial"></div>
            <div class="col-md-2"><input class="form-control" name="cost_impact" placeholder="Cost"></div>
            <div class="col-md-2"><input class="form-control" name="sites_affected" placeholder="Sites"></div>
            <div class="col-md-2"><button class="btn btn-outline-light" type="submit">Raise</button></div>
        </div>
    </form>
    <?php foreach ($changes as $change): ?>
        <form class="sf-panel mt-2" method="post" action="<?= e(url($base . '/changes/' . $change['id'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= (int) $change['version'] ?>">
            <strong><?= e((string) $change['change_number']) ?></strong> <?= e((string) $change['title']) ?> · <?= e((string) $change['status']) ?> · <?= e(money((string) $change['commercial_impact'])) ?>
            <?php if (in_array($change['status'], ['DRAFT', 'AWAITING_APPROVAL'], true)): ?>
                <button class="btn btn-sm btn-sf" name="decision" value="APPROVED" type="submit">Approve</button>
                <button class="btn btn-sm btn-outline-light" name="decision" value="DECLINED" type="submit">Decline</button>
            <?php endif; ?>
        </form>
    <?php endforeach; ?>
    <?php endif; ?>
<?php elseif ($tab === 'documents'): ?>
    <section class="sf-panel">
        <?php if ($documents === []): ?><p>No project documents yet.</p><?php endif; ?>
        <ul><?php foreach ($documents as $document): ?><li><?= e((string) $document['category']) ?> · <?= e((string) $document['original_filename']) ?><?= (int) $document['share_with_jobs'] === 1 ? ' · shared with jobs' : '' ?><?= (int) $document['customer_visible'] === 1 ? ' · customer visible' : '' ?></li><?php endforeach; ?></ul>
    </section>
    <?php if ($canEdit): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/documents')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" type="file" name="file" required></div>
            <div class="col-md-3"><input class="form-control" name="category" value="OTHER" placeholder="CONTRACT"></div>
            <div class="col-md-5">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="share_with_jobs" value="1"> Available to jobs on this project</label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="customer_visible" value="1"> Customer visible</label>
            </div>
        </div>
        <button class="btn btn-sf mt-2" type="submit">Upload</button>
    </form>
    <?php endif; ?>
<?php elseif ($tab === 'contacts'): ?>
    <section class="sf-panel">
        <h2>Team</h2>
        <ul><?php foreach ($team as $member): ?><li><?= e((string) $member['name']) ?> · <?= e((string) $member['role_code']) ?></li><?php endforeach; ?></ul>
        <h2>Customer contacts</h2>
        <ul><?php foreach ($contacts as $contact): ?><li><?= e((string) $contact['name']) ?> · <?= e((string) $contact['role_code']) ?></li><?php endforeach; ?></ul>
    </section>
    <?php if ($canEdit): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/notes')) ?>">
        <?= csrf_field() ?>
        <h2>Note</h2>
        <textarea class="form-control mb-2" name="body" rows="3" required></textarea>
        <select class="form-select mb-2" name="visibility"><option value="INTERNAL">Internal</option><option value="CUSTOMER">Customer visible</option></select>
        <button class="btn btn-outline-light" type="submit">Save note</button>
    </form>
    <?php endif; ?>
<?php elseif ($tab === 'risks'): ?>
    <section class="sf-panel">
        <h2>Risks</h2>
        <p class="sf-muted">Score is probability times impact. It is not a prediction.</p>
        <?php foreach ($risks as $risk): ?>
            <p><?= e((string) $risk['title']) ?> · <?= (int) $risk['probability'] ?>×<?= (int) $risk['impact'] ?> · <?= e((string) $risk['status']) ?></p>
        <?php endforeach; ?>
        <h2>Issues</h2>
        <?php foreach ($issues as $issue): ?>
            <form method="post" action="<?= e(url($base . '/issues/' . $issue['id'])) ?>" class="d-flex gap-2 align-items-center mb-2">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) $issue['version'] ?>">
                <span><?= e((string) $issue['title']) ?></span>
                <?php if ($canIssues): ?>
                    <select class="form-select form-select-sm" name="status">
                        <?php foreach (['OPEN','IN_PROGRESS','RESOLVED','CLOSED'] as $status): ?><option <?= $issue['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-light" type="submit">Save</button>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
    </section>
    <?php if ($canRisks): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/risks')) ?>">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="title" placeholder="Risk" required></div>
            <div class="col-md-2"><input class="form-control" name="category" placeholder="SCHEDULE"></div>
            <div class="col-md-2"><input class="form-control" name="probability" value="3"></div>
            <div class="col-md-2"><input class="form-control" name="impact" value="3"></div>
            <div class="col-md-2"><button class="btn btn-outline-light" type="submit">Add risk</button></div>
        </div>
    </form>
    <?php endif; ?>
    <?php if ($canIssues): ?>
    <form class="sf-panel mt-3" method="post" action="<?= e(url($base . '/issues')) ?>">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-6"><input class="form-control" name="title" placeholder="Issue that has already happened" required></div>
            <div class="col-md-3"><input class="form-control" name="category" placeholder="SITE"></div>
            <div class="col-md-3"><button class="btn btn-outline-light" type="submit">Add issue</button></div>
        </div>
    </form>
    <?php endif; ?>
<?php elseif ($tab === 'activity'): ?>
    <p>
        <?php foreach (['ALL','COMMERCIAL','SITE','PRODUCTION','INSTALLATION','FINANCE','DOCUMENTS','SYSTEM'] as $filter): ?>
            <a href="<?= e(url($base . '?tab=activity&filter=' . $filter)) ?>"><?= e($filter) ?></a>
        <?php endforeach; ?>
    </p>
    <section class="sf-panel">
        <?php if ($activity === []): ?><p>No matching activity.</p><?php endif; ?>
        <ul><?php foreach ($activity as $row): ?><li><?= e((string) $row['created_at']) ?> · <?= e((string) $row['action']) ?> · <?= e((string) ($row['user_name'] ?? '')) ?></li><?php endforeach; ?></ul>
    </section>
<?php endif; ?>
