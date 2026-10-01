<div class="sf-panel p-3">
    <h1>WhatsApp message</h1>
    <p>This opens WhatsApp with the text filled in. It does not send through a business API.</p>
    <textarea class="form-control mb-3" rows="4" readonly><?= e($text) ?></textarea>
    <a class="btn btn-sf" href="<?= e($link) ?>" target="_blank" rel="noopener">Open WhatsApp</a>
</div>
