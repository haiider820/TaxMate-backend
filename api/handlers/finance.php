<?php
// TaxMate API — categories, transactions (with reversing corrections), budgets.
// No DELETE and no UPDATE routes exist for transactions in v1: a wrong entry
// is corrected by a new reversing entry, the original is never edited.

declare(strict_types=1);

function taxmate_handle_finance(PDO $pdo, string $method, array $segments, array $input): void
{
    if ($segments[0] !== 'orgs' || count($segments) < 3) {
        return;
    }
    $orgId = (int)$segments[1];
    $resource = $segments[2];
    if ($resource !== 'categories' && $resource !== 'transactions' && $resource !== 'budgets') {
        return;
    }
    $user = taxmate_require_user($pdo);
    taxmate_org_exists($pdo, $orgId);
    taxmate_require_member($pdo, (int)$user['id'], $orgId);
    $writer = taxmate_writer_roles();

    if ($resource === 'categories' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, name, kind, fbr_income_head FROM categories WHERE organization_id = ? ORDER BY id ASC');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'categories' && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $name = taxmate_require_string($input, 'name', 150);
        $kind = $input['kind'] ?? '';
        if ($kind !== 'income' && $kind !== 'expense') {
            taxmate_error('invalid_field', 'Kind must be income or expense.', 422);
        }
        $head = isset($input['fbr_income_head']) && is_string($input['fbr_income_head']) ? trim($input['fbr_income_head']) : null;
        $stmt = $pdo->prepare('INSERT INTO categories (organization_id, name, kind, fbr_income_head) VALUES (?, ?, ?, ?)');
        $stmt->execute([$orgId, $name, $kind, $head]);
        $id = (int)$pdo->lastInsertId();
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'category.created', 'category', $id);
        taxmate_ok(['id' => $id, 'name' => $name, 'kind' => $kind], 201);
    }

    if ($resource === 'transactions' && $method === 'GET' && count($segments) === 3) {
        $sql = 'SELECT id, user_id, category_id, type, amount_paisa, currency, note, occurred_at, source, status, correction_of_id FROM transactions WHERE organization_id = ?';
        $params = [$orgId];
        if (!empty($_GET['type'])) {
            $sql .= ' AND type = ?';
            $params[] = $_GET['type'];
        }
        if (!empty($_GET['category_id'])) {
            $sql .= ' AND category_id = ?';
            $params[] = (int)$_GET['category_id'];
        }
        if (!empty($_GET['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['from'])) {
            $sql .= ' AND occurred_at >= ?';
            $params[] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $sql .= ' AND occurred_at <= ?';
            $params[] = $_GET['to'];
        }
        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT 500';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'transactions' && $method === 'POST' && count($segments) === 3) {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $type = $input['type'] ?? '';
        if ($type !== 'income' && $type !== 'expense') {
            taxmate_error('invalid_field', 'Type must be income or expense.', 422);
        }
        $amount = taxmate_require_paisa($input, 'amount_paisa');
        if ($amount < 0) {
            taxmate_error('invalid_field', 'Transaction amount must not be negative; use a correction entry to reverse one.', 422);
        }
        $occurred = taxmate_require_string($input, 'occurred_at', 10);
        if (!taxmate_is_valid_date($occurred)) {
            taxmate_error('invalid_field', 'occurred_at must be a YYYY-MM-DD date.', 422);
        }
        $source = $input['source'] ?? 'manual';
        if (!in_array($source, ['manual', 'voice', 'scan'], true)) {
            taxmate_error('invalid_field', 'Source must be manual, voice, or scan.', 422);
        }
        $categoryId = isset($input['category_id']) ? (int)$input['category_id'] : null;
        if ($categoryId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND organization_id = ? LIMIT 1');
            $stmt->execute([$categoryId, $orgId]);
            if ($stmt->fetch() === false) {
                taxmate_error('not_found', 'Category not found in this organization.', 404);
            }
        }
        // Voice/scan entries start pending_review and are excluded from
        // totals until a human reviews them (see docs/API.md).
        $status = ($source === 'manual') ? 'verified' : 'pending_review';
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (organization_id, user_id, category_id, type, amount_paisa, currency, note, occurred_at, source, status) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orgId, (int)$user['id'], $categoryId, $type, $amount, 'PKR', $input['note'] ?? null, $occurred, $source, $status]);
        $id = (int)$pdo->lastInsertId();
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'transaction.created', 'transaction', $id);
        taxmate_ok(['id' => $id, 'status' => $status], 201);
    }
    if ($resource === 'transactions' && count($segments) === 5 && $segments[3] !== '' && $segments[4] === 'correct' && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $originalId = (int)$segments[3];
        $reason = taxmate_require_string($input, 'reason', 500);
        $stmt = $pdo->prepare('SELECT id, category_id, type, amount_paisa, occurred_at FROM transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$originalId, $orgId]);
        $original = $stmt->fetch();
        if ($original === false) {
            taxmate_error('not_found', 'Transaction not found in this organization.', 404);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (organization_id, user_id, category_id, type, amount_paisa, currency, note, occurred_at, source, status, correction_of_id, correction_reason) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orgId, (int)$user['id'], $original['category_id'], $original['type'], -((int)$original['amount_paisa']), 'PKR', 'Correction of transaction ' . $originalId, $original['occurred_at'], 'manual', 'verified', $originalId, $reason]);
        $id = (int)$pdo->lastInsertId();
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'transaction.corrected', 'transaction', $id, ['original_id' => $originalId], ['reason' => $reason]);
        taxmate_ok(['id' => $id, 'correction_of_id' => $originalId], 201);
    }

    if ($resource === 'budgets' && $method === 'GET' && count($segments) === 3) {
        $stmt = $pdo->prepare('SELECT id, category_id, period_type, amount_limit_paisa, alert_threshold_pct, start_date FROM budgets WHERE organization_id = ? ORDER BY id ASC');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'budgets' && $method === 'POST' && count($segments) === 3) {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $limit = taxmate_require_paisa($input, 'amount_limit_paisa');
        $period = $input['period_type'] ?? 'monthly';
        if ($period !== 'weekly' && $period !== 'monthly') {
            taxmate_error('invalid_field', 'period_type must be weekly or monthly.', 422);
        }
        $start = taxmate_require_string($input, 'start_date', 10);
        if (!taxmate_is_valid_date($start)) {
            taxmate_error('invalid_field', 'start_date must be a YYYY-MM-DD date.', 422);
        }
        $categoryId = isset($input['category_id']) ? (int)$input['category_id'] : null;
        $threshold = isset($input['alert_threshold_pct']) ? (int)$input['alert_threshold_pct'] : 80;
        $stmt = $pdo->prepare('INSERT INTO budgets (organization_id, category_id, period_type, amount_limit_paisa, alert_threshold_pct, start_date) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$orgId, $categoryId, $period, $limit, $threshold, $start]);
        $id = (int)$pdo->lastInsertId();
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'budget.created', 'budget', $id);
        taxmate_ok(['id' => $id], 201);
    }
    if ($resource === 'budgets' && count($segments) === 5 && $segments[4] === 'alert' && $method === 'GET') {
        $budgetId = (int)$segments[3];
        $stmt = $pdo->prepare('SELECT id, category_id, period_type, amount_limit_paisa, alert_threshold_pct, start_date FROM budgets WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$budgetId, $orgId]);
        $budget = $stmt->fetch();
        if ($budget === false) {
            taxmate_error('not_found', 'Budget not found in this organization.', 404);
        }
        $start = (string)$budget['start_date'];
        $end = $budget['period_type'] === 'weekly' ? date('Y-m-d', strtotime($start . ' +7 days')) : date('Y-m-d', strtotime($start . ' +1 month'));
        $sql = "SELECT COALESCE(SUM(amount_paisa), 0) AS spent FROM transactions WHERE organization_id = ? AND type = 'expense' AND status = 'verified' AND occurred_at >= ? AND occurred_at < ?";
        $params = [$orgId, $start, $end];
        if ($budget['category_id'] !== null) {
            $sql .= ' AND category_id = ?';
            $params[] = (int)$budget['category_id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $spent = (int)$stmt->fetch()['spent'];
        $limit = (int)$budget['amount_limit_paisa'];
        $pct = $limit > 0 ? (int)(($spent * 100) / $limit) : 0;
        $state = 'ok';
        if ($spent >= $limit) {
            $state = 'over';
        } elseif ($pct >= (int)$budget['alert_threshold_pct']) {
            $state = 'alert';
        }
        taxmate_ok(['budget_id' => $budgetId, 'spent_paisa' => $spent, 'limit_paisa' => $limit, 'percent' => $pct, 'state' => $state]);
    }

    taxmate_error('not_found', 'Route not found.', 404);
}
