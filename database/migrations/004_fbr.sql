-- TaxMate migration 004: FBR preparation (tax years, rules, wealth, withholding, returns)
-- SEED RULE: tax_years dates only. tax_rules gets NO seed rows and NO rates
-- until Gate 0 gazette verification. A return locks only when its wealth
-- reconciliation difference is exactly zero. Locked returns are immutable.

CREATE TABLE IF NOT EXISTS tax_years (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    finance_act VARCHAR(100) NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tax_years_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tax_year_id BIGINT UNSIGNED NOT NULL,
    taxpayer_type ENUM('salaried', 'business', 'aop', 'company') NOT NULL,
    from_paisa BIGINT NOT NULL,
    to_paisa BIGINT NULL,
    rate_bps INT NOT NULL,
    base_tax_paisa BIGINT NOT NULL,
    surcharge_rule VARCHAR(200) NULL,
    finance_act_source VARCHAR(300) NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tax_rules_year_type (tax_year_id, taxpayer_type),
    CONSTRAINT fk_tax_rules_year FOREIGN KEY (tax_year_id) REFERENCES tax_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wealth_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    tax_year_id BIGINT UNSIGNED NOT NULL,
    asset_type VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    amount_paisa BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wealth_assets_org_year (organization_id, tax_year_id),
    CONSTRAINT fk_wealth_assets_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_wealth_assets_year FOREIGN KEY (tax_year_id) REFERENCES tax_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wealth_liabilities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    tax_year_id BIGINT UNSIGNED NOT NULL,
    liability_type VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    amount_paisa BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wealth_liab_org_year (organization_id, tax_year_id),
    CONSTRAINT fk_wealth_liab_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_wealth_liab_year FOREIGN KEY (tax_year_id) REFERENCES tax_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wealth_expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    tax_year_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    amount_paisa BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wealth_exp_org_year (organization_id, tax_year_id),
    CONSTRAINT fk_wealth_exp_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_wealth_exp_year FOREIGN KEY (tax_year_id) REFERENCES tax_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withholding_credits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    tax_year_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(200) NOT NULL,
    psid VARCHAR(100) NULL,
    cpr_no VARCHAR(100) NULL,
    amount_paisa BIGINT NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_withholding_org_year (organization_id, tax_year_id),
    CONSTRAINT fk_withholding_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_withholding_year FOREIGN KEY (tax_year_id) REFERENCES tax_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fbr_returns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    tax_year_id BIGINT UNSIGNED NOT NULL,
    taxpayer_type ENUM('salaried', 'business', 'aop', 'company') NOT NULL,
    status ENUM('draft', 'in_review', 'ready', 'locked', 'submitted', 'accepted', 'rejected') NOT NULL DEFAULT 'draft',
    opening_net_paisa BIGINT NOT NULL,
    closing_net_paisa BIGINT NOT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_decision VARCHAR(20) NULL,
    reviewed_at DATETIME NULL,
    locked_snapshot_json LONGTEXT NULL,
    snapshot_hash CHAR(64) NULL,
    locked_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fbr_returns_org_year (organization_id, tax_year_id),
    CONSTRAINT fk_fbr_returns_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_fbr_returns_year FOREIGN KEY (tax_year_id) REFERENCES tax_years (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fbr_return_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fbr_return_id BIGINT UNSIGNED NOT NULL,
    line_kind ENUM('income', 'expense', 'other') NOT NULL,
    iris_field_code VARCHAR(100) NOT NULL,
    amount_paisa BIGINT NOT NULL,
    source_ref VARCHAR(300) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fbr_lines_return (fbr_return_id),
    CONSTRAINT fk_fbr_lines_return FOREIGN KEY (fbr_return_id) REFERENCES fbr_returns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fbr_submission_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fbr_return_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('assisted', 'iris_guided', 'pral_api') NOT NULL DEFAULT 'assisted',
    acknowledgement_number VARCHAR(200) NULL,
    filed_date DATE NULL,
    payload_hash CHAR(64) NULL,
    result VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fbr_attempts_return (fbr_return_id),
    CONSTRAINT fk_fbr_attempts_return FOREIGN KEY (fbr_return_id) REFERENCES fbr_returns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tax_years (label, start_date, end_date, finance_act, is_current) VALUES
    ('TY2026', '2025-07-01', '2026-06-30', 'Finance Act 2025', 0),
    ('TY2027', '2026-07-01', '2027-06-30', 'Finance Act 2026', 1);
