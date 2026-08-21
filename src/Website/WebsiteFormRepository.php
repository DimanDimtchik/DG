<?php
declare(strict_types=1);

/**
 * Persistence for website forms (visual form builder definitions).
 */
final class WebsiteFormRepository
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Entwurf',
            self::STATUS_PUBLISHED => 'Veröffentlicht',
        ];
    }

    public static function ensureTables(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dg_website_forms (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(191) NOT NULL DEFAULT \'\',
                status VARCHAR(20) NOT NULL DEFAULT \'draft\',
                definition_json LONGTEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_website_form_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dg_website_form_submissions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                form_id INT UNSIGNED NOT NULL,
                payload_json LONGTEXT NULL,
                files_json LONGTEXT NULL,
                ip VARCHAR(64) NOT NULL DEFAULT \'\',
                user_agent VARCHAR(500) NOT NULL DEFAULT \'\',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_form_sub_form (form_id, created_at),
                KEY idx_form_sub_unread (form_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return array{fields: list<array<string, mixed>>, settings: array<string, mixed>}
     */
    public static function emptyDefinition(): array
    {
        return [
            'fields' => [
                self::defaultField('text', 'Name', 'name', true),
                self::defaultField('email', 'E-Mail', 'email', true),
                self::defaultField('textarea', 'Nachricht', 'message', true),
                self::defaultField('consent', 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung zu.', 'privacy', true),
                self::defaultField('submit', 'Absenden', 'submit', false),
            ],
            'settings' => [
                'recipient_email' => '',
                'mail_subject' => 'Formularanfrage',
                'success_message' => 'Vielen Dank! Ihre Nachricht wurde gesendet.',
                'store_submissions' => true,
                'send_email' => true,
                'honeypot' => true,
                'captcha' => true,
            ],
        ];
    }

    /**
     * @return array{title: string, status: string, definition: array<string, mixed>}
     */
    public static function emptyForm(): array
    {
        return [
            'title' => 'Kontaktformular',
            'status' => self::STATUS_DRAFT,
            'definition' => self::emptyDefinition(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultField(string $type, string $label, string $name, bool $required): array
    {
        $field = [
            'id' => self::newId('fld'),
            'type' => $type,
            'label' => $label,
            'name' => $name,
            'required' => $required,
            'placeholder' => '',
            'width' => 12,
            'help' => '',
        ];

        if ($type === 'textarea') {
            $field['rows'] = 4;
        }
        if (in_array($type, ['select', 'checkbox', 'radio'], true)) {
            $field['options'] = [
                ['value' => 'option_1', 'label' => 'Option 1'],
                ['value' => 'option_2', 'label' => 'Option 2'],
            ];
        }
        if ($type === 'file') {
            $field['accept'] = '.pdf,.jpg,.jpeg,.png,.webp';
            $field['max_mb'] = 5;
        }
        if ($type === 'submit') {
            $field['required'] = false;
            $field['name'] = 'submit';
        }

        return $field;
    }

    public static function newId(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        self::ensureTables();

        $stmt = Database::pdo()->query(
            'SELECT f.id, f.title, f.status, f.updated_at,
                (SELECT COUNT(*) FROM dg_website_form_submissions s WHERE s.form_id = f.id) AS submission_count,
                (SELECT COUNT(*) FROM dg_website_form_submissions s WHERE s.form_id = f.id AND s.is_read = 0) AS unread_count
             FROM dg_website_forms f
             ORDER BY f.title ASC, f.id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public static function listPublishedOptions(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        self::ensureTables();

        $stmt = Database::pdo()->query(
            "SELECT id, title FROM dg_website_forms WHERE status = 'published' ORDER BY title ASC, id ASC"
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        if ($id <= 0 || !Database::isConfigured()) {
            return null;
        }
        self::ensureTables();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_website_forms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::map($row) : null;
    }

    /**
     * Published form for public embedding.
     *
     * @return array<string, mixed>|null
     */
    public static function findPublished(int $id): ?array
    {
        $form = self::findById($id);
        if ($form === null || ($form['status'] ?? '') !== self::STATUS_PUBLISHED) {
            return null;
        }

        return $form;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data, ?int $id, ?int $userId): int
    {
        self::ensureTables();

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Titel ist erforderlich.');
        }

        $status = (string) ($data['status'] ?? self::STATUS_DRAFT);
        if (!isset(self::statusOptions()[$status])) {
            $status = self::STATUS_DRAFT;
        }

        $definition = $data['definition'] ?? null;
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);
            $definition = is_array($decoded) ? $decoded : self::emptyDefinition();
        }
        if (!is_array($definition)) {
            $definition = self::emptyDefinition();
        }
        $definition = self::normalizeDefinition($definition);
        $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_website_forms SET title = :title, status = :status, definition_json = :definition_json WHERE id = :id'
            );
            $stmt->execute([
                'title' => $title,
                'status' => $status,
                'definition_json' => $json,
                'id' => $id,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_website_forms (title, status, definition_json, created_by)
             VALUES (:title, :status, :definition_json, :created_by)'
        );
        $stmt->execute([
            'title' => $title,
            'status' => $status,
            'definition_json' => $json,
            'created_by' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): void
    {
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        self::ensureTables();
        WebsiteFormFileStorage::deleteFormDir($id);
        $stmt = Database::pdo()->prepare('DELETE FROM dg_website_forms WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Ensures a starter contact form exists (returns its id).
     */
    public static function ensureDefaultContactForm(?int $userId = null): int
    {
        self::ensureTables();
        $list = self::list();
        if ($list !== []) {
            return (int) $list[0]['id'];
        }

        $form = self::emptyForm();
        $form['title'] = 'Kontaktformular';
        $form['status'] = self::STATUS_PUBLISHED;
        $companyEmail = '';
        try {
            $company = CompanySettings::forForm();
            $companyEmail = trim((string) ($company['email'] ?? ''));
        } catch (Throwable) {
            // ignore
        }
        $form['definition']['settings']['recipient_email'] = $companyEmail;

        return self::save($form, null, $userId);
    }

    /**
     * Resolve a published form for a legacy page "contact" block (create if needed).
     *
     * @return array<string, mixed>
     */
    public static function formForLegacyContact(string $recipientEmail = '', string $mailSubject = 'Kontaktanfrage', ?int $userId = null): array
    {
        self::ensureTables();
        $recipientEmail = trim($recipientEmail);
        $mailSubject = trim($mailSubject) !== '' ? trim($mailSubject) : 'Kontaktanfrage';

        foreach (self::list() as $row) {
            $full = self::findById((int) $row['id']);
            if ($full === null || ($full['status'] ?? '') !== self::STATUS_PUBLISHED) {
                continue;
            }
            $settings = is_array($full['definition']['settings'] ?? null) ? $full['definition']['settings'] : [];
            $formEmail = trim((string) ($settings['recipient_email'] ?? ''));
            if ($recipientEmail !== '' && strcasecmp($formEmail, $recipientEmail) === 0) {
                return $full;
            }
            if ($recipientEmail === '' && strcasecmp((string) ($full['title'] ?? ''), 'Kontaktformular') === 0) {
                return $full;
            }
        }

        $form = self::emptyForm();
        $form['title'] = 'Kontaktformular';
        $form['status'] = self::STATUS_PUBLISHED;
        $form['definition']['settings']['recipient_email'] = $recipientEmail;
        $form['definition']['settings']['mail_subject'] = $mailSubject;
        $form['definition']['settings']['captcha'] = true;
        if ($recipientEmail === '') {
            try {
                $company = CompanySettings::forForm();
                $form['definition']['settings']['recipient_email'] = trim((string) ($company['email'] ?? ''));
            } catch (Throwable) {
                // ignore
            }
        }
        $id = self::save($form, null, $userId);
        $created = self::findById($id);
        if ($created === null) {
            throw new RuntimeException('Kontaktformular konnte nicht angelegt werden.');
        }

        return $created;
    }

    /**
     * Replace contact blocks with form blocks in all website pages.
     *
     * @return int Number of pages updated
     */
    public static function migrateLegacyContactBlocksInPages(?int $userId = null): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }
        self::ensureTables();
        MigrationRunner::runPending();

        $stmt = Database::pdo()->query('SELECT id, layout_json FROM dg_website_pages');
        if (!$stmt) {
            return 0;
        }
        $updated = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $raw = (string) ($row['layout_json'] ?? '');
            $layout = $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($layout)) {
                continue;
            }
            $changed = false;
            $layout = self::convertContactBlocksInLayout($layout, $userId, $changed);
            if (!$changed) {
                continue;
            }
            $upd = Database::pdo()->prepare('UPDATE dg_website_pages SET layout_json = :j WHERE id = :id');
            $upd->execute([
                'j' => json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'id' => (int) $row['id'],
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public static function convertContactBlocksInLayout(array $layout, ?int $userId, bool &$changed): array
    {
        $changed = false;
        $rows = is_array($layout['rows'] ?? null) ? $layout['rows'] : [];
        foreach ($rows as $ri => $row) {
            if (!is_array($row)) {
                continue;
            }
            $cols = is_array($row['columns'] ?? null) ? $row['columns'] : [];
            foreach ($cols as $ci => $col) {
                if (!is_array($col)) {
                    continue;
                }
                $blocks = is_array($col['blocks'] ?? null) ? $col['blocks'] : [];
                foreach ($blocks as $bi => $block) {
                    if (!is_array($block) || ($block['type'] ?? '') !== 'contact') {
                        continue;
                    }
                    $form = self::formForLegacyContact(
                        (string) ($block['email'] ?? ''),
                        (string) ($block['subject'] ?? 'Kontaktanfrage'),
                        $userId
                    );
                    $blocks[$bi] = [
                        'id' => (string) ($block['id'] ?? self::newId('blk')),
                        'type' => 'form',
                        'form_id' => (int) $form['id'],
                    ];
                    $changed = true;
                }
                $cols[$ci]['blocks'] = $blocks;
            }
            $rows[$ri]['columns'] = $cols;
        }
        $layout['rows'] = $rows;

        return $layout;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{fields: list<array<string, mixed>>, settings: array<string, mixed>}
     */
    public static function normalizeDefinition(array $definition): array
    {
        $defaults = self::emptyDefinition();
        $settingsIn = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];
        $settings = array_merge($defaults['settings'], $settingsIn);
        $settings['recipient_email'] = trim((string) ($settings['recipient_email'] ?? ''));
        $settings['mail_subject'] = trim((string) ($settings['mail_subject'] ?? 'Formularanfrage')) ?: 'Formularanfrage';
        $settings['success_message'] = trim((string) ($settings['success_message'] ?? ''))
            ?: 'Vielen Dank! Ihre Nachricht wurde gesendet.';
        $settings['store_submissions'] = !empty($settings['store_submissions']);
        $settings['send_email'] = !empty($settings['send_email']);
        $settings['honeypot'] = !empty($settings['honeypot']);
        $settings['captcha'] = array_key_exists('captcha', $settingsIn)
            ? !empty($settingsIn['captcha'])
            : true;

        $fields = [];
        $rawFields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        $allowedTypes = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'radio', 'file', 'consent', 'submit', 'heading', 'paragraph', 'intent', 'article', 'appointment'];
        $usedNames = [];

        foreach ($rawFields as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $type = (string) ($raw['type'] ?? 'text');
            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }
            $id = trim((string) ($raw['id'] ?? ''));
            if ($id === '') {
                $id = self::newId('fld');
            }
            $label = trim((string) ($raw['label'] ?? ''));
            if ($label === '' && !in_array($type, ['submit', 'paragraph'], true)) {
                $label = 'Feld';
            }
            $name = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string) ($raw['name'] ?? '')))) ?: 'field';
            $name = trim((string) $name, '_');
            if ($name === '') {
                $name = 'field';
            }
            if (isset($usedNames[$name]) && $type !== 'submit') {
                $name .= '_' . substr(md5($id), 0, 4);
            }
            if ($type !== 'submit') {
                $usedNames[$name] = true;
            }

            $field = [
                'id' => $id,
                'type' => $type,
                'label' => $label,
                'name' => $type === 'submit' ? 'submit' : $name,
                'required' => !empty($raw['required']) && $type !== 'submit' && $type !== 'heading' && $type !== 'paragraph',
                'placeholder' => trim((string) ($raw['placeholder'] ?? '')),
                'width' => max(3, min(12, (int) ($raw['width'] ?? 12))),
                'help' => trim((string) ($raw['help'] ?? '')),
            ];

            if ($type === 'textarea') {
                $field['rows'] = max(2, min(20, (int) ($raw['rows'] ?? 4)));
            }
            if (in_array($type, ['select', 'checkbox', 'radio', 'intent', 'article'], true)) {
                $field['options'] = self::normalizeOptions($raw['options'] ?? []);
            }
            if ($type === 'appointment') {
                $field['placeholder'] = trim((string) ($raw['placeholder'] ?? '')) ?: 'z. B. DG-7K2M9P4Q';
                $field['help'] = trim((string) ($raw['help'] ?? ''))
                    ?: 'Buchungsnummer aus Ihrer Bestätigungsmail (z. B. DG-7K2M9P4Q).';
            }
            if ($type === 'file') {
                $field['accept'] = trim((string) ($raw['accept'] ?? '.pdf,.jpg,.jpeg,.png,.webp')) ?: '.pdf,.jpg,.jpeg,.png,.webp';
                $field['max_mb'] = max(1, min(20, (int) ($raw['max_mb'] ?? 5)));
            }

            $fields[] = $field;
        }

        if ($fields === []) {
            $fields = $defaults['fields'];
        }

        $hasSubmit = false;
        foreach ($fields as $f) {
            if (($f['type'] ?? '') === 'submit') {
                $hasSubmit = true;
                break;
            }
        }
        if (!$hasSubmit) {
            $fields[] = self::defaultField('submit', 'Absenden', 'submit', false);
        }

        return ['fields' => $fields, 'settings' => $settings];
    }

    /**
     * @param mixed $raw
     * @return list<array{value: string, label: string}>
     */
    private static function normalizeOptions(mixed $raw): array
    {
        $out = [];
        if (is_string($raw)) {
            $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, '|')) {
                    [$value, $label] = array_map('trim', explode('|', $line, 2));
                } else {
                    $value = $label = $line;
                }
                if ($value === '') {
                    continue;
                }
                $out[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $value = trim((string) ($opt['value'] ?? ''));
                $label = trim((string) ($opt['label'] ?? $value));
                if ($value === '') {
                    continue;
                }
                $out[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
            }
        }

        if ($out === []) {
            $out = [
                ['value' => 'option_1', 'label' => 'Option 1'],
                ['value' => 'option_2', 'label' => 'Option 2'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, title: string, status: string, definition: array<string, mixed>, updated_at: string}
     */
    private static function map(array $row): array
    {
        $definition = self::emptyDefinition();
        $raw = (string) ($row['definition_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $definition = self::normalizeDefinition($decoded);
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'status' => (string) ($row['status'] ?? self::STATUS_DRAFT),
            'definition' => $definition,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
