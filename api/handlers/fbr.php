<?php
// TaxMate API — tax years, tax rules (unverified until Gate 0), FBR returns,
// wealth reconciliation, accountant review, lock, assisted acknowledgement.
// Locked returns are immutable: every modifying route refuses a locked return.
// There is intentionally NO unlock route; the router answers unlock attempts
// with a forbidden error. No IRIS login, submission, or credential handling.

declare(strict_types=1);

function taxmate_fbr_return(PDO $pdo, int $orgId, int $returnId): array
{
    $stmt = $pdo->prepare('SELECT id, organization_id, tax_year_id, taxpayer_type, status, opening_net_paisa, closing_net_paisa, reviewed_by, reviewed_decision, reviewed_at, locked_snapshot_json, snapshot_hash, locked_at, created_by FROM fbr_returns WHERE id = ? AND organization_id = ? LIMIT 1');
    $stmt->execute([$returnId, $orgId]);
    $row = $stmt->fetch();
    if ($row === false) {
        taxmate_error('not_found', 'FBR return not found in this organization.', 404);
    }
    return $row;
}

function taxmate_fbr_reconciliation(PDO $pdo, array $ret): array
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paisa), 0) AS total FROM fbr_return_lines WHERE fbr_return_id = ? AND line_kind = 'income'");
    $stmt->execute([(int)$ret['id']]);
    $income = (int)$stmt->fetch()['total'];
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_paisa), 0) AS total FROM wealth_expenses WHERE organization_id = ? AND tax_year_id = ?');
    $stmt->execute([(int)$ret['organization_id'], (int)$ret['tax_year_id']]);
    $expenses = (int)$stmt->fetch()['total'];
    $expected = (int)$ret['opening_net_paisa'] + $income - $expenses;
    $difference = (int)$ret['closing_net_paisa'] - $expected;
    return [
        'opening_net_paisa' => (int)$ret['opening_net_paisa'],
        'income_paisa' => $income,
        'expenses_paisa' => $expenses,
        'expected_closing_paisa' => $expected,
        'closing_net_paisa' => (int)$ret['closing_net_paisa'],
        'difference_paisa' => $difference,
        'balances' => $difference === 0,
    ];
}

function taxmate_handle_fbr(PDO $pdo, string $method, array $segments, array $input): void
{
    if ($segments[0] === 'tax-years' && $method === 'GET') {
        taxmate_require_user($pdo);
        $stmt = $pdo->query('SELECT id, label, start_date, end_date, finance_act, is_current FROM tax_years ORDER BY start_date ASC');
        taxmate_ok($stmt->fetchAll());
    }
    if ($segments[0] === 'tax-rules' && $method === 'GET') {
        taxmate_require_user($pdo);
        $stmt = $pdo->query('SELECT id, tax_year_id, taxpayer_type, from_paisa, to_paisa, rate_bps, base_tax_paisa, surcharge_rule, finance_act_source, is_verified FROM tax_rules ORDER BY tax_year_id ASC, from_paisa ASC');
        $rows = $stmt->fetchAll();
        taxmate_ok(['rules' => $rows, 'verified' => false, 'note' => 'No verified rates are loaded until Gate 0 gazette verification. See docs/BLOCKED.md.']);
    }
    if ($segments[0] === 'tax-rules' && $method === 'POST' && count($segments) === 2 && $segments[1] === 'calculate') {
        taxmate_require_user($pdo);
        $taxYearId = taxmate_require_int($input, 'tax_year_id');
        $type = $input['taxpayer_type'] ?? '';
        if (!in_array($type, ['salaried', 'business', 'aop', 'company'], true)) {
            taxmate_error('invalid_field', 'taxpayer_type is not valid.', 422);
        }
        $taxable = taxmate_require_paisa($input, 'taxable_paisa');
        try {
            $tax = taxmate_calculate_tax($pdo, $taxYearId, $type, $taxable);
        } catch (TaxRatesNotVerified $e) {
            taxmate_error('rates_not_verified', 'rates not verified (Gate 0 pending)', 422);
        }
        taxmate_ok(['tax_payable_paisa' => $tax]);
    }

    if ($segments[0] !== 'orgs' || count($segments) < 3 || $segments[2] !== 'fbr-returns') {
        return;
    }
    $orgId = (int)$segments[1];
    $user = taxmate_require_user($pdo);
    taxmate_org_exists($pdo, $orgId);
    taxmate_require_member($pdo, (int)$user['id'], $orgId);
    $writer = taxmate_writer_roles();

    if (count($segments) === 3 && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, tax_year_id, taxpayer_type, status, opening_net_paisa, closing_net_paisa, reviewed_decision, locked_at FROM fbr_returns WHERE organization_id = ? ORDER BY id DESC LIMIT 200');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if (count($segments) === 3 && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $taxYearId = taxmate_require_int($input, 'tax_year_id');
        $type = $input['taxpayer_type'] ?? '';
        if (!in_array($type, ['salaried', 'business', 'aop', 'company'], true)) {
            taxmate_error('invalid_field', 'taxpayer_type is not valid.', 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM tax_years WHERE id = ? LIMIT 1');
        $stmt->execute([$taxYearId]);
        if ($stmt->fetch() === false) {
            taxmate_error('not_found', 'Tax year not found.', 404);
        }
        $opening = taxmate_require_paisa($input, 'opening_net_paisa');
        $closing = taxmate_require_paisa($input, 'closing_net_paisa');
        $stmt = $pdo->prepare("INSERT INTO fbr_returns (organization_id, tax_year_id, taxpayer_type, status, opening_net_paisa, closing_net_paisa, created_by) VALUES (?, ?, ?, 'draft', ?, ?, ?)");
        $stmt->execute([$orgId, $taxYearId, $type, $opening, $closing, (int)$user['id']]);
        $id = (int)$pdo->lastInsertId();
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.created', 'fbr_return', $id);
        taxmate_ok(['id' => $id, 'status' => 'draft'], 201);
    }

    $returnId = (int)($segments[3] ?? 0);
    if ($returnId <= 0) {
        taxmate_error('not_found', 'Route not found.', 404);
    }
    $ret = taxmate_fbr_return($pdo, $orgId, $returnId);
    $locked = $ret['status'] === 'locked' || $ret['status'] === 'submitted' || $ret['status'] === 'accepted';
    $sub = $segments[4] ?? '';

    if ($sub === '' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, line_kind, iris_field_code, amount_paisa, source_ref FROM fbr_return_lines WHERE fbr_return_id = ? ORDER BY id ASC');
        $stmt->execute([$returnId]);
        $ret['lines'] = $stmt->fetchAll();
        $ret['reconciliation'] = taxmate_fbr_reconciliation($pdo, $ret);
        taxmate_ok($ret);
    }
    if ($sub === 'unlock') {
        taxmate_error('forbidden', 'Unlocking a locked FBR return is forbidden. A locked return is immutable.', 403);
    }
    if ($sub === 'lines' && $method === 'POST') {
        if ($locked) {
            taxmate_error('locked', 'This return is locked and immutable.', 409);
        }
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $kind = $input['line_kind'] ?? '';
        if (!in_array($kind, ['income', 'expense', 'other'], true)) {
            taxmate_error('invalid_field', 'line_kind must be income, expense, or other.', 422);
        }
        $code = taxmate_require_string($input, 'iris_field_code', 100);
        $amount = taxmate_require_paisa($input, 'amount_paisa');
        $source = taxmate_require_string($input, 'source_ref', 300);
        $stmt = $pdo->prepare('INSERT INTO fbr_return_lines (fbr_return_id, line_kind, iris_field_code, amount_paisa, source_ref) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$returnId, $kind, $code, $amount, $source]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.line_added', 'fbr_return', $returnId);
        taxmate_ok(['id' => (int)$pdo->lastInsertId()], 201);
    }
    if ($sub === 'wealth' && $method === 'POST') {
        if ($locked) {
            taxmate_error('locked', 'This return is locked and immutable.', 409);
        }
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $kind = $input['kind'] ?? '';
        if (!in_array($kind, ['asset', 'liability', 'expense'], true)) {
            taxmate_error('invalid_field', 'Kind must be asset, liability, or expense.', 422);
        }
        $name = taxmate_require_string($input, 'name', 150);
        $amount = taxmate_require_paisa($input, 'amount_paisa');
        $table = $kind === 'asset' ? 'wealth_assets' : ($kind === 'liability' ? 'wealth_liabilities' : 'wealth_expenses');
        $col = $kind === 'expense' ? 'category' : ($kind === 'asset' ? 'asset_type' : 'liability_type');
        $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (organization_id, tax_year_id, ' . $col . ', description, amount_paisa) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$orgId, (int)$ret['tax_year_id'], $name, $input['description'] ?? null, $amount]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.wealth_added', 'fbr_return', $returnId);
        taxmate_ok(['id' => (int)$pdo->lastInsertId(), 'kind' => $kind], 201);
    }
    if ($sub === 'withholding' && $method === 'POST') {
        if ($locked) {
            taxmate_error('locked', 'This return is locked and immutable.', 409);
        }
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $source = taxmate_require_string($input, 'source', 200);
        $amount = taxmate_require_paisa($input, 'amount_paisa');
        $stmt = $pdo->prepare('INSERT INTO withholding_credits (organization_id, tax_year_id, source, psid, cpr_no, amount_paisa) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$orgId, (int)$ret['tax_year_id'], $source, $input['psid'] ?? null, $input['cpr_no'] ?? null, $amount]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.withholding_added', 'fbr_return', $returnId);
        taxmate_ok(['id' => (int)$pdo->lastInsertId()], 201);
    }
    if ($sub === 'reconciliation' && $method === 'GET') {
        taxmate_ok(taxmate_fbr_reconciliation($pdo, $ret));
    }
    if ($sub === 'review' && $method === 'POST') {
        if ($locked) {
            taxmate_error('locked', 'This return is locked and immutable.', 409);
        }
        taxmate_require_member($pdo, (int)$user['id'], $orgId, ['owner', 'admin', 'accountant']);
        $decision = $input['decision'] ?? '';
        if ($decision !== 'approve' && $decision !== 'reject') {
            taxmate_error('invalid_field', 'Decision must be approve or reject.', 422);
        }
        $status = $decision === 'approve' ? 'ready' : 'in_review';
        $stmt = $pdo->prepare('UPDATE fbr_returns SET status = ?, reviewed_by = ?, reviewed_decision = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute([$status, (int)$user['id'], $decision, $returnId]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.reviewed', 'fbr_return', $returnId, null, ['decision' => $decision]);
        taxmate_ok(['id' => $returnId, 'status' => $status]);
    }
    if ($sub === 'lock' && $method === 'POST') {
        if ($locked) {
            taxmate_error('locked', 'This return is already locked.', 409);
        }
        taxmate_require_member($pdo, (int)$user['id'], $orgId, ['owner', 'admin', 'accountant']);
        if ($ret['status'] !== 'ready' || $ret['reviewed_decision'] !== 'approve') {
            taxmate_error('not_ready', 'A return locks only after accountant review approval (status ready).', 409);
        }
        $recon = taxmate_fbr_reconciliation($pdo, $ret);
        if (!$recon['balances']) {
            taxmate_error('reconciliation_failed', 'Wealth reconciliation does not balance. Difference must be exactly zero.', 409);
        }
        $stmt = $pdo->prepare('SELECT id, line_kind, iris_field_code, amount_paisa, source_ref FROM fbr_return_lines WHERE fbr_return_id = ? ORDER BY id ASC');
        $stmt->execute([$returnId]);
        $lines = $stmt->fetchAll();
        if ($lines === []) {
            taxmate_error('not_ready', 'A return needs at least one line before it can lock.', 409);
        }
        $snapshot = [
            'return_id' => $returnId,
            'organization_id' => $orgId,
            'tax_year_id' => (int)$ret['tax_year_id'],
            'taxpayer_type' => $ret['taxpayer_type'],
            'lines' => $lines,
            'reconciliation' => $recon,
            'tax_payable_paisa' => null,
            'tax_note' => 'rates not verified (Gate 0 pending)',
        ];
        $snapshotJson = json_encode($snapshot);
        $hash = hash('sha256', $snapshotJson);
        $stmt = $pdo->prepare("UPDATE fbr_returns SET status = 'locked', locked_snapshot_json = ?, snapshot_hash = ?, locked_at = NOW() WHERE id = ?");
        $stmt->execute([$snapshotJson, $hash, $returnId]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.locked', 'fbr_return', $returnId, null, ['snapshot_hash' => $hash]);
        taxmate_ok(['id' => $returnId, 'status' => 'locked', 'snapshot_hash' => $hash]);
    }
    if ($sub === 'assisted-acknowledgement' && $method === 'POST') {
        if (!$locked) {
            taxmate_error('not_locked', 'Assisted acknowledgement requires a locked return.', 409);
        }
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $ack = taxmate_require_string($input, 'acknowledgement_number', 200);
        $filed = taxmate_require_string($input, 'filed_date', 10);
        if (!taxmate_is_valid_date($filed)) {
            taxmate_error('invalid_field', 'filed_date must be a YYYY-MM-DD date.', 422);
        }
        if ($filed > date('Y-m-d')) {
            taxmate_error('invalid_field', 'filed_date must not be in the future.', 422);
        }
        $hash = hash('sha256', (string)$ret['snapshot_hash'] . $ack . $filed);
        $stmt = $pdo->prepare("INSERT INTO fbr_submission_attempts (fbr_return_id, channel, acknowledgement_number, filed_date, payload_hash, result, created_by) VALUES (?, 'assisted', ?, ?, ?, 'filed_assisted', ?)");
        $stmt->execute([$returnId, $ack, $filed, $hash, (int)$user['id']]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'fbr_return.assisted_acknowledged', 'fbr_return', $returnId);
        taxmate_ok(['id' => (int)$pdo->lastInsertId(), 'acknowledgement_number' => $ack], 201);
    }

    taxmate_error('not_found', 'Route not found.', 404);
}
