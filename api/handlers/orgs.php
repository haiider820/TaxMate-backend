<?php
// TaxMate API — organization and member handlers. Every route is member-only.
// Viewer is read-only: write routes require owner/admin/accountant/member.

declare(strict_types=1);

function taxmate_writer_roles(): array
{
    return ['owner', 'admin', 'accountant', 'member'];
}

function taxmate_handle_orgs(PDO $pdo, string $method, array $segments, array $input): void
{
    if ($segments[0] !== 'orgs') {
        return;
    }
    $user = taxmate_require_user($pdo);

    if (count($segments) === 1 && $method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT o.id, o.name, o.type, m.role FROM organizations o ' .
            'JOIN organization_members m ON m.organization_id = o.id ' .
            'WHERE m.user_id = ? ORDER BY o.id ASC'
        );
        $stmt->execute([(int)$user['id']]);
        taxmate_ok($stmt->fetchAll());
    }

    if (count($segments) === 1 && $method === 'POST') {
        $name = taxmate_require_string($input, 'name', 200);
        $type = $input['type'] ?? 'business';
        if ($type !== 'individual' && $type !== 'business') {
            taxmate_error('invalid_field', 'Type must be individual or business.', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO organizations (name, type, created_by) VALUES (?, ?, ?)');
        $stmt->execute([$name, $type, (int)$user['id']]);
        $orgId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO organization_members (organization_id, user_id, role) VALUES (?, ?, 'owner')");
        $stmt->execute([$orgId, (int)$user['id']]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'org.created', 'organization', $orgId);
        taxmate_ok(['id' => $orgId, 'name' => $name, 'type' => $type], 201);
    }

    $orgId = (int)($segments[1] ?? 0);
    if ($orgId <= 0) {
        taxmate_error('not_found', 'Route not found.', 404);
    }
    taxmate_org_exists($pdo, $orgId);
    $member = taxmate_require_member($pdo, (int)$user['id'], $orgId);

    if (count($segments) === 2 && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, name, type FROM organizations WHERE id = ? LIMIT 1');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetch());
    }

    // Sub-resources under an org (transactions, invoices, fbr-returns, ...)
    // belong to their own handlers. Return here so the router can try them;
    // do NOT 404 here, or those handlers would never run.
    if (count($segments) >= 3 && $segments[2] !== 'members') {
        return;
    }

    if (count($segments) === 3 && $segments[2] === 'members' && $method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, m.role FROM users u ' .
            'JOIN organization_members m ON m.user_id = u.id ' .
            'WHERE m.organization_id = ? ORDER BY u.id ASC'
        );
        $stmt->execute([$orgId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
        }
        taxmate_ok($rows);
    }

    if (count($segments) === 3 && $segments[2] === 'members' && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, ['owner', 'admin']);
        $newUserId = taxmate_require_int($input, 'user_id');
        $role = $input['role'] ?? 'member';
        if (!in_array($role, ['owner', 'admin', 'accountant', 'member', 'viewer'], true)) {
            taxmate_error('invalid_field', 'Role is not valid.', 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$newUserId]);
        if ($stmt->fetch() === false) {
            taxmate_error('not_found', 'User not found.', 404);
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO organization_members (organization_id, user_id, role) VALUES (?, ?, ?)');
            $stmt->execute([$orgId, $newUserId, $role]);
        } catch (PDOException $e) {
            taxmate_error('conflict', 'User is already a member of this organization.', 409);
        }
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'org.member_added', 'organization_member', $newUserId, null, ['role' => $role]);
        taxmate_ok(['organization_id' => $orgId, 'user_id' => $newUserId, 'role' => $role], 201);
    }

    taxmate_error('not_found', 'Route not found.', 404);
}
