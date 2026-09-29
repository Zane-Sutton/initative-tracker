<?php

use function App\e;
use function App\pull_flash;
use function App\csrf_token;

/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>D&amp;D Initiative Tracker</title>
    <link rel="stylesheet" href="/css/app.css">
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <header>
        <div class="header-left">
            <h1><a href="/">D&amp;D Initiative Tracker</a></h1>
            <nav>
                <a href="/encounters">Encounters</a>
                <a href="/characters">Characters</a>
                <a href="/monsters">Monsters</a>
                <a href="/import">Import</a>
            </nav>
        </div>
        <div class="settings-dropdown">
            <button type="button" class="settings-toggle" id="settingsToggle">
                Settings ⚙️
            </button>
            <div class="settings-menu" id="settingsMenu">
                <div class="settings-item" id="darkModeToggle">
                    <span>Dark Mode</span>
                    <input type="checkbox" id="darkModeCheckbox" style="width: auto;">
                </div>
            </div>
        </div>
    </header>
    <main>
        <?php foreach (pull_flash() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('settingsToggle');
            const menu = document.getElementById('settingsMenu');
            const darkToggle = document.getElementById('darkModeToggle');
            const darkCheckbox = document.getElementById('darkModeCheckbox');

            // Initialize checkbox state
            darkCheckbox.checked = document.documentElement.getAttribute('data-theme') === 'dark';

            // Toggle menu visibility
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                menu.classList.remove('show');
            });

            menu.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Dark mode logic
            const setStatus = (isDark) => {
                const theme = isDark ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                darkCheckbox.checked = isDark;
            };

            darkToggle.addEventListener('click', () => {
                setStatus(!darkCheckbox.checked);
            });

            darkCheckbox.addEventListener('change', (e) => {
                setStatus(e.target.checked);
            });
        });
    </script>
</body>
</html>
