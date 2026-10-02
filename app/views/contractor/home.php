<h1>Your work</h1>
<p>Hello <?= e((string) $user['display_name']) ?>.</p>
<?php
$buckets = ['acceptance' => [], 'upcoming' => [], 'active' => [], 'done' => []];
foreach ($rows as $row) {
    $status = (string) $row['status'];
    if (in_array($status, ['SENT', 'VIEWED'], true)) {
        $buckets['acceptance'][] = $row;
    } elseif (in_array($status, ['APPROVED_COMPLETE', 'DECLINED', 'CANCELLED'], true)) {
        $buckets['done'][] = $row;
    } elseif (in_array($status, ['ACCEPTED', 'SCHEDULED'], true)) {
        $buckets['upcoming'][] = $row;
    } else {
        $buckets['active'][] = $row;
    }
}
?>
<?php foreach (['acceptance' => 'Needs your response', 'upcoming' => 'Upcoming', 'active' => 'In progress', 'done' => 'Finished'] as $key => $heading): ?>
    <section class="sf-panel p-3 mb-3">
        <h2 class="h5"><?= e($heading) ?></h2>
        <?php if ($buckets[$key] === []): ?><p class="mb-0">Nothing in this list.</p><?php else: ?>
            <ul class="mb-0"><?php foreach ($buckets[$key] as $row): ?>
                <li><a href="<?= e(url('/contractor/work/' . $row['id'])) ?>"><?= e((string) $row['work_order_number']) ?></a>
                    <small><?= e((string) $row['work_type']) ?> · <?= e((string) ($row['required_date'] ?? 'No date')) ?> · <?= e((string) $row['status']) ?></small></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
