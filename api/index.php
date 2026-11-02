<?php
// TaxMate API — front controller, JSON under /api/v1.
// Deploy: point the web server at this directory as /api, or include this
// file from a route. It does NOT touch the repo's root index.php dashboard.
// This starter dashboard stays untouched; the API lives only under api/.
//
// Routing: the path after /api/v1 (or after /v1) is split into segments.
// Example: GET /api/v1/orgs/3/transactions  ->  ['orgs', '3', 'transactions']

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/response.php';
require __DIR__ . '/lib/validation.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/audit.php';
require __DIR__ . '/lib/slab.php';
require __DIR__ . '/handlers/auth.php';
require __DIR__ . '/handlers/orgs.php';
require __DIR__ . '/handlers/finance.php';
require __DIR__ . '/handlers/business.php';
require __DIR__ . '/handlers/fbr.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pos = strpos($uri, '/api/v1');
if ($pos !== false) {
    $path = substr($uri, $pos + strlen('/api/v1'));
} else {
    $pos = strpos($uri, '/v1');
    $path = $pos !== false ? substr($uri, $pos + strlen('/v1')) : $uri;
}
$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') ? taxmate_input() : [];

try {
    $pdo = taxmate_db();

    if ($segments === ['health'] && $method === 'GET') {
        taxmate_ok(['service' => 'taxmate-api', 'version' => '1.0.0']);
    }

    // Handlers exit on success or on error; if a handler returns without
    // responding, it did not match this route and the next handler runs.
    taxmate_handle_auth($pdo, $method, $segments, $input);
    taxmate_handle_orgs($pdo, $method, $segments, $input);
    taxmate_handle_finance($pdo, $method, $segments, $input);
    taxmate_handle_business($pdo, $method, $segments, $input);
    taxmate_handle_fbr($pdo, $method, $segments, $input);

    taxmate_error('not_found', 'Route not found.', 404);
} catch (PDOException $e) {
    // Never leak SQL or connection details to the client.
    taxmate_error('server_error', 'Database error.', 500);
}
