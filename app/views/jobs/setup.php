<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Production setup</h1>
    <p class="sf-muted mb-0">Stages and route templates. A job keeps its own copy after you apply a route.</p>
</div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Stages</h2></div>
    <ul class="mb-0">
        <?php foreach ($stages as $stage): ?>
            <li><?= e((string) $stage['name']) ?> <span class="sf-muted"><?= e((string) ($stage['description'] ?? '')) ?></span></li>
        <?php endforeach; ?>
    </ul>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/setup/stages')) ?>">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="name" placeholder="Stage name" required></div>
            <div class="col-md-4"><input class="form-control" name="description" placeholder="Description"></div>
            <div class="col-md-2"><input class="form-control" name="sort_order" placeholder="Order" value="200"></div>
            <div class="col-md-2"><button class="btn btn-sf w-100" type="submit">Add stage</button></div>
        </div>
    </form>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Route templates</h2></div>
    <?php foreach ($templates as $index => $template): ?>
        <p class="px-3"><strong><?= e((string) $template['name']) ?></strong>
            <?php foreach ($templateStages[$index] as $stage): ?>
                · <?= e((string) $stage['name']) ?>
            <?php endforeach; ?>
        </p>
    <?php endforeach; ?>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/setup/templates')) ?>">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-3"><input class="form-control" name="code" placeholder="CODE" required></div>
            <div class="col-md-4"><input class="form-control" name="name" placeholder="Template name" required></div>
            <div class="col-md-5"><input class="form-control" name="description" placeholder="Description"></div>
            <div class="col-12">
                <label class="form-label">Stages, in the order you tick them</label>
                <?php foreach ($stages as $stage): ?>
                    <label class="d-block"><input type="checkbox" name="stage_ids[]" value="<?= e((string) $stage['id']) ?>"> <?= e((string) $stage['name']) ?></label>
                <?php endforeach; ?>
            </div>
            <div class="col-12"><button class="btn btn-sf" type="submit">Save template</button></div>
        </div>
    </form>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Teams</h2></div>
    <?php
    $member = [];
    foreach ($memberships as $row) {
        $member[(int) $row['team_id'] . '-' . (int) $row['user_id']] = true;
    }
    ?>
    <?php foreach ($teams as $team): ?>
        <h3 class="h6 px-3"><?= e((string) $team['name']) ?></h3>
        <?php foreach ($staff as $person): ?>
            <?php if ((int) $person['active'] !== 1) { continue; } ?>
            <form class="px-3" method="post" action="<?= e(url('/jobs/setup/teams')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="team_id" value="<?= e((string) $team['id']) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $person['id']) ?>">
                <label>
                    <input type="checkbox" name="member" <?= isset($member[(int) $team['id'] . '-' . (int) $person['id']]) ? 'checked' : '' ?> onchange="this.form.submit()">
                    <?= e((string) $person['name']) ?>
                </label>
            </form>
        <?php endforeach; ?>
    <?php endforeach; ?>
</section>
