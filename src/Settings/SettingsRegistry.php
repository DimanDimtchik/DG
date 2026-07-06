<?php
declare(strict_types=1);

final class SettingsRegistry
{
    /**
     * Einheitliche CRM-Einstellungen — keine Plugin-Gliederung.
     *
     * @return array<string, array{label: string, tabs: array<string, array{label: string, lead: string, template: string}>}>
     */
    public static function navigation(): array
    {
        return [
            'grundlagen' => [
                'label' => 'Grundlagen',
                'tabs' => [
                    'firmendaten' => [
                        'label' => 'Firmendaten',
                        'lead' => 'Firma, Anschrift und Kontakt — Absender für E-Mails und Dokumente.',
                        'template' => 'company',
                    ],
                    'datenbank' => [
                        'label' => 'Datenbank',
                        'lead' => 'MariaDB-Verbindung und Datenbank-Tabellen.',
                        'template' => 'database',
                    ],
                    'email' => [
                        'label' => 'E-Mail / SMTP',
                        'lead' => 'SMTP-Server, Versand und Archiv ausgehender E-Mails.',
                        'template' => 'email',
                    ],
                    'schriften' => [
                        'label' => 'Schriften',
                        'lead' => 'Schriftarten für die CRM-Oberfläche und HTML-E-Mails.',
                        'template' => 'fonts',
                    ],
                    'crm-darstellung' => [
                        'label' => 'Software Design',
                        'lead' => 'Farben der CRM-Oberfläche: Menü, Seitenleiste, Hintergrund und Buttons.',
                        'template' => 'crm-appearance',
                    ],
                ],
            ],
            'organisation' => [
                'label' => 'Organisation',
                'tabs' => [
                    'allgemein' => [
                        'label' => 'Allgemein',
                        'lead' => 'Allgemeine CRM-Optionen und Standardverhalten.',
                        'template' => 'placeholder',
                    ],
                    'abteilungen' => [
                        'label' => 'Abteilungen',
                        'lead' => 'Abteilungen und Zuordnung von Mitarbeitern.',
                        'template' => 'departments',
                    ],
                    'nummernkreise' => [
                        'label' => 'Nummernkreise',
                        'lead' => 'Belege (Angebot, Rechnung, Schlussrechnung) und Stammdaten (Artikel, Leistung, Kunde, Lieferant).',
                        'template' => 'number-ranges',
                    ],
                ],
            ],
            'termine' => [
                'label' => 'Termine',
                'tabs' => [
                    'arbeitszeiten' => [
                        'label' => 'Arbeitszeiten',
                        'lead' => 'Öffnungs- und Buchungszeiten im Kalender.',
                        'template' => 'working-hours',
                    ],
                    'kalender-darstellung' => [
                        'label' => 'Kalender Design',
                        'lead' => 'Farben des öffentlichen Buchungskalenders (Shortcode und Einbindung).',
                        'template' => 'calendar-appearance',
                    ],
                    'kalender-team' => [
                        'label' => 'Team & Bereiche',
                        'lead' => 'Mitarbeiter und Buchungsbereiche für den Terminkalender.',
                        'template' => 'calendar-team',
                    ],
                    'kalender-einbindung' => [
                        'label' => 'Einbindung',
                        'lead' => 'Online-Terminbuchung für Kunden aktivieren und öffentliche Buchungsseite verwalten.',
                        'template' => 'calendar-embed',
                    ],
                ],
            ],
            'kommunikation' => [
                'label' => 'Kommunikation',
                'tabs' => [
                    'postfaecher' => [
                        'label' => 'Postfächer',
                        'lead' => 'Allgemeine Postfächer, Zugriffsrechte und Webhook-URLs für IMAP→Webhook-Dienste.',
                        'template' => 'postboxes',
                    ],
                    'benachrichtigungen' => [
                        'label' => 'Benachrichtigungen',
                        'lead' => 'Kopf- und Fußzeile, E-Mail-Vorlagen für Terminkalender und freie Abteilungsvorlagen. Zuordnung pro Abteilung unter Einstellungen → Abteilungen.',
                        'template' => 'notifications',
                    ],
                ],
            ],
            'buchhaltung' => [
                'label' => 'Buchhaltung',
                'tabs' => [
                    'chart-of-accounts' => [
                        'label' => 'Kontenrahmen',
                        'lead' => 'Standardkontenrahmen SKR03 oder SKR04 für die Buchhaltung.',
                        'template' => 'chart-of-accounts',
                    ],
                    'steuerkanzlei' => [
                        'label' => 'Steuerkanzlei',
                        'lead' => 'Steuerkanzlei als Firmen-Kontakt zuordnen und verknüpfte Ansprechpartner einsehen.',
                        'template' => 'steuerkanzlei',
                    ],
                ],
            ],
            'recht' => [
                'label' => 'Rechtliches',
                'tabs' => [
                    'agb' => [
                        'label' => 'AGB & Widerruf',
                        'lead' => 'AGB, Widerruf und rechtliche Textbausteine.',
                        'template' => 'placeholder',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array{label: string, lead: string, template: string, section: string, sectionLabel: string}> */
    public static function allTabs(): array
    {
        $flat = [];
        foreach (self::navigation() as $sectionId => $section) {
            foreach ($section['tabs'] as $tabId => $tab) {
                $flat[$tabId] = [
                    'label' => $tab['label'],
                    'lead' => $tab['lead'],
                    'template' => $tab['template'],
                    'section' => $sectionId,
                    'sectionLabel' => $section['label'],
                ];
            }
        }

        return $flat;
    }

    public static function canAccess(User $user): bool
    {
        return RoleResolver::isAdmin($user);
    }

    /**
     * @return array{tab: string, tabLabel: string, section: string, sectionLabel: string, lead: string, template: string}
     */
    public static function resolve(?string $tabId): array
    {
        $tabs = self::allTabs();
        if ($tabId !== null && isset($tabs[$tabId])) {
            $tab = $tabs[$tabId];

            return [
                'tab' => $tabId,
                'tabLabel' => $tab['label'],
                'section' => $tab['section'],
                'sectionLabel' => $tab['sectionLabel'],
                'lead' => $tab['lead'],
                'template' => $tab['template'],
            ];
        }

        $firstSection = array_key_first(self::navigation());
        $firstTab = array_key_first(self::navigation()[$firstSection]['tabs']);

        return self::resolve($firstTab);
    }

    public static function tabUrl(string $tabId): string
    {
        return '/app?page=einstellungen&tab=' . rawurlencode($tabId);
    }

    /** Aktiver Tab: bei POST zuerst aus Formularfeldern, sonst aus der URL. */
    public static function resolveActiveTab(): ?string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (isset($_POST['mail_action'])) {
                return 'email';
            }
            if (isset($_POST['mail_address_save'])) {
                return 'email';
            }
            if (isset($_POST['postbox_save'])) {
                return 'postfaecher';
            }
            if (isset($_POST['company_save'])) {
                return 'firmendaten';
            }
            if (isset($_POST['chart_of_accounts_save'])) {
                return 'chart-of-accounts';
            }
            if (isset($_POST['tax_advisor_save'])) {
                return 'steuerkanzlei';
            }
            if (isset($_POST['appearance_save'])) {
                return 'schriften';
            }
            if (isset($_POST['departments_save'])) {
                return 'abteilungen';
            }
            if (isset($_POST['number_ranges_save'])) {
                return 'nummernkreise';
            }
            if (isset($_POST['working_hours_save']) || isset($_POST['working_hours_delete'])) {
                return 'arbeitszeiten';
            }
            if (isset($_POST['articles_save']) || isset($_POST['articles_delete']) || isset($_POST['articles_import'])) {
                return 'artikel-leistungen';
            }
            if (isset($_POST['calendar_appearance_save'])) {
                return 'kalender-darstellung';
            }
            if (isset($_POST['calendar_embed_save'])) {
                return 'kalender-einbindung';
            }
            if (isset($_POST['notification_templates_save']) || isset($_POST['calendar_notifications_save'])) {
                return 'benachrichtigungen';
            }
            if (isset($_POST['db_action'])) {
                return 'datenbank';
            }
        }

        $tab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9-]/', '', (string) $_GET['tab']) : '';

        return $tab !== '' ? $tab : null;
    }

    public static function pageLead(string $tabId): string
    {
        $tabs = self::allTabs();

        return $tabs[$tabId]['lead'] ?? '';
    }

    /** Alte URLs ?group=…&tab=… auf einheitliche CRM-Tabs umleiten. */
    public static function resolveLegacyTab(?string $group, ?string $tab): ?string
    {
        if ($group === null || $tab === null || $group === '' || $tab === '') {
            return null;
        }

        $map = [
            'system:database' => 'datenbank',
            'system:email' => 'email',
            'kontakte:company' => 'firmendaten',
            'kontakte:general' => 'allgemein',
            'kontakte:departments' => 'abteilungen',
            'kontakte:accounting' => 'chart-of-accounts',
            'kontakte:tax_advisor' => 'steuerkanzlei',
            'kontakte:legal' => 'agb',
            'kontakte:numbering' => 'nummernkreise',
            'terminkalender:working-hours' => 'arbeitszeiten',
            'terminkalender:colors' => 'kalender-darstellung',
            'terminkalender:articles' => 'artikel-leistungen',
            'terminkalender:staff-employees' => 'kalender-team',
            'terminkalender:staff-areas' => 'kalender-team',
            'terminkalender:shortcodes' => 'kalender-einbindung',
            'terminkalender:qrcode' => 'kalender-einbindung',
            'terminkalender:calendar-email' => 'benachrichtigungen',
            'terminkalender:messaging' => 'benachrichtigungen',
            'terminkalender:my-company' => 'firmendaten',
        ];

        return $map[$group . ':' . $tab] ?? null;
    }
}
