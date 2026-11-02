# TaxMate API — endpoint reference (v1)

Base: `/api/v1` (served by `api/index.php`). All responses are JSON:
success `{ok: true, data: ...}`, error `{ok: false, error: {code, message}}`.
Errors never contain tokens, passwords, SQL, or stack traces.

## Auth

- `POST /api/v1/auth/register` — body: name, email, password (min 8), cnic (optional).
  Creates the user plus an auto-created individual organization where the
  user is owner. Returns user_id + organization_id.
- `POST /api/v1/auth/login` — body: email, password. Returns a Bearer token
  (plain, exactly once) + public user. Tokens are stored hashed, expire in
  30 days.
- `POST /api/v1/auth/logout` — Bearer token. Invalidates that token.
- `GET /api/v1/me` — Bearer token. User + their organizations.

All other endpoints need `Authorization: Bearer <token>`.

## Organizations and roles

- `GET /api/v1/orgs` — my organizations. `POST /api/v1/orgs` — name, type
  (individual|business); creator becomes owner.
- `GET /api/v1/orgs/{id}` — any member. `GET /api/v1/orgs/{id}/members` —
  any member. `POST /api/v1/orgs/{id}/members` — owner/admin only; body:
  user_id, role (owner|admin|accountant|member|viewer).

Role rule everywhere: viewer is read-only. Writes need
owner/admin/accountant/member. Approving an FBR return needs
owner/admin/accountant only; viewer/member get 403. Every query filters by
organization_id, and membership is checked before any row is returned.

## Money — integer paisa only

Every amount in requests and responses is integer paisa (1 rupee = 100
paisa), stored as MySQL BIGINT. A decimal string such as "150.50" is
accepted and converted by string maths to 15050; floats are never used.
Totals are always computed server-side from line items, never from
client-supplied totals and never from AI fields.

## Categories, transactions, budgets (org-scoped)

- `GET/POST /api/v1/orgs/{id}/categories` — name, kind (income|expense),
  fbr_income_head (optional).
- `GET /api/v1/orgs/{id}/transactions` — filters: type, category_id,
  status, from, to. Voice/scan entries are created as pending_review and
  are excluded from budget totals until reviewed; there is no review
  endpoint in v1 that flips status in place — a reviewed entry is kept as
  a record and totals use verified rows only (see SETUP follow-ups).
- `POST /api/v1/orgs/{id}/transactions` — type, amount_paisa, occurred_at
  (YYYY-MM-DD), category_id (optional), note, source (manual|voice|scan).
- `POST /api/v1/orgs/{id}/transactions/{tid}/correct` — body: reason.
  Creates a reversing entry (negative amount, same type, correction_of_id
  set). The original row is never edited or deleted.
- `GET/POST /api/v1/orgs/{id}/budgets` — category_id (null = overall),
  period_type (weekly|monthly), amount_limit_paisa, alert_threshold_pct,
  start_date. `GET /api/v1/orgs/{id}/budgets/{bid}/alert` — spent_paisa,
  limit_paisa, percent, state (ok|alert|over), from verified expense rows.

There are intentionally NO update/delete routes for transactions in v1.

## Business books

- `GET/POST /api/v1/orgs/{id}/suppliers`, `GET/POST .../customers`.
- `GET /api/v1/orgs/{id}/ledger-totals` — receivable_paisa (sale invoices
  minus sale credit notes) and payable_paisa (purchase side), plus
  per-customer sale totals.
- `GET/POST /api/v1/orgs/{id}/invoices`, `GET .../invoices/{iid}` —
  direction (sale|purchase), items[] (description, quantity,
  unit_price_paisa). Total = sum of quantity x unit_price, server-side.
  ai_confidence_pct and ai_fields are stored separately and never used
  for the total.
- `POST /api/v1/orgs/{id}/invoices/{iid}/void` — creates a credit-note
  invoice with negated items and total. The original invoice is untouched.
- `GET/POST /api/v1/orgs/{id}/bank-accounts` — account numbers are stored
  masked (last 4 only), never in full.
- `GET/POST /api/v1/orgs/{id}/bank-transactions` — import body: rows[]
  (bank_account_id, occurred_at, description, amount_paisa), import_batch.
  Rows start unmatched and never enter ledger totals by themselves.
- `GET .../bank-transactions/{bid}/propose-matches` — exact-amount
  candidates only; changes nothing.
- `POST .../bank-transactions/{bid}/apply-match` — body: transaction_id.
  This is the human review step; it marks the IMPORT row matched and does
  not edit the ledger transaction.

## FBR returns

- `GET /api/v1/tax-years` — TY2026 (2025-07-01 to 2026-06-30, Finance Act
  2025, historical/late only) and TY2027 (2026-07-01 to 2027-06-30,
  Finance Act 2026, live year). Dates only, no rates.
- `GET /api/v1/tax-rules` — returns the rules table (currently empty)
  with verified=false and a Gate 0 note. `POST /api/v1/tax-rules/calculate`
  — body: tax_year_id, taxpayer_type, taxable_paisa. With no verified
  rates it returns 422 `rates_not_verified` ("rates not verified (Gate 0
  pending)") instead of a guessed figure.
- `GET/POST /api/v1/orgs/{id}/fbr-returns` — tax_year_id, taxpayer_type
  (salaried|business|aop|company), opening_net_paisa, closing_net_paisa.
- `GET /api/v1/orgs/{id}/fbr-returns/{rid}` — return + lines +
  reconciliation.
- `POST .../fbr-returns/{rid}/lines` — line_kind (income|expense|other),
  iris_field_code, amount_paisa, source_ref (required). No update/delete.
- `POST .../fbr-returns/{rid}/wealth` — kind (asset|liability|expense),
  name, amount_paisa, description (optional).
- `POST .../fbr-returns/{rid}/withholding` — source, amount_paisa,
  psid/cpr_no (optional, recorded only; payment happens on IRIS/e-payment).
- `GET .../fbr-returns/{rid}/reconciliation` — opening + income lines −
  wealth expenses, expected closing, difference, balances. Wealth
  reconciliation definition used here: income = sum of income lines;
  expenses = sum of wealth_expenses for the org + tax year. Expense lines
  are checklist items, not double-counted as wealth expenses.
- `POST .../fbr-returns/{rid}/review` — owner/admin/accountant only;
  decision approve (status ready) or reject (status in_review).
- `POST .../fbr-returns/{rid}/lock` — owner/admin/accountant only.
  Requires status ready + approved review + reconciliation difference
  exactly zero + at least one line. Sets status locked, stores a JSON
  snapshot + SHA-256 hash, tax_payable_paisa null with the Gate 0 note.
  After lock, lines/wealth/withholding/review/lock all return 409.
- `POST .../fbr-returns/{rid}/assisted-acknowledgement` — requires a
  locked return; body: acknowledgement_number, filed_date (not in the
  future). Records an assisted submission attempt. The user files on IRIS
  themselves; this API never logs into IRIS.
- There is NO unlock endpoint. `POST .../fbr-returns/{rid}/unlock`
  returns 403 `forbidden`.

## Health

- `GET /api/v1/health` — `{service, version}` only. No sensitive data.

## What is NOT in this API (by design)

No IRIS login, scraping, submission, or credential storage. No
auto-payment. No direct MySQL connection from the app. No PDF generation
(backend concern: none; the app's Excel export is separate). No
verified tax rates until Gate 0.
