# TaxMate-backend — Core PHP API

Additive Core PHP (plain PHP 8 + PDO MySQL) API for TaxMate. The repo's
starter dashboard (index.php, config/, includes/, assets/) is untouched;
the API lives only under `api/`. This is NOT a Laravel conversion.

- API: `api/index.php` serves JSON under `/api/v1` — see `docs/API.md`.
- Migrations: `database/migrations/*.sql` (pure SQL) — run with
  `php scripts/migrate.php` — see `docs/SETUP.md`.
- Environment: copy `.env.example` to `.env` (never commit `.env`).
- Blocked items: `docs/BLOCKED.md` (Gate 0 rates, Phase 5B PRAL).

Finance rules: money is integer paisa only, every query is org-scoped,
financial rows are never edited in place (corrections are reversing
entries), locked FBR returns are immutable, and there is no IRIS login,
scraping, submission, or credential storage anywhere in this API.

IMPORTANT: the Luna VM has no php binary, so php -l and live tests were
NOT run here. Run the PC checklist in `docs/SETUP.md` before deploying.
