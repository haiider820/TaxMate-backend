# TaxMate backend — blocked items (do not work around these)

## 1. Gate 0 — verified tax rates: OPEN

`tax_rules` is structure only, with no seed rows and no rates. Until
Phase 0 gazette verification signs off BOTH TY2026 (Finance Act 2025)
and TY2027 (Finance Act 2026) and a Sources list is added, the API
refuses to calculate a final tax figure (`rates_not_verified`, 422).
Do not add rates from blogs, calculators, or memory. Do not copy rates
into comments, seeds, or test fixtures as if they were real.

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
