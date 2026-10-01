<?php
/** Search box and active/inactive filter. $basePath is the list URL. */
$basePath = $basePath ?? '/';
$term = $term ?? '';
$status = $status ?? 'active';
$extra = $extra ?? '';
?>
<form class="sf-filters" method="get" action="<?= e(url($basePath)) ?>">
    <label class="visually-hidden" for="list-q">Search</label>
    <input class="form-control" id="list-q" type="search" name="q" value="<?= e($term) ?>" placeholder="Search">
    <label class="visually-hidden" for="list-status">Status</label>
    <select class="form-select" id="list-status" name="status">
        <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?= $extra ?>
    <button class="btn btn-sf" type="submit">Search</button>
</form>
