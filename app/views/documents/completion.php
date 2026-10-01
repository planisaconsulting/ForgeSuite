<?php /** Installation or job completion certificate. */ ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?= e((string) $number) ?></title>
<style>body{font-family:DejaVu Sans,sans-serif;color:#111;font-size:12px;margin:24px}</style></head><body>
<p><?= e((string) ($company['name'] ?? '')) ?></p>
<h1>Completion certificate <?= e((string) $number) ?></h1>
<p><?= e((string) $generated_at) ?></p>
<p>Job <?= e((string) $job['job_number']) ?> · <?= e(customer_label($job)) ?></p>
<p>Site <?= e((string) ($job['site_address'] ?? '')) ?></p>
<ul><?php foreach ($items as $item): ?><li><?= e((string) $item['description']) ?> · <?= e((string) $item['quantity']) ?></li><?php endforeach; ?></ul>
<?php if ($snags !== []): ?><h2>Outstanding</h2><ul><?php foreach ($snags as $snag): if ((string) $snag['status'] === 'RESOLVED') continue; ?><li><?= e((string) $snag['description']) ?></li><?php endforeach; ?></ul><?php endif; ?>
<p><?= e((string) $statement) ?></p>
<?php if ($signature): ?><p>Signed by <?= e((string) $signature['signer_name']) ?> at <?= e((string) $signature['signed_at']) ?></p><?php endif; ?>
</body></html>
