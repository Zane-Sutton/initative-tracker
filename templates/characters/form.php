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
        <?= field('player_name', 'Player', $values, $errors, 'text', ['maxlength' => 100]) ?>
        <?= field('class', 'Class', $values, $errors, 'text', ['maxlength' => 100]) ?>
        <?= field('level', 'Level', $values, $errors, 'number', ['required' => true, 'min' => 1, 'max' => 20]) ?>
        <?= field('armor_class', 'Armor class', $values, $errors, 'number', ['required' => true, 'min' => 0, 'max' => 40]) ?>
        <?= field('max_hp', 'Max HP', $values, $errors, 'number', ['required' => true, 'min' => 1, 'max' => 9999]) ?>
        <?= field('initiative_bonus', 'Initiative bonus', $values, $errors, 'number', ['required' => true, 'min' => -20, 'max' => 30]) ?>
        <?= field('passive_perception', 'Passive perception', $values, $errors, 'number', ['min' => 0, 'max' => 60]) ?>
    </div>
    <?= field('notes', 'Notes', $values, $errors, 'textarea', ['rows' => 4]) ?>

    <div class="actions">
        <button type="submit">Save</button>
        <a href="/characters">Cancel</a>
    </div>
</form>
