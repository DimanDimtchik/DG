<?php
declare(strict_types=1);

/**
 * KR4c — eigene Steuer-Kurztexte für Kichel (keine Verlagskommentare).
 * Nur Orientierung + CRM-Pfad. Keine Rechtsberatung.
 *
 * @return list<array{
 *   id: string,
 *   keywords: list<string>,
 *   title: string,
 *   body: string,
 *   norm_label: string,
 *   href?: string,
 *   action_label?: string
 * }>
 */
return [
    [
        'id' => 'tax-skonto',
        'keywords' => ['skonto', 'skontoabzug', 'zahlungsziel skonto', 'frühzahler'],
        'title' => 'Skonto (Begriff)',
        'body' => 'Skonto ist ein Nachlass bei Zahlung innerhalb einer Frist — kein Aufschlag. '
            . 'Beträge rechnet das CRM in PHP; Kichel formuliert nur. Einstellen unter Zahlungsbedingungen. '
            . 'Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => 'Praxis / Vertragsgestaltung (kein eigener UStG-Paragraph „Skonto“)',
        'href' => '/app?page=einstellungen&tab=payment-terms',
        'action_label' => 'Zahlungsbedingungen öffnen',
    ],
    [
        'id' => 'tax-u19',
        'keywords' => [
            'kleinunternehmer', 'klein unternehmer', '§19', '§ 19', 'paragraph 19', '19 ustg',
            'ustg 19', 'kleinunternehmerregelung',
        ],
        'title' => 'Kleinunternehmer (§ 19 UStG)',
        'body' => 'Unter der Kleinunternehmerregelung wird in der Regel keine Umsatzsteuer ausgewiesen; '
            . 'auf Rechnungen gehört der passende Hinweis. Im CRM: Firmendaten → Steuerliche Sonderfälle '
            . '(Flag/Zeitraum) und gesetzliche Hinweis-Vorlagen an Belegen. Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => '§ 19 UStG',
        'href' => '/app?page=einstellungen&tab=firmendaten',
        'action_label' => 'Firmendaten öffnen',
    ],
    [
        'id' => 'tax-ist-soll',
        'keywords' => [
            'ist-versteuerung', 'istversteuerung', 'soll-versteuerung', 'sollversteuerung',
            'ist besteuerung', 'soll besteuerung', 'versteuerung', '§20', '§ 20', '20 ustg',
        ],
        'title' => 'Soll- / Ist-Versteuerung',
        'body' => 'Bei Soll-Versteuerung entsteht die USt typischerweise mit der Leistung/Rechnung, '
            . 'bei Ist-Versteuerung oft erst mit Zahlung (nach Gestattung). Im CRM unter Firmendaten '
            . '„Soll- / Ist-Versteuerung“ inkl. Nachweis bei Ist. Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => '§ 20 UStG (Gestattung Ist-Versteuerung)',
        'href' => '/app?page=einstellungen&tab=firmendaten',
        'action_label' => 'Firmendaten öffnen',
    ],
    [
        'id' => 'tax-u13b',
        'keywords' => [
            '13b', '§13b', '§ 13b', 'reverse charge', 'steuerschuldnerschaft',
            'leistungsempfänger', 'bauleistung ust',
        ],
        'title' => 'Steuerschuldnerschaft des Leistungsempfängers (§ 13b UStG)',
        'body' => 'In bestimmten Fällen schuldet der Leistungsempfänger die USt (Reverse Charge). '
            . 'Rechnungen brauchen den passenden Hinweis; im CRM gibt es Vorlagen und Beleg-Hinweise. '
            . 'Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => '§ 13b UStG',
        'href' => '/app?page=buchhaltung-belege',
        'action_label' => 'Belege öffnen',
    ],
    [
        'id' => 'tax-u14',
        'keywords' => [
            'rechnungspflicht', 'pflichtangaben', '§14', '§ 14', '14 ustg',
            'rechnungsangaben', 'was muss auf die rechnung',
        ],
        'title' => 'Pflichtangaben auf Rechnungen (§ 14 UStG)',
        'body' => 'Rechnungen brauchen u. a. vollständige Angaben zu Leistendem, Empfänger, Steuernummer/USt-ID, '
            . 'Leistungsbeschreibung, Entgelt und Steuer. Das CRM unterstützt Druck/PDF; fachlich prüfen. '
            . 'Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => '§ 14 UStG',
        'href' => '/app?page=buchhaltung-belege',
        'action_label' => 'Belege öffnen',
    ],
    [
        'id' => 'tax-belegkette',
        'keywords' => [
            'belegkette', 'gobd', 'nachvollziehbar', 'prüfung beleg', 'folgebeleg',
            'änderungsprotokoll beleg',
        ],
        'title' => 'Belegkette und Nachvollziehbarkeit',
        'body' => 'Angebot → Auftragsbestätigung → Lieferschein → Rechnung sollen nachvollziehbar verknüpft sein. '
            . 'Im CRM: Buchhaltung → Belege, Folgebelege und Status. Für die Betriebsprüfung zählt die Kette. '
            . 'Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => 'GoBD / AO (Ordnungsmäßigkeit der Buchführung)',
        'href' => '/app?page=buchhaltung-belege',
        'action_label' => 'Belege öffnen',
    ],
    [
        'id' => 'tax-vorsteuer',
        'keywords' => [
            'vorsteuer', 'vorsteuerabzug', 'eingangsrechnung', '§15', '§ 15', '15 ustg',
        ],
        'title' => 'Vorsteuer (Eingangsbelege)',
        'body' => 'Vorsteuer setzt u. a. eine ordnungsgemäße Rechnung und unternehmerische Verwendung voraus. '
            . 'Eingangsbelege pflegst du in der Belegkette/Buchhaltung des CRM. Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => '§ 15 UStG',
        'href' => '/app?page=buchhaltung-belege',
        'action_label' => 'Belege öffnen',
    ],
    [
        'id' => 'tax-ustva',
        'keywords' => ['ustva', 'umsatzsteuervoranmeldung', 'voranmeldung', 'elster ust'],
        'title' => 'Umsatzsteuer-Voranmeldung',
        'body' => 'Die UStVA fasst die Periode zusammen. Im CRM: Buchhaltung → UStVA (Export); '
            . 'Live-ELSTER erst nach Server-Umzug. Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => 'UStG / UStDV (Voranmeldung)',
        'href' => '/app?page=buchhaltung-ustva',
        'action_label' => 'UStVA öffnen',
    ],
    [
        'id' => 'tax-aufbewahrung',
        'keywords' => [
            'aufbewahrung', 'aufbewahrungsfrist', '10 jahre', 'archivierung belege', 'gobd archiv',
        ],
        'title' => 'Aufbewahrung von Belegen',
        'body' => 'Handels- und Steuerunterlagen sind über gesetzliche Fristen aufzubewahren und müssen lesbar bleiben. '
            . 'Das CRM speichert Belege in der Instanz — Backup und Zugriff sichern. Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => '§ 147 AO / GoBD',
        'href' => '/app?page=einstellungen&tab=backup',
        'action_label' => 'Backup öffnen',
    ],
    [
        'id' => 'tax-datev',
        'keywords' => ['datev', 'steuerberater export', 'extf', 'kanzlei export'],
        'title' => 'Steuerberater- / DATEV-Export',
        'body' => 'Für die Kanzlei exportierst du Buchungsdaten unter Einstellungen → Steuerkanzlei. '
            . 'Fachliche Kontierung bleibt eure/PHP-Logik. Orientierungshilfe, keine Rechtsberatung.',
        'norm_label' => 'Praxis / DATEV-Schnittstelle (kein UStG-Paragraph)',
        'href' => '/app?page=einstellungen&tab=steuerkanzlei',
        'action_label' => 'Steuerkanzlei öffnen',
    ],
];
