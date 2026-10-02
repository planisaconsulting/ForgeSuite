<div class="sf-page-head">
    <h1>Request a quote</h1>
    <p class="mb-0">Tell us what you need. This does not create a job.</p>
</div>
<form class="sf-panel p-3" method="post" action="<?= e(url('/portal/quote-request')) ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="request_type">What do you need?</label>
    <select class="form-select form-select-lg mb-3" id="request_type" name="request_type">
        <?php foreach ($types as $type): ?>
            <option value="<?= e($type) ?>"><?= e(str_replace('_', ' ', $type)) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="form-label" for="site">Site</label>
    <input class="form-control form-control-lg mb-3" id="site" name="site">
    <label class="form-label" for="notes">What should we know?</label>
    <textarea class="form-control mb-3" id="notes" name="notes" rows="4" required></textarea>
    <button class="btn btn-light btn-lg w-100" type="submit">Send request</button>
</form>
