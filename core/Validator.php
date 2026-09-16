<?php
declare(strict_types=1);

final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data) { $this->data = $data; }

    public function required(string $field, string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function email(string $field): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Invalid email address.';
        }
        return $this;
    }

    public function min(string $field, int $len, string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (!empty($this->data[$field]) && mb_strlen((string)$this->data[$field]) < $len) {
            $this->errors[$field] = "$label must be at least $len characters.";
        }
        return $this;
    }

    public function numeric(string $field, string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "$label must be numeric.";
        }
        return $this;
    }

    public function date(string $field, string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (!empty($this->data[$field])) {
            $d = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field] = "$label is not a valid date.";
            }
        }
        return $this;
    }

    public function matches(string $field, string $otherField, string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (($this->data[$field] ?? null) !== ($this->data[$otherField] ?? null)) {
            $this->errors[$field] = "$label does not match.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "$label has an invalid value.";
        }
        return $this;
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function firstError(): ?string { return array_values($this->errors)[0] ?? null; }
}