<?php
// TaxMate API — PDO connection.
// Reads DB settings ONLY from environment variables. No credentials are
// hardcoded here. See .env.example (placeholders only) and docs/SETUP.md.
// Production MUST set DB_HOST, DB_NAME, DB_USER, DB_PASS in the environment.
// Local XAMPP: set the same variables to your local values; do not commit
// a real .env file.

declare(strict_types=1);

function taxmate_db(): PDO
{
    $host = getenv('DB_HOST');
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    if ($host === false || $host === '') {
        $host = 'localhost';
    }
    if ($name === false || $name === '') {
        $name = 'taxmate';
    }
    if ($user === false) {
        $user = '';
    }
    if ($pass === false) {
        $pass = '';
    }
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
