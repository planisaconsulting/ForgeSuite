<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Recommendations</h1><p class="sf-muted">Accepting one creates a new recipe version. Older quotes stay on the version they stored.</p></div>
<section class="sf-panel">
<?php if ($rows === []): ?><p class="p-3 mb-0">No recommendations yet.</p><?php endif; ?>
<?php foreach ($rows as $row): ?>
    <article class="p-3 border-bottom">
        <h2 class="h6"><?= e((string) $row['recommendation_type']) ?> · <?= e((string) ($row['recipe_name'] ?? 'Recipe')) ?> · <?= e((string) $row['status']) ?></h2>
        <p><?= e((string) $row['reason']) ?></p>
        <p>Current <?= e((string) $row['current_value']) ?> · Suggested <?= e((string) $row['suggested_value']) ?> · Sample <?= e((string) $row['sample_size']) ?> · <?= e((string) $row['confidence_basis']) ?></p>
        <?php if ((string) $row['status'] === 'NEW' && (can('pricing_recommendations.apply') || can('pricing_recommendations.review'))): ?>
            <form method="post" action="<?= e(url('/estimating/recommendations/' . $row['id'])) ?>" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-md-3"><input class="form-control" name="value" value="<?= e((string) $row['suggested_value']) ?>"></div>
                <div class="col-md-3"><button class="btn btn-sf" name="action" value="accept" type="submit">Accept</button> <button class="btn btn-outline-light" name="action" value="reject" type="submit">Reject</button></div>
            </form>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</section>
