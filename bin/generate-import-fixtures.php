<?php
declare(strict_types=1);

/**
 * Erzeugt Testdateien unter docs/testdata/import-fixtures/ (Kontakte + Stunden).
 * php bin/generate-import-fixtures.php
 */
$root = dirname(__DIR__);
$outBase = $root . '/docs/testdata/import-fixtures';
$kontakteDir = $outBase . '/kontakte';
$stundenDir = $outBase . '/stunden';

foreach ([$kontakteDir, $stundenDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Verzeichnis nicht anlegbar: {$dir}\n");
        exit(1);
    }
}

/** @return list<array{login: string, first: string, last: string, email: string, phone: string, dup: bool}> */
function fixtureRoster(): array
{
    $people = [
        ['demo-dup-01', 'Anna', 'Duplikat', true],
        ['demo-dup-02', 'Bernd', 'Doppel', true],
        ['demo-ma-03', 'Clara', 'Fischer', false],
        ['demo-ma-04', 'David', 'Hoffmann', false],
        ['demo-ma-05', 'Elena', 'Jung', false],
        ['demo-ma-06', 'Felix', 'Keller', false],
        ['demo-ma-07', 'Greta', 'Lange', false],
        ['demo-ma-08', 'Hans', 'Meier', false],
        ['demo-ma-09', 'Ina', 'Neumann', false],
        ['demo-ma-10', 'Jonas', 'Otto', false],
        ['demo-ma-11', 'Karla', 'Peters', false],
        ['demo-ma-12', 'Leon', 'Richter', false],
    ];
    $out = [];
    foreach ($people as $i => [$login, $first, $last, $dup]) {
        $out[] = [
            'login' => $login,
            'first' => $first,
            'last' => $last,
            'email' => strtolower($first) . '.' . strtolower($last) . '@demo-import.ganz-om.invalid',
            'phone' => sprintf('030 %04d %04d', 1000 + $i, 2000 + $i),
            'dup' => $dup,
        ];
    }

    return $out;
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $rows
 */
function writeCsv(string $path, array $headers, array $rows): void
{
    $fh = fopen($path, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Schreiben fehlgeschlagen: ' . $path);
    }
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, $headers, ';', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($fh, $row, ';', '"', '\\');
    }
    fclose($fh);
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $dataRows
 */
function writeJson(string $path, array $headers, array $dataRows): void
{
    $rows = [];
    foreach ($dataRows as $line) {
        $obj = [];
        foreach ($headers as $i => $h) {
            $obj[$h] = $line[$i] ?? '';
        }
        $rows[] = $obj;
    }
    file_put_contents(
        $path,
        json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
    );
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $dataRows
 */
function writeXml(string $path, array $headers, array $dataRows): void
{
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><records/>');
    foreach ($dataRows as $line) {
        $row = $xml->addChild('row');
        foreach ($headers as $i => $h) {
            $key = preg_replace('/[^a-zA-Z0-9_]+/', '_', $h) ?: 'field';
            $row->addChild($key, htmlspecialchars((string) ($line[$i] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML() ?: '<records/>');
    $dom->save($path);
}

/**
 * Minimales OOXML .xlsx (eine Sheet, shared strings).
 *
 * @param list<string> $headers
 * @param list<list<string>> $dataRows
 */
function writeXlsx(string $path, array $headers, array $dataRows): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive fehlt — xlsx nicht erzeugbar.');
    }
    $all = array_merge([$headers], $dataRows);
    $shared = [];
    $sharedIndex = [];
    $addShared = static function (string $v) use (&$shared, &$sharedIndex): int {
        if (isset($sharedIndex[$v])) {
            return $sharedIndex[$v];
        }
        $idx = count($shared);
        $shared[] = $v;
        $sharedIndex[$v] = $idx;

        return $idx;
    };

    $sheetRows = '';
    foreach ($all as $rIdx => $line) {
        $rowNum = $rIdx + 1;
        $cells = '';
        foreach ($line as $cIdx => $val) {
            $col = '';
            $n = $cIdx;
            do {
                $col = chr(65 + ($n % 26)) . $col;
                $n = intdiv($n, 26) - 1;
            } while ($n >= 0);
            $si = $addShared((string) $val);
            $ref = $col . $rowNum;
            $cells .= '<c r="' . $ref . '" t="s"><v>' . $si . '</v></c>';
        }
        $sheetRows .= '<row r="' . $rowNum . '">' . $cells . '</row>';
    }

    $sst = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
        . count($shared) . '" uniqueCount="' . count($shared) . '">';
    foreach ($shared as $s) {
        $sst .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
    }
    $sst .= '</sst>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
        . ' Target="xl/workbook.xml"/></Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
        . ' Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"'
        . ' Target="sharedStrings.xml"/></Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '</Types>';

    if (is_file($path)) {
        unlink($path);
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('xlsx Zip fehlgeschlagen: ' . $path);
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->addFromString('xl/sharedStrings.xml', $sst);
    $zip->close();
}

/**
 * @param list<array{login: string, first: string, last: string, email: string, phone: string, dup: bool}> $roster
 * @return array{0: list<string>, 1: list<list<string>>}
 */
function contactRowsForSource(string $source, array $roster): array
{
    $excelRows = [];
    foreach ($roster as $i => $p) {
        $excelRows[] = [
            ($i % 2 === 0) ? 'Frau' : 'Herr',
            $p['first'],
            $p['last'],
            '',
            $p['email'],
            $p['phone'],
            $p['login'],
            '',
            'mitarbeiter',
        ];
    }

    return match ($source) {
        'excel', 'other' => [
            ['Anrede', 'Vorname', 'Nachname', 'Firma', 'E-Mail', 'Telefon', 'Login', 'Kundennummer', 'Rolle'],
            $excelRows,
        ],
        'outlook' => [
            ['Given Name', 'Family Name', 'Company', 'E-mail Address', 'Business Phone', 'Login', 'Kundennummer', 'Rolle'],
            array_map(static fn (array $p): array => [
                $p['first'], $p['last'], '', $p['email'], $p['phone'], $p['login'], '', 'mitarbeiter',
            ], $roster),
        ],
        'google' => [
            ['Given Name', 'Family Name', 'Organization Name', 'E-Mail', 'Phone 1 - Value', 'Login', 'Kundennummer', 'Rolle'],
            array_map(static fn (array $p): array => [
                $p['first'], $p['last'], '', $p['email'], $p['phone'], $p['login'], '', 'mitarbeiter',
            ], $roster),
        ],
        'datev' => [
            ['Vorname', 'Nachname', 'Firma', 'E-Mail', 'Telefon', 'Login', 'Mitarbeiternummer', 'Rolle'],
            array_map(static fn (array $p): array => [
                $p['first'], $p['last'], '', $p['email'], $p['phone'], $p['login'], '', 'mitarbeiter',
            ], $roster),
        ],
        'lexware' => [
            ['Vorname', 'Nachname', 'Firma', 'E-Mail', 'Telefon', 'Benutzername', 'Kundennummer', 'Rolle'],
            array_map(static fn (array $p): array => [
                $p['first'], $p['last'], '', $p['email'], $p['phone'], $p['login'], '', 'mitarbeiter',
            ], $roster),
        ],
        'sevdesk' => [
            ['Vorname', 'Nachname', 'company', 'email', 'Telefon', 'Login', 'Kundennummer', 'Rolle'],
            array_map(static fn (array $p): array => [
                $p['first'], $p['last'], '', $p['email'], $p['phone'], $p['login'], '', 'mitarbeiter',
            ], $roster),
        ],
        'shiftbase' => [
            ['Employee', 'Company', 'E-Mail', 'Phone', 'Personalnummer', 'Kundennummer', 'Rolle'],
            array_map(static fn (array $p): array => [
                $p['first'] . ' ' . $p['last'], '', $p['email'], $p['phone'], $p['login'], '', 'mitarbeiter',
            ], $roster),
        ],
        default => throw new InvalidArgumentException('Unbekannte Quelle: ' . $source),
    };
}

/**
 * @return list<string> Y-m-d weekdays inclusive
 */
function weekdaysBetween(string $from, string $to): array
{
    $out = [];
    $d = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    while ($d <= $end) {
        $n = (int) $d->format('N');
        if ($n <= 5) {
            $out[] = $d->format('Y-m-d');
        }
        $d = $d->modify('+1 day');
    }

    return $out;
}

$roster = fixtureRoster();
$dups = array_values(array_filter($roster, static fn (array $p): bool => $p['dup']));
$sources = ['excel', 'outlook', 'google', 'datev', 'lexware', 'sevdesk', 'shiftbase', 'other'];

// Seed
[$seedH, $seedR] = contactRowsForSource('excel', $dups);
writeCsv($kontakteDir . '/00-seed-duplikate.csv', $seedH, $seedR);

foreach ($sources as $src) {
    [$h, $r] = contactRowsForSource($src, $roster);
    writeCsv($kontakteDir . '/kontakte-' . $src . '.csv', $h, $r);
}

// Format-Pack excel
[$exH, $exR] = contactRowsForSource('excel', $roster);
writeCsv($kontakteDir . '/kontakte-excel.txt', $exH, $exR);
writeJson($kontakteDir . '/kontakte-excel.json', $exH, $exR);
writeXml($kontakteDir . '/kontakte-excel.xml', $exH, $exR);
writeXlsx($kontakteDir . '/kontakte-excel.xlsx', $exH, $exR);

// Stunden
$days = weekdaysBetween('2026-06-01', '2026-09-19');
$excelHoursRows = [];
$shiftbaseRows = [];
$crewRows = [];
$otherRows = [];

foreach ($roster as $pi => $p) {
    foreach ($days as $di => $day) {
        // leichte Variation: Start 08:00 oder 07:30, Ende 16:30 / 17:00
        $start = ($pi + $di) % 2 === 0 ? '08:00' : '07:30';
        $end = ($pi + $di) % 3 === 0 ? '17:00' : '16:30';
        $pause = '30';
        $de = DateTimeImmutable::createFromFormat('Y-m-d', $day)?->format('d.m.Y') ?? $day;

        $excelHoursRows[] = [$p['email'], $p['login'], $p['first'] . ' ' . $p['last'], $de, $start, $end, $pause, ''];
        $shiftbaseRows[] = [
            $p['first'] . ' ' . $p['last'],
            $p['email'],
            $day,
            $start,
            $end,
            $pause,
            '',
        ];
        $crewRows[] = [
            $p['first'] . ' ' . $p['last'],
            $p['login'],
            $de,
            $start,
            $end,
            $pause,
        ];
        // other: abwechselnd Zeiten vs. nur Stunden
        if (($pi + $di) % 4 === 0) {
            $otherRows[] = [$p['login'], $de, '', '', '', '8'];
        } else {
            $otherRows[] = [$p['login'], $de, $start, $end, $pause, ''];
        }
    }
}

writeCsv(
    $stundenDir . '/stunden-excel.csv',
    ['E-Mail', 'Login', 'Name', 'Datum', 'Beginn', 'Ende', 'Pause_Minuten', 'Stunden'],
    $excelHoursRows
);
writeXlsx(
    $stundenDir . '/stunden-excel.xlsx',
    ['E-Mail', 'Login', 'Name', 'Datum', 'Beginn', 'Ende', 'Pause_Minuten', 'Stunden'],
    $excelHoursRows
);
writeCsv(
    $stundenDir . '/stunden-shiftbase.csv',
    ['Employee', 'Email', 'Date', 'Clocked in', 'Clocked out', 'Break', 'Hours'],
    $shiftbaseRows
);
writeCsv(
    $stundenDir . '/stunden-crewmeister.csv',
    ['Mitarbeiter', 'Personalnummer', 'Datum', 'Kommen', 'Gehen', 'Pause'],
    $crewRows
);
writeCsv(
    $stundenDir . '/stunden-other.csv',
    ['Login', 'Datum', 'Beginn', 'Ende', 'Pause_Minuten', 'Stunden'],
    $otherRows
);

echo "OK: Kontakte + Stunden unter docs/testdata/import-fixtures/\n";
echo '  Roster: ' . count($roster) . " (davon Duplikat-Seed: " . count($dups) . ")\n";
echo '  Werktage: ' . count($days) . ' -> Stundenzeilen ca. ' . (count($days) * count($roster)) . "\n";
