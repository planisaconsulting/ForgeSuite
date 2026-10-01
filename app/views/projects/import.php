<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><a href="<?= e(url('/projects/' . $project['id'])) ?>"><?= e((string) $project['project_number']) ?></a></p>
    <h1>Import sites</h1>
    <p class="sf-muted">Paste a spreadsheet. The first row is the headings. Duplicate site codes stop the import so nothing is half saved.</p>
</div>
<form class="sf-panel" method="post" action="<?= e(url('/projects/' . $project['id'] . '/import')) ?>">
    <?= csrf_field() ?>
    <textarea class="form-control" name="csv" rows="12" required><?= e((string) ($csv ?? "site_code,site_name,address,city,contact,phone,email,target_date,region,reference\nJHB-01,Sandton,1 Rivonia Road,Johannesburg,Anele,0110000000,anele@example.com,2027-06-01,Gauteng,BR-01\n")) ?></textarea>
    <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm" value="1"> Save the valid rows</label>
    <button class="btn btn-sf mt-2" type="submit">Check and import</button>
</form>
<?php if (!empty($report)): ?>
<section class="sf-panel mt-3">
    <p>Imported <?= (int) $report['imported'] ?> · skipped <?= (int) $report['skipped'] ?> · failed <?= (int) $report['failed'] ?> · <?= $report['committed'] ? 'saved' : 'not saved' ?></p>
    <?php foreach ($report['errors'] as $error): ?><p class="text-warning mb-1"><?= e($error) ?></p><?php endforeach; ?>
    <?php foreach ($report['warnings'] as $warning): ?><p class="sf-muted mb-1"><?= e($warning) ?></p><?php endforeach; ?>
</section>
<?php endif; ?>
