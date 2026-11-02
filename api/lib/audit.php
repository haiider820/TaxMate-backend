<?php
// TaxMate API — append-only audit log.
// audit_logs rows are INSERT-only by convention: no handler in this API
// updates or deletes them. old/new values are JSON and must never contain
// tokens, passwords, or unmasked CNIC/NTN.

declare(strict_types=1);

function taxmate_audit(PDO $pdo, ?int $orgId, ?int $userId, string $action, string $entityType, ?int $entityId, mixed $oldValue = null, mixed $newValue = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (organization_id, user_id, action, entity_type, entity_id, old_value_json, new_value_json, created_at) ' .
        'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $orgId,
        $userId,
        $action,
        $entityType,
        $entityId,
        $oldValue === null ? null : json_encode($oldValue),
        $newValue === null ? null : json_encode($newValue),
    ]);
}
