<?php
// TaxMate API — validation helpers.
// MONEY RULE: all money is integer paisa (PHP int, MySQL BIGINT).
// Decimal strings are converted by STRING maths only. float/double is
// never used for money anywhere in this API.

declare(strict_types=1);

// Parse an integer paisa value, or a decimal string like "150.50" / "-20.05".
// Returns null when the input is not a valid money value.
// Accepted: int, or a string matching ^-?\d+(\.\d{1,2})?$ .
// Rejected: floats, scientific notation, more than 2 decimal places.
function taxmate_parse_paisa(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
        return null;
    }
    $negative = false;
    if (str_starts_with($value, '-')) {
        $negative = true;
        $value = substr($value, 1);
    }
    $parts = explode('.', $value);
    $rupees = (int)$parts[0];
    $paisaPart = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
    $total = $rupees * 100 + (int)$paisaPart;
    return $negative ? -$total : $total;
}

function taxmate_require_paisa(array $input, string $field): int
{
    if (!array_key_exists($field, $input)) {
        taxmate_error('missing_field', 'Missing required field: ' . $field, 422);
    }
    $parsed = taxmate_parse_paisa($input[$field]);
    if ($parsed === null) {
        taxmate_error('invalid_money', 'Field ' . $field . ' must be integer paisa or a decimal string with at most 2 places.', 422);
    }
    return $parsed;
}

function taxmate_require_int(array $input, string $field): int
{
    if (!isset($input[$field]) || !is_numeric($input[$field])) {
        taxmate_error('invalid_field', 'Field ' . $field . ' must be an integer.', 422);
    }
    return (int)$input[$field];
}

function taxmate_require_string(array $input, string $field, int $maxLength = 500): string
{
    if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
        taxmate_error('missing_field', 'Missing required field: ' . $field, 422);
    }
    $value = trim($input[$field]);
    if (strlen($value) > $maxLength) {
        taxmate_error('invalid_field', 'Field ' . $field . ' is too long.', 422);
    }
    return $value;
}

// Mask a CNIC/NTN: keep only the last 4 characters, everything else '*'].
// Used in every API response and in every log/audit write that touches one.
function taxmate_mask_identifier(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', $len - 4) . substr($value, -4);
}

function taxmate_is_valid_date(string $value): bool
{
    $parts = explode('-', $value);
    if (count($parts) !== 3) {
        return false;
    }
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}
