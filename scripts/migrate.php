<?php
// TaxMate migration runner — run on the PC or server AFTER php -l checks.
// Usage (from the repo root):  php scripts/migrate.php
// Reads database/migrations/*.sql in filename order, applies each file once,
// and records it in schema_migrations. Files are pure SQL; this script is
// the only PHP that touches them. It never writes credentials anywhere.
//
// Environment: DB_HOST, DB_NAME, DB_USER, DB_PASS (see .env.example).
// This script does not run on the Luna VM: that VM has no php binary.

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'taxmate';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (' .
    'filename VARCHAR(255) NOT NULL PRIMARY KEY, ' .
    'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$files = glob(__DIR__ . '/../database/migrations/*.sql');
sort($files);
$applied = 0;
$skipped = 0;
foreach ($files as $file) {
    $filename = basename($file);
    $stmt = $pdo->prepare('SELECT filename FROM schema_migrations WHERE filename = ? LIMIT 1');
    $stmt->execute([$filename]);
    if ($stmt->fetch() !== false) {
        echo "SKIP {$filename} (already applied)\n";
        $skipped++;
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read {$filename}\n");
        exit(1);
    }
    // Split on a semicolon followed by a newline. Migration files are kept
    // simple DDL/DML with no semicolons inside string literals.
    $statements = preg_split('/;\s*\n/', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        // Strip leading -- comment lines. Without this, the first statement
        // of each file (which follows the file header comment) would be
        // skipped entirely, silently losing the first table in every file.
        $lines = explode("\n", $statement);
        $kept = [];
        foreach ($lines as $line) {
            if (!str_starts_with(trim($line), '--')) {
                $kept[] = $line;
            }
        }
        $statement = trim(implode("\n", $kept));
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
    $stmt->execute([$filename]);
    echo "APPLIED {$filename}\n";
    $applied++;
}
echo "Done: {$applied} applied, {$skipped} skipped.\n";
