<?php
declare(strict_types=1);

final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data) { $this->data = $data; }

    public function required(string $field, ?string $label = null): self {
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

    /** The shared new-password rule (password_problems()); the message names exactly what is missing. */
    public function strongPassword(string $field): self {
        if (isset($this->data[$field]) && (string)$this->data[$field] !== '' && ($p = password_problems((string)$this->data[$field]))) {
            $this->errors[$field] = password_rule_message($p);
        }
        return $this;
    }

    /**
     * A password being created or changed: the shared rule (password_problems(), also when empty), then the confirmation
     * must match. One message at a time, the rule first, as every password form shows it.
     */
    public function newPassword(string $field, string $confirmField, string $mismatch = 'The two passwords do not match.'): self {
        $password = (string)($this->data[$field] ?? '');
        if ($problems = password_problems($password)) $this->errors[$field] = password_rule_message($problems);
        elseif ($password !== (string)($this->data[$confirmField] ?? '')) $this->errors[$confirmField] = $mismatch;
        return $this;
    }

    public function matches(string $field, string $otherField, ?string $label = null): self {
        $label ??= ucfirst(str_replace('_', ' ', $field));
        if (($this->data[$field] ?? null) !== ($this->data[$otherField] ?? null)) {
            $this->errors[$field] = "$label does not match.";
        }
        return $this;
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function firstError(): ?string { return array_values($this->errors)[0] ?? null; }
}