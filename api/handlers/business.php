<?php
// TaxMate API — suppliers, customers, ledger totals, invoices, bank import.
// Invoice totals are computed server-side from items only. AI fields are
// stored separately and never used for a total. Voiding never edits the
// original invoice: it creates a credit-note invoice that nets it to zero.

declare(strict_types=1);

function taxmate_handle_business(PDO $pdo, string $method, array $segments, array $input): void
{
    if ($segments[0] !== 'orgs' || count($segments) < 3) {
        return;
    }
    $orgId = (int)$segments[1];
    $resource = $segments[2];
    $allowed = ['suppliers', 'customers', 'ledger-totals', 'invoices', 'bank-accounts', 'bank-transactions'];
    if (!in_array($resource, $allowed, true)) {
        return;
    }
    $user = taxmate_require_user($pdo);
    taxmate_org_exists($pdo, $orgId);
    taxmate_require_member($pdo, (int)$user['id'], $orgId);
    $writer = taxmate_writer_roles();

    if (($resource === 'suppliers' || $resource === 'customers') && $method === 'GET') {
        $table = $resource;
        $stmt = $pdo->prepare('SELECT id, name, phone FROM ' . $table . ' WHERE organization_id = ? ORDER BY id ASC');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if (($resource === 'suppliers' || $resource === 'customers') && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $name = taxmate_require_string($input, 'name', 200);
        $phone = isset($input['phone']) && is_string($input['phone']) ? trim($input['phone']) : null;
        $stmt = $pdo->prepare('INSERT INTO ' . $resource . ' (organization_id, name, phone) VALUES (?, ?, ?)');
        $stmt->execute([$orgId, $name, $phone]);
        taxmate_ok(['id' => (int)$pdo->lastInsertId(), 'name' => $name], 201);
    }

    if ($resource === 'ledger-totals' && $method === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT direction, COALESCE(SUM(total_paisa), 0) AS total FROM invoices WHERE organization_id = ? GROUP BY direction"
        );
        $stmt->execute([$orgId]);
        $totals = ['receivable_paisa' => 0, 'payable_paisa' => 0];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['direction'] === 'sale') {
                $totals['receivable_paisa'] = (int)$row['total'];
            } else {
                $totals['payable_paisa'] = (int)$row['total'];
            }
        }
        $stmt = $pdo->prepare(
            'SELECT customer_id, COALESCE(SUM(total_paisa), 0) AS total FROM invoices WHERE organization_id = ? AND direction = ? AND customer_id IS NOT NULL GROUP BY customer_id'
        );
        $stmt->execute([$orgId, 'sale']);
        $totals['per_customer'] = $stmt->fetchAll();
        taxmate_ok($totals);
    }

    if ($resource === 'invoices' && $method === 'GET' && count($segments) === 3) {
        $stmt = $pdo->prepare('SELECT id, customer_id, supplier_id, direction, invoice_type, original_invoice_id, total_paisa, ai_confidence_pct FROM invoices WHERE organization_id = ? ORDER BY id DESC LIMIT 500');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'invoices' && $method === 'GET' && count($segments) === 4) {
        $invoiceId = (int)$segments[3];
        $stmt = $pdo->prepare('SELECT id, customer_id, supplier_id, direction, invoice_type, original_invoice_id, total_paisa, ai_confidence_pct, ai_fields_json FROM invoices WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$invoiceId, $orgId]);
        $invoice = $stmt->fetch();
        if ($invoice === false) {
            taxmate_error('not_found', 'Invoice not found in this organization.', 404);
        }
        $stmt = $pdo->prepare('SELECT id, description, quantity, unit_price_paisa, line_total_paisa FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC');
        $stmt->execute([$invoiceId]);
        $invoice['items'] = $stmt->fetchAll();
        taxmate_ok($invoice);
    }
    if ($resource === 'invoices' && $method === 'POST' && count($segments) === 3) {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $direction = $input['direction'] ?? '';
        if ($direction !== 'sale' && $direction !== 'purchase') {
            taxmate_error('invalid_field', 'Direction must be sale or purchase.', 422);
        }
        $items = $input['items'] ?? null;
        if (!is_array($items) || $items === []) {
            taxmate_error('invalid_field', 'Items must be a non-empty array.', 422);
        }
        $customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : null;
        $supplierId = isset($input['supplier_id']) ? (int)$input['supplier_id'] : null;
        if ($customerId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND organization_id = ? LIMIT 1');
            $stmt->execute([$customerId, $orgId]);
            if ($stmt->fetch() === false) {
                taxmate_error('not_found', 'Customer not found in this organization.', 404);
            }
        }
        if ($supplierId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM suppliers WHERE id = ? AND organization_id = ? LIMIT 1');
            $stmt->execute([$supplierId, $orgId]);
            if ($stmt->fetch() === false) {
                taxmate_error('not_found', 'Supplier not found in this organization.', 404);
            }
        }
        $parsedItems = [];
        $total = 0;
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['description']) || !isset($item['quantity']) || !isset($item['unit_price_paisa'])) {
                taxmate_error('invalid_field', 'Each item needs description, quantity, and unit_price_paisa.', 422);
            }
            $unit = taxmate_parse_paisa($item['unit_price_paisa']);
            if ($unit === null) {
                taxmate_error('invalid_money', 'Item unit_price_paisa is not a valid money value.', 422);
            }
            $qty = (int)$item['quantity'];
            $line = $qty * $unit;
            $total += $line;
            $parsedItems[] = ['description' => trim((string)$item['description']), 'quantity' => $qty, 'unit_price_paisa' => $unit, 'line_total_paisa' => $line];
        }
        $confidence = isset($input['ai_confidence_pct']) ? (int)$input['ai_confidence_pct'] : null;
        $aiJson = isset($input['ai_fields']) && is_array($input['ai_fields']) ? json_encode($input['ai_fields']) : null;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO invoices (organization_id, customer_id, supplier_id, direction, invoice_type, total_paisa, ai_confidence_pct, ai_fields_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$orgId, $customerId, $supplierId, $direction, 'invoice', $total, $confidence, $aiJson, (int)$user['id']]);
            $invoiceId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price_paisa, line_total_paisa) VALUES (?, ?, ?, ?, ?)');
            foreach ($parsedItems as $item) {
                $stmt->execute([$invoiceId, $item['description'], $item['quantity'], $item['unit_price_paisa'], $item['line_total_paisa']]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            taxmate_error('server_error', 'Invoice creation failed.', 500);
        }
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'invoice.created', 'invoice', $invoiceId);
        taxmate_ok(['id' => $invoiceId, 'total_paisa' => $total], 201);
    }
    if ($resource === 'invoices' && count($segments) === 5 && $segments[4] === 'void' && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $invoiceId = (int)$segments[3];
        $stmt = $pdo->prepare('SELECT id, customer_id, supplier_id, direction, total_paisa FROM invoices WHERE id = ? AND organization_id = ? AND invoice_type = ? LIMIT 1');
        $stmt->execute([$invoiceId, $orgId, 'invoice']);
        $original = $stmt->fetch();
        if ($original === false) {
            taxmate_error('not_found', 'Invoice not found, or it is already a credit note.', 404);
        }
        $stmt = $pdo->prepare('SELECT description, quantity, unit_price_paisa, line_total_paisa FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC');
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO invoices (organization_id, customer_id, supplier_id, direction, invoice_type, original_invoice_id, total_paisa, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$orgId, $original['customer_id'], $original['supplier_id'], $original['direction'], 'credit_note', $invoiceId, -((int)$original['total_paisa']), (int)$user['id']]);
            $creditId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price_paisa, line_total_paisa) VALUES (?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $stmt->execute([$creditId, $item['description'], (int)$item['quantity'], -((int)$item['unit_price_paisa']), -((int)$item['line_total_paisa'])]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            taxmate_error('server_error', 'Credit note creation failed.', 500);
        }
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'invoice.voided_via_credit_note', 'invoice', $creditId, ['original_id' => $invoiceId]);
        taxmate_ok(['id' => $creditId, 'original_invoice_id' => $invoiceId, 'total_paisa' => -((int)$original['total_paisa'])], 201);
    }

    if ($resource === 'bank-accounts' && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT id, bank_name, account_label, account_number_masked FROM bank_accounts WHERE organization_id = ? ORDER BY id ASC');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'bank-accounts' && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $bank = taxmate_require_string($input, 'bank_name', 200);
        $label = taxmate_require_string($input, 'account_label', 200);
        $masked = isset($input['account_number']) && is_string($input['account_number']) ? taxmate_mask_identifier(trim($input['account_number'])) : null;
        $stmt = $pdo->prepare('INSERT INTO bank_accounts (organization_id, bank_name, account_label, account_number_masked) VALUES (?, ?, ?, ?)');
        $stmt->execute([$orgId, $bank, $label, $masked]);
        taxmate_ok(['id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($resource === 'bank-transactions' && $method === 'GET' && count($segments) === 3) {
        $stmt = $pdo->prepare('SELECT id, bank_account_id, occurred_at, description, amount_paisa, status, matched_transaction_id, import_batch FROM bank_transactions WHERE organization_id = ? ORDER BY occurred_at DESC, id DESC LIMIT 500');
        $stmt->execute([$orgId]);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'bank-transactions' && $method === 'POST' && count($segments) === 3) {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $rows = $input['rows'] ?? null;
        if (!is_array($rows) || $rows === []) {
            taxmate_error('invalid_field', 'Rows must be a non-empty array.', 422);
        }
        $batch = isset($input['import_batch']) && is_string($input['import_batch']) ? trim($input['import_batch']) : null;
        $inserted = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!is_array($row) || !isset($row['bank_account_id']) || !isset($row['occurred_at']) || !isset($row['description']) || !isset($row['amount_paisa'])) {
                    throw new Exception('Invalid bank row.');
                }
                $accountId = (int)$row['bank_account_id'];
                $stmt = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = ? AND organization_id = ? LIMIT 1');
                $stmt->execute([$accountId, $orgId]);
                if ($stmt->fetch() === false) {
                    throw new Exception('Bank account not found in this organization.');
                }
                if (!taxmate_is_valid_date((string)$row['occurred_at'])) {
                    throw new Exception('Invalid occurred_at date.');
                }
                $amount = taxmate_parse_paisa($row['amount_paisa']);
                if ($amount === null) {
                    throw new Exception('Invalid amount_paisa.');
                }
                $stmt = $pdo->prepare('INSERT INTO bank_transactions (organization_id, bank_account_id, occurred_at, description, amount_paisa, import_batch) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$orgId, $accountId, (string)$row['occurred_at'], trim((string)$row['description']), $amount, $batch]);
                $inserted++;
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            taxmate_error('invalid_field', 'Bank import failed: ' . $e->getMessage(), 422);
        }
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'bank.imported', 'bank_transaction', null, null, ['rows' => $inserted, 'batch' => $batch]);
        taxmate_ok(['inserted' => $inserted], 201);
    }
    if ($resource === 'bank-transactions' && count($segments) === 5 && $segments[4] === 'propose-matches' && $method === 'GET') {
        $bankId = (int)$segments[3];
        $stmt = $pdo->prepare('SELECT id, amount_paisa FROM bank_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$bankId, $orgId]);
        $bank = $stmt->fetch();
        if ($bank === false) {
            taxmate_error('not_found', 'Bank transaction not found in this organization.', 404);
        }
        // Proposing changes nothing. Candidates are exact-amount matches only.
        $stmt = $pdo->prepare('SELECT id, category_id, type, amount_paisa, occurred_at, note, status FROM transactions WHERE organization_id = ? AND (amount_paisa = ? OR amount_paisa = ?) ORDER BY occurred_at DESC LIMIT 20');
        $stmt->execute([$orgId, (int)$bank['amount_paisa'], -((int)$bank['amount_paisa'])]);
        taxmate_ok($stmt->fetchAll());
    }
    if ($resource === 'bank-transactions' && count($segments) === 5 && $segments[4] === 'apply-match' && $method === 'POST') {
        taxmate_require_member($pdo, (int)$user['id'], $orgId, $writer);
        $bankId = (int)$segments[3];
        $transactionId = taxmate_require_int($input, 'transaction_id');
        $stmt = $pdo->prepare('SELECT id FROM bank_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$bankId, $orgId]);
        if ($stmt->fetch() === false) {
            taxmate_error('not_found', 'Bank transaction not found in this organization.', 404);
        }
        $stmt = $pdo->prepare('SELECT id FROM transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$transactionId, $orgId]);
        if ($stmt->fetch() === false) {
            taxmate_error('not_found', 'Transaction not found in this organization.', 404);
        }
        // apply-match is the human review step. It marks the IMPORT row as
        // matched; it never edits the ledger transaction itself.
        $stmt = $pdo->prepare("UPDATE bank_transactions SET status = 'matched', matched_transaction_id = ? WHERE id = ?");
        $stmt->execute([$transactionId, $bankId]);
        taxmate_audit($pdo, $orgId, (int)$user['id'], 'bank.matched', 'bank_transaction', $bankId, null, ['transaction_id' => $transactionId]);
        taxmate_ok(['bank_transaction_id' => $bankId, 'transaction_id' => $transactionId, 'status' => 'matched']);
    }

    taxmate_error('not_found', 'Route not found.', 404);
}
