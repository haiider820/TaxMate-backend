<?php
// TaxMate API — JSON response helpers. Every response is {ok, data|error}.
// Error bodies never contain tokens, passwords, SQL, or stack traces.

declare(strict_types=1);

function taxmate_json(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function taxmate_ok(mixed $data, int $status = 200): void
{
    taxmate_json($status, ['ok' => true, 'data' => $data]);
}

function taxmate_error(string $code, string $message, int $status = 400): void
{
    taxmate_json($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

function taxmate_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        taxmate_error('invalid_json', 'Request body must be valid JSON.', 400);
    }
    return $decoded;
}

function taxmate_require_fields(array $input, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === null) {
            taxmate_error('missing_field', 'Missing required field: ' . $field, 422);
        }
    }
}
