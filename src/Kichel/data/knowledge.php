<?php
declare(strict_types=1);

/**
 * Kichel-Wissensbasis: Navigation, Fachbegriffe, CRM-Hinweise.
 *
 * @return list<array{
 *   id: string,
 *   keywords: list<string>,
 *   title: string,
 *   answer: string,
 *   href?: string,
 *   tags?: list<string>
 * }>
 */
return [
    [
        'id' => 'ust-id',
        'keywords' => ['ust-id', 'ustid', 'umsatzsteuer', 'umsatzsteuer-id', 'vat', 'steuernummer'],
        'title' => 'USt-ID und Steuernummer',
        'answer' => 'Die Umsatzsteuer-Identifikationsnummer (USt-ID) und die Steuernummer der Firma pflegen Sie unter Einstellungen → Firmendaten. Sie erscheinen auf Rechnungen und im Impressum.',
        'href' => '/app?page=einstellungen&tab=firmendaten',
        'tags' => ['steuer', 'firma'],
    ],
    [
        'id' => 'skonto',
        'keywords' => ['skonto', 'zahlungsziel', 'mahnung', 'mahngebühr', 'zahlungsbedingung'],
        'title' => 'Skonto und Mahnwesen',
        'answer' => 'Skonto-Stufen, Zahlungsziele und automatische Mahnungen konfigurieren Sie unter Einstellungen → Buchhaltung → Zahlungsbedingungen & Mahnung. Beim Beleg können Sie die Stufen pro Dokument nutzen.',
        'href' => '/app?page=einstellungen&tab=payment-terms',
        'tags' => ['buchhaltung', 'steuer'],
    ],
    [
        'id' => 'belegkette',
        'keywords' => ['belegkette', 'workflow', 'belegstatus', 'freigabe', 'goäd', 'gobd'],
        'title' => 'Belegkette und Workflow',
        'answer' => 'Das CRM unterstützt Belegketten (Angebot → Auftrag → Lieferschein → Rechnung) mit Status-Workflow. Details: docs/BUCHHALTUNG-BELEGKETTE.md. Belege finden Sie unter Buchhaltung → Belege.',
        'href' => '/app?page=buchhaltung-belege',
        'tags' => ['buchhaltung'],
    ],
    [
        'id' => 'imap',
        'keywords' => ['imap', 'postfach', 'postfächer', 'e-mail abruf', 'webhook', 'mail sync'],
        'title' => 'Postfächer und IMAP',
        'answer' => 'Postfächer, IMAP-Zugriff und Webhook-URLs für eingehende E-Mails richten Sie unter Einstellungen → Kommunikation → Postfächer ein. SMTP-Versand liegt unter Einstellungen → E-Mail / SMTP.',
        'href' => '/app?page=einstellungen&tab=postfaecher',
        'tags' => ['mail'],
    ],
    [
        'id' => 'smtp',
        'keywords' => ['smtp', 'e-mail versand', 'mail versand', 'mailserver'],
        'title' => 'SMTP / E-Mail-Versand',
        'answer' => 'SMTP-Server, Absender und Archiv ausgehender E-Mails: Einstellungen → E-Mail / SMTP. Testversand ist dort möglich.',
        'href' => '/app?page=einstellungen&tab=email',
        'tags' => ['mail'],
    ],
    [
        'id' => 'kontenrahmen',
        'keywords' => ['skr03', 'skr04', 'kontenrahmen', 'sachkonto', 'kontenplan'],
        'title' => 'Kontenrahmen SKR03/SKR04',
        'answer' => 'Der Standard-Kontenrahmen (SKR03 oder SKR04) wird unter Einstellungen → Buchhaltung → Kontenrahmen gewählt. Er steuert Vorschlagskonten bei Belegen und Exporte.',
        'href' => '/app?page=einstellungen&tab=chart-of-accounts',
        'tags' => ['buchhaltung'],
    ],
    [
        'id' => 'elster',
        'keywords' => ['elster', 'eric', 'ustva', 'steuererklärung', 'übermittlung'],
        'title' => 'ELSTER / ERiC',
        'answer' => 'ELSTER-Vorbereitung: Einstellungen → Buchhaltung → ELSTER / ERiC. Auf dem Kasserver ist Live-Übermittlung blockiert — bis zum Hetzner-Umzug CSV-Modus nutzen. Siehe docs/ELSTER-ERIC-TODO.md.',
        'href' => '/app?page=einstellungen&tab=elster',
        'tags' => ['steuer'],
    ],
    [
        'id' => 'datev',
        'keywords' => ['datev', 'extf', 'export steuerberater', 'steuerberater export'],
        'title' => 'DATEV / Steuerberater-Export',
        'answer' => 'DATEV-EXTF-Export und Steuerberater-Übergabe finden Sie in der Buchhaltung (Kontenübersicht / Export-Bereich). Die Steuerkanzlei verknüpfen Sie unter Einstellungen → Steuerkanzlei.',
        'href' => '/app?page=einstellungen&tab=steuerkanzlei',
        'tags' => ['buchhaltung', 'steuer'],
    ],
    [
        'id' => 'kontakt-bemerkung',
        'keywords' => ['bemerkung', 'contact_note', 'interne notiz', 'geräte-ip', 'geräte ip'],
        'title' => 'Interne Bemerkung am Kontakt',
        'answer' => 'Das Feld „Bemerkung (intern)“ am Kontakt speichert interne Hinweise (z. B. Geräte-IP). Es ist in der Suche enthalten, erscheint nicht öffentlich.',
        'href' => '/app?page=kontakte',
        'tags' => ['kontakte'],
    ],
    [
        'id' => 'rechtstexte',
        'keywords' => ['mehrprodukt', 'produkt tabs', 'rechtstext varianten'],
        'title' => 'Rechtstexte / Mehrprodukt',
        'answer' => 'Mehrprodukt-Tabs für Rechtstexte: Einstellungen → Rechtliches / Produkte aktivieren. Öffentlich z. B. /datenschutz?produkt=schluessel. Für Links zu allen Pflichtseiten fragen Sie: „Wo finde ich Pflichtseiten?“',
        'href' => '/app?page=einstellungen&tab=agb',
        'tags' => ['website', 'recht'],
    ],
    [
        'id' => 'zeiterfassung',
        'keywords' => ['zeiterfassung', 'stempeluhr', 'arbzg', 'überstunden', 'pause'],
        'title' => 'Zeiterfassung & ArbZG',
        'answer' => 'Stempeluhr unter Zeiterfassung im Menü. Pausenregeln nach Arbeitszeitgesetz: Einstellungen → Termine → Zeiterfassung. Überstunden: docs/ZEITERFASSUNG-PLAN.md.',
        'href' => '/app?page=zeiterfassung',
        'tags' => ['personal'],
    ],
    [
        'id' => 'bank-geister',
        'keywords' => ['geisterumsatz', 'bankabgleich', 'bank', 'fingerprint', 'doppelbuchung'],
        'title' => 'Bank Geisterumsätze',
        'answer' => 'Doppelte oder erkannte Geisterumsätze beim Bankabgleich können manuell ausgeblendet werden (Migration 064, BankGhostDetectionService).',
        'href' => '/app?page=buchhaltung-bank',
        'tags' => ['buchhaltung'],
    ],
    [
        'id' => 'nummernkreis',
        'keywords' => ['nummernkreis', 'rechnungsnummer', 'angebotsnummer', 'belegnummer'],
        'title' => 'Nummernkreise',
        'answer' => 'Nummernkreise für Belege und Stammdaten: Einstellungen → Organisation → Nummernkreise.',
        'href' => '/app?page=einstellungen&tab=nummernkreise',
        'tags' => ['organisation'],
    ],
    [
        'id' => 'website-wartung',
        'keywords' => ['wartungsmodus', 'website offline', '503'],
        'title' => 'Website-Wartungsmodus',
        'answer' => 'Der Wartungsmodus zeigt Besuchern HTTP 503; /login bleibt erreichbar. Kontakt-Link kommt aus CRM (Wartungs-E-Mail oder Firmen-E-Mail). Standard nach Install: Wartung an.',
        'href' => '/app?page=website-chrome',
        'tags' => ['website'],
    ],
];
