<?php
declare(strict_types=1);

/**
 * Wendet SQL-Migrationen aus database/migrations an (idempotent, legacy-aware).
 */
final class MigrationRunner
{
    private static bool $ranThisRequest = false;

    /**
     * Führt Migrationen einmal pro HTTP-Request beim CRM-Zugriff aus (Fehler werden geschluckt).
     */
    public static function runOnCrmAccess(): int
    {
        if (self::$ranThisRequest || !Database::isConfigured()) {
            return 0;
        }
        self::$ranThisRequest = true;

        try {
            return self::runPending();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Wendet alle noch nicht registrierten .sql-Dateien an.
     *
     * @return int Anzahl neu angewendeter Migrationen.
     */
    public static function runPending(): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }

        $pdo = Database::pdo();
        self::ensureMigrationsTable($pdo);
        self::bootstrapLegacyInstallations($pdo);
        self::repairStaleMigrations($pdo);

        $dir = DG_ROOT . '/database/migrations';
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);

        $applied = self::appliedIds($pdo);
        $count = 0;

        foreach ($files as $file) {
            $id = basename($file);
            if (isset($applied[$id])) {
                continue;
            }

            if (self::isMigrationSatisfied($pdo, $id)) {
                $stmt = $pdo->prepare('INSERT INTO dg_migrations (id) VALUES (:id)');
                $stmt->execute(['id' => $id]);
                $count++;
                continue;
            }

            self::executeFile($pdo, $file);
            $stmt = $pdo->prepare('INSERT INTO dg_migrations (id) VALUES (:id)');
            $stmt->execute(['id' => $id]);
            $count++;
        }

        return $count;
    }

    /** Legt dg_migrations an, falls nicht vorhanden. */
    private static function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dg_migrations (
                id VARCHAR(128) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Bereits migrierte Schritte erkennen — fehlende Tabellen bleiben offen.
     * Markiert erfüllte Migrationen bei Altinstallationen ohne dg_migrations-Einträge.
     */
    private static function bootstrapLegacyInstallations(PDO $pdo): void
    {
        if (self::appliedIds($pdo) !== []) {
            return;
        }

        if (!self::tableExists($pdo, 'dg_users')) {
            return;
        }

        $dir = DG_ROOT . '/database/migrations';
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        $stmt = $pdo->prepare('INSERT IGNORE INTO dg_migrations (id) VALUES (:id)');
        foreach ($files as $file) {
            $id = basename($file);
            if (self::isMigrationSatisfied($pdo, $id)) {
                $stmt->execute(['id' => $id]);
            }
        }
    }

    /**
     * Prüft anhand von Schema-Heuristiken, ob eine Migration bereits erfüllt ist.
     */
    private static function isMigrationSatisfied(PDO $pdo, string $migrationId): bool
    {
        return match ($migrationId) {
            '001_initial.sql' => self::tableExists($pdo, 'dg_users'),
            '002_contacts.sql' => self::tableExists($pdo, 'dg_contacts'),
            '003_bookings.sql' => self::tableExists($pdo, 'dg_bookings'),
            '004_merge_kunde_lieferant.sql' => self::tableExists($pdo, 'dg_contacts'),
            '005_contact_roles_crm.sql' => self::tableExists($pdo, 'dg_contacts'),
            '006_bank_social.sql' => self::columnExists($pdo, 'dg_contacts', 'bank_accounts'),
            '007_employee_data.sql' => self::columnExists($pdo, 'dg_contacts', 'employee_data'),
            '008_mail_log.sql' => self::tableExists($pdo, 'dg_mail_log'),
            '009_settings.sql' => self::tableExists($pdo, 'dg_settings'),
            '010_calendar_staff.sql' => self::tableExists($pdo, 'dg_calendar_areas'),
            '011_media.sql' => self::tableExists($pdo, 'dg_media'),
            '012_department_access.sql' => self::columnExists($pdo, 'dg_departments', 'is_hr'),
            '013_number_range_history.sql' => self::tableExists($pdo, 'dg_number_range_history'),
            '014_calendar_working_hours.sql' => self::tableExists($pdo, 'dg_calendar_working_hours'),
            '015_calendar_articles.sql' => self::tableExists($pdo, 'dg_calendar_articles'),
            '016_calendar_links_and_article_catalog.sql' => self::columnExists($pdo, 'dg_calendar_articles', 'article_number'),
            '017_catalog_kind_and_department_catalog.sql' => self::columnExists($pdo, 'dg_calendar_articles', 'catalog_kind')
                && self::columnExists($pdo, 'dg_departments', 'is_purchasing')
                && self::columnExists($pdo, 'dg_departments', 'allow_article_catalog'),
            '018_merge_purchasing_into_catalog.sql' => !self::departmentFlagEnabled($pdo, 'is_purchasing'),
            '019_contact_company_links.sql' => self::tableExists($pdo, 'dg_contact_company_links'),
            '020_mailboxes.sql' => self::tableExists($pdo, 'dg_mailboxes'),
            '021_mailbox_smtp.sql' => self::columnExists($pdo, 'dg_mailboxes', 'smtp_host'),
            '022_password_reset.sql' => self::tableExists($pdo, 'dg_password_reset_tokens'),
            '023_mail_imap_folders.sql' => self::columnExists($pdo, 'dg_mail_log', 'imap_folder'),
            '024_chart_of_accounts.sql' => self::tableExists($pdo, 'dg_chart_accounts'),
            '025_chart_account_name_index.sql' => self::tableExists($pdo, 'dg_chart_accounts'),
            '026_vouchers.sql' => self::tableExists($pdo, 'dg_vouchers'),
            '027_voucher_lines_and_types.sql' => self::tableExists($pdo, 'dg_voucher_lines'),
            '028_voucher_reverse_charge.sql' => self::columnExists($pdo, 'dg_vouchers', 'reverse_charge_type')
                && self::columnExists($pdo, 'dg_voucher_lines', 'line_kind'),
            '029_voucher_payment_status.sql' => self::columnExists($pdo, 'dg_vouchers', 'payment_status')
                && self::columnTypeAllows($pdo, 'dg_vouchers', 'payment_status', 'varchar'),
            '030_voucher_items.sql' => self::tableExists($pdo, 'dg_voucher_items'),
            '031_voucher_delivery_date.sql' => self::columnExists($pdo, 'dg_vouchers', 'delivery_date'),
            '032_voucher_arap.sql' => self::columnExists($pdo, 'dg_vouchers', 'arap_enabled'),
            '033_bank_transfers.sql' => self::tableExists($pdo, 'dg_bank_transfers'),
            '034_ledger.sql' => self::tableExists($pdo, 'dg_ledger_postings')
                && self::tableExists($pdo, 'dg_fiscal_years'),
            '035_voucher_files.sql' => self::tableExists($pdo, 'dg_voucher_files'),
            '036_contact_register_fields.sql' => self::columnExists($pdo, 'dg_contacts', 'commercial_register')
                && self::columnExists($pdo, 'dg_contacts', 'weee_registration'),
            '037_voucher_drafts.sql' => self::columnExists($pdo, 'dg_vouchers', 'is_draft'),
            '038_supplier_customer_number.sql' => self::columnExists($pdo, 'dg_contacts', 'supplier_customer_number'),
            '039_website_pages.sql' => self::tableExists($pdo, 'dg_website_pages'),
            '040_kdv_customers.sql' => self::tableExists($pdo, 'dg_kdv_customers'),
            '041_login_attempts.sql' => self::tableExists($pdo, 'dg_login_attempts'),
            '042_security_log.sql' => self::tableExists($pdo, 'dg_security_log'),
            '043_website_forms.sql' => self::tableExists($pdo, 'dg_website_forms'),
            '044_website_pageviews.sql' => self::tableExists($pdo, 'dg_website_pageviews'),
            '045_booking_code.sql' => self::columnExists($pdo, 'dg_bookings', 'booking_code'),
            '046_contact_email_existence.sql' => self::columnExists($pdo, 'dg_contacts', 'email_existence_status'),
            '047_kdv_license_shop_account.sql' => self::columnExists($pdo, 'dg_kdv_customers', 'license_key')
                && self::columnExists($pdo, 'dg_kdv_customers', 'shop_password_hash'),
            '048_kdv_shop_password_reset.sql' => self::tableExists($pdo, 'dg_kdv_password_reset_tokens'),
            '049_kdv_mailbox_credentials.sql' => self::columnExists($pdo, 'dg_kdv_customers', 'mailbox_password'),
            '050_support_access.sql' => self::tableExists($pdo, 'dg_support_access')
                && self::tableExists($pdo, 'dg_support_signals')
                && self::tableExists($pdo, 'dg_kdv_support_sessions'),
            '051_ledger_datev.sql' => self::columnExists($pdo, 'dg_ledger_postings', 'tax_key')
                && self::columnExists($pdo, 'dg_contacts', 'debtor_account')
                && self::tableExists($pdo, 'dg_cash_journal'),
            '052_banking_manual.sql' => self::tableExists($pdo, 'dg_bank_transactions')
                && self::tableExists($pdo, 'dg_manual_journal_batches'),
            '053_elster_submissions.sql' => self::tableExists($pdo, 'dg_elster_submissions'),
            '054_cash_day_closing.sql' => self::tableExists($pdo, 'dg_cash_day_closings'),
            '055_voucher_document_chain.sql' => self::columnExists($pdo, 'dg_vouchers', 'document_kind'),
            '056_voucher_document_status.sql' => self::columnExists($pdo, 'dg_vouchers', 'document_status'),
            '057_voucher_document_texts.sql' => self::columnExists($pdo, 'dg_vouchers', 'document_intro_text'),
            '058_voucher_document_legal_clauses.sql' => self::columnExists($pdo, 'dg_vouchers', 'document_legal_clauses'),
            '059_voucher_payment_terms_dunning.sql' => self::columnExists($pdo, 'dg_vouchers', 'payment_due_date')
                && self::tableExists($pdo, 'dg_voucher_dunnings'),
            default => false,
        };
    }

    /**
     * Entfernt fehlerhaft als erledigt markierte Migrationen (Tabelle/Spalte fehlt).
     * Unbekannte IDs ohne Heuristik werden nicht angefasst (sonst Endlos-Re-Run).
     */
    private static function repairStaleMigrations(PDO $pdo): void
    {
        $applied = self::appliedIds($pdo);
        if ($applied === []) {
            return;
        }

        $known = self::knownSatisfactionIds();
        $stmt = $pdo->prepare('DELETE FROM dg_migrations WHERE id = :id');
        foreach (array_keys($applied) as $id) {
            if (!isset($known[$id])) {
                continue;
            }
            if (!self::isMigrationSatisfied($pdo, $id)) {
                $stmt->execute(['id' => $id]);
            }
        }
    }

    /** @return array<string, true> */
    private static function knownSatisfactionIds(): array
    {
        return [
            '001_initial.sql' => true,
            '002_contacts.sql' => true,
            '003_bookings.sql' => true,
            '004_merge_kunde_lieferant.sql' => true,
            '005_contact_roles_crm.sql' => true,
            '006_bank_social.sql' => true,
            '007_employee_data.sql' => true,
            '008_mail_log.sql' => true,
            '009_settings.sql' => true,
            '010_calendar_staff.sql' => true,
            '011_media.sql' => true,
            '012_department_access.sql' => true,
            '013_number_range_history.sql' => true,
            '014_calendar_working_hours.sql' => true,
            '015_calendar_articles.sql' => true,
            '016_calendar_links_and_article_catalog.sql' => true,
            '017_catalog_kind_and_department_catalog.sql' => true,
            '018_merge_purchasing_into_catalog.sql' => true,
            '019_contact_company_links.sql' => true,
            '020_mailboxes.sql' => true,
            '021_mailbox_smtp.sql' => true,
            '022_password_reset.sql' => true,
            '023_mail_imap_folders.sql' => true,
            '024_chart_of_accounts.sql' => true,
            '025_chart_account_name_index.sql' => true,
            '026_vouchers.sql' => true,
            '027_voucher_lines_and_types.sql' => true,
            '028_voucher_reverse_charge.sql' => true,
            '029_voucher_payment_status.sql' => true,
            '030_voucher_items.sql' => true,
            '031_voucher_delivery_date.sql' => true,
            '032_voucher_arap.sql' => true,
            '033_bank_transfers.sql' => true,
            '034_ledger.sql' => true,
            '035_voucher_files.sql' => true,
            '036_contact_register_fields.sql' => true,
            '037_voucher_drafts.sql' => true,
            '038_supplier_customer_number.sql' => true,
            '039_website_pages.sql' => true,
            '040_kdv_customers.sql' => true,
            '041_login_attempts.sql' => true,
            '042_security_log.sql' => true,
            '043_website_forms.sql' => true,
            '044_website_pageviews.sql' => true,
            '045_booking_code.sql' => true,
            '046_contact_email_existence.sql' => true,
            '047_kdv_license_shop_account.sql' => true,
            '048_kdv_shop_password_reset.sql' => true,
            '049_kdv_mailbox_credentials.sql' => true,
            '050_support_access.sql' => true,
            '051_ledger_datev.sql' => true,
            '052_banking_manual.sql' => true,
            '053_elster_submissions.sql' => true,
            '054_cash_day_closing.sql' => true,
            '055_voucher_document_chain.sql' => true,
            '056_voucher_document_status.sql' => true,
            '057_voucher_document_texts.sql' => true,
            '058_voucher_document_legal_clauses.sql' => true,
            '059_voucher_payment_terms_dunning.sql' => true,
        ];
    }

    /** @return bool */
    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return bool */
    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return bool */
    private static function columnTypeAllows(PDO $pdo, string $table, string $column, string $typeFragment): bool
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column));
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($row)) {
            return false;
        }
        $fieldType = strtolower((string) ($row['Type'] ?? ''));

        return str_contains($fieldType, strtolower($typeFragment));
    }

    /** @return bool */
    private static function departmentFlagEnabled(PDO $pdo, string $column): bool
    {
        if (!self::tableExists($pdo, 'dg_departments') || !self::columnExists($pdo, 'dg_departments', $column)) {
            return false;
        }

        $safeColumn = preg_replace('/[^a-z_]/', '', $column) ?? '';
        if ($safeColumn === '') {
            return false;
        }

        $stmt = $pdo->query('SELECT 1 FROM dg_departments WHERE `' . $safeColumn . '` = 1 LIMIT 1');

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return array<string, true> */
    private static function appliedIds(PDO $pdo): array
    {
        try {
            $rows = $pdo->query('SELECT id FROM dg_migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $id) {
            $out[(string) $id] = true;
        }

        return $out;
    }

    /**
     * Führt eine SQL-Datei Statement für Statement aus (Kommentare werden entfernt).
     *
     * @throws PDOException Bei SQL-Fehlern.
     */
    private static function executeFile(PDO $pdo, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        if (!str_ends_with(rtrim($sql), ';')) {
            $sql = rtrim($sql) . ';';
        }
        $sql .= "\n";

        foreach (preg_split('/;\s*\n/', $sql) as $statement) {
            $statement = self::stripSqlComments(trim($statement));
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    /** Entfernt Zeilenkommentare (-- ...) aus einem SQL-Statement. */
    private static function stripSqlComments(string $sql): string
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }
}
