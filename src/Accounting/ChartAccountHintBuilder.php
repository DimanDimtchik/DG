<?php
declare(strict_types=1);

/** Erzeugt ausführliche DG-Hinweise für SKR-Konten ohne manuelles Kuratieren. */
final class ChartAccountHintBuilder
{
  private const GENERATOR_VERSION = '2026-07-06-v7';

  /**
   * Liefert die Version des Hinweis-Generators
   * @return string
   */
  public static function generatorVersion(): string
  {
    return self::GENERATOR_VERSION;
  }

    /**
   * Prüft, ob Kontenhinweise nachgeneriert werden sollen
   * @param array $hints Kontenhinweise
   * @return bool
   */
  public static function needsEnhancement(array $hints): bool
  {
    if (($hints['dg_hint_level'] ?? '') === 'manual') {
      return false;
    }

    if (($hints['dg_hint_version'] ?? '') !== self::GENERATOR_VERSION) {
      return true;
    }

    $explanations = $hints['digit_explanations'] ?? null;
    if (is_array($explanations) && count($explanations) >= 2) {
      return false;
    }

    return true;
  }

    /**
   * Erzeugt DG-Hinweise für ein SKR-Konto
   * @param string $skrType Kontenrahmen (skr03/skr04)
   * @param string $accountNumber Kontonummer
   * @param string $name Name
   * @param string $section Kontenabschnitt
   * @param array $existing Bestehende Hinweisdaten
   * @return array<string, mixed>
   */
  public static function build(
    string $skrType,
    string $accountNumber,
    string $name,
    string $section,
    array $existing = []
  ): array {
    $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType);
    $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
    $name = trim($name);
    $section = in_array($section, ChartAccountRepository::sections(), true) ? $section : self::sectionFromNumber($skrType, $number);

    $category = self::detectCategory($skrType, $number, $name, $section);
    $features = self::detectFeatures($name, $category);
    $classification = is_array($existing['classification'] ?? null)
      ? $existing['classification']
      : self::classificationForSection($section);

    $summary = trim((string) ($existing['summary'] ?? ''));
    if ($summary === '' || ($existing['catalog_source'] ?? '') !== '') {
      $summary = self::buildSummary($name, $section, $category);
    }

    $generatedTerms = self::searchTerms($name, $category, $features, $skrType, $number);
    $searchTerms = $generatedTerms;
    if (($existing['search_terms_edited'] ?? false) === true && is_array($existing['search_terms'] ?? null)) {
      $searchTerms = ChartAccountHintTerms::normalizeList($existing['search_terms']);
    }

    $hints = [
      'summary' => $summary,
      'digit_explanations' => self::digitExplanations($skrType, $number, $name, $category),
      'features' => $features,
      'search_terms' => $searchTerms,
      'search_terms_edited' => ($existing['search_terms_edited'] ?? false) === true,
      'examples' => self::examplesFor($category, $name, $features),
      'edge_cases' => self::edgeCasesFor($category, $features),
      'dependencies' => self::dependenciesFor($category, $section),
      'classification' => $classification,
      'tax_effects' => self::taxEffectsFor($section, $category, $features),
      'dg_hint_level' => 'generated',
      'dg_hint_version' => self::GENERATOR_VERSION,
      'category' => $category,
      'gruppe' => $existing['gruppe'] ?? null,
      'catalog_source' => $existing['catalog_source'] ?? 'datev-pdf',
    ];

    return self::applyBookingGuidance($number, $hints);
  }

    /**
   * applyBookingGuidance
   * @param string $accountNumber Kontonummer
   * @param array $hints Kontenhinweise
   * @return array<string, mixed>
   */
  private static function applyBookingGuidance(string $accountNumber, array $hints): array
  {
    if (in_array($accountNumber, ['4832', '4842', '4852', '6222'], true)) {
      $hints['booking_mode'] = 'jahresabschluss';
      $hints['edge_cases'] = array_values(array_unique(array_merge(
        is_array($hints['edge_cases'] ?? null) ? $hints['edge_cases'] : [],
        [
          'Nicht für Tanken, Reparatur oder Versicherungsrechnungen — nur planmäßige AfA (Jahresabschluss).',
          'Typischer Buchungssatz: Soll 4832 Abschreibungen auf Fahrzeuge / Haben 0320 PKW.',
        ]
      )));
    } elseif (in_array($accountNumber, ['0320', '0520'], true)) {
      $hints['booking_mode'] = 'anschaffung';
      $hints['examples'] = array_merge(
        is_array($hints['examples'] ?? null) ? $hints['examples'] : [],
        ['Kauf Firmenwagen (Netto + VSt auf 1576)', 'Anzahlung beim Händler']
      );
    } elseif (str_starts_with($accountNumber, '45') || in_array($accountNumber, ['4530', '6430', '6530'], true)) {
      $hints['booking_mode'] = 'laufende_kosten';
    }

    return $hints;
  }

  /**
   * buildSummary
   * @param string $name Name
   * @param string $section Kontenabschnitt
   * @param string $category Kontenkategorie
   * @return string
   */
  private static function buildSummary(string $name, string $section, string $category): string
  {
    $sectionLabel = ChartAccountRepository::sectionLabel($section);
    $categoryLabel = self::categoryLabel($category);

    if ($categoryLabel !== '') {
      return $name . ' — ' . $categoryLabel . ' (' . $sectionLabel . ').';
    }

    return $name . ' (' . $sectionLabel . ').';
  }

    /**
   * digitExplanations
   * @param string $skrType Kontenrahmen (skr03/skr04)
   * @param string $number
   * @param string $name Name
   * @param string $category Kontenkategorie
   * @return array<int, string>
   */
  private static function digitExplanations(string $skrType, string $number, string $name, string $category): array
  {
    $legend = SkrDigitLegend::forSkr($skrType);
    $first = (int) substr($number, 0, 1);
    $prefix2 = substr($number, 0, 2);
    $prefix3 = substr($number, 0, 3);
    $groupMap = $skrType === 'skr04' ? self::skr04Groups() : self::skr03Groups();

    $groupLabel = $groupMap[$prefix2] ?? $groupMap[$prefix3] ?? self::categoryLabel($category);

    return [
      1 => $legend[$first] ?? 'Kontenklasse ' . $first,
      2 => 'Kontengruppe ' . $prefix2 . ($groupLabel !== '' ? ' — ' . $groupLabel : ''),
      3 => 'Untergruppe ' . $prefix3,
      4 => 'Einzelkonto ' . $number . ' — ' . $name,
    ];
  }

    /**
   * detectFeatures
   * @param string $name Name
   * @param string $category Kontenkategorie
   * @return list<string>
   */
  private static function detectFeatures(string $name, string $category): array
  {
    $lower = mb_strtolower($name);
    $features = [];

    $rules = [
      'vorsteuer' => ['vorsteuer', 'vst'],
      'umsatzsteuer' => ['umsatzsteuer', 'ust ', ' umsatzsteuer'],
      'ust_19' => ['19 %', '19%'],
      'ust_7' => ['7 %', '7%'],
      'keine_ust' => ['ohne ust', 'steuerfrei', 'nicht steuerbar'],
      'fahrzeug' => ['kfz', 'fahrzeug', 'pkw', 'lkw', 'fuhrpark', 'kraftfahr'],
      'personal' => ['lohn', 'gehalt', 'sozial', 'personal', 'arbeitnehmer'],
      'wareneinkauf' => ['waren', 'wareneingang', 'material', 'rohstoff', 'hilfsstoff', 'betriebsstoff'],
      'debitoren' => ['forderung', 'debitoren', 'kunden'],
      'kreditoren' => ['verbindlichkeit', 'kreditoren', 'lieferant'],
      'abschreibung' => ['abschreib', 'afa'],
      'anlagevermoegen' => ['anlage', 'sachanlage', 'immateriell', 'konzession', 'geschäfts- oder firmenwert'],
      'bargeld' => ['kasse', 'bargeld'],
      'bank' => ['bank', 'giro', 'paypal', 'kreditkarte'],
      'eigenkapital' => ['kapital', 'rücklage', 'gewinn', 'verlustvortrag', 'eigenkapital'],
      'erloes' => ['erlös', 'ertrag', 'umsatz'],
      'leasing' => ['leasing', 'miete'],
      'versicherung' => ['versicherung'],
      'zinsen' => ['zins', 'disagio', 'agio'],
      'bewirtung' => ['bewirtung', 'verpflegung', 'gastronomie'],
      'reisekosten' => ['reise', 'fahrtkosten', 'übernacht', 'kilometer'],
    ];

    foreach ($rules as $feature => $needles) {
      foreach ($needles as $needle) {
        if (str_contains($lower, $needle)) {
          $features[] = $feature;
          break;
        }
      }
    }

    if ($features === []) {
      $features[] = match ($category) {
        'erloes' => 'umsatz',
        'material' => 'material',
        'personal' => 'personal',
        'finanz' => 'finanzkonto',
        'anlage' => 'anlagevermoegen',
        default => 'standard',
      };
    }

    return array_values(array_unique($features));
  }

  /**
   * detectCategory
   * @param string $skrType Kontenrahmen (skr03/skr04)
   * @param string $number
   * @param string $name Name
   * @param string $section Kontenabschnitt
   * @return string
   */
  private static function detectCategory(string $skrType, string $number, string $name, string $section): string
  {
    $lower = mb_strtolower($name);
    $prefix2 = substr($number, 0, 2);
    $prefix3 = substr($number, 0, 3);
    $groups = $skrType === 'skr04' ? self::skr04Groups() : self::skr03Groups();

    foreach ([$prefix3, $prefix2] as $prefix) {
      if (isset($groups[$prefix])) {
        $group = mb_strtolower($groups[$prefix]);
        if (str_contains($group, 'erlös') || str_contains($group, 'ertrag')) {
          return 'erloes';
        }
        if (str_contains($group, 'vorsteuer')) {
          return 'vorsteuer';
        }
        if (str_contains($group, 'umsatzsteuer')) {
          return 'umsatzsteuer';
        }
        if (str_contains($group, 'forderung')) {
          return 'debitoren';
        }
        if (str_contains($group, 'verbindlich')) {
          return 'kreditoren';
        }
        if (str_contains($group, 'personal') || str_contains($group, 'lohn')) {
          return 'personal';
        }
        if (str_contains($group, 'material') || str_contains($group, 'waren')) {
          return 'material';
        }
        if (str_contains($group, 'abschreib')) {
          return 'abschreibung';
        }
        if (str_contains($group, 'anlage') || str_contains($group, 'sachanlage')) {
          return 'anlage';
        }
        if (str_contains($group, 'eigenkapital') || str_contains($group, 'kapital')) {
          return 'eigenkapital';
        }
      }
    }

    if (str_contains($lower, 'vorsteuer')) {
      return 'vorsteuer';
    }
    if (str_contains($lower, 'umsatzsteuer') || str_contains($lower, ' ust')) {
      return 'umsatzsteuer';
    }
    if (str_contains($lower, 'forderung')) {
      return 'debitoren';
    }
    if (str_contains($lower, 'verbindlich')) {
      return 'kreditoren';
    }
    if (str_contains($lower, 'erlös') || str_contains($lower, 'ertrag')) {
      return 'erloes';
    }
    if (str_contains($lower, 'lohn') || str_contains($lower, 'gehalt') || str_contains($lower, 'sozial')) {
      return 'personal';
    }
    if (str_contains($lower, 'waren') || str_contains($lower, 'material')) {
      return 'material';
    }
    if (str_contains($lower, 'abschreib')) {
      return 'abschreibung';
    }
    if (str_contains($lower, 'kasse') || str_contains($lower, 'bank')) {
      return 'finanz';
    }

    return match ($section) {
      'ertrag' => 'erloes',
      'aufwand' => 'aufwand',
      'aktiva' => 'aktiva',
      'passiva' => 'passiva',
      default => 'standard',
    };
  }

    /**
   * searchTerms
   * @param string $name Name
   * @param string $category Kontenkategorie
   * @param array $features Erkannte Merkmale
   * @param string $skrType Kontenrahmen (skr03/skr04)
   * @param string $accountNumber Kontonummer
   * @return list<string>
   */
  private static function searchTerms(string $name, string $category, array $features, string $skrType, string $accountNumber): array
  {
    $terms = [];
    $words = preg_split('/[\s,\/\-–]+/u', mb_strtolower($name)) ?: [];
    foreach ($words as $word) {
      $word = trim($word);
      if (mb_strlen($word) >= 4 && !in_array($word, ['sowie', 'oder', 'und', 'für', 'aus', 'der', 'die', 'das'], true)) {
        $terms[] = $word;
      }
    }

    foreach ($features as $feature) {
      if ($feature !== 'standard') {
        $terms[] = str_replace('_', ' ', $feature);
      }
    }

    if ($category !== 'standard') {
      $terms[] = str_replace('_', ' ', $category);
    }

    $terms = array_merge($terms, ChartAccountSearchLexicon::synonymsForAccount($skrType, $accountNumber, $name));

    return array_values(array_unique(array_slice($terms, 0, 24)));
  }

    /**
   * examplesFor
   * @param string $category Kontenkategorie
   * @param string $name Name
   * @param array $features Erkannte Merkmale
   * @return list<string>
   */
  private static function examplesFor(string $category, string $name, array $features): array
  {
    $examples = match ($category) {
      'erloes' => ['Ausgangsrechnung an Kunden', 'Barverkauf mit Kassenbon', 'Gutschrift an Kunden'],
      'vorsteuer' => ['Eingangsrechnung mit ausgewiesener USt', 'Investition mit Vorsteuerabzug'],
      'umsatzsteuer' => ['Umsatzsteuer aus Ausgangsrechnungen', 'Zahlung an Finanzamt'],
      'debitoren' => ['Rechnungsstellung', 'Zahlungseingang vom Kunden', 'Mahngebühr'],
      'kreditoren' => ['Eingangsrechnung vom Lieferanten', 'Überweisung an Lieferant', 'Skonto in Anspruch nehmen'],
      'material' => ['Wareneinkauf', 'Liefereingang mit Lieferschein', 'Retoure an Lieferant'],
      'personal' => ['Monatslohnabrechnung', 'AG-Anteil Sozialversicherung', 'Sonderzahlung'],
      'fahrzeug' => ['Tanken Firmenwagen', 'Kfz-Versicherung', 'Werkstattrechnung'],
      'abschreibung' => ['Planmäßige AfA', 'GWG-Sofortabschreibung'],
      'anlage' => ['Anschaffung Wirtschaftsgut', 'Anzahlung auf Anlagegut'],
      'eigenkapital' => ['Einlage Gesellschafter', 'Jahresgewinn-Verwendung'],
      'finanz' => ['Kontoauszug buchen', 'Bankgebühren', 'Bareinzahlung'],
      default => ['Typische Geschäftsbuchung auf dieses Konto', 'Beleg mit klarer Kontenzuordnung'],
    };

    if (in_array('bewirtung', $features, true)) {
      $examples = ['Geschäftsessen mit Partnern', 'Catering bei Veranstaltung'];
    }
    if (in_array('reisekosten', $features, true)) {
      $examples = ['Dienstreise Hotel', 'Kilometerpauschale', 'Verpflegungsmehraufwand'];
    }

    return array_slice($examples, 0, 4);
  }

    /**
   * edgeCasesFor
   * @param string $category Kontenkategorie
   * @param array $features Erkannte Merkmale
   * @return list<string>
   */
  private static function edgeCasesFor(string $category, array $features): array
  {
    $cases = match ($category) {
      'erloes' => ['Steuerfreie Umsätze gesondert prüfen', 'Reverse Charge beim Leistungsempfänger'],
      'vorsteuer' => ['Rechnung ohne USt-Ausweis — kein Vorsteuerabzug', 'Gemischt genutzte Wirtschaftsgüter'],
      'umsatzsteuer' => ['Kleinunternehmer — kein USt-Ausweis', 'Dauerfristverlängerung beachten'],
      'debitoren' => ['Forderungsausfall und Einzelwertberichtigung', 'Skonto als Erlösminderung'],
      'kreditoren' => ['Guthaben beim Lieferanten', 'Doppelzahlung erkennen'],
      'material' => ['Bestandsveränderung zum Stichtag', 'Innergemeinschaftlicher Erwerb'],
      'personal' => ['Minijob vs. sozialversicherungspflichtig', 'GF-Gehalt bei GmbH'],
      'fahrzeug' => ['1 %-Regelung / Privatanteil Firmenwagen', 'Leasing vs. Kauf'],
      'abschreibung' => ['Sonder-AfA und GWG-Grenze', 'Herabsetzung Nutzungsdauer'],
      'bewirtung' => ['70 % Vorsteuerabzug', 'Teilnehmerliste aufbewahren'],
      default => ['Steuerliche Behandlung im Einzelfall prüfen', 'Abgrenzung zu ähnlichen Konten beachten'],
    };

    if (in_array('ust_7', $features, true)) {
      $cases[] = 'Ermäßigter Steuersatz 7 % — Anwendungsvoraussetzungen prüfen';
    }

    return array_slice(array_values(array_unique($cases)), 0, 4);
  }

    /**
   * dependenciesFor
   * @param string $category Kontenkategorie
   * @param string $section Kontenabschnitt
   * @return list<string>
   */
  private static function dependenciesFor(string $category, string $section): array
  {
    return match ($category) {
      'erloes' => ['Ausgangsrechnungen', 'USt-Voranmeldung'],
      'vorsteuer', 'umsatzsteuer' => ['USt-Voranmeldung', 'Eingangs-/Ausgangsrechnungen'],
      'debitoren' => ['Debitorenliste', 'OP-Verwaltung'],
      'kreditoren' => ['Kreditorenliste', 'OP-Verwaltung'],
      'material' => ['Lagerbestand', 'Eingangsrechnungen'],
      'personal' => ['Lohnabrechnung', 'SV-Meldungen'],
      'fahrzeug' => ['Fuhrparkliste', 'Fahrtenbuch'],
      'abschreibung', 'anlage' => ['Anlagenbuchhaltung', 'Inventar'],
      'finanz' => ['Kontoauszug', 'Zahlungsabgleich'],
      default => match ($section) {
        'aktiva', 'passiva' => ['Bilanz', 'Jahresabschluss'],
        default => ['Belegprüfung', 'GuV / EÜR'],
      },
    };
  }

    /**
   * taxEffectsFor
   * @param string $section Kontenabschnitt
   * @param string $category Kontenkategorie
   * @param array $features Erkannte Merkmale
   * @return array<string, string>
   */
  private static function taxEffectsFor(string $section, string $category, array $features): array
  {
    if (in_array('vorsteuer', $features, true) || $category === 'vorsteuer') {
      return ['ust' => 'abzug', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'];
    }
    if (in_array('umsatzsteuer', $features, true) || $category === 'umsatzsteuer') {
      return ['ust' => 'schuld', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'];
    }
    if ($category === 'erloes' || $section === 'ertrag') {
      return ['ust' => 'schuld', 'gewst' => 'erhoehung', 'kst' => 'erhoehung', 'est' => 'neutral'];
    }
    if ($section === 'aufwand' || in_array('material', $features, true) || in_array('personal', $features, true)) {
      $ust = in_array('keine_ust', $features, true) ? 'neutral' : (in_array('vorsteuer', $features, true) ? 'vorsteuer' : 'teilweise');
      return ['ust' => $ust, 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'];
    }
    if (in_array('bewirtung', $features, true)) {
      return ['ust' => 'teilweise', 'gewst' => 'teilweise', 'kst' => 'teilweise', 'est' => 'neutral'];
    }

    return ['ust' => 'neutral', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'];
  }

    /**
   * classificationForSection
   * @param string $section Kontenabschnitt
   * @return array{balance_sheet: bool, guv: bool, eur: bool}
   */
  private static function classificationForSection(string $section): array
  {
    return match ($section) {
      'aktiva', 'passiva' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
      default => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
    };
  }

  /**
   * sectionFromNumber
   * @param string $skrType Kontenrahmen (skr03/skr04)
   * @param string $number
   * @return string
   */
  private static function sectionFromNumber(string $skrType, string $number): string
  {
    $first = (int) substr($number, 0, 1);
    if ($skrType === 'skr04') {
      return match ($first) {
        1 => 'aktiva',
        2 => 'passiva',
        3, 4, 5, 6 => 'aufwand',
        7, 8, 9 => 'ertrag',
        default => 'aufwand',
      };
    }

    return match ($first) {
      0, 1, 2 => 'aktiva',
      3 => 'passiva',
      4, 5, 6, 7 => 'aufwand',
      8, 9 => 'ertrag',
      default => 'aufwand',
    };
  }

  /**
   * categoryLabel
   * @param string $category Kontenkategorie
   * @return string
   */
  private static function categoryLabel(string $category): string
  {
    return match ($category) {
      'erloes' => 'Erlöskonto',
      'vorsteuer' => 'Vorsteuerkonto',
      'umsatzsteuer' => 'Umsatzsteuerkonto',
      'debitoren' => 'Debitorenkonto',
      'kreditoren' => 'Kreditorenkonto',
      'material' => 'Material- / Wareneingangskonto',
      'personal' => 'Personalkonto',
      'fahrzeug' => 'Fahrzeugkosten',
      'abschreibung' => 'Abschreibungskonto',
      'anlage' => 'Anlagevermögen',
      'eigenkapital' => 'Eigenkapital',
      'finanz' => 'Finanzkonto',
      'aufwand' => 'Aufwandskonto',
      'aktiva' => 'Aktivkonto',
      'passiva' => 'Passivkonto',
      default => '',
    };
  }

  /**
   * skr03Groups.
   *
   * @return array<string, string>
   */
    private static function skr03Groups(): array
  {
    return [
      '00' => 'Immaterielle Vermögensgegenstände / Konzessionen',
      '01' => 'Sachanlagen — Grundstücke und Bauten',
      '02' => 'Sachanlagen — Technische Anlagen und Maschinen',
      '03' => 'Sachanlagen — Andere Anlagen und Betriebsausstattung',
      '04' => 'Sachanlagen — Fuhrpark / Fahrzeuge',
      '05' => 'Sachanlagen — Werkzeuge und Betriebsausstattung',
      '06' => 'Finanzanlagen',
      '07' => 'Anlagen im Bau',
      '10' => 'Kasse',
      '11' => 'Bank und Giro',
      '12' => 'Bank (weitere Konten)',
      '13' => 'Wechsel, Schecks',
      '14' => 'Forderungen aus Lieferungen und Leistungen',
      '15' => 'Abziehbare Vorsteuer',
      '16' => 'Verbindlichkeiten aus Lieferungen und Leistungen',
      '17' => 'Umsatzsteuer',
      '18' => 'Gegenkonten / Verrechnung',
      '20' => 'Eigenkapital',
      '21' => 'Kapitalrücklagen',
      '23' => 'Gewinn-/Verlustvortrag',
      '30' => 'Materialaufwand / Rohstoffe',
      '31' => 'Hilfs- und Betriebsstoffe',
      '32' => 'Wareneingang',
      '33' => 'Wareneingang mit Vorsteuer',
      '34' => 'Bezugsnebenkosten',
      '40' => 'Personalaufwand — Löhne und Gehälter',
      '41' => 'Löhne',
      '42' => 'Sozialaufwand',
      '45' => 'Fahrzeugkosten',
      '46' => 'Werbe- und Reisekosten',
      '48' => 'Sonstige betriebliche Aufwendungen',
      '49' => 'Reisekosten / Bewirtung',
      '60' => 'Abschreibungen',
      '63' => 'Zinsen und ähnliche Erträge/Aufwendungen',
      '70' => 'Bestandsveränderungen',
      '80' => 'Erlöse',
      '83' => 'Erlöse ermäßigter Steuersatz',
      '84' => 'Erlöse Regelsteuersatz',
      '86' => 'Sonstige betriebliche Erträge',
      '90' => 'Vortragskonten / statistische Konten',
    ];
  }

  /**
   * skr04Groups.
   *
   * @return array<string, string>
   */
    private static function skr04Groups(): array
  {
    return [
      '00' => 'Immaterielle Vermögensgegenstände',
      '01' => 'Sachanlagen — Grundstücke',
      '02' => 'Sachanlagen — Technische Anlagen',
      '03' => 'Sachanlagen — Betriebsausstattung',
      '04' => 'Fuhrpark',
      '10' => 'Kasse und Bank',
      '14' => 'Forderungen',
      '15' => 'Vorsteuer',
      '16' => 'Verbindlichkeiten',
      '17' => 'Umsatzsteuer',
      '20' => 'Eigenkapital',
      '30' => 'Materialaufwand',
      '32' => 'Wareneingang',
      '40' => 'Personalaufwand',
      '45' => 'Fahrzeugkosten',
      '48' => 'Sonstige Aufwendungen',
      '60' => 'Abschreibungen',
      '80' => 'Erlöse',
      '86' => 'Sonstige Erträge',
    ];
  }
}
