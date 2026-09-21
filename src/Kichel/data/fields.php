<?php
declare(strict_types=1);

/**
 * Schicht B: Feld-Metadaten pro CRM-Bereich (deklarativ, erweiterbar).
 *
 * @return list<array{
 *   area_id: string,
 *   area_label: string,
 *   href: string,
 *   intro?: string,
 *   fields: list<array{
 *     label: string,
 *     format?: string,
 *     hint: string,
 *     keywords: list<string>
 *   }>
 * }>
 */
return [
    [
        'area_id' => 'settings:firmendaten',
        'area_label' => 'Firmendaten',
        'href' => '/app?page=einstellungen&tab=firmendaten',
        'intro' => 'Stammdaten der Firma — erscheinen auf Rechnungen, in E-Mails und im Impressum.',
        'fields' => [
            [
                'label' => 'USt-ID',
                'format' => 'DE + 9 Ziffern (z. B. DE123456789)',
                'hint' => 'Umsatzsteuer-Identifikationsnummer für innergemeinschaftliche Lieferungen und Rechnungen.',
                'keywords' => ['ust-id', 'ustid', 'umsatzsteuer-id', 'vat', 'ust'],
            ],
            [
                'label' => 'Steuernummer',
                'format' => 'Bundeslandabhängig, oft mit Schrägstrichen',
                'hint' => 'Vom Finanzamt vergebene Steuernummer — nicht mit der USt-ID verwechseln.',
                'keywords' => ['steuernummer', 'steuer-nummer', 'finanzamt'],
            ],
            [
                'label' => 'Firmenname',
                'format' => 'Freitext',
                'hint' => 'Offizieller Name für Dokumente und Impressum.',
                'keywords' => ['firmenname', 'firma', 'unternehmensname'],
            ],
            [
                'label' => 'E-Mail',
                'format' => 'name@domain.de',
                'hint' => 'Standard-Absender und Kontakt-E-Mail der Firma.',
                'keywords' => ['firmen-email', 'firmenmail', 'kontakt-email'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:payment-terms',
        'area_label' => 'Zahlungsbedingungen & Mahnung',
        'href' => '/app?page=einstellungen&tab=payment-terms',
        'intro' => 'Skonto-Stufen, Zahlungsziele und Mahngebühren für Rechnungen.',
        'fields' => [
            [
                'label' => 'Skonto-Stufen',
                'format' => 'Prozent + Tage (z. B. 3 % bei Zahlung innerhalb 7 Tagen)',
                'hint' => 'Mehrere Stufen möglich — erscheinen auf Rechnungs-PDF und in Belegtexten.',
                'keywords' => ['skonto', 'zahlungsziel', 'skontostufe', 'zahlungsbedingung', 'mahnung', 'mahngebühr'],
            ],
        ],
    ],
    [
        'area_id' => 'bilder',
        'area_label' => 'Media',
        'href' => '/app?page=bilder',
        'intro' => 'Media-Bibliothek für Logos, Fotos und Grafiken.',
        'fields' => [
            [
                'label' => 'CRM-Logo',
                'format' => 'PNG/SVG/JPG, Bild unter Media öffnen → „Als CRM-Logo in Kopfzeile“',
                'hint' => 'Das Logo erscheint in der CRM-Kopfzeile; für E-Mails zusätzlich unter Benachrichtigungen aktivieren.',
                'keywords' => ['logo', 'firmenlogo', 'crm-logo', 'logodatei', 'logos'],
            ],
            [
                'label' => 'PNG',
                'format' => 'PNG — Rastergrafik mit Transparenz, gut für Logos',
                'hint' => 'PNG eignet sich für Logos und Grafiken mit transparentem Hintergrund. Für Fotos oft JPG; Vektorgrafiken als SVG.',
                'keywords' => ['png', 'png-datei', 'png-format', 'bildformat', 'dateiformat', 'transparenz'],
            ],
            [
                'label' => 'JPG / JPEG',
                'format' => 'JPEG/JPG — komprimierte Fotos, ohne Transparenz',
                'hint' => 'Für Fotos und große Bilder ohne Alpha-Kanal. Logos mit transparentem Hintergrund besser als PNG oder SVG.',
                'keywords' => ['jpg', 'jpeg', 'jpg-format'],
            ],
            [
                'label' => 'SVG',
                'format' => 'SVG — Vektorgrafik, skalierbar ohne Qualitätsverlust',
                'hint' => 'Ideal für Logos und Icons. Wird im Browser scharf dargestellt — unabhängig von der Anzeigegröße.',
                'keywords' => ['svg', 'vektor', 'vektorgrafik'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:benachrichtigungen',
        'area_label' => 'Benachrichtigungen / E-Mail-Layout',
        'href' => '/app?page=einstellungen&tab=benachrichtigungen',
        'intro' => 'Kopf- und Fußzeile für System-E-Mails inkl. Social-Media-Leiste.',
        'fields' => [
            [
                'label' => 'Social-Media-Links',
                'format' => 'Vollständige URL mit https://',
                'hint' => 'Facebook, Instagram, LinkedIn usw. — nur ausgefüllte Profile erscheinen im E-Mail-Fuß. Kein Posten ins Netzwerk.',
                'keywords' => ['facebook', 'instagram', 'linkedin', 'xing', 'tiktok', 'youtube', 'social', 'sozial', 'verlinken'],
            ],
            [
                'label' => 'Logo in E-Mail-Kopfzeile',
                'format' => 'Checkbox — Logo kommt aus der Media-Bibliothek',
                'hint' => 'Helles Logo auf transparentem Hintergrund für Dark Mode in Mail-Apps.',
                'keywords' => ['email-logo', 'mail-logo', 'kopfzeile'],
            ],
        ],
    ],
    [
        'area_id' => 'support-freigabe',
        'area_label' => 'Support-Freigabe',
        'href' => '/app?page=support-freigabe',
        'intro' => 'Zeitlich begrenzter CRM-Zugang für Ganz Soft — optional Bildschirm-Zuschauen.',
        'fields' => [
            [
                'label' => 'Freigabe-Dauer',
                'format' => 'Auswahl in Stunden (z. B. 4 h, 24 h)',
                'hint' => 'Nach Ablauf endet der Zugang automatisch; Sie können jederzeit manuell beenden.',
                'keywords' => ['support', 'supportfreigabe', 'support-freigabe', 'fernzugriff', 'fernwartung', 'bildschirm'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:schriften',
        'area_label' => 'Schriften',
        'href' => '/app?page=einstellungen&tab=schriften',
        'intro' => 'Schriftarten für CRM-Oberfläche und HTML-E-Mails.',
        'fields' => [
            [
                'label' => 'Schriftart CRM',
                'format' => 'Auswahl aus Webfonts / Systemschriften',
                'hint' => 'Gilt für die eingeloggte CRM-Oberfläche.',
                'keywords' => ['schriftart', 'schriftarten', 'schriften', 'font', 'fonts', 'typografie'],
            ],
        ],
    ],
    [
        'area_id' => 'settings:crm-darstellung',
        'area_label' => 'Software Design',
        'href' => '/app?page=einstellungen&tab=crm-darstellung',
        'intro' => 'Farben der CRM-Oberfläche: Menü, Text, Hintergrund und Buttons.',
        'fields' => [
            [
                'label' => 'Fließtext / Textfarbe',
                'format' => 'Hex-Farbe',
                'hint' => 'Haupttextfarbe im Inhaltsbereich unter Einstellungen → Software Design.',
                'keywords' => ['textfarbe', 'fließtext', 'fliesstext', 'text color', 'schriftfarbe'],
            ],
            [
                'label' => 'Markenfarbe',
                'format' => 'Hex-Farbe',
                'hint' => 'Logo-Akzent und Hervorhebungen in der Navigation.',
                'keywords' => ['markenfarbe', 'brand', 'akzentfarbe', 'branding'],
            ],
            [
                'label' => 'Primärfarbe (Buttons)',
                'format' => 'Hex-Farbe',
                'hint' => 'Farbe für primäre Buttons und Aktionen.',
                'keywords' => ['primärfarbe', 'primaerfarbe', 'buttonfarbe', 'button farbe', 'primary'],
            ],
            [
                'label' => 'CRM-Farben',
                'format' => 'Farbpalette / Hex-Werte',
                'hint' => 'Gesamte CRM-Farbwelt: Menü, Hintergrund, Rahmen, Buttons.',
                'keywords' => ['farbe', 'farben', 'color', 'colors', 'theme', 'design', 'hintergrundfarbe'],
            ],
        ],
    ],
    [
        'area_id' => 'buchhaltung-ustva',
        'area_label' => 'UStVA',
        'href' => '/app?page=buchhaltung-ustva',
        'intro' => 'Umsatzsteuer-Voranmeldung aus Belegen — Export als ELSTER-CSV zur manuellen Übermittlung.',
        'fields' => [
            [
                'label' => 'Umsatzsteuer-Voranmeldung',
                'format' => 'Zeitraum wählen → Kennziffern prüfen → CSV für ELSTER exportieren',
                'hint' => 'Die Voranmeldung fasst Umsatzsteuer aus gebuchten Belegen zusammen. Direkte ELSTER-Übermittlung kommt erst nach dem Server-Umzug.',
                'keywords' => [
                    'ustva',
                    'umsatzsteuervoranmeldung',
                    'umsatzsteuer-voranmeldung',
                    'umsatzsteuer voranmeldung',
                    'ust voranmeldung',
                    'voranmeldung',
                    'umsatzsteuer',
                ],
            ],
        ],
    ],
    [
        'area_id' => 'buchhaltung-kontenuebersicht',
        'area_label' => 'Kontenübersicht',
        'href' => '/app?page=buchhaltung-kontenuebersicht',
        'intro' => 'Salden und Kontoauszüge je Geschäftsjahr und Konto.',
        'fields' => [
            [
                'label' => 'Kontonummer',
                'format' => 'SKR-Kontonummer, z. B. 3200',
                'hint' => 'Konto in der Übersicht anklicken — alle Buchungen und Saldo erscheinen im Kontoauszug.',
                'keywords' => ['kontonummer', 'kontoauszug', 'kontenübersicht', 'kontenuebersicht', 'sachkonto'],
            ],
        ],
    ],
    [
        'area_id' => 'buchhaltung-beleg-form',
        'area_label' => 'Beleg erfassen',
        'href' => '/app?page=buchhaltung-belege',
        'intro' => 'Einnahmen, Ausgaben und Korrekturbelege — mit Steuerfeldern, Positionen und Kontenzuordnung.',
        'fields' => [
            [
                'label' => 'Belegart',
                'format' => 'Einnahmen · Einnahmenminderung · Ausgaben · Ausgabenminderung · Kundengutschrift',
                'hint' => 'Steuert Buchungslogik und Nummernkreis. Unter dem Feld erscheint eine Kurzerklärung zur gewählten Art.',
                'keywords' => ['belegart', 'beleg-art', 'voucher_type', 'belegtyp'],
            ],
            [
                'label' => 'Einnahmenminderung',
                'format' => 'Betrag mindert einen früheren Ertrag — Positionen können negativ sein',
                'hint' => 'Wähle diese Belegart bei Erlösschmälerung oder gewährtem Skonto vom Kunden. '
                    . 'Wichtige Felder: Belegdatum, Kontakt, Positionen (Netto/USt), optional Rechnungsnummer. '
                    . 'Bei negativem Gesamtbetrag verlangt das System Einnahmenminderung oder Kundengutschrift.',
                'keywords' => [
                    'einnahmenminderung',
                    'einnahmen-minderung',
                    'income_reduction',
                    'erloesschmaelerung',
                    'erloesschmälerung',
                    'ertrag mindern',
                ],
            ],
            [
                'label' => 'Kundengutschrift',
                'format' => 'Gutschrift an Kunden — mindert früheren Umsatz (Ausgangsbeleg)',
                'hint' => 'Für Korrekturen zu Ihrer Ausgangsrechnung an einen Kunden. '
                    . 'Nicht für Lieferantengutschriften — dafür „Ausgabenminderung“. '
                    . 'Felder wie bei Einnahmen: Kontakt, Positionen, Belegdatum; negative Beträge sind erlaubt.',
                'keywords' => [
                    'kundengutschrift',
                    'kunden-gutschrift',
                    'credit',
                    'gutschrift kunde',
                    'ausgangsgutschrift',
                ],
            ],
            [
                'label' => 'Belegpositionen',
                'format' => 'Beschreibung, Menge, Netto, USt-Satz — Summe bildet den Beleg',
                'hint' => 'Bei Einnahmenminderung und Kundengutschrift können einzelne Zeilen negative Beträge haben. '
                    . 'Kontenzuordnung je Position steuert die Buchung in der Kontenübersicht.',
                'keywords' => ['positionen', 'belegposition', 'position', 'zeilen', 'felder'],
            ],
        ],
    ],
    [
        'area_id' => 'website-seiten',
        'area_label' => 'Website-Seiten',
        'href' => '/app?page=website-seiten',
        'intro' => 'Öffentliche Seiten inkl. Pflichtseiten Impressum, Datenschutz, AGB, Widerruf.',
        'fields' => [
            [
                'label' => 'Pflichtseiten',
                'format' => 'Slug impressum, datenschutz, agb, widerruf',
                'hint' => 'Eigener Block „Pflichtseiten“ auf der Seiten-Übersicht — fehlende Seiten können direkt angelegt werden.',
                'keywords' => ['pflichtseite', 'pflichtseiten', 'impressum', 'datenschutz', 'agb', 'widerruf', 'rechtstext'],
            ],
        ],
    ],
];
