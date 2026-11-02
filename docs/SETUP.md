# TaxMate backend — setup (Core PHP + PDO MySQL)

This repo is NOT Laravel. It is the basic Core PHP starter Haider pushed
(index.php dashboard, config/, includes/, assets/). The API is additive
plain PHP 8 + PDO MySQL under `api/`. The starter dashboard is untouched.
Do NOT convert this repo to Laravel without Haider's explicit decision;
that would delete/replace the starter he pushed.

## 1. Environment variables (required)

Copy `.env.example` to `.env` (never commit `.env`) or set the variables
in your server/XAMPP environment:

- DB_HOST — e.g. localhost
- DB_NAME — e.g. taxmate
- DB_USER — your database user
- DB_PASS — your database password

The API reads these from the environment. It does not read the starter
`config/database.php`, and no real credential is stored in code. The
starter file's local defaults are left exactly as Haider pushed them.

## 2. Serve the API

Point the web server at the repo so `api/index.php` answers under
`/api/v1`. Examples (adjust to your setup, do not invent a production
domain here): XAMPP `http://localhost/taxmate-backend/api/index.php/v1/...`
or a virtual host with `/api` mapped to `api/index.php`. The router also
accepts a path containing `/api/v1` directly.

## 3. Run migrations (PC/server, after the php -l check below)

From the repo root:

```
php scripts/migrate.php
```

It applies `database/migrations/*.sql` in filename order once each and
records them in `schema_migrations`. Expected first run: 4 applied
(001 foundation, 002 finance, 003 business, 004 FBR), 0 skipped.
Re-running must report 4 skipped. Migration files are pure SQL only.

## 4. PC php -l checklist — CRITICAL, not done on the Luna VM

The Luna VM has NO php binary (`php --version` fails), so NO php -l,
no PHPUnit, and no live MySQL smoke test could be run there. Before
merging or deploying this push, run on the PC (repo root):

```
for /r %f in (*.php) do php -l "%f"
```

or the PowerShell equivalent. Every file must report "No syntax errors".
Then run `php scripts/migrate.php` against a test database and smoke-test:
`GET /api/v1/health`, register, login, create an org, create a
transaction, and confirm a locked-return flow refuses an edit. Report
any failure; do not deploy past a php -l failure.

## 5. Follow-ups deliberately left for later (not in this push)

- A review endpoint for pending_review voice/scan transactions (v1 keeps
  them out of totals; the flip-to-verified step is not yet an endpoint).
- Member removal, category deletion, and invoice search filters.
- A real production deployment guide (server, TLS, backups) — no server
  URL, domain, or IP is invented in this repo.
- PDF generation is not a backend feature here.
