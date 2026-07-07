<?php
declare(strict_types=1);

/**
 * Buchungsbegriffe → SKR-Konto: Was auf welches Konto gebucht wird (Lexoffice-Sprache).
 *
 * Nur explizite Zuordnungen — keine Substring-Suche im DATEV-Namen über „post“ o. Ä.
 */
final class ChartAccountSearchLexicon
{
  /**
   * SKR03: Kontonummer → Begriffe, die auf dieses Konto gebucht werden.
   *
   * @var array<string, list<string>>
   */
  private const SKR03_BOOKING = [
    '4910' => [
      'porto', 'briefmarke', 'briefmarken', 'frankierung', 'frankiermaschine', 'frankier',
      'luftpost', 'luftpostzuschlag', 'luftbrief', 'paketversand', 'paket', 'pakete',
      'versand', 'versandkosten', 'portokosten', 'postgebühr', 'postgebühren', 'postversand',
      'einschreiben', 'warenpost', 'postkarte', 'postkarten', 'zustellung', 'sendung',
      'dhl', 'dpd', 'hermes', 'ups', 'gls', 'courier', 'kurier', 'deutsche post',
    ],
    '4920' => [
      'telefon', 'telefonkosten', 'telefonrechnung', 'handy', 'mobilfunk', 'mobiltelefon',
      'festnetz', 'telekom', 'vodafone', 'o2', 'sim', 'gesprächskosten', 'fax',
    ],
    '4925' => [
      'internet', 'internetkosten', 'wlan', 'dsl', 'glasfaser', 'hosting', 'domain',
      'server', 'webseite', 'website', 'webhosting',
    ],
    '4930' => [
      'bürobedarf', 'bueroedarf', 'büromaterial', 'bueromaterial', 'papier', 'druckerpapier',
      'toner', 'drucker', 'schreibwaren', 'ordner', 'stifte', 'kugelschreiber', 'büro',
    ],
    '4600' => [
      'werbung', 'werbekosten', 'marketing', 'anzeige', 'anzeigen', 'google ads',
      'facebook ads', 'flyer', 'plakat', 'werbemittel', 'werbebanner',
    ],
    '0320' => ['pkw', 'firmenwagen', 'fahrzeug kaufen', 'auto kaufen', 'fuhrpark', 'anschaffung pkw', 'fahrzeug anschaffung'],
    '4500' => ['fahrzeugkosten', 'kfz-kosten'],
    '4510' => ['kfz-steuer', 'kfz steuer', 'kraftfahrzeugsteuer', 'auto steuer', 'fahrzeugsteuer'],
    '4520' => [
      'kfz-versicherung', 'kfz versicherung', 'autoversicherung', 'haftpflicht auto',
      'vollkasko', 'teilkasko', 'flottenversicherung', 'fahrzeugversicherung',
    ],
    '4530' => [
      'tanken', 'kraftstoff', 'benzin', 'diesel', 'tankstelle', 'tankfüllung',
      'laufende fahrzeugkosten', 'fahrzeugkosten', 'betriebskosten auto', 'wartung auto',
      'ölwechsel', 'reifen',
    ],
    '4540' => ['kfz-reparatur', 'fahrzeugreparatur', 'werkstatt', 'reparatur auto', 'bremsen', 'inspektion'],
    '4550' => ['garage', 'garagenmiete', 'stellplatz'],
    '4560' => ['maut', 'mautgebühr', 'vignette'],
    '4570' => ['kfz-leasing', 'leasing auto', 'fahrzeug leasing', 'leasingrate auto', 'fahrzeugleasing', 'mietleasing kfz'],
    '4580' => ['sonstige fahrzeugkosten', 'kfz sonstiges'],
    '4590' => ['privat pkw betrieblich', 'privatfahrzeug betrieblich', '1 prozent', '1%-regelung'],
    '4595' => ['fremdfahrzeug', 'fremdfahrzeugkosten', 'mietwagen', 'carsharing', 'fahrzeug miete'],
    '4650' => ['fremdfahrzeug', 'mietwagen', 'carsharing'],
    '3200' => ['wareneingang', 'wareneinkauf', 'handelsware', 'einkauf ware', 'material', 'waren'],
    '3300' => ['wareneingang 19', 'wareneinkauf 19'],
    '3400' => ['bezugsnebenkosten', 'fracht', 'spedition', 'zoll'],
    '8400' => ['erlös 19', 'erloes 19', 'umsatz 19', 'verkauf 19', 'ausgangsrechnung 19'],
    '8410' => ['erlös 19', 'erloes 19', 'umsatz 19', 'verkauf 19', 'ausgangsrechnung 19', 'einnahme 19'],
    '8334' => ['erlös 7', 'erloes 7', 'umsatz 7', 'verkauf 7', 'ausgangsrechnung 7', 'einnahme 7'],
    '8300' => ['erlös 7', 'erloes 7', 'umsatz 7', 'verkauf 7'],
    '8120' => ['erlös 0', 'erloes 0', 'umsatz 0', 'steuerfrei', 'einnahme 0', 'ohne ust'],
    '8110' => ['steuerfreie umsätze', 'steuerfreie erlöse', 'erlös steuerfrei', 'erloes steuerfrei'],
    '8192' => ['kleinunternehmer', '§19 ustg', 'steuerfreie erlöse', 'einnahme steuerfrei'],
    '4210' => ['miete', 'mietkosten', 'pacht', 'mietnebenkosten'],
    '4240' => ['nebenkosten', 'betriebskosten', 'heizung', 'strom', 'wasser', 'müll'],
    '4360' => ['versicherung', 'versicherungsbeitrag', 'betriebshaftpflicht', 'haftpflicht'],
    '4380' => ['berufsgenossenschaft', 'bg', 'unfallversicherung', 'uv'],
    '4950' => [
      'rechtsanwalt', 'anwalt', 'notar', 'steuerberater', 'kanzlei', 'beratung',
      'honorar anwalt', 'honorar steuerberater', 'lohnbüro', 'lohnbuero',
    ],
    '4970' => ['bankgebühr', 'bankgebühren', 'kontoführung', 'kontofuehrung', 'dispo'],
    '1200' => ['bank', 'girokonto', 'überweisung', 'ueberweisung', 'paypal'],
    '1000' => ['kasse', 'bargeld', 'barzahlung', 'bareinzahlung'],
    '4800' => [
      'bewirtung', 'restaurant', 'geschäftsessen', 'geschaeftsessen', 'bewirtungsbeleg',
      'gastronomie', 'catering',
    ],
    '4900' => [
      'reisekosten', 'dienstreise', 'hotel', 'übernachtung', 'uebernachtung',
      'bahn', 'flug', 'taxi', 'kilometergeld', 'fahrtkosten', 'verpflegungsmehraufwand',
    ],
    '4100' => ['lohn', 'gehalt', 'lohnabrechnung', 'bruttolohn'],
    '4110' => ['gehalt', 'geschäftsführergehalt'],
    '4200' => ['sozialversicherung', 'ag-anteil', 'krankenkasse', 'sozialaufwand'],
    '4964' => ['software', 'lizenz', 'saas', 'microsoft', 'adobe', 'abonnement software'],
    '4969' => ['it-kosten', 'edv', 'computer', 'hardware'],
    '1400' => ['forderung', 'forderungen', 'kundenforderung', 'debitoren', 'ausgangsrechnung offen'],
    '1600' => ['verbindlichkeit', 'verbindlichkeiten', 'lieferantenrechnung', 'kreditoren'],
    '4320' => ['gewerbesteuer', 'gewst'],
    '2200' => ['körperschaftsteuer', 'koerperschaftsteuer', 'kst'],
    '3110' => ['bauleistung 7', 'bau 7', 'bauleistungen 7', '§13b', '§ 13b', '§13', '13b', 'reverse charge', 'steuerschuld', 'bau'],
    '3120' => ['bauleistung 19', 'bau 19', 'bauleistungen 19', 'bauleistung §13b', 'bau §13b', '§13b', '§ 13b', '§13', '13b', 'reverse charge', 'steuerschuld', 'bau'],
    '3123' => ['eu-leistung 19', 'innergemeinschaftliche leistung 19', '§13b', '13b', 'reverse charge'],
    '3125' => ['fremdleistung drittland', 'drittland leistung', 'ausland leistung 19', '§13b', '13b', 'reverse charge'],
    '3130' => ['bauleistung ohne', 'bau ohne 7', '§13b', '13b'],
    '3140' => ['bauleistung ohne 19', 'bau ohne 19', '§13b', '13b'],
    '3160' => ['leistung §13b mit', '§13b mit vorsteuer', '§13b', '§ 13b', '§13', '13b', 'reverse charge'],
    '3165' => ['leistung §13b ohne', '§13b ohne vorsteuer', '§13b', '§ 13b', '§13', '13b', 'reverse charge'],
    '8337' => ['erlös §13b', 'erloes §13b', 'umsatz §13b', 'leistungsempfänger steuerschuldner', '§13b', '§13', '13b', 'reverse charge'],
    '3100' => ['fremdleistung', 'fremdleistungen', '§13b', '13b'],
    '3109' => ['fremdleistung steuerschuldnerschaft', '§13b', '13b', 'reverse charge'],
  ];

  /**
   * Direkt bebuchbare §13b-/Reverse-Charge-Konten (Lexoffice-Parität, SKR03).
   *
   * @var list<string>
   */
  private const SKR03_REVERSE_CHARGE_DIRECT = [
    '3100', '3106', '3108', '3109', '3110', '3113', '3115',
    '3120', '3123', '3125', '3130', '3133', '3135', '3140', '3143',
    '3160', '3165', '3170', '3175', '3180', '3185',
    '4600', '4945', '4950', '4964',
    '8337', '8727',
  ];

  /** @var list<string> */
  private const SKR04_REVERSE_CHARGE_DIRECT = [
    '5900', '5906', '5908', '5909', '5910', '5913', '5915',
    '5920', '5923', '5925', '5930', '5933', '5935', '5940', '5943',
    '5960', '5965', '5970', '5975', '5980', '5985',
    '6300', '6650', '6821', '6825', '6837',
    '4335', '4337',
  ];

  /**
   * Oberbegriffe → direkt bebuchbare Konten (alle Themen, nicht nur Fahrzeug).
   *
   * @var array<string, list<string>>
   */
  private const SKR03_TOPIC_DIRECT = [
    'fahrzeug' => ['0320', '4500', '4510', '4520', '4530', '4540', '4550', '4560', '4570', '4580', '4590', '4595'],
    'kfz' => ['0320', '4500', '4510', '4520', '4530', '4540', '4550', '4560', '4570', '4580', '4590', '4595'],
    'auto' => ['0320', '4500', '4510', '4520', '4530', '4540', '4550', '4560', '4570', '4580', '4590', '4595'],
    'firmenwagen' => ['0320', '4500', '4510', '4520', '4530', '4540', '4550', '4560', '4570', '4580', '4590', '4595'],
    'porto' => ['4910'],
    'post' => ['4910'],
    'versand' => ['4910'],
    'telefon' => ['4920'],
    'handy' => ['4920'],
    'internet' => ['4925'],
    'büro' => ['4930'],
    'buero' => ['4930'],
    'werbung' => ['4600'],
    'marketing' => ['4600'],
    'material' => ['3200', '3300', '3400'],
    'waren' => ['3200', '3300', '3400'],
    'einkauf' => ['3200', '3300', '3400'],
    'erlös' => ['8410', '8334', '8192', '8120', '8110'],
    'erloes' => ['8410', '8334', '8192', '8120', '8110'],
    'umsatz' => ['8410', '8334', '8192', '8120', '8110'],
    'einnahme' => ['8410', '8334', '8192', '8120', '8110'],
    'einnahmen' => ['8410', '8334', '8192', '8120', '8110'],
    'miete' => ['4210', '4240'],
    'versicherung' => ['4360', '4380', '4520'],
    'steuerberater' => ['4950'],
    'anwalt' => ['4950'],
    'bank' => ['1200', '4970'],
    'kasse' => ['1000'],
    'bewirtung' => ['4800'],
    'reise' => ['4900'],
    'lohn' => ['4100', '4110'],
    'gehalt' => ['4100', '4110'],
    'software' => ['4964', '4969'],
    '13b' => self::SKR03_REVERSE_CHARGE_DIRECT,
    'reverse charge' => self::SKR03_REVERSE_CHARGE_DIRECT,
    'reversecharge' => self::SKR03_REVERSE_CHARGE_DIRECT,
    'bauleistung' => ['3110', '3120', '3130', '3140', '3160', '3165'],
    'bau' => ['3110', '3120', '3130', '3140'],
    'steuerschuld' => self::SKR03_REVERSE_CHARGE_DIRECT,
  ];

  /** @var array<string, list<string>> */
  private const SKR04_TOPIC_DIRECT = [
    'fahrzeug' => ['0520', '7685', '6520', '6530', '6540', '6550', '6560', '6570', '6580', '6590'],
    'kfz' => ['0520', '7685', '6520', '6530', '6540', '6550', '6560', '6570', '6580', '6590'],
    'auto' => ['0520', '7685', '6520', '6530', '6540', '6550', '6560', '6570', '6580', '6590'],
    'firmenwagen' => ['0520', '7685', '6520', '6530', '6540', '6550', '6560', '6570', '6580', '6590'],
    'porto' => ['6800'],
    'telefon' => ['6810'],
    'internet' => ['6815'],
    'büro' => ['6820'],
    'buero' => ['6820'],
    'werbung' => ['6300'],
    'material' => ['3800'],
    'erlös' => ['5000', '5100'],
    'erloes' => ['5000', '5100'],
    'miete' => ['4210'],
    'bewirtung' => ['6700'],
    'reise' => ['6650'],
    'lohn' => ['4100'],
    'bank' => ['1200'],
    'kasse' => ['1000'],
    '13b' => self::SKR04_REVERSE_CHARGE_DIRECT,
    'reverse charge' => self::SKR04_REVERSE_CHARGE_DIRECT,
    'bauleistung' => ['5910', '5920', '5930', '5940', '5960', '5965'],
  ];

  /** @return array<string, list<string>> */
  private static function topicDirectMap(string $skrType): array
  {
    return ChartOfAccountsSettings::sanitizeSkrType($skrType) === 'skr04'
      ? self::SKR04_TOPIC_DIRECT
      : self::SKR03_TOPIC_DIRECT;
  }

  /**
   * SKR04: eigene Kontonummern (nicht 1:1 zu SKR03).
   *
   * @var array<string, list<string>>
   */
  private const SKR04_BOOKING = [
    '6800' => self::SKR03_BOOKING['4910'],
    '6810' => self::SKR03_BOOKING['4920'],
    '6815' => self::SKR03_BOOKING['4925'],
    '6820' => self::SKR03_BOOKING['4930'],
    '6300' => self::SKR03_BOOKING['4600'],
    '6430' => self::SKR03_BOOKING['4530'],
    '6420' => self::SKR03_BOOKING['4520'],
    '6410' => self::SKR03_BOOKING['4510'],
    '6470' => self::SKR03_BOOKING['4570'],
    '5000' => self::SKR03_BOOKING['8400'],
    '5100' => self::SKR03_BOOKING['8410'],
    '3800' => self::SKR03_BOOKING['3200'],
    '1400' => self::SKR03_BOOKING['1400'],
    '3300' => self::SKR03_BOOKING['1600'],
    '1200' => self::SKR03_BOOKING['1200'],
    '1000' => self::SKR03_BOOKING['1000'],
    '4100' => self::SKR03_BOOKING['4100'],
    '6700' => self::SKR03_BOOKING['4800'],
    '6650' => self::SKR03_BOOKING['4900'],
  ];

  /**
   * Suchbegriff → Zielkonten mit Relevanz (niedriger = besser).
   *
   * @return list<array{account_number: string, score: int}>
   */
  public static function resolveBookingTargets(string $query, string $skrType): array
  {
    $needle = mb_strtolower(trim($query));
    if ($needle === '' || mb_strlen($needle) < 2) {
      return [];
    }

    $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType);

    if (ChartAccountSearchNormalizer::isReverseChargeQuery($query)) {
      return self::topicAccountHits(self::reverseChargeDirectAccounts($skrType), $skrType, 12);
    }

    $topicMap = self::topicDirectMap($skrType);
    $normalizedTopic = ChartAccountSearchNormalizer::normalizeLegal($needle);
    if (isset($topicMap[$needle])) {
      return self::topicAccountHits($topicMap[$needle], $skrType, 15);
    }
    if ($normalizedTopic !== '' && isset($topicMap[$normalizedTopic])) {
      return self::topicAccountHits($topicMap[$normalizedTopic], $skrType, 15);
    }

    $map = self::bookingMap($skrType);
    $hits = [];

    foreach ($map as $accountNumber => $terms) {
      if ($terms === []) {
        continue;
      }

      $accountKey = str_pad((string) $accountNumber, 4, '0', STR_PAD_LEFT);
      if (!ChartAccountBookingEligibility::isPotentiallySearchableNumber($skrType, $accountKey)) {
        continue;
      }

      $best = null;
      foreach ($terms as $term) {
        $termLower = mb_strtolower(trim($term));
        if ($termLower === '') {
          continue;
        }
        if ($termLower === $needle) {
          $best = 10;
          break;
        }
        if (str_starts_with($termLower, $needle)) {
          $best = min($best ?? 20, 20);
          continue;
        }
        if (mb_strlen($needle) >= 4 && str_starts_with($needle, $termLower)) {
          $best = min($best ?? 25, 25);
        }
      }
      if ($best !== null) {
        $hits[] = ['account_number' => $accountKey, 'score' => $best];
      }
    }

    usort($hits, static function (array $a, array $b): int {
      $cmp = $a['score'] <=> $b['score'];
      if ($cmp !== 0) {
        return $cmp;
      }

      return strcmp((string) $a['account_number'], (string) $b['account_number']);
    });

    return $hits;
  }

  /**
   * @param list<string> $accountNumbers
   * @return list<array{account_number: string, score: int}>
   */
  private static function topicAccountHits(array $accountNumbers, string $skrType, int $score): array
  {
    if ($accountNumbers === []) {
      return [];
    }

    $keys = [];
    foreach ($accountNumbers as $accountNumber) {
      $key = str_pad((string) $accountNumber, 4, '0', STR_PAD_LEFT);
      if (ChartAccountBookingEligibility::isPotentiallySearchableNumber($skrType, $key)) {
        $keys[] = $key;
      }
    }
    if ($keys === []) {
      return [];
    }

    $eligible = [];
    if (Database::isConfigured()) {
      try {
        $placeholders = [];
        $params = ['skr_type' => $skrType];
        foreach ($keys as $index => $key) {
          $param = 'num_' . $index;
          $placeholders[] = ':' . $param;
          $params[$param] = $key;
        }
        $stmt = Database::pdo()->prepare(
          'SELECT account_number, name, section, skr_type
           FROM dg_chart_accounts
           WHERE skr_type = :skr_type AND is_active = 1
             AND account_number IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
          if (ChartAccountBookingEligibility::isSearchableRow($row)) {
            $eligible[(string) $row['account_number']] = true;
          }
        }
      } catch (Throwable) {
        foreach ($keys as $key) {
          $eligible[$key] = true;
        }
      }
    } else {
      foreach ($keys as $key) {
        $eligible[$key] = true;
      }
    }

    $hits = [];
    foreach ($keys as $key) {
      if (!isset($eligible[$key])) {
        continue;
      }
      $hits[] = [
        'account_number' => $key,
        'score' => $score,
      ];
    }

    return $hits;
  }

  public static function isSearchableAccountNumber(string $skrType, string $accountNumber): bool
  {
    $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
    if (!ChartAccountBookingEligibility::isPotentiallySearchableNumber($skrType, $number)) {
      return false;
    }

    if (!Database::isConfigured()) {
      return true;
    }

    $stmt = Database::pdo()->prepare(
      'SELECT account_number, name, section, skr_type
       FROM dg_chart_accounts
       WHERE skr_type = :skr_type AND account_number = :account_number AND is_active = 1
       LIMIT 1'
    );
    $stmt->execute(['skr_type' => $skrType, 'account_number' => $number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      return false;
    }

    return ChartAccountBookingEligibility::isSearchableRow($row);
  }

  /**
   * Buchungsbegriffe für ein Konto (für hints.search_terms).
   *
   * @return list<string>
   */
  public static function synonymsForAccount(string $skrType, string $accountNumber, string $name): array
  {
    $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
    $map = self::bookingMap($skrType);
    $terms = [];
    foreach ($map as $accountKey => $bookingTerms) {
      if (str_pad((string) $accountKey, 4, '0', STR_PAD_LEFT) === $number) {
        $terms = $bookingTerms;
        break;
      }
    }

    if ($terms === [] && $name !== '') {
      $nameLower = mb_strtolower(trim($name));
      if ($nameLower !== '') {
        $terms[] = $nameLower;
      }
    }

    return array_values(array_unique($terms));
  }

  public static function synonymScore(string $query, string $skrType, string $accountNumber, string $name): ?int
  {
    $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
    foreach (self::resolveBookingTargets($query, $skrType) as $hit) {
      if ($hit['account_number'] === $number) {
        return $hit['score'];
      }
    }

    return null;
  }

  public static function isInvoiceBookingAccount(string $accountNumber): bool
  {
    return ChartAccountBookingEligibility::isSearchable('skr03', $accountNumber, '', 'aufwand');
  }

  /** @deprecated Nur noch für Abwärtskompatibilität — liefert Buchungsbegriffe des ersten Treffers. */
  public static function expandQuery(string $query): array
  {
    $needle = mb_strtolower(trim($query));
    if ($needle === '') {
      return [];
    }

    return [$needle];
  }

  /** @return list<string> */
  public static function reverseChargeDirectAccounts(string $skrType): array
  {
    return ChartOfAccountsSettings::sanitizeSkrType($skrType) === 'skr04'
      ? self::SKR04_REVERSE_CHARGE_DIRECT
      : self::SKR03_REVERSE_CHARGE_DIRECT;
  }

  /** @return array<string, list<string>> */
  private static function bookingMap(string $skrType): array
  {
    return ChartOfAccountsSettings::sanitizeSkrType($skrType) === 'skr04'
      ? self::SKR04_BOOKING
      : self::SKR03_BOOKING;
  }
}
