<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Campaigns</h1><p class="sf-muted mb-0">Attribution is first touch. Commercial value, invoiced value, and gross profit are shown separately.</p></div>
<section class="sf-panel mb-3">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Channel</th><th>Budget</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/marketing/campaigns/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                    <td><?= e((string) $row['campaign_code']) ?></td>
                    <td><?= e((string) $row['channel']) ?></td>
                    <td><?= e((string) ($row['budget'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/marketing/campaigns')) ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Name" required></div>
        <div class="col-md-3"><input class="form-control" name="campaign_code" placeholder="Code" required></div>
        <div class="col-md-3"><select class="form-select" name="channel"><?php foreach ($channels as $channel): ?><option><?= e($channel) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control" name="budget" placeholder="Budget"></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Save campaign</button></div>
    </div>
</form>
<?php endif; ?>
