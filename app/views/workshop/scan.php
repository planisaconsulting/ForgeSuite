<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1>Scan</h1>
        <p class="sf-muted mb-0">Use the camera, a USB scanner, or type the tracking code.</p>
    </div>
</div>
<section class="sf-panel p-3 mb-3">
    <div id="scan-camera" class="mb-3"></div>
    <p id="scan-camera-note" class="sf-muted">If the camera is unavailable, use the code box. A keyboard scanner can type into it.</p>
    <form method="post" action="<?= e(url('/workshop/scan')) ?>">
        <?= csrf_field() ?>
        <label class="form-label" for="code">Tracking code</label>
        <input class="form-control form-control-lg mb-3" id="code" name="code" autocomplete="off" autofocus required>
        <button class="btn btn-sf btn-lg w-100" type="submit">Open</button>
    </form>
</section>
