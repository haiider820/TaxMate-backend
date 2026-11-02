<?php
// TaxMate API — authentication and org/role authorization.
// - Passwords: password_hash / password_verify only, never plain text.
// - API tokens: 32 random bytes, stored as SHA-256 hash only, with expiry.
//   The plain token is returned exactly once, at login. It is never logged.
// - Every org-scoped endpoint must call taxmate_require_member().
// Roles: owner, admin, accountant, member, viewer.
// Only owner/admin/accountant may approve a return (checked in fbr handler).

declare(strict_types=1);

function taxmate_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $header = $value;
                break;
            }
        }
    }
    if (!str_starts_with($header, 'Bearer ')) {
        return null;
    }
    $token = trim(substr($header, 7));
    return $token === '' ? null : $token;
}

// Returns the authenticated user row, or sends 401.
function taxmate_require_user(PDO $pdo): array
{
    $token = taxmate_bearer_token();
    if ($token === null) {
        taxmate_error('unauthorized', 'A valid Bearer token is required.', 401);
    }
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.cnic FROM users u ' .
        'JOIN api_tokens t ON t.user_id = u.id ' .
        'WHERE t.token_hash = ? AND t.expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if ($user === false) {
        taxmate_error('unauthorized', 'Token is invalid or expired.', 401);
    }
    $user['cnic_masked'] = taxmate_mask_identifier($user['cnic'] ?? null);
    unset($user['cnic']);
    return $user;
}

// Returns the membership row for this user+org, or sends 403.
// $roles: null = any membership is enough; otherwise one of these roles.
function taxmate_require_member(PDO $pdo, int $userId, int $orgId, ?array $roles = null): array
{
    $stmt = $pdo->prepare(
        'SELECT id, organization_id, user_id, role FROM organization_members ' .
        'WHERE organization_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$orgId, $userId]);
    $member = $stmt->fetch();
    if ($member === false) {
        taxmate_error('forbidden', 'You are not a member of this organization.', 403);
    }
    if ($roles !== null && !in_array($member['role'], $roles, true)) {
        taxmate_error('forbidden', 'Your role does not allow this action.', 403);
    }
    return $member;
}

function taxmate_org_exists(PDO $pdo, int $orgId): void
{
    $stmt = $pdo->prepare('SELECT id FROM organizations WHERE id = ? LIMIT 1');
    $stmt->execute([$orgId]);
    if ($stmt->fetch() === false) {
        taxmate_error('not_found', 'Organization not found.', 404);
    }
}

// Public user shape: never includes password hash or full CNIC/NTN.
function taxmate_public_user(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'cnic_masked' => taxmate_mask_identifier($user['cnic'] ?? null),
    ];
}
