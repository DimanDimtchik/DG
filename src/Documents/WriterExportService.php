<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Dokumentenverfasser D5 — Export PDF (Dompdf), TXT, MD, DOCX.
 * Formate folgen LetterheadSettings::export_formats.
 */
final class WriterExportService
{
    /**
     * @return array<string, string> format => Label
     */
    public static function formatLabels(): array
    {
        return [
            'pdf' => 'PDF',
            'txt' => 'TXT',
            'md' => 'Markdown',
            'docx' => 'Word',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function enabledFormats(?array $letterheadCfg = null): array
    {
        $cfg = $letterheadCfg ?? LetterheadSettings::config();
        $export = is_array($cfg['export_formats'] ?? null) ? $cfg['export_formats'] : [];
        $out = [];
        foreach (array_keys(self::formatLabels()) as $key) {
            $out[$key] = !empty($export[$key]);
        }
        if (!in_array(true, $out, true)) {
            $out['pdf'] = true;
        }

        return $out;
    }

    public static function isFormatEnabled(string $format, ?array $letterheadCfg = null): bool
    {
        $format = strtolower(trim($format));
        $enabled = self::enabledFormats($letterheadCfg);

        return !empty($enabled[$format]);
    }

    /**
     * Streamt den Download / die Druckansicht. Beendet das Skript.
     *
     * @return never
     */
    public static function download(int $documentId, string $format, ?int $userId): never
    {
        $format = strtolower(trim($format));
        if (!isset(self::formatLabels()[$format])) {
            throw new InvalidArgumentException('Unbekanntes Exportformat.');
        }
        if (!self::isFormatEnabled($format)) {
            throw new InvalidArgumentException('Dieses Exportformat ist in den Briefpapier-Einstellungen nicht freigeschaltet.');
        }

        $doc = WriterDocumentRepository::findDocument($documentId);
        if ($doc === null) {
            throw new InvalidArgumentException('Dokument nicht gefunden.');
        }

        $basename = self::safeBasename($doc);
        match ($format) {
            'txt' => self::streamText($doc, $basename . '.txt', 'text/plain; charset=utf-8', self::toPlainText($doc), $userId, $format),
            'md' => self::streamText($doc, $basename . '.md', 'text/markdown; charset=utf-8', self::toMarkdown($doc), $userId, $format),
            'docx' => self::streamBinary($doc, $basename . '.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', self::toDocxBinary($doc), $userId, $format),
            'pdf' => self::streamPdf($doc, $basename, $userId),
            default => throw new InvalidArgumentException('Unbekanntes Exportformat.'),
        };
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function toPlainText(array $doc): string
    {
        $clean = WriterDocument::sanitize($doc);
        $lines = [];
        $recv = array_values(array_filter($clean['recipient_lines'], static fn(string $l): bool => trim($l) !== ''));
        if ($recv !== []) {
            $lines[] = 'Empfänger:';
            foreach ($recv as $l) {
                $lines[] = $l;
            }
            $lines[] = '';
        }
        foreach ($clean['info'] as $key => $val) {
            $val = trim((string) $val);
            if ($val === '') {
                continue;
            }
            $label = LetterheadSettings::defaultInfoFields()[$key]['label'] ?? $key;
            $lines[] = $label . ': ' . $val;
        }
        if ($clean['subject'] !== '') {
            $lines[] = '';
            $lines[] = 'Betreff: ' . $clean['subject'];
        }
        $lines[] = '';
        if ($clean['greeting'] !== '') {
            $lines[] = $clean['greeting'];
            $lines[] = '';
        }
        foreach (WriterDocument::paragraphTexts(array_merge($doc, ['body' => $clean['body']])) as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $lines[] = $p;
            $lines[] = '';
        }
        // Rich-Text-Fallback
        if (trim(implode('', $lines)) === '' || self::onlyMetaLines($lines, $clean)) {
            $fromHtml = self::plainFromHtml($clean['body_html_cache']);
            if ($fromHtml !== '') {
                $lines[] = $fromHtml;
                $lines[] = '';
            }
        }
        if ($clean['closing'] !== '') {
            $lines[] = $clean['closing'];
        }
        if ($clean['signature'] !== '') {
            $lines[] = $clean['signature'];
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function toMarkdown(array $doc): string
    {
        $clean = WriterDocument::sanitize($doc);
        $md = [];
        if ($clean['subject'] !== '') {
            $md[] = '# ' . self::escapeMd($clean['subject']);
            $md[] = '';
        }
        $recv = array_values(array_filter($clean['recipient_lines'], static fn(string $l): bool => trim($l) !== ''));
        if ($recv !== []) {
            $md[] = '**Empfänger**';
            $md[] = '';
            foreach ($recv as $l) {
                $md[] = '- ' . self::escapeMd($l);
            }
            $md[] = '';
        }
        $infoLines = [];
        foreach ($clean['info'] as $key => $val) {
            $val = trim((string) $val);
            if ($val === '') {
                continue;
            }
            $label = LetterheadSettings::defaultInfoFields()[$key]['label'] ?? $key;
            $infoLines[] = '- **' . self::escapeMd($label) . ':** ' . self::escapeMd($val);
        }
        if ($infoLines !== []) {
            $md[] = '**Infoblock**';
            $md[] = '';
            foreach ($infoLines as $l) {
                $md[] = $l;
            }
            $md[] = '';
        }
        if ($clean['greeting'] !== '') {
            $md[] = self::escapeMd($clean['greeting']);
            $md[] = '';
        }
        foreach (WriterDocument::paragraphTexts(array_merge($doc, ['body' => $clean['body']])) as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $md[] = self::escapeMd($p);
            $md[] = '';
        }
        $fromHtml = self::plainFromHtml($clean['body_html_cache']);
        $bodyJoined = implode("\n", $md);
        if ($fromHtml !== '' && !str_contains($bodyJoined, mb_substr($fromHtml, 0, 40))) {
            $md[] = self::escapeMd($fromHtml);
            $md[] = '';
        }
        if ($clean['closing'] !== '') {
            $md[] = self::escapeMd($clean['closing']);
            $md[] = '';
        }
        if ($clean['signature'] !== '') {
            $md[] = '*' . self::escapeMd($clean['signature']) . '*';
        }

        return rtrim(implode("\n", $md)) . "\n";
    }

    /**
     * @param array<string, mixed> $doc
     */
    public static function toDocxBinary(array $doc): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('DOCX-Export benötigt die PHP-Erweiterung ZipArchive.');
        }

        $plain = self::toPlainText($doc);
        $paragraphs = preg_split("/\r\n|\n|\r/", $plain) ?: [''];
        $bodyXml = '';
        foreach ($paragraphs as $line) {
            $bodyXml .= '<w:p><w:r><w:t xml:space="preserve">' . self::xml($line) . '</w:t></w:r></w:p>';
        }
        if ($bodyXml === '') {
            $bodyXml = '<w:p><w:r><w:t></w:t></w:r></w:p>';
        }

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $bodyXml . '</w:body></w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'dgdocx');
        if ($tmp === false) {
            throw new RuntimeException('Temporäre Datei für DOCX konnte nicht angelegt werden.');
        }
        $zipPath = $tmp . '.docx';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('DOCX-ZIP konnte nicht erzeugt werden.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $binary = (string) file_get_contents($zipPath);
        @unlink($zipPath);
        if ($binary === '') {
            throw new RuntimeException('DOCX-Datei ist leer.');
        }

        return $binary;
    }

    /**
     * @param array<string, mixed> $doc
     * @return never
     */
    private static function streamPdf(array $doc, string $basename, ?int $userId): never
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'PDF-Bibliothek (Dompdf) nicht geladen. Bitte vendor/ auf dem Server deployen (composer require dompdf/dompdf).'
            );
        }

        $renderDoc = WriterDocument::toRenderDocument($doc);
        $html = LetterheadRenderer::renderFullHtml(
            $renderDoc,
            LetterheadSettings::config(),
            ['show_guides' => false, 'title' => $basename]
        );

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        if (!is_string($pdf) || $pdf === '') {
            throw new RuntimeException('PDF-Erzeugung fehlgeschlagen.');
        }

        self::streamBinary($doc, $basename . '.pdf', 'application/pdf', $pdf, $userId, 'pdf');
    }

    /**
     * @param array<string, mixed> $doc
     * @return never
     */
    private static function streamText(array $doc, string $filename, string $mime, string $body, ?int $userId, string $format): never
    {
        self::audit($doc, $userId, $format);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . self::headerFilename($filename) . '"');
        echo $body;
        exit;
    }

    /**
     * @param array<string, mixed> $doc
     * @return never
     */
    private static function streamBinary(array $doc, string $filename, string $mime, string $binary, ?int $userId, string $format): never
    {
        self::audit($doc, $userId, $format);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) strlen($binary));
        header('Content-Disposition: attachment; filename="' . self::headerFilename($filename) . '"');
        echo $binary;
        exit;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private static function audit(array $doc, ?int $userId, string $format): void
    {
        AuditLog::record('writer_document_export', $userId, json_encode([
            'document_id' => (int) ($doc['id'] ?? 0),
            'version' => (int) ($doc['version'] ?? 0),
            'status' => (string) ($doc['status'] ?? ''),
            'format' => $format,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, mixed> $doc
     */
    private static function safeBasename(array $doc): string
    {
        $name = trim((string) ($doc['title'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($doc['subject'] ?? ''));
        }
        if ($name === '') {
            $name = 'dokument-' . (int) ($doc['id'] ?? 0);
        }
        $name = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $name) ?? 'dokument';
        $name = trim(preg_replace('/\s+/', '-', $name) ?? 'dokument', '-');
        if ($name === '') {
            $name = 'dokument';
        }

        return mb_substr($name, 0, 80);
    }

    private static function headerFilename(string $filename): string
    {
        $filename = str_replace(['"', "\r", "\n"], '', $filename);

        return $filename !== '' ? $filename : 'download';
    }

    private static function escapeMd(string $text): string
    {
        return str_replace(['\\', '*', '_', '`', '['], ['\\\\', '\\*', '\\_', '\\`', '\\['], $text);
    }

    private static function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function plainFromHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $clean
     */
    private static function onlyMetaLines(array $lines, array $clean): bool
    {
        $paras = WriterDocument::paragraphTexts(['body' => $clean['body']]);
        foreach ($paras as $p) {
            if (trim($p) !== '') {
                return false;
            }
        }

        return true;
    }
}
