<?php
declare(strict_types=1);

/**
 * E-Mail-Vorlagen für Terminkalender und Abteilungen.
 */
final class NotificationTemplateSettings
{
    public const STORE_KEY = 'notification_templates';

    public const CATEGORY_CALENDAR = 'calendar';
    public const CATEGORY_DEPARTMENT = 'department';
    public const CATEGORY_COMMUNICATION = 'communication';
    public const CATEGORY_ACCOUNTING = 'accounting';

    public const MODE_STANDARD = 'standard';
    public const MODE_OWN = 'own';
    public const MODE_INHERIT = 'inherit';

    public const SLUG_CONFIRMATION = 'confirmation';
    public const SLUG_CANCELLATION = 'cancellation';
    public const SLUG_ADMIN = 'admin';

    /** UI-Schlüssel für Terminkalender (gespeichert als leere department_id). */
    public const CALENDAR_OWNER_ID = 'terminkalender';

    /**
     * Mappt Owner-ID auf Abteilungs-ID.
     * @param string $ownerId
     * @return string
     */
    public static function ownerIdToDepartmentId(string $ownerId): string
    {
        $ownerId = trim($ownerId);

        return $ownerId === self::CALENDAR_OWNER_ID || $ownerId === '' ? '' : $ownerId;
    }

    /**
     * Mappt Abteilungs-ID auf Owner-ID.
     * @param string $departmentId
     * @return string
     */
    public static function departmentIdToOwnerId(string $departmentId): string
    {
        return trim($departmentId) === '' ? self::CALENDAR_OWNER_ID : trim($departmentId);
    }

    /**
     * Liefert Vorlagen-Eigentümer für die UI.
     * @return list<array{id: string, name: string, is_calendar: bool}>
     */
    public static function templateOwners(): array
    {
        $owners = [
            [
                'id' => self::CALENDAR_OWNER_ID,
                'name' => 'Terminkalender',
                'is_calendar' => true,
            ],
        ];
        foreach (DepartmentRepository::allWithMembers() as $dept) {
            $id = trim((string) ($dept['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $owners[] = [
                'id' => $id,
                'name' => trim((string) ($dept['name'] ?? '')) !== '' ? (string) $dept['name'] : $id,
                'is_calendar' => false,
            ];
        }

        return $owners;
    }

    /**
     * Methode templates for owner.
     * @param string $ownerId
     * @param array $templates
     * @param bool $displayableOnly
     * @return array<string, mixed>
     */
    public static function templatesForOwner(string $ownerId, array $templates, bool $displayableOnly = true): array
    {
        $departmentId = self::ownerIdToDepartmentId($ownerId);
        $out = [];
        foreach ($templates as $template) {
            if (trim((string) ($template['department_id'] ?? '')) !== $departmentId) {
                continue;
            }
            if ($displayableOnly && !self::isDisplayableTemplate($template)) {
                continue;
            }
            $out[] = $template;
        }

        usort($out, static function (array $a, array $b): int {
            $categoryOrder = array_keys(self::categories());
            $posA = array_search((string) ($a['category'] ?? ''), $categoryOrder, true);
            $posB = array_search((string) ($b['category'] ?? ''), $categoryOrder, true);
            $posA = $posA === false ? PHP_INT_MAX : (int) $posA;
            $posB = $posB === false ? PHP_INT_MAX : (int) $posB;
            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        });

        return $out;
    }

    /**
     * Prüft: is displayable template.
     * @param array $template
     * @return bool
     */
    public static function isDisplayableTemplate(array $template): bool
    {
        if (!empty($template['builtin'])) {
            return true;
        }

        $name = trim((string) ($template['name'] ?? ''));
        if ($name === '') {
            return false;
        }

        $subject = trim((string) ($template['subject'] ?? ''));
        $title = trim((string) ($template['title'] ?? ''));
        $intro = trim((string) ($template['intro'] ?? ''));

        return $subject !== '' || $title !== '' || $intro !== '';
    }

    /**
     * Methode categories.
     * @return array<string, mixed>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_CALENDAR => 'Terminkalender',
            self::CATEGORY_DEPARTMENT => 'Abteilungsvorlagen',
        ];
    }

    /**
     * Methode mode labels.
     * @return array<string, mixed>
     */
    public static function modeLabels(): array
    {
        return [
            self::MODE_STANDARD => 'Standard-Vorlagen',
            self::MODE_OWN => 'Eigene Vorlagen',
            self::MODE_INHERIT => 'Vorlage übernehmen',
        ];
    }

    /**
     * Methode default builtin templates.
     * @return array<string, mixed>
     */
    public static function defaultBuiltinTemplates(): array
    {
        $calendar = [
            self::SLUG_CONFIRMATION => [
                'name' => 'Buchungsbestätigung (Kunde)',
                'subject' => 'Terminbestätigung: {termin_datum}',
                'title' => 'Terminbestätigung',
                'intro' => 'Ihr Termin am {termin_datum} um {termin_zeit} wurde gebucht. Ihre Buchungsnummer: {buchungsnummer}. Bitte bewahren Sie diese Nummer auf – z. B. für Rückfragen im Kontaktformular.',
            ],
            self::SLUG_CANCELLATION => [
                'name' => 'Storno (Kunde)',
                'subject' => 'Termin storniert: {termin_datum}',
                'title' => 'Termin storniert',
                'intro' => 'Ihr Termin am {termin_datum} wurde storniert. Bei Fragen wenden Sie sich bitte an uns.',
            ],
            self::SLUG_ADMIN => [
                'name' => 'Neue Buchung (Intern)',
                'subject' => 'Neue Buchung: {termin_datum}',
                'title' => 'Neue Buchung',
                'intro' => 'Es ist eine neue Buchung für {termin_datum} eingegangen.',
            ],
        ];

        $out = [];
        $sort = 0;
        foreach ($calendar as $slug => $row) {
            $out[] = [
                'id' => $slug,
                'name' => $row['name'],
                'category' => self::CATEGORY_CALENDAR,
                'department_id' => '',
                'event_slug' => $slug,
                'builtin' => true,
                'subject' => $row['subject'],
                'title' => $row['title'],
                'intro' => $row['intro'],
                'sort_order' => $sort++,
            ];
        }

        return $out;
    }

    /**
     * Methode all.
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $defaults = [
            'templates' => self::defaultBuiltinTemplates(),
            'department_modes' => [],
        ];

        if (!Database::isConfigured()) {
            return $defaults;
        }

        $stored = SettingsStore::get(self::STORE_KEY, ['templates' => [], 'department_modes' => []]);
        if (!isset($stored['templates']) || $stored['templates'] === []) {
            $legacy = SettingsStore::get('calendar_email_templates', ['globals' => [], 'departments' => []]);
            if ($legacy !== []) {
                $stored = self::migrateLegacy($legacy);
            }
        }

        return self::sanitizeStore(is_array($stored) ? $stored : []);
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $all = self::all();
        $departmentOptions = [];
        foreach (DepartmentRepository::allWithMembers() as $dept) {
            $id = trim((string) ($dept['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $departmentOptions[] = [
                'id' => $id,
                'name' => trim((string) ($dept['name'] ?? '')) !== '' ? (string) $dept['name'] : $id,
            ];
        }

        $departmentModes = [];
        foreach ($departmentOptions as $option) {
            $id = $option['id'];
            $row = is_array($all['department_modes'][$id] ?? null) ? $all['department_modes'][$id] : [];
            $departmentModes[$id] = [
                'mode' => self::normalizeMode((string) ($row['mode'] ?? self::MODE_STANDARD)),
                'inherit_from' => trim((string) ($row['inherit_from'] ?? '')),
            ];
        }

        return [
            'templates' => $all['templates'],
            'department_modes' => $departmentModes,
            'department_options' => $departmentOptions,
            'inherit_sources' => self::departmentsWithOwnTemplates($departmentModes, $departmentOptions),
            'categories' => self::categories(),
            'template_owners' => self::templateOwners(),
        ];
    }

    /**
     * Methode templates for scope.
     * @param string $category
     * @param string $departmentId
     * @param array $templates
     * @return array<string, mixed>
     */
    public static function templatesForScope(string $category, string $departmentId, array $templates): array
    {
        $departmentId = trim($departmentId);
        $out = [];
        foreach ($templates as $template) {
            if ((string) ($template['category'] ?? '') !== $category) {
                continue;
            }
            if ((string) ($template['department_id'] ?? '') === $departmentId) {
                $out[] = $template;
            }
        }

        usort($out, static fn(array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return $out;
    }

    /**
     * Führt aus: resolved template.
     * @param string $departmentId
     * @param string $category
     * @param string $templateId
     * @return array|null
     */
    public static function resolvedTemplate(string $departmentId, string $category, string $templateId): ?array
    {
        $category = self::normalizeCategory($category);
        $templateId = trim($templateId);
        $all = self::all();
        $modeRow = is_array($all['department_modes'][trim($departmentId)] ?? null)
            ? $all['department_modes'][trim($departmentId)]
            : ['mode' => self::MODE_STANDARD, 'inherit_from' => ''];

        $sourceDeptId = trim($departmentId);
        $visited = [];

        while (true) {
            $mode = self::normalizeMode((string) ($modeRow['mode'] ?? self::MODE_STANDARD));

            if ($mode === self::MODE_OWN) {
                $found = self::findTemplate($all['templates'], $templateId, $category, $sourceDeptId);
                if ($found !== null) {
                    return $found;
                }
                if ($sourceDeptId === '') {
                    break;
                }
                return self::findTemplate($all['templates'], $templateId, $category, '') ?? self::findBuiltin($all['templates'], $templateId, $category);
            }

            if ($mode === self::MODE_INHERIT) {
                $inherit = trim((string) ($modeRow['inherit_from'] ?? ''));
                if ($inherit === '' || $inherit === $sourceDeptId || isset($visited[$inherit])) {
                    break;
                }
                $visited[$sourceDeptId] = true;
                $sourceDeptId = $inherit;
                $modeRow = is_array($all['department_modes'][$inherit] ?? null)
                    ? $all['department_modes'][$inherit]
                    : ['mode' => self::MODE_STANDARD, 'inherit_from' => ''];
                continue;
            }

            break;
        }

        return self::findTemplate($all['templates'], $templateId, $category, '')
            ?? self::findBuiltin($all['templates'], $templateId, $category);
    }

    /**
     * Speichert Formulardaten.
     * @param array $input
     * @return void
     */
    public static function saveFromPost(array $input): void
    {
        self::saveAllTemplatesFromPost($input);
    }

    /** Alle Vorlagen aus Benachrichtigungen — Zuordnungsmodi bleiben erhalten. */
    /**
     * Speichert all templates from post.
     * @param array $input
     * @return void
     */
    public static function saveAllTemplatesFromPost(array $input): void
    {
        $all = self::all();
        $posted = self::parseTemplatesFromPost($input, static fn(array $template): bool => true);
        $postedIds = [];
        foreach ($posted as $template) {
            $postedIds[$template['id']] = true;
        }

        $deleteIds = [];
        $deleted = $input['notification_delete'] ?? [];
        if (is_array($deleted)) {
            foreach ($deleted as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $deleteIds[trim($id)] = true;
                }
            }
        }

        $merged = $posted;
        foreach ($all['templates'] as $existing) {
            $id = (string) ($existing['id'] ?? '');
            if ($id === '' || isset($deleteIds[$id]) || isset($postedIds[$id])) {
                continue;
            }
            if (self::isDisplayableTemplate($existing)) {
                $merged[] = $existing;
            }
        }

        SettingsStore::set(self::STORE_KEY, [
            'templates' => self::ensureBuiltinTemplates($merged),
            'department_modes' => $all['department_modes'],
        ]);
    }

    /**
     * Speichert global templates from post.
     * @param array $input
     * @return void
     */
    public static function saveGlobalTemplatesFromPost(array $input): void
    {
        self::saveAllTemplatesFromPost($input);
    }

    /** Abteilungs-Zuordnung und abteilungsspezifische Vorlagen (Einstellungen → Abteilungen). */
    /**
     * Speichert department notification from post.
     * @param array $input
     * @return void
     */
    public static function saveDepartmentNotificationFromPost(array $input): void
    {
        $all = self::all();

        SettingsStore::set(self::STORE_KEY, [
            'templates' => $all['templates'],
            'department_modes' => self::parseDepartmentModesFromPost($input, $all['department_modes']),
        ]);
    }

    /**
     * Methode filter templates.
     * @param array $templates
     * @param callable $predicate
     * @return array<string, mixed>
     */
    private static function filterTemplates(array $templates, callable $predicate): array
    {
        $out = [];
        foreach ($templates as $template) {
            if ($predicate($template)) {
                $out[] = $template;
            }
        }

        return $out;
    }

    /**
     * Führt aus: parse templates from post.
     * @param array $input
     * @param callable $predicate
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function parseTemplatesFromPost(array $input, callable $predicate): array
    {
        $templatesRaw = $input['notification_templates'] ?? [];
        $deleted = $input['notification_delete'] ?? [];

        if (!is_array($templatesRaw)) {
            throw new InvalidArgumentException('Ungültige Vorlagendaten.');
        }

        $deleteIds = [];
        if (is_array($deleted)) {
            foreach ($deleted as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $deleteIds[trim($id)] = true;
                }
            }
        }

        $templates = [];
        foreach ($templatesRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $template = self::sanitizeTemplateRow($row);
            if ($template === null || isset($deleteIds[$template['id']]) || !$predicate($template)) {
                continue;
            }
            $templates[] = $template;
        }

        return $templates;
    }

    /**
     * Führt aus: parse department modes from post.
     * @param array $input Eingabedaten
     * @param array $fallback
     * @return array<string, array{mode: string, inherit_from: string}>
     */
    private static function parseDepartmentModesFromPost(array $input, array $fallback): array
    {
        $modesRaw = $input['notification_department_modes'] ?? [];
        if (!is_array($modesRaw)) {
            return $fallback;
        }

        $departmentModes = $fallback;
        foreach ($modesRaw as $deptId => $rowRaw) {
            if (!is_string($deptId) || !is_array($rowRaw) || !DepartmentRepository::exists(trim($deptId))) {
                continue;
            }
            $deptId = trim($deptId);
            $mode = self::normalizeMode((string) ($rowRaw['mode'] ?? self::MODE_STANDARD));
            $inheritFrom = trim((string) ($rowRaw['inherit_from'] ?? ''));
            if ($inheritFrom === $deptId) {
                $inheritFrom = '';
            }
            if ($mode !== self::MODE_INHERIT) {
                $inheritFrom = '';
            }
            if ($mode === self::MODE_INHERIT && $inheritFrom === '') {
                $mode = self::MODE_STANDARD;
            }
            $departmentModes[$deptId] = [
                'mode' => $mode,
                'inherit_from' => $inheritFrom,
            ];
        }

        return $departmentModes;
    }

    /**
     * Methode departments with own templates.
     * @param array $departmentModes
     * @param array $options
     * @return list<array{id: string, name: string}>
     */
    public static function departmentsWithOwnTemplates(array $departmentModes, array $options): array
    {
        $out = [];
        foreach ($options as $option) {
            $id = $option['id'];
            $mode = $departmentModes[$id]['mode'] ?? self::MODE_STANDARD;
            if ($mode === self::MODE_OWN) {
                $out[] = $option;
            }
        }

        return $out;
    }

    /**
     * Methode migrate legacy.
     * @param array $legacy
     * @return array<string, mixed>
     */
    private static function migrateLegacy(array $legacy): array
    {
        $templates = self::defaultBuiltinTemplates();
        $globals = is_array($legacy['globals'] ?? null) ? $legacy['globals'] : [];
        foreach ($templates as $index => $template) {
            if ($template['department_id'] !== '' || !$template['builtin']) {
                continue;
            }
            $slug = $template['event_slug'];
            if (!is_array($globals[$slug] ?? null)) {
                continue;
            }
            $templates[$index]['subject'] = trim((string) $globals[$slug]['subject']);
            $templates[$index]['title'] = trim((string) $globals[$slug]['title']);
            $templates[$index]['intro'] = trim((string) $globals[$slug]['intro']);
        }

        $builtinNames = [];
        foreach (self::defaultBuiltinTemplates() as $builtin) {
            $builtinNames[$builtin['event_slug']] = $builtin['name'];
        }

        $departmentModes = [];
        $departments = is_array($legacy['departments'] ?? null) ? $legacy['departments'] : [];
        foreach ($departments as $deptId => $deptRaw) {
            if (!is_string($deptId) || !is_array($deptRaw)) {
                continue;
            }
            $deptId = trim($deptId);
            if ($deptId === '') {
                continue;
            }
            $useOwn = !empty($deptRaw['use_own']);
            $inheritFrom = trim((string) ($deptRaw['inherit_from'] ?? ''));
            $departmentModes[$deptId] = [
                'mode' => $useOwn ? self::MODE_OWN : ($inheritFrom !== '' ? self::MODE_INHERIT : self::MODE_STANDARD),
                'inherit_from' => $useOwn ? '' : $inheritFrom,
            ];

            if ($useOwn && is_array($deptRaw['templates'] ?? null)) {
                foreach ($deptRaw['templates'] as $slug => $row) {
                    if (!is_string($slug) || !is_array($row)) {
                        continue;
                    }
                    $found = false;
                    foreach ($templates as $ti => $template) {
                        if ($template['department_id'] === $deptId && $template['event_slug'] === $slug) {
                            $templates[$ti]['subject'] = trim((string) ($row['subject'] ?? ''));
                            $templates[$ti]['title'] = trim((string) ($row['title'] ?? ''));
                            $templates[$ti]['intro'] = trim((string) ($row['intro'] ?? ''));
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && in_array($slug, [self::SLUG_CONFIRMATION, self::SLUG_CANCELLATION, self::SLUG_ADMIN], true)) {
                        $templates[] = [
                            'id' => $deptId . '-' . $slug,
                            'name' => $builtinNames[$slug] ?? $slug,
                            'category' => self::CATEGORY_DEPARTMENT,
                            'department_id' => $deptId,
                            'event_slug' => $slug,
                            'builtin' => true,
                            'subject' => trim((string) ($row['subject'] ?? '')),
                            'title' => trim((string) ($row['title'] ?? '')),
                            'intro' => trim((string) ($row['intro'] ?? '')),
                            'sort_order' => 0,
                        ];
                    }
                }
            }
        }

        return ['templates' => $templates, 'department_modes' => $departmentModes];
    }

    /**
     * Führt aus: sanitize store.
     * @param array $stored
     * @return array<string, mixed>
     */
    private static function sanitizeStore(array $stored): array
    {
        $templates = [];
        foreach (is_array($stored['templates'] ?? null) ? $stored['templates'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $template = self::sanitizeTemplateRow($row);
            if ($template !== null) {
                $templates[] = $template;
            }
        }
        $templates = self::ensureBuiltinTemplates($templates);

        $departmentModes = [];
        foreach (is_array($stored['department_modes'] ?? null) ? $stored['department_modes'] : [] as $deptId => $rowRaw) {
            if (!is_string($deptId) || !is_array($rowRaw)) {
                continue;
            }
            $deptId = trim($deptId);
            if ($deptId === '') {
                continue;
            }
            if (array_key_exists('mode', $rowRaw)) {
                $departmentModes[$deptId] = [
                    'mode' => self::normalizeMode((string) ($rowRaw['mode'] ?? self::MODE_STANDARD)),
                    'inherit_from' => trim((string) ($rowRaw['inherit_from'] ?? '')),
                ];
                continue;
            }
            $departmentTemplateMode = is_array($rowRaw[self::CATEGORY_DEPARTMENT] ?? null)
                ? $rowRaw[self::CATEGORY_DEPARTMENT]
                : (is_array($rowRaw[self::CATEGORY_COMMUNICATION] ?? null)
                    ? $rowRaw[self::CATEGORY_COMMUNICATION]
                    : []);
            $departmentModes[$deptId] = [
                'mode' => self::normalizeMode((string) ($departmentTemplateMode['mode'] ?? self::MODE_STANDARD)),
                'inherit_from' => trim((string) ($departmentTemplateMode['inherit_from'] ?? '')),
            ];
        }

        return ['templates' => $templates, 'department_modes' => $departmentModes];
    }

    /**
     * Methode ensure builtin templates.
     * @param array $templates
     * @return array<string, mixed>
     */
    private static function ensureBuiltinTemplates(array $templates): array
    {
        $result = [];
        $presentSlugs = [];

        foreach ($templates as $template) {
            if (trim((string) ($template['department_id'] ?? '')) !== '') {
                $result[] = $template;
                continue;
            }

            $slug = trim((string) ($template['event_slug'] ?? ''));
            if ($slug === '') {
                $slug = trim((string) ($template['id'] ?? ''));
            }

            if (self::isGlobalBuiltinSlug($slug)) {
                $merged = self::mergeWithBuiltinDefaults($template);
                $eventSlug = (string) $merged['event_slug'];
                if (!isset($presentSlugs[$eventSlug])) {
                    $result[] = $merged;
                    $presentSlugs[$eventSlug] = true;
                }
                continue;
            }

            $result[] = $template;
        }

        foreach (self::defaultBuiltinTemplates() as $builtin) {
            if (!isset($presentSlugs[$builtin['event_slug']])) {
                $result[] = $builtin;
            }
        }

        return $result;
    }

    /**
     * Führt aus: sanitize template row.
     * @param array $row
     * @return array|null
     */
    private static function sanitizeTemplateRow(array $row): ?array
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = 'custom-' . bin2hex(random_bytes(4));
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $category = self::normalizeCategory((string) ($row['category'] ?? self::CATEGORY_CALENDAR));
        $departmentId = trim((string) ($row['department_id'] ?? ''));
        if ($departmentId === self::CALENDAR_OWNER_ID) {
            $departmentId = '';
        }
        $builtin = !empty($row['builtin']);
        $eventSlug = trim((string) ($row['event_slug'] ?? ''));
        $builtinSlug = $eventSlug !== '' ? $eventSlug : $id;
        if (!$builtin && $departmentId === '' && self::isGlobalBuiltinSlug($builtinSlug)) {
            $builtin = true;
            $eventSlug = $builtinSlug;
        }
        if ($builtin && $eventSlug === '') {
            $eventSlug = $id;
        }

        $template = [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'department_id' => $departmentId,
            'event_slug' => $eventSlug,
            'builtin' => $builtin,
            'subject' => trim((string) ($row['subject'] ?? '')),
            'title' => trim((string) ($row['title'] ?? '')),
            'intro' => trim((string) ($row['intro'] ?? '')),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
        ];

        return self::mergeWithBuiltinDefaults($template);
    }

    /**
     * Methode default builtin by slug.
     * @param string $slug
     * @return array|null
     */
    private static function defaultBuiltinBySlug(string $slug): ?array
    {
        foreach (self::defaultBuiltinTemplates() as $builtin) {
            if ($builtin['event_slug'] === $slug || $builtin['id'] === $slug) {
                return $builtin;
            }
        }

        return null;
    }

    /**
     * Prüft: is global builtin slug.
     * @param string $slug
     * @return bool
     */
    private static function isGlobalBuiltinSlug(string $slug): bool
    {
        return $slug !== '' && self::defaultBuiltinBySlug($slug) !== null;
    }

    /**
     * Methode merge with builtin defaults.
     * @param array $template
     * @return array<string, mixed>
     */
    private static function mergeWithBuiltinDefaults(array $template): array
    {
        if (trim((string) ($template['department_id'] ?? '')) !== '') {
            return $template;
        }

        $slug = trim((string) ($template['event_slug'] ?? ''));
        if ($slug === '') {
            $slug = trim((string) ($template['id'] ?? ''));
        }

        $default = self::defaultBuiltinBySlug($slug);
        if ($default === null) {
            return $template;
        }

        return [
            'id' => (string) $default['id'],
            'name' => trim((string) ($template['name'] ?? '')) !== '' ? (string) $template['name'] : (string) $default['name'],
            'category' => self::CATEGORY_CALENDAR,
            'department_id' => '',
            'event_slug' => (string) $default['event_slug'],
            'builtin' => true,
            'subject' => trim((string) ($template['subject'] ?? '')) !== '' ? (string) $template['subject'] : (string) $default['subject'],
            'title' => trim((string) ($template['title'] ?? '')) !== '' ? (string) $template['title'] : (string) $default['title'],
            'intro' => trim((string) ($template['intro'] ?? '')) !== '' ? (string) $template['intro'] : (string) $default['intro'],
            'sort_order' => max(0, (int) ($template['sort_order'] ?? $default['sort_order'] ?? 0)),
        ];
    }

    /**
     * Liefert template.
     * @param array $templates
     * @param string $id
     * @param string $category
     * @param string $departmentId
     * @return array|null
     */
    private static function findTemplate(array $templates, string $id, string $category, string $departmentId): ?array
    {
        foreach ($templates as $template) {
            if ($template['id'] === $id && $template['category'] === $category && $template['department_id'] === $departmentId) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Liefert builtin.
     * @param array $templates
     * @param string $slug
     * @param string $category
     * @return array|null
     */
    private static function findBuiltin(array $templates, string $slug, string $category): ?array
    {
        foreach ($templates as $template) {
            if ($template['category'] !== $category || $template['department_id'] !== '') {
                continue;
            }
            if ($template['event_slug'] === $slug || $template['id'] === $slug) {
                return self::mergeWithBuiltinDefaults($template);
            }
        }

        return null;
    }

    /**
     * Führt aus: normalize category.
     * @param string $category
     * @return string
     */
    private static function normalizeCategory(string $category): string
    {
        if ($category === self::CATEGORY_COMMUNICATION || $category === self::CATEGORY_ACCOUNTING) {
            return self::CATEGORY_DEPARTMENT;
        }

        return isset(self::categories()[$category]) ? $category : self::CATEGORY_DEPARTMENT;
    }

    /**
     * Führt aus: normalize mode.
     * @param string $mode
     * @return string
     */
    private static function normalizeMode(string $mode): string
    {
        return in_array($mode, [self::MODE_STANDARD, self::MODE_OWN, self::MODE_INHERIT], true)
            ? $mode
            : self::MODE_STANDARD;
    }
}
