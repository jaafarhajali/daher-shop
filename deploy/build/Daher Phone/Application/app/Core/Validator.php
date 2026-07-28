<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal declarative validator.
 *
 * $v = Validator::make($_POST, [
 *     'name'  => 'required|maxlen:150',
 *     'price' => 'required|numeric|min:0',
 *     'email' => 'email',
 * ]);
 * if ($v->fails()) { ... $v->errors() ... }
 *
 * Rules: required, int, numeric, min:N, max:N, maxlen:N, minlen:N,
 *        email, date, in:a,b,c
 * Empty non-required fields pass automatically.
 */
final class Validator
{
    /** @var array<string, string> field => first error message */
    private array $errors = [];

    private function __construct(
        private readonly array $data,
        private readonly array $rules
    ) {
        $this->validate();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return $this->errors === [] ? '' : (string) reset($this->errors);
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = trim((string) ($this->data[$field] ?? ''));
            $label = ucwords(str_replace('_', ' ', $field));
            $rules = explode('|', $ruleString);
            $required = in_array('required', $rules, true);

            if ($value === '') {
                if ($required) {
                    $this->errors[$field] = "{$label} is required.";
                }
                continue; // optional + empty: skip remaining rules
            }

            foreach ($rules as $rule) {
                if (isset($this->errors[$field])) {
                    break;
                }
                $param = '';
                if (str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                $error = match ($rule) {
                    'required' => null,
                    'int'      => filter_var($value, FILTER_VALIDATE_INT) === false
                                    ? "{$label} must be a whole number." : null,
                    'numeric'  => !is_numeric($value)
                                    ? "{$label} must be a number." : null,
                    'min'      => is_numeric($value) && (float) $value < (float) $param
                                    ? "{$label} must be at least {$param}." : null,
                    'max'      => is_numeric($value) && (float) $value > (float) $param
                                    ? "{$label} must not exceed {$param}." : null,
                    'maxlen'   => mb_strlen($value) > (int) $param
                                    ? "{$label} must be at most {$param} characters." : null,
                    'minlen'   => mb_strlen($value) < (int) $param
                                    ? "{$label} must be at least {$param} characters." : null,
                    'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                                    ? "{$label} must be a valid email address." : null,
                    'date'     => !self::isValidDate($value)
                                    ? "{$label} must be a valid date (YYYY-MM-DD)." : null,
                    'in'       => !in_array($value, explode(',', $param), true)
                                    ? "{$label} has an invalid value." : null,
                    default    => null,
                };

                if ($error !== null) {
                    $this->errors[$field] = $error;
                }
            }
        }
    }

    private static function isValidDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
