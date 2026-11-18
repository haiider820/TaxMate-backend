-- TaxMate migration 006: FBR return line void + correct (soft void, never hard DELETE).
-- A wrong line on an unlocked return can be voided (excluded from the
-- reconciliation, the lock snapshot, and totals) and, for a correction,
-- replaced by a new active line that supersedes it. The old line stays in
-- the table as an audit trail: status 'void', voided_at, voided_by, and
-- supersedes_line_id on the replacement. Locked returns stay immutable:
-- the void/correct endpoints refuse them. MariaDB 10.6 supports
-- ADD COLUMN IF NOT EXISTS, so this migration is safe to re-run.
-- NOTE: no semicolons inside string literals (the migrate.php
-- splitter splits on semicolon + newline).

ALTER TABLE fbr_return_lines
    ADD COLUMN IF NOT EXISTS status ENUM('active', 'void') NOT NULL DEFAULT 'active';

ALTER TABLE fbr_return_lines
    ADD COLUMN IF NOT EXISTS voided_at DATETIME NULL;

ALTER TABLE fbr_return_lines
    ADD COLUMN IF NOT EXISTS voided_by BIGINT UNSIGNED NULL;

ALTER TABLE fbr_return_lines
    ADD COLUMN IF NOT EXISTS supersedes_line_id BIGINT UNSIGNED NULL;
