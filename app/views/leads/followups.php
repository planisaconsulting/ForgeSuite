<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Sales follow-ups</h1><p class="sf-muted mb-0">Earlier notes stay on the lead. Setting a new date does not erase them.</p></div>
<?php foreach (['overdue' => 'Overdue', 'today' => 'Today', 'upcoming' => 'Upcoming'] as $key => $label): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2><?= e($label) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Lead</th><th>Name</th><th>Next</th></tr></thead>
            <tbody>
            <?php if ($queue[$key] === []): ?><tr><td colspan="3" class="sf-muted">None</td></tr><?php endif; ?>
            <?php foreach ($queue[$key] as $row): ?>
                <tr>
                    <td><a href="<?= e(url((string) ($row['href'] ?? '/leads/' . $row['id']))) ?>"><?= e((string) $row['lead_number']) ?></a></td>
                    <td><?= e((string) $row['name']) ?></td>
                    <td><?= e((string) $row['next_followup_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>
