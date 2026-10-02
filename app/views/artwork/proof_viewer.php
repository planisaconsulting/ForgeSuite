<?php
/** @var array<string, mixed>|null $proof */
/** @var list<array<string, mixed>> $annotations */
/** @var bool $customer */
$proof = $proof ?? null;
$annotations = $annotations ?? [];
$customer = $customer ?? false;
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($proof === null) {
    echo '<p>No proof has been sent for this revision.</p>';

    return;
}
$visible = [];
foreach ($annotations as $note) {
    if ($customer && (string) $note['visibility'] !== 'CUSTOMER_SHARED') {
        continue;
    }
    if ((int) $note['page_number'] !== $page) {
        continue;
    }
    $visible[] = $note;
}
$mime = (string) $proof['mime_type'];
$src = $fileUrl ?? '';
?>
<section class="sf-panel mb-3" id="proof">
    <div class="p-3">
        <p class="sf-kicker">PROOF · NOT FOR PRODUCTION</p>
        <p><?= e((string) ($label ?? '')) ?> · <?= e((string) $proof['original_filename']) ?> · page <?= (int) $page ?></p>
        <?php if ((int) ($proof['page_count'] ?? 0) > 1): ?>
            <p>
                <?php for ($i = 1; $i <= (int) $proof['page_count']; $i++): ?>
                    <a class="btn btn-sm btn-outline-light" href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
            </p>
        <?php endif; ?>
        <div class="proof-stage" id="proof-stage" style="position:relative;overflow:auto;background:#111;min-height:240px;">
            <?php if (str_starts_with($mime, 'image/') && $src !== ''): ?>
                <img id="proof-image" src="<?= e($src) ?>" alt="Artwork proof" style="max-width:100%;transform-origin:0 0;">
            <?php elseif ($mime === 'application/pdf'): ?>
                <p class="p-3">PDF page <?= (int) $page ?>. The page number is stored with each comment. A bitmap of this page is not rendered on this server.</p>
            <?php else: ?>
                <p class="p-3">This proof has no preview. The file is still the customer proof for this revision.</p>
            <?php endif; ?>
            <?php foreach ($visible as $note): ?>
                <?php if ((string) $note['kind'] === 'AREA'): ?>
                    <div style="position:absolute;left:<?= e((string) round(((float) $note['x']) * 100, 4)) ?>%;top:<?= e((string) round(((float) $note['y']) * 100, 4)) ?>%;width:<?= e((string) round(((float) $note['width']) * 100, 4)) ?>%;height:<?= e((string) round(((float) $note['height']) * 100, 4)) ?>%;border:2px solid #f5c16c;background:rgba(245,193,108,.2);"></div>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-warning" style="position:absolute;left:<?= e((string) round(((float) $note['x']) * 100, 4)) ?>%;top:<?= e((string) round(((float) $note['y']) * 100, 4)) ?>%;transform:translate(-50%,-50%);" title="<?= e((string) $note['body']) ?>"><?= (int) $note['id'] ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-outline-light" type="button" id="proof-zoom-in">Zoom</button>
            <button class="btn btn-outline-light" type="button" id="proof-fit">Fit</button>
        </div>
        <ul class="mt-3">
            <?php foreach ($visible as $note): ?>
                <li><?= e((string) $note['kind']) ?> page <?= (int) $note['page_number'] ?> · <?= e((string) $note['status']) ?> · <?= e((string) $note['body']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<script>
(function () {
    var image = document.getElementById('proof-image');
    var scale = 1;
    var zoom = document.getElementById('proof-zoom-in');
    var fit = document.getElementById('proof-fit');
    if (!image || !zoom || !fit) return;
    zoom.addEventListener('click', function () {
        scale = Math.min(scale + 0.25, 4);
        image.style.transform = 'scale(' + scale + ')';
    });
    fit.addEventListener('click', function () {
        scale = 1;
        image.style.transform = 'scale(1)';
    });
})();
</script>
