<?php

use function App\csrf_field;
use function App\e;

/** @var string $json */
/** @var bool $update */
/** @var list<string> $errors */
$shown = array_slice($errors, 0, 50);
?>
<div class="toolbar">
    <h2>Import characters &amp; monsters</h2>
    <div><a href="/characters">Characters</a> &middot; <a href="/monsters">Monsters</a></div>
</div>

<?php if ($errors !== []): ?>
    <div class="flash flash-error">
        <strong>Nothing was imported.</strong> Fix the following and try again:
        <ul>
            <?php foreach ($shown as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
        <?php if (count($errors) > count($shown)): ?>
            <p>&hellip;and <?= e(count($errors) - count($shown)) ?> more.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" action="/import" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>

    <div class="field">
        <label for="f_json">JSON</label>
        <textarea id="f_json" name="json" rows="16" spellcheck="false" placeholder="Paste JSON here, or use the sample"><?= e($json) ?></textarea>
    </div>

    <div class="field">
        <label for="f_file">...or upload a .json file (takes priority over pasted text)</label>
        <input id="f_file" type="file" name="file" accept=".json,application/json">
    </div>

    <div class="field checkbox">
        <input id="f_update" type="checkbox" name="update_existing" value="1"<?= $update ? ' checked' : '' ?>>
        <label for="f_update">Update existing entries with the same name (replaces all their fields). Otherwise they are skipped.</label>
    </div>

    <div class="actions">
        <button type="submit">Import</button>
        <button type="button" id="load-sample" class="link">Load sample</button>
        <a href="/import/sample" target="_blank" rel="noopener">View sample</a>
    </div>
</form>

<details>
    <summary>Format reference</summary>
    <p>A JSON object with a <code>characters</code> list and/or a <code>monsters</code> list. Everything is validated first; if any entry is invalid, nothing is imported. Absent numeric fields use the defaults shown.</p>
    <ul>
        <li><strong>characters</strong>: <code>name</code> (required, max 100), <code>player_name</code>, <code>class</code>, <code>level</code> 1&ndash;20 (1), <code>armor_class</code> 0&ndash;40 (10), <code>max_hp</code> 1&ndash;9999 (1), <code>initiative_bonus</code> &minus;20&ndash;30 (0), <code>passive_perception</code> 0&ndash;60, <code>notes</code></li>
        <li><strong>monsters</strong>: <code>name</code> (required), <code>size</code>, <code>creature_type</code>, <code>challenge_rating</code> (text, e.g. "1/4"), <code>armor_class</code> (10), <code>hp_average</code> (1), <code>hp_formula</code>, <code>initiative_bonus</code> (0), <code>speed</code>, <code>source</code>, <code>actions</code> (text, or a list of strings, one per line), <code>stats</code> (any JSON object)</li>
    </ul>
<pre>{
  "characters": [
    { "name": "Example", "class": "Rogue", "level": 3, "armor_class": 14, "max_hp": 24, "initiative_bonus": 3 }
  ],
  "monsters": [
    { "name": "Example Bat", "armor_class": 12, "hp_average": 1, "actions": ["Bite. +0 to hit, 1 piercing."], "stats": { "dex": 15 } }
  ]
}</pre>
</details>

<script>
document.getElementById('load-sample').addEventListener('click', async () => {
    const res = await fetch('/import/sample');
    if (res.ok) {
        document.getElementById('f_json').value = await res.text();
    } else {
        alert('Could not load the sample.');
    }
});
</script>
