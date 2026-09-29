<?php

declare(strict_types=1);

namespace App;

/**
 * Small fluent validator. Empty optional values become null.
 *
 *   $v = (new Validator($_POST))->string('name', 'Name', 100, required: true)->int('level', 'Level', 1, 20);
 *   $v->fails() ? $v->errors() : $v->data();
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data = [];

    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    public function string(string $key, string $label, int $max, bool $required = false): static
    {
        $value = $this->raw($key);

        if ($value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            $this->data[$key] = null;

            return $this;
        }

        if (mb_strlen($value) > $max) {
            $this->errors[$key] = "$label must be at most $max characters.";

            return $this;
        }

        $this->data[$key] = $value;

        return $this;
    }

    public function int(string $key, string $label, int $min, int $max, bool $required = true): static
    {
        $value = $this->raw($key);

        if ($value === '') {
            if ($required) {
                $this->errors[$key] = "$label is required.";
            }
            $this->data[$key] = null;

            return $this;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false || $int < $min || $int > $max) {
            $this->errors[$key] = "$label must be a whole number between $min and $max.";

            return $this;
        }

        $this->data[$key] = $int;

        return $this;
    }

    /** Optional JSON object/array; the original text is stored as entered. */
    public function json(string $key, string $label): static
    {
        $value = $this->raw($key);

        if ($value === '') {
            $this->data[$key] = null;

            return $this;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->errors[$key] = "$label must be valid JSON.";

            return $this;
        }

        if (!is_array($decoded)) {
            $this->errors[$key] = "$label must be a JSON object or array.";

            return $this;
        }

        $this->data[$key] = $value;

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function raw(string $key): string
    {
        $value = $this->input[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
