<div class="sf-page-head"><h1>Scan</h1></div>
<p id="sf-scan-result" class="sf-muted">Point the camera at a code, or type it. The camera is requested only when you start a scan.</p>
<div id="sf-scan-camera" class="sf-scan-box mb-3"></div>
<div class="d-grid gap-2">
    <button class="btn btn-sf sf-touch" type="button" id="sf-scan-start">Start camera</button>
    <button class="btn btn-outline-light sf-touch" type="button" id="sf-scan-stop" hidden>Stop camera</button>
    <form method="get" action="<?= e(url('/workshop/scan')) ?>" class="d-grid gap-2" id="sf-scan-form">
        <label class="form-label" for="code">Code</label>
        <input class="form-control form-control-lg" id="code" name="code" autocomplete="off">
        <button class="btn btn-outline-light sf-touch" type="submit">Look up</button>
    </form>
    <label class="form-label" for="sf-scan-file">Photo of a code</label>
    <input class="form-control" id="sf-scan-file" type="file" accept="image/*">
</div>
<p class="sf-muted mt-3">A USB or Bluetooth scanner can type into the code field. Stock does not change from an offline scan.</p>
