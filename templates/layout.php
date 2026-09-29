<?php

use function App\e;
use function App\pull_flash;

/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>D&amp;D Initiative Tracker</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <header>
        <h1><a href="/">D&amp;D Initiative Tracker</a></h1>
        <nav>
            <a href="/encounters">Encounters</a>
            <a href="/characters">Characters</a>
            <a href="/monsters">Monsters</a>
            <a href="/import">Import</a>
        </nav>
    </header>
    <main>
        <?php foreach (pull_flash() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</body>
</html>
