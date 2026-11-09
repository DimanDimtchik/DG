<?php
declare(strict_types=1);

/**
 * DSFinV-K Export-Stub (K17b): Minimal-CSV mit Kopfzeilen + index.xml + DTD im ZIP.
 * Keine Bon-Daten — Befüllung = K17c. Spec: docs/DSFINVK-SPEC.md
 */
final class CashDsfinvkStubExport
{
    public const EXPORT_TYPE = 'dsfinvk_stub';
    public const SPEC_VERSION = '2.3';

    /**
     * Pflicht-CSV (englische Dateinamen) → logischer Name → Kopfspalten (Minimal-Kern).
     *
     * @return array<string, array{logical: string, columns: list<string>}>
     */
    public static function csvCatalog(): array
    {
        return [
            'cashpointclosing.csv' => [
                'logical' => 'Stamm_Abschluss',
                'columns' => ['Z_KASSE_ID', 'Z_ERSTELLUNG', 'Z_NR', 'Z_START_ID', 'Z_ENDE_ID'],
            ],
            'location.csv' => [
                'logical' => 'Stamm_Orte',
                'columns' => ['LOC_ID', 'LOC_NAME', 'LOC_STRASSE', 'LOC_PLZ', 'LOC_ORT', 'LOC_LAND'],
            ],
            'cashregister.csv' => [
                'logical' => 'Stamm_Kassen',
                'columns' => ['Z_KASSE_ID', 'KASSE_NAME', 'KASSE_TYP', 'SOFTWARE', 'LOC_ID'],
            ],
            'slaves.csv' => [
                'logical' => 'Stamm_Terminals',
                'columns' => ['Z_KASSE_ID', 'TERMINAL_ID', 'TERMINAL_NAME'],
            ],
            'pa.csv' => [
                'logical' => 'Stamm_Agenturen',
                'columns' => ['Z_KASSE_ID', 'AGENTUR_ID', 'AGENTUR_NAME'],
            ],
            'vat.csv' => [
                'logical' => 'Stamm_USt',
                'columns' => ['UST_ID', 'UST_SATZ', 'UST_BESCHREIBUNG'],
            ],
            'tse.csv' => [
                'logical' => 'Stamm_TSE',
                'columns' => ['TSE_ID', 'TSE_SERIAL', 'TSE_SIG_ALGO', 'TSE_ZEITFORMAT'],
            ],
            'businesscases.csv' => [
                'logical' => 'Z_GV_TYP',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'GV_TYP', 'GV_NAME', 'BON_BRUTTO'],
            ],
            'payment.csv' => [
                'logical' => 'Z_Zahlart',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'ZAHLART_TYP', 'ZAHLART_NAME', 'ZAHLART_BETRAG'],
            ],
            'cash_per_currency.csv' => [
                'logical' => 'Z_Waehrungen',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'WAEHRUNG', 'ZAHLART_BETRAG'],
            ],
            'transactions.csv' => [
                'logical' => 'Bonkopf',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'BON_TYP', 'BON_NAME', 'BON_START', 'BON_ENDE', 'BON_BRUTTO', 'BON_NETTO', 'BON_UST'],
            ],
            'allocation_groups.csv' => [
                'logical' => 'Bonkopf_AbrKreis',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'ABRECHNUNGSKREIS'],
            ],
            'transactions_vat.csv' => [
                'logical' => 'Bonkopf_USt',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'UST_ID', 'BON_BRUTTO', 'BON_NETTO', 'BON_UST'],
            ],
            'datapayment.csv' => [
                'logical' => 'Bonkopf_Zahlarten',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'ZAHLART_TYP', 'ZAHLART_NAME', 'ZAHLART_BETRAG', 'ZAHLART_WAEHRUNG'],
            ],
            'lines.csv' => [
                'logical' => 'Bonpos',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'POS_ZEILE', 'ARTIKELTEXT', 'MENGE', 'BRUTTO'],
            ],
            'lines_vat.csv' => [
                'logical' => 'Bonpos_USt',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'POS_ZEILE', 'UST_ID', 'POS_BRUTTO', 'POS_NETTO', 'POS_UST'],
            ],
            'itemamounts.csv' => [
                'logical' => 'Bonpos_Preisfindung',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'POS_ZEILE', 'TYP', 'UST_ID', 'PF_BRUTTO'],
            ],
            'subitems.csv' => [
                'logical' => 'Bonpos_Zusatzinfo',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'POS_ZEILE', 'ZI_ART', 'ZI_NAME'],
            ],
            'references.csv' => [
                'logical' => 'Bon_Referenzen',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'REF_TYP', 'REF_Z_KASSE_ID', 'REF_BON_ID'],
            ],
            'transactions_tse.csv' => [
                'logical' => 'TSE_Transaktionen',
                'columns' => ['Z_KASSE_ID', 'Z_NR', 'BON_ID', 'TSE_ID', 'TSE_TANR', 'TSE_SIGZ', 'TSE_VORGANGSDATEN', 'TSE_SIG'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function expectedArchiveNames(): array
    {
        $names = array_keys(self::csvCatalog());
        $names[] = 'index.xml';
        $names[] = 'gdpdu-01-09-2004.dtd';
        $names[] = 'README.txt';
        sort($names);

        return $names;
    }

    /**
     * Dateiname → Dateiinhalt (UTF-8, CSV mit Semikolon + BOM nur bei CSV).
     *
     * @return array<string, string>
     */
    public static function buildFileMap(): array
    {
        $files = [];
        foreach (self::csvCatalog() as $filename => $meta) {
            $files[$filename] = self::csvHeaderOnly($meta['columns']);
        }
        $files['index.xml'] = self::buildIndexXml();
        $files['gdpdu-01-09-2004.dtd'] = self::minimalDtd();
        $files['README.txt'] = self::readme();

        return $files;
    }

    /**
     * @return array{path: string, filename: string, sha256: string, files: int}
     */
    public static function buildZip(?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP-ZipArchive fehlt — DSFinV-K-Export nicht möglich.');
        }
        $from = self::sanitizeDate($dateFrom) ?? date('Y-m-d');
        $to = self::sanitizeDate($dateTo) ?? $from;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dg_dsfinvk_');
        if ($tmp === false) {
            throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }

        $map = self::buildFileMap();
        foreach ($map as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $sha = hash_file('sha256', $zipPath);
        if (!is_string($sha)) {
            $sha = '';
        }

        return [
            'path' => $zipPath,
            'filename' => sprintf('DSFinVK_stub_%s_%s.zip', $from, $to),
            'sha256' => $sha,
            'files' => count($map),
        ];
    }

    public static function downloadAndExit(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $userId = null
    ): void {
        $pack = self::buildZip($dateFrom, $dateTo);
        $binary = file_get_contents($pack['path']);
        @unlink($pack['path']);
        if ($binary === false) {
            throw new RuntimeException('ZIP konnte nicht gelesen werden.');
        }

        if (class_exists('CashExportRepository') && CashExportRepository::tableExists()) {
            try {
                $exportId = CashExportRepository::insert([
                    'export_type' => self::EXPORT_TYPE,
                    'date_from' => self::sanitizeDate($dateFrom) ?? date('Y-m-d'),
                    'date_to' => self::sanitizeDate($dateTo) ?? date('Y-m-d'),
                    'status' => 'done',
                    'file_path' => $pack['filename'],
                    'file_checksum' => $pack['sha256'],
                    'created_by' => $userId !== null && $userId > 0 ? $userId : null,
                    'finished_at' => date('Y-m-d H:i:s'),
                    'meta_json' => json_encode([
                        'spec' => self::SPEC_VERSION,
                        'stub' => true,
                        'files' => $pack['files'],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
                if (CashAuditLogRepository::tableExists()) {
                    CashAuditLogRepository::insert([
                        'event_type' => 'export.dsfinvk',
                        'user_id' => $userId,
                        'message' => 'DSFinV-K Stub-ZIP ' . $pack['filename'],
                        'details_json' => json_encode([
                            'export_id' => $exportId,
                            'sha256' => $pack['sha256'],
                            'stub' => true,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            } catch (Throwable) {
                // Download trotzdem
            }
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $pack['filename'] . '"');
        header('Content-Length: ' . (string) strlen($binary));
        header('Cache-Control: no-store');
        echo $binary;
        exit;
    }

    /**
     * @param list<string> $columns
     */
    private static function csvHeaderOnly(array $columns): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('CSV-Puffer fehlgeschlagen.');
        }
        fputcsv($fh, $columns, ';', '"', '\\');
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        if ($csv === false) {
            return "\xEF\xBB\xBF";
        }

        return "\xEF\xBB\xBF" . $csv;
    }

    private static function buildIndexXml(): string
    {
        $tables = '';
        foreach (self::csvCatalog() as $filename => $meta) {
            $cols = '';
            foreach ($meta['columns'] as $col) {
                $safe = htmlspecialchars($col, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cols .= "        <VariableColumn name=\"{$safe}\">\n"
                    . "          <Name>{$safe}</Name>\n"
                    . "          <Description>Stub-Spalte {$safe}</Description>\n"
                    . "          <AlphaNumeric/>\n"
                    . "        </VariableColumn>\n";
            }
            $url = htmlspecialchars($filename, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $logical = htmlspecialchars($meta['logical'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $tables .= "    <Table>\n"
                . "      <URL>{$url}</URL>\n"
                . "      <Name>{$logical}</Name>\n"
                . "      <Description>DSFinV-K Stub {$logical} (ohne Datensätze)</Description>\n"
                . "      <UTF8>true</UTF8>\n"
                . "      <DecimalSymbol>.</DecimalSymbol>\n"
                . "      <DigitGroupingSymbol></DigitGroupingSymbol>\n"
                . "      <Range>\n"
                . "        <From>2</From>\n"
                . "      </Range>\n"
                . "      <VariableLength>\n"
                . "        <ColumnDelimiter>;</ColumnDelimiter>\n"
                . "        <RecordDelimiter>\\n</RecordDelimiter>\n"
                . "        <TextEncapsulator>\"</TextEncapsulator>\n"
                . $cols
                . "      </VariableLength>\n"
                . "    </Table>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE DataSet SYSTEM "gdpdu-01-09-2004.dtd">' . "\n"
            . "<DataSet>\n"
            . "  <Version>1</Version>\n"
            . "  <DataSupplier>\n"
            . "    <Name>DG CRM Registerkasse</Name>\n"
            . "    <Location>DSFinV-K Stub " . self::SPEC_VERSION . "</Location>\n"
            . "    <Comment>Leerstruktur K17b — Befüllung folgt in K17c</Comment>\n"
            . "  </DataSupplier>\n"
            . "  <Media>\n"
            . "    <Name>DSFinV-K</Name>\n"
            . $tables
            . "  </Media>\n"
            . "</DataSet>\n";
    }

    private static function minimalDtd(): string
    {
        // Minimal-Platzhalter, damit das Paket eine DTD mitliefert (volle amtliche DTD später möglich).
        return <<<'DTD'
<!-- DG CRM K17b: Minimal-DTD-Platzhalter für DSFinV-K-Stub. Amtliche GDPDU-DTD bei Bedarf ersetzen. -->
<!ELEMENT DataSet (Version, DataSupplier?, Media+)>
<!ELEMENT Version (#PCDATA)>
<!ELEMENT DataSupplier (Name, Location?, Comment?)>
<!ELEMENT Name (#PCDATA)>
<!ELEMENT Location (#PCDATA)>
<!ELEMENT Comment (#PCDATA)>
<!ELEMENT Media (Name?, Table+)>
<!ELEMENT Table (URL, Name, Description?, UTF8?, DecimalSymbol?, DigitGroupingSymbol?, Range?, VariableLength?)>
<!ELEMENT URL (#PCDATA)>
<!ELEMENT Description (#PCDATA)>
<!ELEMENT UTF8 (#PCDATA)>
<!ELEMENT DecimalSymbol (#PCDATA)>
<!ELEMENT DigitGroupingSymbol (#PCDATA)>
<!ELEMENT Range (From?, To?)>
<!ELEMENT From (#PCDATA)>
<!ELEMENT To (#PCDATA)>
<!ELEMENT VariableLength (ColumnDelimiter?, RecordDelimiter?, TextEncapsulator?, VariableColumn*)>
<!ELEMENT ColumnDelimiter (#PCDATA)>
<!ELEMENT RecordDelimiter (#PCDATA)>
<!ELEMENT TextEncapsulator (#PCDATA)>
<!ELEMENT VariableColumn (Name, Description?, AlphaNumeric?)>
<!ATTLIST VariableColumn name CDATA #REQUIRED>
<!ELEMENT AlphaNumeric EMPTY>

DTD;
    }

    private static function readme(): string
    {
        return "DG CRM — DSFinV-K Export-Stub (K17b)\n"
            . "Spec-Version: " . self::SPEC_VERSION . "\n"
            . "Stand: " . date('c') . "\n\n"
            . "Dieses ZIP enthält die Pflicht-CSV-Dateien nur mit Kopfzeilen (keine Bon-Daten),\n"
            . "plus index.xml und DTD-Platzhalter.\n"
            . "Befüllung aus dg_cash_* = K17c.\n"
            . "Dokumentation: docs/DSFINVK-SPEC.md\n";
    }

    private static function sanitizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
