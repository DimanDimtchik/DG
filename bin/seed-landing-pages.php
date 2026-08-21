<?php
/**
 * Seed the 6 GanzSoft landing pages into dg_website_pages.
 * Run once on the target server: php bin/seed-landing-pages.php
 */
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    echo "ERROR: Datenbank nicht konfiguriert.\n";
    exit(1);
}

MigrationRunner::runPending();

$pdo = Database::pdo();

/**
 * Converts imported landing page text into normal UTF-8 content for the builder.
 *
 * - HTML entities become real characters
 * - <br> becomes line breaks
 * - remaining tags are removed so text blocks stay plain text
 *
 * @param mixed $value
 * @return mixed
 */
function normalizeSeedValue(mixed $value): mixed
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = normalizeSeedValue($item);
        }
        if (($value['type'] ?? null) === 'button' && isset($value['text']) && !isset($value['label'])) {
            $value['label'] = $value['text'];
            unset($value['text']);
        }
        return $value;
    }

    if (!is_string($value)) {
        return $value;
    }

    $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
    $value = strip_tags($value);
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$pages = [
    [
        'title'  => 'Startseite',
        'slug'   => 'startseite',
        'status' => 'published',
        'layout' => [
            'rows' => [
                [
                    'id' => 'row-hero',
                    'columns' => [[
                        'id' => 'col-hero',
                        'width' => 12,
                        'blocks' => [
                            ['id' => 'blk-h1', 'type' => 'heading', 'text' => 'Ihr Business. Eine Software.', 'level' => 'h1'],
                            ['id' => 'blk-sub', 'type' => 'text', 'text' => 'Terminkalender, Buchhaltung, Kundenmanagement und Ihr eigener Internetauftritt &ndash; alles in einem modernen System. Ohne Vorkenntnisse. Ohne Chaos.'],
                            ['id' => 'blk-cta1', 'type' => 'button', 'text' => 'Kostenlos testen', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                            ['id' => 'blk-cta2', 'type' => 'button', 'text' => 'Funktionen entdecken', 'url' => '#funktionen', 'style' => 'outline'],
                        ],
                    ]],
                ],
                [
                    'id' => 'row-features',
                    'columns' => [
                        [
                            'id' => 'col-f1', 'width' => 3,
                            'blocks' => [
                                ['id' => 'blk-f1h', 'type' => 'heading', 'text' => 'Terminkalender', 'level' => 'h3'],
                                ['id' => 'blk-f1t', 'type' => 'text', 'text' => 'Online-Buchung, automatische Erinnerungen, Mitarbeiter-Kalender. Ihre Kunden buchen selbst &ndash; Sie behalten den &Uuml;berblick.'],
                            ],
                        ],
                        [
                            'id' => 'col-f2', 'width' => 3,
                            'blocks' => [
                                ['id' => 'blk-f2h', 'type' => 'heading', 'text' => 'Buchhaltung', 'level' => 'h3'],
                                ['id' => 'blk-f2t', 'type' => 'text', 'text' => 'Belege erfassen, Kontenrahmen (SKR03/04), &Uuml;berweisungen, Jahresabschluss. Finanzamt-konforme Buchf&uuml;hrung ohne Steuerberater-Zwang.'],
                            ],
                        ],
                        [
                            'id' => 'col-f3', 'width' => 3,
                            'blocks' => [
                                ['id' => 'blk-f3h', 'type' => 'heading', 'text' => 'Kundenmanagement', 'level' => 'h3'],
                                ['id' => 'blk-f3t', 'type' => 'text', 'text' => 'Kunden, Lieferanten, Mitarbeiter &ndash; alle Kontakte an einem Ort. Mit Bankdaten, Kunden- und Lieferantennummern.'],
                            ],
                        ],
                        [
                            'id' => 'col-f4', 'width' => 3,
                            'blocks' => [
                                ['id' => 'blk-f4h', 'type' => 'heading', 'text' => 'Website-Builder', 'level' => 'h3'],
                                ['id' => 'blk-f4t', 'type' => 'text', 'text' => 'Erstellen Sie Ihren Internetauftritt direkt im CRM. Seiten, Men&uuml;, Design &ndash; ohne externe Tools oder Programmierkenntnisse.'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'row-usps',
                    'columns' => [
                        ['id' => 'col-u1', 'width' => 3, 'blocks' => [
                            ['id' => 'blk-u1h', 'type' => 'heading', 'text' => 'DSGVO-konform', 'level' => 'h4'],
                            ['id' => 'blk-u1t', 'type' => 'text', 'text' => 'Hosting in Deutschland. Cookie-Consent, Impressum und Datenschutzerkl&auml;rung automatisch generiert.'],
                        ]],
                        ['id' => 'col-u2', 'width' => 3, 'blocks' => [
                            ['id' => 'blk-u2h', 'type' => 'heading', 'text' => 'Keine Vorkenntnisse n&ouml;tig', 'level' => 'h4'],
                            ['id' => 'blk-u2t', 'type' => 'text', 'text' => 'Intuitiv bedienbar. Installations-Assistent f&uuml;hrt Sie Schritt f&uuml;r Schritt durch die Einrichtung.'],
                        ]],
                        ['id' => 'col-u3', 'width' => 3, 'blocks' => [
                            ['id' => 'blk-u3h', 'type' => 'heading', 'text' => 'Automatische Updates', 'level' => 'h4'],
                            ['id' => 'blk-u3t', 'type' => 'text', 'text' => 'Sicherheitsupdates und neue Funktionen werden automatisch eingespielt. Sie m&uuml;ssen nichts tun.'],
                        ]],
                        ['id' => 'col-u4', 'width' => 3, 'blocks' => [
                            ['id' => 'blk-u4h', 'type' => 'heading', 'text' => 'Alles inklusive', 'level' => 'h4'],
                            ['id' => 'blk-u4t', 'type' => 'text', 'text' => 'Domain, E-Mail, SSL-Zertifikat, Backup &ndash; alles im Paket. Keine versteckten Kosten.'],
                        ]],
                    ],
                ],
                [
                    'id' => 'row-cta',
                    'columns' => [[
                        'id' => 'col-cta', 'width' => 12,
                        'blocks' => [
                            ['id' => 'blk-ctah', 'type' => 'heading', 'text' => 'Bereit, Ihr Business zu vereinfachen?', 'level' => 'h2'],
                            ['id' => 'blk-ctat', 'type' => 'text', 'text' => 'Starten Sie jetzt &ndash; kostenlos und unverbindlich.'],
                            ['id' => 'blk-ctab', 'type' => 'button', 'text' => 'Jetzt bestellen', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                        ],
                    ]],
                ],
            ],
        ],
    ],

    [
        'title'  => 'Terminkalender',
        'slug'   => 'terminkalender',
        'status' => 'published',
        'layout' => [
            'rows' => [
                ['id' => 'row-tk-h', 'columns' => [['id' => 'col-tk-h', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-tkh1', 'type' => 'heading', 'text' => 'Terminkalender', 'level' => 'h1'],
                    ['id' => 'blk-tks', 'type' => 'text', 'text' => 'Ihre Kunden buchen online. Ihre Mitarbeiter sehen alles auf einen Blick. Automatische Erinnerungen erledigen den Rest.'],
                ]]]],
                ['id' => 'row-tk-d', 'columns' => [
                    ['id' => 'col-tk1', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-tk1h', 'type' => 'heading', 'text' => 'Online-Terminbuchung', 'level' => 'h3'],
                        ['id' => 'blk-tk1t', 'type' => 'text', 'text' => 'Kunden w&auml;hlen Dienstleistung, Mitarbeiter und Zeitfenster &ndash; direkt auf Ihrer Website. Rund um die Uhr, auch au&szlig;erhalb der &Ouml;ffnungszeiten.<br><br>&bull; Buchungsseite in Ihren Farben<br>&bull; Dienstleistungen mit Dauer und Preis<br>&bull; Verf&uuml;gbare Zeitfenster automatisch berechnet<br>&bull; Best&auml;tigungsmail an Kunden'],
                    ]],
                    ['id' => 'col-tk2', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-tk2h', 'type' => 'heading', 'text' => 'Mitarbeiter-Kalender', 'level' => 'h3'],
                        ['id' => 'blk-tk2t', 'type' => 'text', 'text' => 'Jeder Mitarbeiter hat seinen eigenen Kalender. Sie sehen auf einen Blick, wer wann frei ist oder gebucht wurde.<br><br>&bull; Tages-, Wochen- und Monatsansicht<br>&bull; Arbeitszeiten pro Mitarbeiter<br>&bull; Urlaub und Abwesenheiten<br>&bull; Drag &amp; Drop zum Verschieben'],
                    ]],
                ]],
                ['id' => 'row-tk-d2', 'columns' => [
                    ['id' => 'col-tk3', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-tk3h', 'type' => 'heading', 'text' => 'Automatische Erinnerungen', 'level' => 'h3'],
                        ['id' => 'blk-tk3t', 'type' => 'text', 'text' => 'Weniger No-Shows: Kunden werden automatisch per E-Mail an ihren Termin erinnert.<br><br>&bull; 24 Stunden oder individuell vorher<br>&bull; E-Mail-Vorlage anpassbar<br>&bull; Stornierungslink in der Erinnerung'],
                    ]],
                    ['id' => 'col-tk4', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-tk4h', 'type' => 'heading', 'text' => 'Artikelverkn&uuml;pfung', 'level' => 'h3'],
                        ['id' => 'blk-tk4t', 'type' => 'text', 'text' => 'Verkn&uuml;pfen Sie Termine direkt mit Ihrem Artikelkatalog. So sehen Sie Umsatz pro Dienstleistung und Mitarbeiter.<br><br>&bull; Artikel und Leistungen zuordnen<br>&bull; Preise automatisch berechnet<br>&bull; Umsatzauswertung pro Monat'],
                    ]],
                ]],
                ['id' => 'row-tk-cta', 'columns' => [['id' => 'col-tk-cta', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-tkctah', 'type' => 'heading', 'text' => 'Bereit f&uuml;r weniger Terminausf&auml;lle?', 'level' => 'h2'],
                    ['id' => 'blk-tkctat', 'type' => 'text', 'text' => 'Lassen Sie Ihre Kunden selbst buchen und automatisieren Sie Erinnerungen.'],
                    ['id' => 'blk-tkctab', 'type' => 'button', 'text' => 'Jetzt bestellen', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                ]]]],
            ],
        ],
    ],

    [
        'title'  => 'Buchhaltung',
        'slug'   => 'buchhaltung-info',
        'status' => 'published',
        'layout' => [
            'rows' => [
                ['id' => 'row-bh-h', 'columns' => [['id' => 'col-bh-h', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-bhh1', 'type' => 'heading', 'text' => 'Buchhaltung', 'level' => 'h1'],
                    ['id' => 'blk-bhs', 'type' => 'text', 'text' => 'Belege erfassen, &Uuml;berweisungen vorbereiten, Jahresabschluss erstellen &ndash; alles in einem System. Kein Excel, kein Papierchaos.'],
                ]]]],
                ['id' => 'row-bh-d', 'columns' => [
                    ['id' => 'col-bh1', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-bh1h', 'type' => 'heading', 'text' => 'Belegerfassung', 'level' => 'h3'],
                        ['id' => 'blk-bh1t', 'type' => 'text', 'text' => 'Eingangs- und Ausgangsrechnungen mit allen steuerrelevanten Feldern. Automatische Kontenzuordnung nach SKR03 oder SKR04.<br><br>&bull; Netto, Brutto, MwSt automatisch berechnet<br>&bull; Steuerschl&uuml;ssel (19%, 7%, 0%)<br>&bull; Kontakt-Verkn&uuml;pfung (Kunde/Lieferant)<br>&bull; Dateinh&auml;nge (PDF, Scan)'],
                    ]],
                    ['id' => 'col-bh2', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-bh2h', 'type' => 'heading', 'text' => 'Kontenrahmen', 'level' => 'h3'],
                        ['id' => 'blk-bh2t', 'type' => 'text', 'text' => 'Vollst&auml;ndiger SKR03 und SKR04 mit &uuml;ber 1.000 Konten. Jedes Konto mit Erkl&auml;rung und Buchungshinweisen.<br><br>&bull; SKR03 und SKR04 vorinstalliert<br>&bull; Ausf&uuml;hrliche Kontenhinweise<br>&bull; Suche nach Kontonummer oder Name<br>&bull; Kontensalden und Kontoausz&uuml;ge'],
                    ]],
                ]],
                ['id' => 'row-bh-d2', 'columns' => [
                    ['id' => 'col-bh3', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-bh3h', 'type' => 'heading', 'text' => '&Uuml;berweisungen', 'level' => 'h3'],
                        ['id' => 'blk-bh3t', 'type' => 'text', 'text' => 'Direkt aus dem Beleg eine &Uuml;berweisung vorbereiten. Mit QR-Code f&uuml;r die Banking-App oder als Foto-Vorlage f&uuml;r die Bank.<br><br>&bull; QR-Code (EPC) f&uuml;r Banking-Apps<br>&bull; Fotovorlage f&uuml;r Bankbesuch<br>&bull; Automatisch aus Beleg bef&uuml;llt<br>&bull; IBAN-Validierung'],
                    ]],
                    ['id' => 'col-bh4', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-bh4h', 'type' => 'heading', 'text' => 'Jahresabschluss', 'level' => 'h3'],
                        ['id' => 'blk-bh4t', 'type' => 'text', 'text' => 'Gesch&auml;ftsjahr abschlie&szlig;en, Salden vortragen und Bilanz/GuV vorbereiten &ndash; mit wenigen Klicks.<br><br>&bull; Automatische Saldovortragung<br>&bull; Gesch&auml;ftsjahr sperren<br>&bull; &Uuml;bersicht aller offenen Posten<br>&bull; Export f&uuml;r den Steuerberater'],
                    ]],
                ]],
                ['id' => 'row-bh-cta', 'columns' => [['id' => 'col-bh-cta', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-bhctah', 'type' => 'heading', 'text' => 'Schluss mit dem Belegchaos', 'level' => 'h2'],
                    ['id' => 'blk-bhctat', 'type' => 'text', 'text' => 'Alle Belege an einem Ort. Finanzamt-konforme Buchf&uuml;hrung ohne Vorkenntnisse.'],
                    ['id' => 'blk-bhctab', 'type' => 'button', 'text' => 'Jetzt bestellen', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                ]]]],
            ],
        ],
    ],

    [
        'title'  => 'Kundenmanagement',
        'slug'   => 'kontakte-info',
        'status' => 'published',
        'layout' => [
            'rows' => [
                ['id' => 'row-km-h', 'columns' => [['id' => 'col-km-h', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-kmh1', 'type' => 'heading', 'text' => 'Kundenmanagement', 'level' => 'h1'],
                    ['id' => 'blk-kms', 'type' => 'text', 'text' => 'Alle Kontakte an einem Ort: Kunden, Lieferanten, Mitarbeiter und Firmen. Mit allen relevanten Daten f&uuml;r Buchhaltung und Tagesgesch&auml;ft.'],
                ]]]],
                ['id' => 'row-km-d', 'columns' => [
                    ['id' => 'col-km1', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-km1h', 'type' => 'heading', 'text' => 'Zentrale Kontaktverwaltung', 'level' => 'h3'],
                        ['id' => 'blk-km1t', 'type' => 'text', 'text' => 'Ob Privat- oder Gesch&auml;ftskunde, Lieferant oder Mitarbeiter &ndash; jeder Kontakt hat ein vollst&auml;ndiges Profil.<br><br>&bull; Adresse, Telefon, E-Mail<br>&bull; Firmenname und Ansprechpartner<br>&bull; Freitext-Notizen<br>&bull; Schnellsuche &uuml;ber alle Felder'],
                    ]],
                    ['id' => 'col-km2', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-km2h', 'type' => 'heading', 'text' => 'Bankdaten &amp; Nummern', 'level' => 'h3'],
                        ['id' => 'blk-km2t', 'type' => 'text', 'text' => 'Bankverbindung, Kunden- und Lieferantennummern direkt am Kontakt. F&uuml;r &Uuml;berweisungen und Belege sofort verf&uuml;gbar.<br><br>&bull; IBAN und BIC mit Validierung<br>&bull; Kundennummer und Lieferantennummer<br>&bull; Bank-Autovervollst&auml;ndigung (BLZ-Suche)<br>&bull; Verkn&uuml;pfung mit Belegen'],
                    ]],
                ]],
                ['id' => 'row-km-d2', 'columns' => [
                    ['id' => 'col-km3', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-km3h', 'type' => 'heading', 'text' => 'Kontakttypen &amp; Rollen', 'level' => 'h3'],
                        ['id' => 'blk-km3t', 'type' => 'text', 'text' => 'Unterscheiden Sie zwischen Kunden, Lieferanten und Mitarbeitern. Jede Rolle hat eigene Felder und Berechtigungen.<br><br>&bull; Kunde, Lieferant, Mitarbeiter, Firma<br>&bull; Automatische Nummernvergabe<br>&bull; Rollenbezogene Ansichten<br>&bull; Mitarbeiter mit CRM-Zugang'],
                    ]],
                    ['id' => 'col-km4', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-km4h', 'type' => 'heading', 'text' => 'Verkn&uuml;pfungen', 'level' => 'h3'],
                        ['id' => 'blk-km4t', 'type' => 'text', 'text' => 'Kontakte sind mit Belegen, Terminen und &Uuml;berweisungen verkn&uuml;pft. Sie sehen die komplette Historie auf einen Blick.<br><br>&bull; Belege des Kontakts anzeigen<br>&bull; Termine des Kontakts<br>&bull; Offene &Uuml;berweisungen<br>&bull; Gesamtumsatz pro Kontakt'],
                    ]],
                ]],
                ['id' => 'row-km-cta', 'columns' => [['id' => 'col-km-cta', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-kmctah', 'type' => 'heading', 'text' => 'Ihre Kontakte verdienen Ordnung', 'level' => 'h2'],
                    ['id' => 'blk-kmctat', 'type' => 'text', 'text' => 'Kein Suchen mehr in Excel-Listen oder Karteik&auml;sten. Alles an einem Ort.'],
                    ['id' => 'blk-kmctab', 'type' => 'button', 'text' => 'Jetzt bestellen', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                ]]]],
            ],
        ],
    ],

    [
        'title'  => 'Website-Builder',
        'slug'   => 'website-builder-info',
        'status' => 'published',
        'layout' => [
            'rows' => [
                ['id' => 'row-wb-h', 'columns' => [['id' => 'col-wb-h', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-wbh1', 'type' => 'heading', 'text' => 'Website-Builder', 'level' => 'h1'],
                    ['id' => 'blk-wbs', 'type' => 'text', 'text' => 'Ihr Internetauftritt &ndash; direkt im CRM. Kein WordPress, kein externer Anbieter. Seiten bauen, Design anpassen, online gehen.'],
                ]]]],
                ['id' => 'row-wb-d', 'columns' => [
                    ['id' => 'col-wb1', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-wb1h', 'type' => 'heading', 'text' => '10 Block-Typen', 'level' => 'h3'],
                        ['id' => 'blk-wb1t', 'type' => 'text', 'text' => 'Bauen Sie Ihre Seiten aus fertigen Bausteinen zusammen &ndash; ohne eine Zeile Code.<br><br>&bull; &Uuml;berschriften, Texte, Bilder<br>&bull; Buttons und Links<br>&bull; YouTube/Vimeo-Videos<br>&bull; Kontaktformular<br>&bull; Bildergalerie<br>&bull; HTML f&uuml;r Profis'],
                    ]],
                    ['id' => 'col-wb2', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-wb2h', 'type' => 'heading', 'text' => 'Design &amp; Farben', 'level' => 'h3'],
                        ['id' => 'blk-wb2t', 'type' => 'text', 'text' => 'Passen Sie Farben an Ihr Corporate Design an. &Auml;nderungen sind sofort auf der gesamten Website sichtbar.<br><br>&bull; Prim&auml;rfarbe, Hintergrund, Text<br>&bull; Live-Vorschau beim &Auml;ndern<br>&bull; Responsive Design (Handy-optimiert)'],
                    ]],
                ]],
                ['id' => 'row-wb-d2', 'columns' => [
                    ['id' => 'col-wb3', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-wb3h', 'type' => 'heading', 'text' => 'SEO &amp; Recht', 'level' => 'h3'],
                        ['id' => 'blk-wb3t', 'type' => 'text', 'text' => 'Suchmaschinenoptimierung und rechtliche Pflichtseiten werden automatisch generiert.<br><br>&bull; robots.txt und sitemap.xml<br>&bull; Meta-Tags und Open Graph<br>&bull; JSON-LD Structured Data<br>&bull; Impressum und Datenschutz automatisch<br>&bull; Cookie-Consent-Banner inklusive'],
                    ]],
                    ['id' => 'col-wb4', 'width' => 6, 'blocks' => [
                        ['id' => 'blk-wb4h', 'type' => 'heading', 'text' => 'Alles integriert', 'level' => 'h3'],
                        ['id' => 'blk-wb4t', 'type' => 'text', 'text' => 'Ihre Website lebt im selben System wie Ihre Kontakte, Termine und Buchhaltung. Keine Dateninseln.<br><br>&bull; Kontaktformular &rarr; E-Mail direkt im CRM<br>&bull; Navigation und Men&uuml; zentral pflegen<br>&bull; Kopf- und Fu&szlig;zeile anpassbar<br>&bull; Eigene Skripte einbinden (Analytics etc.)'],
                    ]],
                ]],
                ['id' => 'row-wb-cta', 'columns' => [['id' => 'col-wb-cta', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-wbctah', 'type' => 'heading', 'text' => 'Ihre Website. Ihr System.', 'level' => 'h2'],
                    ['id' => 'blk-wbctat', 'type' => 'text', 'text' => 'Kein externes Tool, keine monatliche Website-Geb&uuml;hr. Alles in GanzSoft inklusive.'],
                    ['id' => 'blk-wbctab', 'type' => 'button', 'text' => 'Jetzt bestellen', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                ]]]],
            ],
        ],
    ],

    [
        'title'  => 'Preise',
        'slug'   => 'preise',
        'status' => 'published',
        'layout' => [
            'rows' => [
                ['id' => 'row-pr-h', 'columns' => [['id' => 'col-pr-h', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-prh1', 'type' => 'heading', 'text' => 'Transparente Preise', 'level' => 'h1'],
                    ['id' => 'blk-prs', 'type' => 'text', 'text' => 'Alles inklusive: Domain, E-Mail, SSL, Hosting, Backups, Updates und Support. Keine versteckten Kosten.'],
                ]]]],
                ['id' => 'row-pr-cards', 'columns' => [
                    ['id' => 'col-pr1', 'width' => 4, 'blocks' => [
                        ['id' => 'blk-pr1h', 'type' => 'heading', 'text' => 'Starter &ndash; 29&thinsp;&euro;/Monat', 'level' => 'h3'],
                        ['id' => 'blk-pr1t', 'type' => 'text', 'text' => 'F&uuml;r Einzelunternehmer und kleine Betriebe.<br><br>&bull; 1 Domain inklusive<br>&bull; 3 E-Mail-Postf&auml;cher<br>&bull; Terminkalender<br>&bull; Buchhaltung (SKR03/04)<br>&bull; Kontaktverwaltung<br>&bull; Website-Builder<br>&bull; Automatische Updates<br>&bull; T&auml;gliche Backups'],
                        ['id' => 'blk-pr1b', 'type' => 'button', 'text' => 'Jetzt starten', 'url' => 'https://shop.ganz-soft.de', 'style' => 'outline'],
                    ]],
                    ['id' => 'col-pr2', 'width' => 4, 'blocks' => [
                        ['id' => 'blk-pr2h', 'type' => 'heading', 'text' => 'Business &ndash; 49&thinsp;&euro;/Monat', 'level' => 'h3'],
                        ['id' => 'blk-pr2t', 'type' => 'text', 'text' => 'F&uuml;r wachsende Unternehmen.<br><br>&bull; Alles aus Starter<br>&bull; 1 Domain + 1 Zusatzdomain<br>&bull; 10 E-Mail-Postf&auml;cher<br>&bull; Bis zu 5 Benutzer<br>&bull; Mitarbeiter-Kalender<br>&bull; Vorrang-Support'],
                        ['id' => 'blk-pr2b', 'type' => 'button', 'text' => 'Jetzt starten', 'url' => 'https://shop.ganz-soft.de', 'style' => 'primary'],
                    ]],
                    ['id' => 'col-pr3', 'width' => 4, 'blocks' => [
                        ['id' => 'blk-pr3h', 'type' => 'heading', 'text' => 'Premium &ndash; 89&thinsp;&euro;/Monat', 'level' => 'h3'],
                        ['id' => 'blk-pr3t', 'type' => 'text', 'text' => 'F&uuml;r gr&ouml;&szlig;ere Teams.<br><br>&bull; Alles aus Business<br>&bull; Unbegrenzte Domains<br>&bull; Unbegrenzte E-Mail-Postf&auml;cher<br>&bull; Unbegrenzte Benutzer<br>&bull; Individuelle Anpassungen<br>&bull; Pers&ouml;nlicher Ansprechpartner'],
                        ['id' => 'blk-pr3b', 'type' => 'button', 'text' => 'Jetzt starten', 'url' => 'https://shop.ganz-soft.de', 'style' => 'outline'],
                    ]],
                ]],
                ['id' => 'row-pr-note', 'columns' => [['id' => 'col-pr-note', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-prnote', 'type' => 'text', 'text' => 'Alle Preise zzgl. 19% MwSt. Vertragslaufzeit: 12 Monate. Danach monatlich k&uuml;ndbar.<br>Individuelle Anforderungen? Schreiben Sie uns: <a href="mailto:info@ganz-soft.de">info@ganz-soft.de</a>'],
                ]]]],
                ['id' => 'row-pr-cta', 'columns' => [['id' => 'col-pr-cta', 'width' => 12, 'blocks' => [
                    ['id' => 'blk-prctah', 'type' => 'heading', 'text' => 'Noch Fragen?', 'level' => 'h2'],
                    ['id' => 'blk-prctat', 'type' => 'text', 'text' => 'Schreiben Sie uns oder rufen Sie an. Wir beraten Sie gerne &ndash; kostenlos und unverbindlich.'],
                    ['id' => 'blk-prctab', 'type' => 'button', 'text' => 'Kontakt aufnehmen', 'url' => 'mailto:info@ganz-soft.de', 'style' => 'primary'],
                ]]]],
            ],
        ],
    ],
];

$insert = $pdo->prepare(
    'INSERT INTO dg_website_pages (title, slug, status, layout_json, created_by)
     VALUES (:title, :slug, :status, :layout_json, NULL)
     ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), layout_json = VALUES(layout_json)'
);

foreach ($pages as $p) {
    $p = normalizeSeedValue($p);
    $json = json_encode($p['layout'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $insert->execute([
        'title'       => $p['title'],
        'slug'        => $p['slug'],
        'status'      => $p['status'],
        'layout_json' => $json,
    ]);
    echo "OK: {$p['title']} (/{$p['slug']})\n";
}

echo "\nAlle 6 Seiten angelegt.\n";
