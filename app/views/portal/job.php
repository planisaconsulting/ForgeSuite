<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $job['job_number']) ?></p>
    <h1><?= e((string) $job['title']) ?></h1>
    <p><?= e((string) $job['customer_status']) ?></p>
    <?php if (!empty($job['customer_promised_date'])): ?><p>Expected completion <?= e((string) $job['customer_promised_date']) ?></p><?php endif; ?>
</div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Progress</h2></div>
    <ul class="sf-feed">
        <?php foreach ($job['timeline'] as $event): ?>
            <li><?= e($event['label']) ?><small><?= e($event['at']) ?></small></li>
        <?php endforeach; ?>
        <?php if ($job['timeline'] === []): ?><li>We will show progress here as the job moves forward.</li><?php endif; ?>
    </ul>
</section>
<form class="sf-panel p-3" method="post" action="<?= e(url('/portal/messages')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="entity_type" value="job">
    <input type="hidden" name="entity_id" value="<?= e((string) $job['id']) ?>">
    <label class="form-label" for="body">Message about this job</label>
    <textarea class="form-control mb-2" id="body" name="body" rows="3"></textarea>
    <button class="btn btn-sf" type="submit">Send</button>
</form>
<p class="mt-3"><a href="<?= e(url('/portal')) ?>">Back</a></p>
