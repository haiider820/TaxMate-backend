# TaxMate backend — blocked items (do not work around these)

## 1. Gate 0 — verified tax rates: PASSED 2026-11-11

`tax_rules` now holds the verified rows (migration
`005_gate0_rates.sql`, `is_verified = 1`): TY2026 salaried Clause (2)
(6 slabs, 9% surcharge above Rs 10m), TY2027 salaried Clause (2)
(8 slabs, no surcharge for salaried), and Clause (1) for business/AOP
in both years (6 slabs, 10% surcharge above Rs 10m). Full tables +
Sources list: `docs/GATE0.md` and the research report. `POST
/api/v1/tax-rules/calculate` returns the computed figure; it still
returns 422 `rates_not_verified` for any year + type with no verified
row (e.g. `company`). Do not add rates from blogs, calculators, or
memory.

## 2. Phase 5B — live PRAL/IRIS adapter: BLOCKED

There are no PRAL credentials and no official channel granted. This API
contains no IRIS login, no scraping, no automated submission, and no
IRIS credential storage, and none may be added as a workaround.
Conditions to start 5B: an official channel in writing, sandbox
credentials/scenarios through that channel, a sandbox pass, and a
separate production yes. Sales-tax Digital Invoicing via PRAL is a
different system from income-tax return filing; do not conflate them.

## 3. Verification limits on the Luna VM

No php binary, so php -l, PHPUnit, and live MySQL endpoint tests were
NOT run here. The PC checklist in docs/SETUP.md is mandatory after push.
Do not describe this API as smoke-tested until that checklist passes.

## 4. Not built in this push

Voice/OCR capture endpoints, document file upload handling (documents
table stores a private file path only; upload wiring is a later step),
team/dispute UIs (backend has members + audit only), PDF generation,
and production deployment. These are gaps, not hidden features.
