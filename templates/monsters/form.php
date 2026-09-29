<?php

use function App\csrf_field;
use function App\e;
use function App\field;

/** @var string $title */
/** @var string $action */
/** @var array<string,mixed> $values */
/** @var array<string,string> $errors */
?>
<h2><?= e($title) ?></h2>

<?php if ($errors !== []): ?>
    <p class="error">Please fix the highlighted fields.</p>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="form">
    <?= csrf_field() ?>
    <div class="grid">
        <?= field('name', 'Name', $values, $errors, 'text', ['required' => true, 'maxlength' => 100]) ?>
        <?= field('size', 'Size', $values, $errors, 'text', ['maxlength' => 20, 'placeholder' => 'Medium']) ?>
        <?= field('creature_type', 'Type', $values, $errors, 'text', ['maxlength' => 50, 'placeholder' => 'humanoid (goblinoid)']) ?>
        <?= field('challenge_rating', 'Challenge rating', $values, $errors, 'text', ['maxlength' => 10, 'placeholder' => '1/4']) ?>
        <?= field('armor_class', 'Armor class', $values, $errors, 'number', ['required' => true, 'min' => 0, 'max' => 40]) ?>
        <?= field('hp_average', 'Average HP', $values, $errors, 'number', ['required' => true, 'min' => 1, 'max' => 9999]) ?>
        <?= field('hp_formula', 'HP formula', $values, $errors, 'text', ['maxlength' => 50, 'placeholder' => '2d6']) ?>
        <?= field('initiative_bonus', 'Initiative bonus', $values, $errors, 'number', ['required' => true, 'min' => -20, 'max' => 30]) ?>
        <?= field('speed', 'Speed', $values, $errors, 'text', ['maxlength' => 100, 'placeholder' => '30 ft.']) ?>
        <?= field('source', 'Source', $values, $errors, 'text', ['maxlength' => 100]) ?>
    </div>
    <?= field('actions', 'Actions', $values, $errors, 'textarea', ['rows' => 8]) ?>
    <?= field('stats', 'Stats (JSON, optional)', $values, $errors, 'textarea', ['rows' => 8, 'placeholder' => '{"str": 8, "dex": 14, "con": 10}', 'spellcheck' => 'false']) ?>

    <div class="actions">
        <button type="submit">Save</button>
        <a href="/monsters">Cancel</a>
    </div>
</form>
