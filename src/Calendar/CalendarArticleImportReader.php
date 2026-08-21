<?php
declare(strict_types=1);

/** Liest tabellarische Leistungsdaten aus CSV, Excel, XML, JSON oder PDF. */
final class CalendarArticleImportReader
{
    /** @var list<string> */
    private const SUPPORTED_EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls', 'xml', 'json', 'pdf'];

    /**
     * Methode supported extensions.
     * @return array<string, mixed>
     */
    public static function supportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Prüft: is supported extension.
     * @param string $extension
     * @return bool
     */
    public static function isSupportedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::SUPPORTED_EXTENSIONS, true);
    }

    /**
     * Methode read file.
     * @param string $path
     * @param string $extension
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function readFile(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if (!self::isSupportedExtension($extension)) {
            throw new InvalidArgumentException(
                'Nicht unterstütztes Format. Erlaubt: ' . implode(', ', self::SUPPORTED_EXTENSIONS) . '.'
            );
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException('Die Datei konnte nicht gelesen werden.');
        }

        $resolved = self::resolveFormat($path, $extension);

        return match ($resolved) {
            'csv' => self::readDelimitedFile($path),
            'xlsx' => self::readXlsx($path),
            'xml' => self::readXmlFile($path),
            'json' => self::readJsonFile($path),
            'pdf' => self::readPdfFile($path),
            default => throw new InvalidArgumentException('Dateiformat wird nicht unterstützt.'),
        };
    }

    /**
     * Führt aus: resolve format.
     * @param string $path
     * @param string $extension
     * @return string
     * @throws InvalidArgumentException
     */
    private static function resolveFormat(string $path, string $extension): string
    {
        $head = file_get_contents($path, false, null, 0, 8);
        if ($head === false) {
            return $extension === 'txt' ? 'csv' : $extension;
        }

        if (str_starts_with($head, "PK\x03\x04")) {
            return 'xlsx';
        }

        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            throw new InvalidArgumentException(
                'Das ältere Excel-Format (.xls) wird nicht unterstützt. Bitte als .xlsx speichern oder CSV exportieren.'
            );
        }

        if ($extension === 'txt') {
            return 'csv';
        }

        return $extension;
    }

    /**
     * Methode read delimited file.
     * @param string $path
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function readDelimitedFile(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('Die Datei ist leer.');
        }

        return self::parseDelimitedWithEncodingDetection($raw);
    }

    /**
     * Führt aus: parse delimited with encoding detection.
     * @param string $rawContent
     * @return array<string, mixed>
     */
    public static function parseDelimitedWithEncodingDetection(string $rawContent): array
    {
        $variants = [self::normalizeEncoding($rawContent)];

        if (self::looksLikeUtf16Le($rawContent)) {
            $body = str_starts_with($rawContent, "\xFF\xFE") ? substr($rawContent, 2) : $rawContent;
            $variants[] = self::convertUtf16LeToUtf8($body);
        }

        $lastRows = [];

        foreach ($variants as $content) {
            if (!is_string($content) || $content === '') {
                continue;
            }

            $rows = self::parseDelimitedRecords($content);
            if ($rows === []) {
                continue;
            }

            $lastRows = $rows;
            if (self::rowLooksLikeHeader($rows[0])) {
                return $rows;
            }
        }

        return $lastRows;
    }

    /**
     * Methode row looks like header.
     * @param array $row
     * @return bool
     */
    private static function rowLooksLikeHeader(array $row): bool
    {
        $joined = strtolower(implode(' ', $row));

        return str_contains($joined, 'bezeichnung')
            || str_contains($joined, 'artikelnummer')
            || str_contains($joined, 'title')
            || str_contains($joined, 'name');
    }

    /**
     * Führt aus: parse delimited records.
     * @param string $content
     * @return array<string, mixed>
     */
    private static function parseDelimitedRecords(string $content): array
    {
        $content = preg_replace('/^sep\s*=\s*[^\r\n]*[\r\n]+/i', '', $content) ?? $content;
        $delimiter = self::detectDelimiterFromContent($content);

        $handle = fopen('php://memory', 'rb+');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                continue;
            }
            $rows[] = array_map(self::cleanCell(...), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Methode detect delimiter from content.
     * @param string $content
     * @return string
     */
    private static function detectDelimiterFromContent(string $content): string
    {
        $firstLine = strtok($content, "\r\n");
        if (!is_string($firstLine) || $firstLine === '') {
            return ';';
        }

        $firstLine = self::cleanCell($firstLine);
        $best = ';';
        $max = 0;

        foreach ([',', ';', "\t"] as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $max) {
                $max = $count;
                $best = $delimiter;
            }
        }

        return $best;
    }

    /**
     * Methode read xlsx.
     * @param string $path
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private static function readXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP-Erweiterung fehlt (für Excel .xlsx).');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Excel-Datei konnte nicht geöffnet werden.');
        }

        $sharedStrings = self::readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName(self::findFirstSheetPath($zip));
        $zip->close();

        if ($sheetXml === false || $sheetXml === '') {
            throw new InvalidArgumentException('Kein Arbeitsblatt in der Excel-Datei gefunden.');
        }

        return self::parseXlsxSheet($sheetXml, $sharedStrings);
    }

    /**
     * Liefert first sheet path.
     * @param ZipArchive $zip
     * @return string
     */
    private static function findFirstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if (is_string($workbook) && $workbook !== '') {
            $wb = @simplexml_load_string($workbook);
            if ($wb !== false) {
                $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $sheets = $wb->xpath('//m:sheets/m:sheet');
                if (is_array($sheets) && isset($sheets[0])) {
                    $sheetId = (string) ($sheets[0]['r:id'] ?? '');
                    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
                    if (is_string($rels) && $rels !== '' && $sheetId !== '') {
                        $relsXml = @simplexml_load_string($rels);
                        if ($relsXml !== false) {
                            foreach ($relsXml->Relationship as $rel) {
                                if ((string) $rel['Id'] === $sheetId) {
                                    $target = (string) $rel['Target'];
                                    if ($target !== '') {
                                        return str_starts_with($target, 'worksheets/')
                                            ? 'xl/' . $target
                                            : 'xl/worksheets/' . basename($target);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * Methode read xlsx shared strings.
     * @param ZipArchive $zip
     * @return array<string, mixed>
     */
    private static function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $doc->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $items = $doc->xpath('//m:si') ?: [];
        $strings = [];

        foreach ($items as $si) {
            $si->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = $si->xpath('.//m:t') ?: [];
            $text = '';
            foreach ($parts as $part) {
                $text .= (string) $part;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * Führt aus: parse xlsx sheet.
     * @param string $xml
     * @param array $sharedStrings
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function parseXlsxSheet(string $xml, array $sharedStrings): array
    {
        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            throw new InvalidArgumentException('Excel-Arbeitsblatt ist ungültig.');
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $doc->registerXPathNamespace('m', $ns);
        $rowNodes = $doc->xpath('//m:sheetData/m:row') ?: [];
        $rows = [];

        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $maxCol = 0;
            foreach ($rowNode->children($ns)->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $col = $ref !== '' ? self::columnIndexFromCellRef($ref) : $maxCol;
                $cells[$col] = self::xlsxCellValue($cell, $sharedStrings, $ns);
                $maxCol = max($maxCol, $col + 1);
            }

            if ($cells === []) {
                continue;
            }

            $line = [];
            for ($c = 0; $c < $maxCol; $c++) {
                $line[] = $cells[$c] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Methode xlsx cell value.
     * @param SimpleXMLElement $cell
     * @param array $sharedStrings
     * @param string $ns
     * @return string
     */
    private static function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings, string $ns): string
    {
        $type = (string) ($cell['t'] ?? '');
        $children = $cell->children($ns);

        if ($type === 's') {
            $index = (int) (string) ($children->v ?? '0');

            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            return (string) ($children->is->t ?? '');
        }

        return (string) ($children->v ?? '');
    }

    /**
     * Column Index From Cell Ref.
     * @param string $cellRef
     * @return int
     */
    private static function columnIndexFromCellRef(string $cellRef): int
    {
        if (!preg_match('/^([A-Z]+)/i', strtoupper($cellRef), $match)) {
            return 0;
        }

        $letters = strtoupper($match[1]);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * Methode read xml file.
     * @param string $path
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function readXmlFile(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('Die XML-Datei ist leer.');
        }

        $raw = self::normalizeEncoding($raw);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new InvalidArgumentException('XML konnte nicht gelesen werden.');
        }

        $rootName = strtolower($xml->getName());
        if ($rootName === 'workbook' || $xml->Worksheet !== null) {
            return self::readSpreadsheetMl($xml);
        }

        foreach (['row', 'item', 'article', 'artikel', 'leistung', 'product', 'record', 'entry'] as $tag) {
            $items = $xml->xpath('//*[local-name()="' . $tag . '"]');
            if (is_array($items) && count($items) > 0) {
                return self::xmlElementsToRows($items);
            }
        }

        $children = $xml->children();
        if (count($children) > 0) {
            return self::xmlElementsToRows(iterator_to_array($children));
        }

        throw new InvalidArgumentException('Keine importierbaren Datensätze in der XML-Datei gefunden.');
    }

    /**
     * Methode xml elements to rows.
     * @param array $elements
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function xmlElementsToRows(array $elements): array
    {
        $fieldOrder = [];
        $records = [];

        foreach ($elements as $element) {
            $record = [];
            foreach ($element->children() as $child) {
                $name = (string) $child->getName();
                if (!in_array($name, $fieldOrder, true)) {
                    $fieldOrder[] = $name;
                }
                $record[$name] = trim((string) $child);
            }
            foreach ($element->attributes() as $attrName => $attrValue) {
                $name = '@' . (string) $attrName;
                if (!in_array($name, $fieldOrder, true)) {
                    $fieldOrder[] = $name;
                }
                $record[$name] = trim((string) $attrValue);
            }
            if ($record !== []) {
                $records[] = $record;
            }
        }

        if ($records === []) {
            throw new InvalidArgumentException('XML-Datensätze enthalten keine Felder.');
        }

        $rows = [$fieldOrder];
        foreach ($records as $record) {
            $line = [];
            foreach ($fieldOrder as $field) {
                $line[] = $record[$field] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Methode read spreadsheet ml.
     * @param SimpleXMLElement $xml
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function readSpreadsheetMl(SimpleXMLElement $xml): array
    {
        $rows = [];
        $rowNodes = $xml->xpath('//*[local-name()="Row"]') ?: [];
        foreach ($rowNodes as $rowNode) {
            $line = [];
            $cellNodes = $rowNode->xpath('.//*[local-name()="Cell"]') ?: [];
            foreach ($cellNodes as $cellNode) {
                $dataNodes = $cellNode->xpath('.//*[local-name()="Data"]');
                $line[] = isset($dataNodes[0]) ? trim((string) $dataNodes[0]) : '';
            }
            if ($line !== []) {
                $rows[] = $line;
            }
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Excel-XML enthält keine Zeilen.');
        }

        return $rows;
    }

    /**
     * Methode read json file.
     * @param string $path
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function readJsonFile(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('Die JSON-Datei ist leer.');
        }

        $raw = self::normalizeEncoding($raw);
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('JSON ist ungültig: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('JSON muss ein Objekt oder Array enthalten.');
        }

        foreach (['items', 'products', 'articles', 'leistungen', 'data', 'rows', 'records'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data = $data[$key];
                break;
            }
        }

        if ($data === []) {
            throw new InvalidArgumentException('JSON enthält keine Datensätze.');
        }

        if (!isset($data[0]) || !is_array($data[0])) {
            throw new InvalidArgumentException('JSON muss eine Liste von Objekten sein.');
        }

        $headers = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (array_keys($item) as $key) {
                if (!in_array((string) $key, $headers, true)) {
                    $headers[] = (string) $key;
                }
            }
        }

        if ($headers === []) {
            throw new InvalidArgumentException('JSON-Objekte enthalten keine Felder.');
        }

        $rows = [$headers];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $line = [];
            foreach ($headers as $header) {
                $value = $item[$header] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
                }
                $line[] = trim((string) $value);
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Methode read pdf file.
     * @param string $path
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function readPdfFile(string $path): array
    {
        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('Die PDF-Datei ist leer.');
        }

        if (!str_starts_with($binary, '%PDF')) {
            throw new InvalidArgumentException('Keine gültige PDF-Datei.');
        }

        $text = self::extractPdfText($binary);
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException(
                'Aus der PDF konnte kein Text extrahiert werden. Bitte CSV oder Excel exportieren.'
            );
        }

        $rows = self::parseDelimitedRecords($text);
        if (count($rows) < 2) {
            $rows = self::parsePdfLinesAsRows($text);
        }

        if (count($rows) < 2) {
            throw new InvalidArgumentException(
                'PDF enthält keine erkennbare Tabelle. Bitte als CSV, Excel, XML oder JSON exportieren.'
            );
        }

        return $rows;
    }

    /**
     * Methode extract pdf text.
     * @param string $binary
     * @return string
     */
  private static function extractPdfText(string $binary): string
    {
        $text = self::extractPdfTextFromContent($binary);

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate(substr($stream, 2));
                }
                if (is_string($decoded) && $decoded !== '') {
                    $text .= self::extractPdfTextFromContent($decoded);
                }
            }
        }

        return $text;
    }

    /**
     * Extract Pdf Text From Content.
     * @param string $content
     * @return string
     */
    private static function extractPdfTextFromContent(string $content): string
    {
        $parts = [];

        if (preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\\)\s*Tj/s', $content, $matches)) {
            foreach ($matches[1] as $raw) {
                $parts[] = self::unescapePdfString($raw);
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/', $content, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $bytes = pack('H*', $hex);
                $parts[] = self::normalizeEncoding($bytes);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Methode unescape pdf string.
     * @param string $value
     * @return string
     */
    private static function unescapePdfString(string $value): string
    {
        return stripcslashes(str_replace(['\\(', '\\)', '\\n', '\\r', '\\t'], ["\n", ')', "\n", "\r", "\t"], $value));
    }

    /**
     * Führt aus: parse pdf lines as rows.
     * @param string $text
     * @return array<string, mixed>
     */
    private static function parsePdfLinesAsRows(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $delimiter = self::detectDelimiterFromContent($line . "\n");
            if ($delimiter === ';' && substr_count($line, ';') === 0) {
                $delimiter = "\t";
            }
            if (substr_count($line, $delimiter) > 0) {
                $rows[] = array_map(self::cleanCell(...), str_getcsv($line, $delimiter, '"', '\\'));
            }
        }

        return $rows;
    }

    /**
     * Führt aus: normalize encoding.
     * @param string $content
     * @return string
     */
    public static function normalizeEncoding(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = self::convertUtf16LeToUtf8(substr($content, 2));
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
                if (is_string($converted) && $converted !== '') {
                    $content = $converted;
                }
            }
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (isset($content[0], $content[1]) && $content[0] === '?' && $content[1] === '"') {
            $content = substr($content, 1);
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            if (is_string($converted) && $converted !== '') {
                $content = $converted;
            }
        }

        return $content;
    }

    /**
     * Looks Like Utf16Le.
     * @param string $content
     * @return bool
     */
    private static function looksLikeUtf16Le(string $content): bool
    {
        $len = min(strlen($content), 4000);
        if ($len < 10) {
            return false;
        }

        return substr_count(substr($content, 0, $len), "\0") > ($len / 10);
    }

    /**
     * Convert Utf16Le To Utf8.
     * @param string $content
     * @return string
     */
    private static function convertUtf16LeToUtf8(string $content): string
    {
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $content;
    }

    /**
     * Methode clean cell.
     * @param mixed $value
     * @return string
     */
    public static function cleanCell(mixed $value): string
    {
        $value = str_replace("\0", '', (string) $value);
        $value = ltrim($value, '?' . "\xEF\xBB\xBF");

        return trim($value, " \t\n\r\0\x0B\xA0\"'");
    }
}
