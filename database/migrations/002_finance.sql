-- TaxMate migration 002: personal finance (categories, transactions, budgets)
-- Money: BIGINT paisa only. Never float/double. No UPDATE/DELETE is done by
-- the API on transactions; corrections are new reversing rows (see API docs).

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    kind ENUM('income', 'expense') NOT NULL,
    fbr_income_head VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_categories_org (organization_id),
    CONSTRAINT fk_categories_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    type ENUM('income', 'expense') NOT NULL,
    amount_paisa BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PKR',
    note TEXT NULL,
    occurred_at DATE NOT NULL,
    source ENUM('manual', 'voice', 'scan') NOT NULL DEFAULT 'manual',
    document_id BIGINT UNSIGNED NULL,
    status ENUM('verified', 'pending_review') NOT NULL DEFAULT 'verified',
    correction_of_id BIGINT UNSIGNED NULL,
    correction_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transactions_org_date (organization_id, occurred_at),
    KEY idx_transactions_category (category_id),
    KEY idx_transactions_correction (correction_of_id),
    CONSTRAINT fk_transactions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_correction FOREIGN KEY (correction_of_id) REFERENCES transactions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    period_type ENUM('weekly', 'monthly') NOT NULL DEFAULT 'monthly',
    amount_limit_paisa BIGINT NOT NULL,
    alert_threshold_pct INT NOT NULL DEFAULT 80,
    start_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_budgets_org (organization_id),
    CONSTRAINT fk_budgets_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
