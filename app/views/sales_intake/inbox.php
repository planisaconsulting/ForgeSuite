<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Sales intake</h1></div>
<div class="row g-3 mb-3">
    <?php foreach ([
        'new' => 'New enquiries',
        'awaiting_review' => 'Awaiting review',
        'awaiting_information' => 'Awaiting information',
        'ready_to_estimate' => 'Ready to estimate',
        'estimates_ready' => 'Estimates ready',
        'draft_quotes' => 'Draft quotes',
        'unassigned' => 'Unassigned',
        'failed' => 'Failed analyses',
    ] as $key => $label): ?>
        <div class="col-6 col-md-3">
            <section class="sf-panel p-3 h-100">
                <p class="mb-1 text-secondary"><?= e($label) ?></p>
                <strong class="fs-3"><?= (int) ($cards[$key] ?? 0) ?></strong>
            </section>
        </div>
    <?php endforeach; ?>
</div>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Capture an enquiry</h2>
    <form method="post" action="<?= e(url('/sales/intake')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-3">
            <select class="form-select" name="source_type">
                <?php foreach (\App\Services\SalesIntakeService::SOURCES as $source): ?>
                    <option><?= e($source) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3"><input class="form-control" name="sender_name" placeholder="Sender or company"></div>
        <div class="col-12 col-md-3"><input class="form-control" name="sender_email" placeholder="Email"></div>
        <div class="col-12 col-md-3"><input class="form-control" name="subject" placeholder="Subject"></div>
        <div class="col-12"><textarea class="form-control" name="message" rows="4" placeholder="Paste the enquiry. The original text is kept." required></textarea></div>
        <div class="col-12 col-md-3"><button class="btn btn-sf w-100" type="submit">Save enquiry</button></div>
    </form>
</section>
<section class="sf-panel">
    <table class="table table-sm mb-0">
        <thead><tr><th>Number</th><th>Status</th><th>Source</th><th>Intent</th><th>Received</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="5">No enquiries yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/sales/intake/' . (int) $row['id'])) ?>"><?= e((string) $row['intake_number']) ?></a></td>
                <td><?= e((string) $row['status']) ?></td>
                <td><?= e((string) $row['source_type']) ?></td>
                <td><?= e((string) $row['intent_type']) ?></td>
                <td><?= e((string) $row['received_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
