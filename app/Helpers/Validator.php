<?php
class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (empty($this->data[$field]) && $this->data[$field] !== '0' && $this->data[$field] !== 0) {
            $this->errors[$field] = "{$label}は必須です。";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) > $max) {
            $this->errors[$field] = "{$label}は{$max}桁以内で入力してください。";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) < $min) {
            $this->errors[$field] = "{$label}は{$min}桁以上で入力してください。";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && $this->data[$field] !== '' && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "{$label}は数値で入力してください。";
        }
        return $this;
    }

    public function digits(string $field, int $length, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!preg_match('/^\d{' . $length . '}$/', $this->data[$field])) {
                $this->errors[$field] = "{$label}は{$length}桁の数字で入力してください。";
            }
        }
        return $this;
    }

    public function date(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            $d = DateTime::createFromFormat('Y/m/d', $this->data[$field]);
            if (!$d || $d->format('Y/m/d') !== $this->data[$field]) {
                $this->errors[$field] = "{$label}は正しい日付形式で入力してください。";
            }
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "{$label}は有効な値を選択してください。";
        }
        return $this;
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getError(string $field): ?string {
        return $this->errors[$field] ?? null;
    }

    public function getFirstError(): ?string {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}
