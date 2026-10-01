<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Templates</h1><p class="sf-muted mb-0">Placeholders such as {{quote_number}} are replaced. PHP and other expressions are not run.</p></div>
<section class="sf-panel mb-3">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Name</th><th>Channel</th><th>Category</th><th>Active</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['name']) ?></td>
                    <td><?= e((string) $row['channel']) ?></td>
                    <td><?= e((string) $row['category']) ?></td>
                    <td><?= (int) $row['active'] === 1 ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/communications/templates')) ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Name" required></div>
        <div class="col-md-2"><select class="form-select" name="channel"><option>EMAIL</option><option>WHATSAPP</option></select></div>
        <div class="col-md-3"><input class="form-control" name="category" value="CUSTOM"></div>
        <div class="col-md-3"><input class="form-control" name="subject_template" placeholder="Subject"></div>
        <div class="col-12"><textarea class="form-control" name="body_template" rows="4" placeholder="Hello {{contact_name}}" required></textarea></div>
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="active" checked> Active</label></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Save template</button></div>
    </div>
</form>
<?php endif; ?>
