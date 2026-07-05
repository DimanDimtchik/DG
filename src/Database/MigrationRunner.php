<?php
declare(strict_types=1);

final class MigrationRunner
{
    private static bool $ranThisRequest = false;

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

    /** Bereits migrierte Schritte erkennen — fehlende Tabellen bleiben offen. */
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
            default => false,
        };
    }

    /** Entfernt fehlerhaft als erledigt markierte Migrationen (Tabelle fehlt). */
    private static function repairStaleMigrations(PDO $pdo): void
    {
        $applied = self::appliedIds($pdo);
        if ($applied === []) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM dg_migrations WHERE id = :id');
        foreach (array_keys($applied) as $id) {
            if (!self::isMigrationSatisfied($pdo, $id)) {
                $stmt->execute(['id' => $id]);
            }
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

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
