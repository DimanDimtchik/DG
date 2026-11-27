<?php
declare(strict_types=1);

/**
 * KL1 — GII-Norm-XML → §/Abs.-Chunks (Plaintext + Metadaten).
 *
 * Erwartetes Wurzelelement: <dokumente><norm>… (DTD gii-norm).
 */
final class KichelGiiXmlParser
{
    /**
     * @return list<array{
     *   type: string,
     *   law: string,
     *   enbez: string,
     *   absatz: string|null,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   jurabk: string,
     *   gii_slug: string,
     *   fetched_at: string,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }>
     */
    public static function parseFile(
        string $xmlPath,
        string $lawAbbr,
        string $giiSlug,
        ?string $fetchedAt = null
    ): array {
        if (!is_readable($xmlPath)) {
            throw new InvalidArgumentException('GII-XML nicht lesbar: ' . $xmlPath);
        }
        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            throw new RuntimeException('GII-XML Parsefehler: ' . $xmlPath);
        }

        return self::parseDocument($xml, $lawAbbr, $giiSlug, $fetchedAt);
    }

    /**
     * @return list<array{
     *   type: string,
     *   law: string,
     *   enbez: string,
     *   absatz: string|null,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   jurabk: string,
     *   gii_slug: string,
     *   fetched_at: string,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }>
     */
    public static function parseDocument(
        SimpleXMLElement $xml,
        string $lawAbbr,
        string $giiSlug,
        ?string $fetchedAt = null
    ): array {
        $lawAbbr = trim($lawAbbr);
        $giiSlug = trim($giiSlug);
        $fetchedAt = $fetchedAt ?? gmdate('c');
        $chunks = [];

        foreach ($xml->xpath('//norm') ?: [] as $norm) {
            $meta = $norm->metadaten ?? null;
            if ($meta === null) {
                continue;
            }
            $enbez = trim((string) ($meta->enbez ?? ''));
            if (!self::isChunkableEnbez($enbez)) {
                continue;
            }
            $titel = trim((string) ($meta->titel ?? ''));
            $jurabk = self::pickJurabk($meta, $lawAbbr);
            $plain = self::plainFromNorm($norm);
            if ($plain === '') {
                continue;
            }

            $absParts = self::splitAbsaetze($plain);
            if (count($absParts) >= 2) {
                foreach ($absParts as $absKey => $absBody) {
                    $chunks[] = self::makeChunk(
                        $lawAbbr,
                        $giiSlug,
                        $enbez,
                        (string) $absKey,
                        $titel,
                        $absBody,
                        $jurabk,
                        $fetchedAt
                    );
                }
            } else {
                $chunks[] = self::makeChunk(
                    $lawAbbr,
                    $giiSlug,
                    $enbez,
                    null,
                    $titel,
                    $plain,
                    $jurabk,
                    $fetchedAt
                );
            }
        }

        return $chunks;
    }

    public static function isChunkableEnbez(string $enbez): bool
    {
        $enbez = trim($enbez);
        if ($enbez === '') {
            return false;
        }
        if (strcasecmp($enbez, 'Inhaltsübersicht') === 0) {
            return false;
        }

        // Kein \b nach „§“ — § ist Non-Word, Space ebenfalls (sonst Match-Fail).
        return (bool) preg_match('/^(?:§\s|Anlage\b)/u', $enbez);
    }

    /**
     * § 1b → 1b · Anlage 2 → anlage_2
     */
    public static function hrefSuffix(string $enbez): ?string
    {
        $enbez = trim($enbez);
        if (preg_match('/^§\s*(.+)$/u', $enbez, $m)) {
            $id = preg_replace('/\s+/u', '', $m[1]) ?? '';
            $id = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $id);

            return $id !== '' ? '__' . mb_strtolower($id, 'UTF-8') : null;
        }
        if (preg_match('/^Anlage\s+(.+)$/iu', $enbez, $m)) {
            $id = preg_replace('/\s+/u', '_', trim($m[1])) ?? '';

            return $id !== '' ? 'anlage_' . mb_strtolower($id, 'UTF-8') : null;
        }

        return null;
    }

    public static function publicHref(string $giiSlug, string $enbez): ?string
    {
        $suffix = self::hrefSuffix($enbez);
        if ($suffix === null || $giiSlug === '') {
            return null;
        }

        return 'https://www.gesetze-im-internet.de/' . rawurlencode($giiSlug) . '/' . $suffix . '.html';
    }

    /**
     * @return array{
     *   type: string,
     *   law: string,
     *   enbez: string,
     *   absatz: string|null,
     *   title: string,
     *   body: string,
     *   source_label: string,
     *   source_ref: string,
     *   href: string|null,
     *   jurabk: string,
     *   gii_slug: string,
     *   fetched_at: string,
     *   confidence: float,
     *   numbers: array<string, float|int>
     * }
     */
    private static function makeChunk(
        string $lawAbbr,
        string $giiSlug,
        string $enbez,
        ?string $absatz,
        string $titel,
        string $body,
        string $jurabk,
        string $fetchedAt
    ): array {
        $displayEnbez = $absatz !== null ? $enbez . ' Abs. ' . $absatz : $enbez;
        $title = $titel !== ''
            ? $lawAbbr . ' ' . $displayEnbez . ' — ' . $titel
            : $lawAbbr . ' ' . $displayEnbez;

        $refCore = self::normalizeRefEnbez($enbez);
        $sourceRef = 'gii:' . mb_strtolower($lawAbbr, 'UTF-8') . ':' . $refCore;
        if ($absatz !== null) {
            $sourceRef .= ':abs' . $absatz;
        }

        $bodyOut = $lawAbbr . ' ' . $displayEnbez;
        if ($titel !== '') {
            $bodyOut .= ' (' . $titel . ')';
        }
        $bodyOut .= "\n" . $body;
        $bodyOut .= "\nQuelle: konsolidiert GII, Abruf " . $fetchedAt . '.';

        return [
            'type' => KichelRagFact::TYPE_STATUTE,
            'law' => $lawAbbr,
            'enbez' => $enbez,
            'absatz' => $absatz,
            'title' => $title,
            'body' => $bodyOut,
            'source_label' => 'GII ' . $lawAbbr,
            'source_ref' => $sourceRef,
            'href' => self::publicHref($giiSlug, $enbez),
            'jurabk' => $jurabk,
            'gii_slug' => $giiSlug,
            'fetched_at' => $fetchedAt,
            'confidence' => 0.95,
            'numbers' => [],
        ];
    }

    public static function normalizeRefEnbez(string $enbez): string
    {
        $enbez = trim($enbez);
        $enbez = preg_replace('/\s+/u', '', $enbez) ?? $enbez;

        return mb_strtolower($enbez, 'UTF-8');
    }

    private static function pickJurabk(SimpleXMLElement $meta, string $fallback): string
    {
        $last = '';
        foreach ($meta->jurabk as $j) {
            $v = trim((string) $j);
            if ($v !== '') {
                $last = $v;
            }
        }

        return $last !== '' ? $last : $fallback;
    }

    private static function plainFromNorm(SimpleXMLElement $norm): string
    {
        if (!isset($norm->textdaten->text)) {
            return '';
        }
        $parts = [];
        foreach ($norm->textdaten->text->children() as $child) {
            $parts[] = $child->asXML() ?: '';
        }
        $html = implode('', $parts);
        if ($html === '') {
            $html = $norm->textdaten->text->asXML() ?: '';
        }

        return self::htmlToPlain($html);
    }

    public static function htmlToPlain(string $html): string
    {
        $html = preg_replace('/<(br|BR)\s*\/?>/u', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(P|p|DD|dd|DT|dt|LA|la|Title|title)>/u', "\n", $html) ?? $html;
        $html = preg_replace('/<(DT|dt)[^>]*>/u', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<string, string> absatz-nr => text
     */
    public static function splitAbsaetze(string $plain): array
    {
        $plain = trim($plain);
        if ($plain === '') {
            return [];
        }
        if (!preg_match_all('/^\((\d+[a-z]?)\)\s+/um', $plain, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        if (count($matches[0]) < 2) {
            return [];
        }

        $out = [];
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $num = $matches[1][$i][0];
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($plain);
            $slice = trim(substr($plain, $start, $end - $start));
            if ($slice !== '') {
                $out[$num] = $slice;
            }
        }

        return $out;
    }
}
