-- LDAP / externe Anmeldung: Herkunft des Benutzerkontos (Vorbereitung)
ALTER TABLE dg_users
    ADD COLUMN auth_source VARCHAR(20) NOT NULL DEFAULT 'local' COMMENT 'local|ldap' AFTER employee_active,
    ADD COLUMN auth_external_id VARCHAR(191) NULL DEFAULT NULL AFTER auth_source;

CREATE INDEX idx_dg_users_auth_external ON dg_users (auth_source, auth_external_id);
