<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e($title) ?></h1>
    <p class="sf-muted mb-0">Dates come from jobs, installations, and tasks. Moving a date on the job moves it here.</p>
</div>
<form class="sf-filters mb-3" method="get" action="<?= e(url('/calendar')) ?>">
    <label class="visually-hidden" for="cal-from">From</label>
    <input class="form-control" id="cal-from" type="date" name="from" value="<?= e($from) ?>">
    <label class="visually-hidden" for="cal-view">View</label>
    <select class="form-select" id="cal-view" name="view">
        <?php foreach (['agenda' => 'Agenda', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $view === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="visually-hidden" for="cal-scope">Scope</label>
    <select class="form-select" id="cal-scope" name="scope">
        <option value="MY" <?= $scope === 'MY' ? 'selected' : '' ?>>My calendar</option>
        <?php if (can('calendar.view_company')): ?><option value="COMPANY" <?= $scope === 'COMPANY' ? 'selected' : '' ?>>Company</option><?php endif; ?>
    </select>
    <button class="btn btn-sf" type="submit">Show</button>
</form>
<section class="sf-panel">
    <div class="sf-panel-head"><h2><?= e(ucfirst($view)) ?></h2></div>
    <?php if ($events === []): ?>
        <div class="sf-empty"><p>Nothing is scheduled in this range. Installations and tasks appear when they have a date.</p></div>
    <?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($events as $event): ?>
                <li>
                    <a href="<?= e(url((string) $event['href'])) ?>"><?= e((string) $event['summary']) ?></a>
                    <small><?= e((string) $event['date']) ?> · <?= e((string) $event['status']) ?> · <?= e((string) $event['priority']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
