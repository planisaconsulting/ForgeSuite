<div class="sf-page-head">
    <h1>Warehouse locations</h1>
    <p>Warehouse, zone, aisle, rack, shelf, and bin use one location list. A parent is optional.</p>
</div>
<form method="post" class="sf-panel">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" placeholder="WH1-VIN-A-02"></div>
        <div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="name"></div>
        <div class="col-md-3"><label class="form-label">Type</label>
            <select class="form-select" name="location_type">
                <?php foreach (['WAREHOUSE','ZONE','AISLE','RACK','SHELF','BIN','QUARANTINE','OFFCUT','VEHICLE','WORKSHOP'] as $type): ?>
                    <option><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Parent id</label><input class="form-control" name="parent_id"></div>
    </div>
    <button class="btn btn-light mt-3" type="submit">Save location</button>
</form>
