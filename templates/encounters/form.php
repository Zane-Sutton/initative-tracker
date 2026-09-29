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
        <?= field('name', 'Encounter name', $values, $errors, 'text', ['required' => true, 'maxlength' => 150, 'placeholder' => 'e.g. Goblin Ambush on Triboar Trail']) ?>
    </div>
    <?= field('notes', 'Notes & description', $values, $errors, 'textarea', ['rows' => 4, 'placeholder' => 'Environment, tactics, reinforcements, room description...']) ?>

    <div class="actions">
        <button type="submit">Save</button>
        <a href="/encounters">Cancel</a>
    </div>
</form>
