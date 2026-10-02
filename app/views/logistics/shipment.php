<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e((string) $shipment['shipment_number']) ?></h1>
    <p class="text-secondary mb-0"><?= e((string) $shipment['shipment_type']) ?> · <?= e((string) $shipment['status']) ?></p>
</div>
<?php if ($checks !== []): ?>
    <section class="sf-panel p-3 mb-3"><h2 class="h6">Before it leaves</h2><ul class="mb-0"><?php foreach ($checks as $check): ?><li><?= e($check) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <section class="sf-panel p-3">
            <h2 class="h5">Items</h2>
            <?php if ($items === []): ?><p>Nothing is on this shipment yet.</p><?php else: ?>
                <ul><?php foreach ($items as $item): ?><li><?= e((string) $item['description']) ?> · <?= e((string) $item['quantity']) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/logistics/shipments/' . $shipment['id'] . '/items')) ?>" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-6"><input class="form-control" name="job_id" placeholder="Job" value="<?= e((string) ($shipment['job_id'] ?? '')) ?>"></div>
                <div class="col-6"><input class="form-control" name="job_item_id" placeholder="Job item"></div>
                <div class="col-6"><input class="form-control" name="fulfilment_requirement_id" placeholder="Fulfilment"></div>
                <div class="col-6"><input class="form-control" name="quantity" placeholder="Quantity"></div>
                <div class="col-12"><input class="form-control" name="description" placeholder="Description"></div>
                <div class="col-12"><button class="btn btn-sf" type="submit">Add item</button></div>
            </form>
        </section>
    </div>
    <div class="col-12 col-lg-6">
        <section class="sf-panel p-3 mb-3">
            <h2 class="h5">Courier</h2>
            <p class="text-secondary">Booking a courier does not mean it has been collected.</p>
            <form method="post" action="<?= e(url('/logistics/shipments/' . $shipment['id'] . '/book')) ?>" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12"><select class="form-select" name="courier_id"><?php foreach ($couriers as $courier): ?><option value="<?= (int) $courier['id'] ?>"><?= e((string) $courier['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><input class="form-control" name="waybill_number" placeholder="Waybill" value="<?= e((string) ($shipment['waybill_number'] ?? '')) ?>"></div>
                <div class="col-6"><input class="form-control" name="tracking_number" placeholder="Tracking" value="<?= e((string) ($shipment['tracking_number'] ?? '')) ?>"></div>
                <div class="col-4"><input class="form-control" name="estimated_courier_cost" placeholder="Estimate"></div>
                <div class="col-4"><input class="form-control" name="actual_courier_cost" placeholder="Actual"></div>
                <div class="col-4"><input class="form-control" name="customer_delivery_charge" placeholder="Customer charge"></div>
                <div class="col-12"><button class="btn btn-outline-light" type="submit">Book manually</button></div>
            </form>
            <?php if ($link): ?><p class="mt-2 mb-0"><a href="<?= e($link) ?>">Tracking link</a></p><?php endif; ?>
        </section>
        <?php if ($profit !== []): ?>
            <section class="sf-panel p-3">
                <h2 class="h6">Internal delivery result</h2>
                <p class="mb-0">Charge <?= e((string) $profit['customer_delivery_charge']) ?> · actual <?= e((string) $profit['actual_courier_cost']) ?> · variance <?= e((string) $profit['variance']) ?></p>
            </section>
        <?php endif; ?>
    </div>
</div>
<section class="sf-panel p-3 mt-3">
    <h2 class="h5">Dispatch</h2>
    <form method="post" action="<?= e(url('/logistics/shipments/' . $shipment['id'] . '/dispatch')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-8"><input class="form-control" name="override" placeholder="Override reason if a check is still open"></div>
        <div class="col-12 col-md-4"><button class="btn btn-sf w-100" type="submit">Dispatch</button></div>
    </form>
    <form method="post" action="<?= e(url('/logistics/shipments/' . $shipment['id'] . '/events')) ?>" class="row g-2 mt-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-4"><select class="form-select" name="status"><?php foreach (['COLLECTED','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED','DELIVERY_FAILED'] as $status): ?><option><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-6"><input class="form-control" name="description" placeholder="What happened"></div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-light w-100" type="submit">Record</button></div>
    </form>
</section>
<?php if ($packages !== []): ?>
    <section class="sf-panel p-3 mt-3"><h2 class="h5">Packages</h2><ul class="mb-0"><?php foreach ($packages as $package): ?>
        <li><a href="<?= e(url('/logistics/packages/' . $package['id'] . '/label')) ?>"><?= e((string) $package['package_code']) ?></a> · <?= e((string) $package['status']) ?></li>
    <?php endforeach; ?></ul></section>
<?php endif; ?>
