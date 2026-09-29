<?php

declare(strict_types=1);

namespace App;

/** HTML-escape helper for templates. */
function e(mixed $value): string
{
    if (is_array($value)) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a modifier with an explicit sign: 2 => "+2", 0 => "+0", -1 => "-1". */
function signed(mixed $number): string
{
    $n = (int) $number;

    return ($n >= 0 ? '+' : '') . $n;
}

/** Standard 5e ability modifier for a given ability score: 10-11 => 0, 12-13 => 1, 8-9 => -1, etc. */
function ability_modifier(mixed $score): int
{
    return (int) floor(((int) $score - 10) / 2);
}

/** Redirect to a local path and stop. */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

/** Render the error page with the given HTTP status and stop. */
function abort(int $status, string $message): never
{
    http_response_code($status);
    if (PHP_SAPI !== 'cli') {
        View::render('error', ['status' => $status, 'message' => $message]);
        exit;
    }
    throw new \RuntimeException("Abort $status: $message");
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function pull_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Abort with 419 unless the POSTed token matches the session token. */
function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';

    if ($sent === '') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['_csrf'])) {
                    $sent = $decoded['_csrf'];
                }
            }
        }
    }

    // Also check X-CSRF-TOKEN header for common AJAX patterns
    if ($sent === '') {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(419, 'Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

/** Pretty-print a JSON string for display/editing; returns the input unchanged if it is not valid JSON. */
function pretty_json(?string $json): string
{
    if ($json === null || $json === '') {
        return '';
    }

    try {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        return $json;
    }

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $json;
}

/**
 * Render a labelled form control with its validation error.
 *
 * @param array<string,mixed>  $values Current values keyed by field name
 * @param array<string,string> $errors Error messages keyed by field name
 * @param array<string,mixed>  $attrs  Extra HTML attributes (true = boolean attribute)
 */
function field(string $name, string $label, array $values, array $errors, string $type = 'text', array $attrs = []): string
{
    $id = 'f_' . $name;
    $value = $values[$name] ?? '';

    $attrHtml = '';
    foreach ($attrs as $attr => $attrValue) {
        if ($attrValue === true) {
            $attrHtml .= ' ' . e($attr);
        } elseif ($attrValue !== false && $attrValue !== null) {
            $attrHtml .= sprintf(' %s="%s"', e($attr), e($attrValue));
        }
    }

    $control = $type === 'textarea'
        ? sprintf('<textarea id="%s" name="%s"%s>%s</textarea>', e($id), e($name), $attrHtml, e($value))
        : sprintf('<input id="%s" type="%s" name="%s" value="%s"%s>', e($id), e($type), e($name), e($value), $attrHtml);

    $error = isset($errors[$name])
        ? sprintf('<span class="error-text">%s</span>', e($errors[$name]))
        : '';

    return sprintf(
        '<div class="field%s"><label for="%s">%s</label>%s%s</div>',
        $error !== '' ? ' has-error' : '',
        e($id),
        e($label),
        $control,
        $error
    );
}
