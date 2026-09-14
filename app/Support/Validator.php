<?php
declare(strict_types=1);

namespace App\Support;

final class Validator
{
    private array $errors = [];

    private function __construct(private array $data, private array $rules)
    {
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool { return $this->errors === []; }
    public function errors(): array { return $this->errors; }

    public function valid(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleStr) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = match ($name) {
                    'required' => $value !== null && $value !== '',
                    'email' => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'int' => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'min' => $value === null || $value === '' || (int) $value >= (int) $arg,
                    'minlen' => $value === null || $value === '' || mb_strlen((string) $value) >= (int) $arg,
                    'max' => $value === null || $value === '' || (int) $value <= (int) $arg,
                    'in' => $value === null || $value === '' || in_array((string) $value, explode(',', (string) $arg), true),
                    'host' => $value === null || $value === '' || preg_match('/^[a-zA-Z0-9.\-]+$/', (string) $value) === 1,
                    default => true,
                };
                if (!$ok) {
                    $this->errors[$field][] = $name;
                }
            }
        }
    }
}
