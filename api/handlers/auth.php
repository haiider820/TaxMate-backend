<?php
// TaxMate API — auth handlers. Passwords hashed, tokens hashed, plain token
// returned exactly once at login. No IRIS credential handling exists here.

declare(strict_types=1);

function taxmate_handle_auth(PDO $pdo, string $method, array $segments, array $input): void
{
    if ($segments[0] !== 'auth' && $segments[0] !== 'me') {
        return;
    }

    if ($segments[0] === 'me' && $method === 'GET') {
        $user = taxmate_require_user($pdo);
        $stmt = $pdo->prepare(
            'SELECT o.id, o.name, o.type, m.role FROM organizations o ' .
            'JOIN organization_members m ON m.organization_id = o.id ' .
            'WHERE m.user_id = ? ORDER BY o.id ASC'
        );
        $stmt->execute([(int)$user['id']]);
        taxmate_ok(['user' => taxmate_public_user($user), 'organizations' => $stmt->fetchAll()]);
    }

    if ($segments[0] !== 'auth') {
        taxmate_error('not_found', 'Route not found.', 404);
    }

    $action = $segments[1] ?? '';

    if ($action === 'register' && $method === 'POST') {
        taxmate_require_fields($input, ['name', 'email', 'password']);
        $name = taxmate_require_string($input, 'name', 200);
        $email = taxmate_require_string($input, 'email', 255);
        $password = $input['password'];
        if (!is_string($password) || strlen($password) < 8) {
            taxmate_error('invalid_field', 'Password must be at least 8 characters.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            taxmate_error('invalid_field', 'Email is not valid.', 422);
        }
        $cnic = isset($input['cnic']) && is_string($input['cnic']) ? trim($input['cnic']) : null;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, cnic) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $cnic]);
            $userId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO organizations (name, type, created_by) VALUES (?, 'individual', ?)");
            $stmt->execute([$name . ' Personal', $userId]);
            $orgId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO organization_members (organization_id, user_id, role) VALUES (?, ?, 'owner')");
            $stmt->execute([$orgId, $userId]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (strpos($e->getMessage(), 'uq_users_email') !== false || $e->getCode() === '23000') {
                taxmate_error('email_taken', 'This email is already registered.', 409);
            }
            taxmate_error('server_error', 'Registration failed.', 500);
        }
        taxmate_audit($pdo, $orgId, $userId, 'user.registered', 'user', $userId);
        taxmate_ok(['user_id' => $userId, 'organization_id' => $orgId], 201);
    }

    if ($action === 'login' && $method === 'POST') {
        taxmate_require_fields($input, ['email', 'password']);
        $email = taxmate_require_string($input, 'email', 255);
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, cnic FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user === false || !password_verify((string)$input['password'], (string)$user['password_hash'])) {
            taxmate_error('unauthorized', 'Email or password is incorrect.', 401);
        }
        $plain = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))');
        $stmt->execute([(int)$user['id'], hash('sha256', $plain)]);
        taxmate_ok(['token' => $plain, 'user' => taxmate_public_user($user)]);
    }

    if ($action === 'logout' && $method === 'POST') {
        $token = taxmate_bearer_token();
        if ($token !== null) {
            $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
            $stmt->execute([hash('sha256', $token)]);
        }
        taxmate_ok(['logged_out' => true]);
    }

    taxmate_error('not_found', 'Route not found.', 404);
}
