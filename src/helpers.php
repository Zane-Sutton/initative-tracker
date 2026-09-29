<?php

declare(strict_types=1);

namespace App;

/** HTML-escape helper for templates. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
