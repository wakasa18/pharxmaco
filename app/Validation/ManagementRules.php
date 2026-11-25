<?php

namespace App\Validation;

/** Field-specific character rules shared with the browser form checks. */
final class ManagementRules
{
    public function product_unit(mixed $value, ?string &$error = null): bool
    {
        $error = 'Select a unit from the list.';
        return is_string($value) && \App\Libraries\ProductUnit::accepts($value, db_connect());
    }

    public static function definitions(): array
    {
        $catalog = "\\p{L}\\p{M}\\p{N} .,‘’'\"()+&%/:#°×®™–—\\-";
        return [
            'catalog' => ['forbidden' => '[^' . $catalog . ']', 'content' => '[\\p{L}\\p{N}]',
                'message' => 'Include a letter or number. Allowed punctuation includes +, &, %, /, parentheses, apostrophes, commas, dots and hyphens.'],
            'person' => ['forbidden' => "[^\\p{L}\\p{M} .,‘’'\\-]", 'content' => '\\p{L}',
                'message' => 'Use letters, spaces, apostrophes, commas, dots or hyphens for a person’s name.'],
            'code' => ['forbidden' => '[^A-Za-z0-9._/\\-]', 'content' => '[A-Za-z0-9]',
                'message' => 'Use letters or numbers with hyphens (-), underscores (_), dots (.) or slashes (/). Spaces and other symbols are not allowed.'],
            'username' => ['forbidden' => '[^A-Za-z0-9._\\-]', 'content' => '[A-Za-z0-9]',
                'message' => 'Use letters or numbers with dots (.), underscores (_) or hyphens (-) for the username.'],
            'phone' => ['forbidden' => '[^0-9+() .\\-]', 'content' => '[0-9]', 'phone' => true,
                'message' => 'Enter a contact number with 7–15 digits. A leading +, spaces, parentheses, dots and hyphens are allowed.'],
            'address' => ['forbidden' => '[^' . $catalog . '\\r\\n]', 'content' => '[\\p{L}\\p{N}]',
                'message' => 'Include a letter or number. Use address punctuation such as #, commas, dots, slashes, apostrophes and hyphens.'],
            'money' => ['pattern' => '^\\d{1,8}(?:\\.\\d{1,2})?$',
                'message' => 'Enter an amount from 0 to 99,999,999.99 with at most two decimal places. Do not enter currency symbols or exponent notation.'],
            'quantity' => ['pattern' => '^\\d{1,7}$', 'max' => 1000000,
                'message' => 'Enter a whole quantity from 0 to 1,000,000, without symbols, decimals or exponent notation.'],
        ];
    }

    public function management_text(mixed $value, string $type, array $data, ?string &$error = null): bool
    {
        $definition = self::definitions()[$type] ?? null;
        $error = $definition['message'] ?? 'Enter a valid value.';
        if (!is_string($value) || $definition === null) return false;
        if (isset($definition['pattern'])) return preg_match('~' . $definition['pattern'] . '~Du', $value) === 1
            && (!isset($definition['max']) || (float) $value <= $definition['max']);
        if (preg_match('~' . $definition['forbidden'] . '~u', $value) !== 0
            || preg_match('~' . $definition['content'] . '~u', $value) !== 1) return false;
        if (!empty($definition['phone'])) {
            $digits = preg_replace('/\D/', '', $value);
            return strlen($digits) >= 7 && strlen($digits) <= 15
                && substr_count($value, '+') <= 1 && (!str_contains($value, '+') || str_starts_with($value, '+'));
        }
        return true;
    }
}
