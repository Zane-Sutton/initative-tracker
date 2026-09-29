<?php

declare(strict_types=1);

namespace App;

final class View
{
    /** Render templates/<name>.php inside templates/layout.php. */
    public static function render(string $name, array $data = []): void
    {
        $templateDir = dirname(__DIR__) . '/templates';

        extract($data, EXTR_SKIP);
        ob_start();
        require "$templateDir/$name.php";
        $content = ob_get_clean();

        require "$templateDir/layout.php";
    }
}
