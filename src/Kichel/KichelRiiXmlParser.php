<?php
declare(strict_types=1);

/**
 * KL5 — RII-Entscheidungs-XML → caselaw-Chunks (Pilot).
 *
 * DTD: rii-dok.dtd · Wurzel <dokument>.
 */
final class KichelRiiXmlParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function parseFile(string $xmlPath, ?string $fetchedAt = null): array
    {
        if (!is_readable($xmlPath)) {
            throw new InvalidArgumentException('RII-XML nicht lesbar: ' . $xmlPath);
        }
        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            throw new RuntimeException('RII-XML Parsefehler: ' . $xmlPath);
        }

        return self::parseDocument($xml, $fetchedAt);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseDocument(SimpleXMLElement $xml, ?string $fetchedAt = null): array
    {
        $fetchedAt = $fetchedAt ?? gmdate('c');
        $doknr = trim((string) ($xml->doknr ?? ''));
        $gertyp = trim((string) ($xml->gertyp ?? ''));
        $spruch = trim((string) ($xml->spruchkoerper ?? ''));
        $court = trim($gertyp . ($spruch !== '' ? ' ' . $spruch : ''));
        $date = trim((string) ($xml->{'entsch-datum'} ?? ''));
        $az = trim((string) ($xml->aktenzeichen ?? ''));
        $doktyp = trim((string) ($xml->doktyp ?? ''));
        $norm = self::plainField($xml->norm ?? null);
        $titel = self::plainField($xml->titelzeile ?? null);
        $leitsatz = self::plainField($xml->leitsatz ?? null);
        $tenor = self::plainField($xml->tenor ?? null);
        $identifier = trim((string) ($xml->identifier ?? ''));

        if ($doknr === '' && $az === '') {
            return [];
        }
        if ($titel === '' && $leitsatz === '' && $tenor === '') {
            return [];
        }

        $bodyParts = [];
        if ($leitsatz !== '') {
            $bodyParts[] = "Leitsatz:\n" . $leitsatz;
        }
        if ($tenor !== '') {
            $bodyParts[] = "Tenor:\n" . $tenor;
        }
        if ($bodyParts === [] && $titel !== '') {
            $bodyParts[] = $titel;
        }
        $meta = [];
        if ($court !== '') {
            $meta[] = $court;
        }
        if ($az !== '') {
            $meta[] = 'Az. ' . $az;
        }
        if ($date !== '') {
            $meta[] = 'Datum ' . self::formatDate($date);
        }
        if ($doktyp !== '') {
            $meta[] = $doktyp;
        }
        if ($norm !== '') {
            $meta[] = 'Normen: ' . $norm;
        }
        $body = implode("\n\n", $bodyParts);
        if ($meta !== []) {
            $body .= "\n\n" . implode(' · ', $meta);
        }
        $body .= "\nQuelle: RII (amtliche Entscheidung), Abruf " . $fetchedAt . '.';

        $title = $court !== '' ? $court : 'Entscheidung';
        if ($az !== '') {
            $title .= ' — ' . $az;
        }
        if ($titel !== '') {
            $title .= ' — ' . $titel;
        }

        $refId = $doknr !== '' ? $doknr : preg_replace('/\s+/', '', $az);
        $courtKey = mb_strtolower($gertyp !== '' ? $gertyp : 'gericht', 'UTF-8');

        return [[
            'type' => KichelRagFact::TYPE_CASELAW,
            'court' => $court,
            'gertyp' => $gertyp,
            'aktenzeichen' => $az,
            'date' => $date,
            'doknr' => $doknr,
            'doktyp' => $doktyp,
            'norm' => $norm,
            'title' => $title,
            'body' => $body,
            'source_label' => 'RII ' . ($gertyp !== '' ? $gertyp : 'Entscheidung'),
            'source_ref' => 'rii:' . $courtKey . ':' . mb_strtolower((string) $refId, 'UTF-8'),
            'href' => $identifier !== '' ? $identifier : null,
            'fetched_at' => $fetchedAt,
            'confidence' => 0.9,
            'numbers' => [],
        ]];
    }

    public static function matchesTopicFilter(array $chunk, ?array $needles = null): bool
    {
        $needles = $needles ?? KichelRiiImporter::TOPIC_NEEDLES;
        $hay = mb_strtolower(
            (string) ($chunk['norm'] ?? '') . "\n" . (string) ($chunk['title'] ?? '') . "\n" . (string) ($chunk['body'] ?? ''),
            'UTF-8'
        );
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private static function plainField(mixed $node): string
    {
        if ($node === null) {
            return '';
        }
        if ($node instanceof SimpleXMLElement) {
            $xml = $node->asXML() ?: '';
            $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace("\xc2\xa0", ' ', $text);
            $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
            $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

            return trim($text);
        }

        return trim((string) $node);
    }

    public static function formatDate(string $yyyymmdd): string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $yyyymmdd, $m)) {
            return $m[3] . '.' . $m[2] . '.' . $m[1];
        }

        return $yyyymmdd;
    }
}
