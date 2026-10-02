<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Logistics</h1></div>
<div class="row g-3 mb-3">
    <?php foreach ([
        'ready_to_dispatch' => 'Ready to dispatch',
        'awaiting_courier' => 'Awaiting courier',
        'in_transit' => 'In transit',
        'due_today' => 'Due today',
        'delivery_exceptions' => 'Delivery exceptions',
        'installations_today' => 'Installations today',
        'installations_blocked' => 'Installations blocked',
        'contractor_overdue' => 'Contractor work overdue',
        'open_snags' => 'Open snags',
    ] as $key => $label): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <section class="sf-panel p-3 h-100">
                <p class="mb-1 text-secondary"><?= e($label) ?></p>
                <strong class="fs-3"><?= (int) ($cards[$key] ?? 0) ?></strong>
            </section>
        </div>
    <?php endforeach; ?>
</div>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">New shipment</h2>
    <form method="post" action="<?= e(url('/logistics/shipments')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-3"><input class="form-control" name="customer_id" inputmode="numeric" placeholder="Customer id" required></div>
        <div class="col-12 col-md-3"><input class="form-control" name="job_id" inputmode="numeric" placeholder="Job id"></div>
        <div class="col-12 col-md-3">
            <select class="form-select" name="shipment_type">
                <?php foreach (\App\Services\LogisticsService::TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3"><input class="form-control" name="destination_address" placeholder="Destination"></div>
        <div class="col-12 col-md-4"><input class="form-control" name="contact_name" placeholder="Contact"></div>
        <div class="col-12 col-md-4"><input class="form-control" name="required_date" type="date"></div>
        <div class="col-12 col-md-4"><button class="btn btn-sf w-100" type="submit">Create shipment</button></div>
    </form>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Recent shipments</h2></div>
    <?php if ($shipments === []): ?><p class="p-3 mb-0">No shipments yet. A finished job still needs a shipment before it can leave.</p><?php else: ?>
        <ul class="sf-feed"><?php foreach ($shipments as $row): ?>
            <li><a href="<?= e(url('/logistics/shipments/' . $row['id'])) ?>"><?= e((string) $row['shipment_number']) ?></a>
                <small><?= e((string) $row['shipment_type']) ?> · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
