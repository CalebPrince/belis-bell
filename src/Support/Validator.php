<?php
declare(strict_types=1);

namespace Belis\Support;

/**
 * Central server-side validation (CTL-INP-001). Client checks only assist.
 * Rules: required, email, max:N, min:N, int, in:a,b,c
 */
final class Validator
{
    /**
     * @param array<string,mixed>  $input
     * @param array<string,string> $rules field => "required|email|max:120"
     * @return array<string,string> field => message (empty when valid)
     */
    public static function check(array $input, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $empty = $value === null || $value === '';
                if ($name === 'required' && $empty) {
                    $errors[$field] = 'This field is required.';
                    break;
                }
                if ($empty) {
                    continue;
                }
                $msg = match ($name) {
                    'required' => null,
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Enter a valid email address.' : null,
                    'max' => is_string($value) && mb_strlen($value) > (int) $arg ? "Use {$arg} characters or fewer." : null,
                    'min' => is_string($value) && mb_strlen($value) < (int) $arg ? "Use at least {$arg} characters." : null,
                    'int' => filter_var($value, FILTER_VALIDATE_INT) === false ? 'Enter a whole number.' : null,
                    'in' => !in_array((string) $value, explode(',', (string) $arg), true) ? 'Choose one of the listed options.' : null,
                    default => throw new \InvalidArgumentException("Unknown rule {$name}"),
                };
                if ($msg !== null) {
                    $errors[$field] = $msg;
                    break;
                }
            }
        }
        return $errors;
    }
}
