<?php
/** @var list<string> $photoCategories */
$photoCategories = $photoCategories ?? ['OTHER'];
?>
<label class="form-label" for="sf-photo-cat">Category</label>
<select class="form-select mb-2" id="sf-photo-cat">
    <?php foreach ($photoCategories as $cat): ?>
        <option value="<?= e($cat) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $cat)))) ?></option>
    <?php endforeach; ?>
</select>
<label class="form-label" for="sf-photo-caption">Caption</label>
<input class="form-control mb-2" id="sf-photo-caption">
<label class="form-label" for="sf-photo-file">Camera or file</label>
<input class="form-control mb-2" id="sf-photo-file" type="file" accept="image/*" capture="environment">
<div class="d-flex flex-wrap gap-2 mb-2" id="sf-annotate-tools" hidden>
    <button class="btn btn-outline-light" type="button" data-tool="arrow">Arrow</button>
    <button class="btn btn-outline-light" type="button" data-tool="circle">Circle</button>
    <button class="btn btn-outline-light" type="button" data-tool="freehand">Freehand</button>
    <button class="btn btn-outline-light" type="button" data-tool="text">Text</button>
    <input class="form-control" id="sf-annotate-label" placeholder="Label or measurement">
</div>
<canvas id="sf-annotate" class="sf-annotate" width="640" height="360" hidden></canvas>
<p class="sf-muted">Annotation is stored as a second copy. The original photo is not replaced.</p>
<button class="btn btn-outline-light sf-touch" type="button" id="sf-photo-add">Add photo</button>
