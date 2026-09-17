-- Multi-Firma Phase 0: Organisation ↔ Firma (KDV-Register, Variante A)
-- Keine Buchhaltungs-/Beleg-Tabellen — nur Master-Registry.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_kdv_orgs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    billing_email VARCHAR(191) NULL DEFAULT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kdv_orgs_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_kdv_customers
    ADD COLUMN org_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Organisation (Rechnungsempfänger / Login-Träger)' AFTER id,
    ADD COLUMN firm_relation VARCHAR(20) NOT NULL DEFAULT 'standalone'
        COMMENT 'standalone|tochter|schwester|nachfolger|vorgaenger' AFTER org_id,
    ADD COLUMN related_customer_id INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Verknüpfte Firma (z. B. Vorgänger)' AFTER firm_relation,
    ADD COLUMN firm_slot_status VARCHAR(20) NOT NULL DEFAULT 'active'
        COMMENT 'active|archive_readonly|closed' AFTER related_customer_id,
    ADD COLUMN effective_from DATE NULL DEFAULT NULL AFTER firm_slot_status,
    ADD COLUMN effective_to DATE NULL DEFAULT NULL AFTER effective_from,
    ADD KEY idx_kdv_customers_org (org_id),
    ADD KEY idx_kdv_customers_related (related_customer_id),
    ADD KEY idx_kdv_customers_slot (firm_slot_status);
