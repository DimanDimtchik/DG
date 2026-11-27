<?php
declare(strict_types=1);

/**
 * BO5b — Katalog-Match-Vorschläge für Beleg-Extrakt-Zeilen (Nummer/Titel).
 * Kein Speichern; Caller zeigt Liste zur Bestätigung.
 */
final class VoucherArticleMatchService
{
    /**
     * @param array{title?: string, sku?: string, catalog_kind?: string} $line
     * @return list<array<string, mixed>>
     */
    public static function candidatesForLine(array $line, int $limit = 5): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $title = trim((string) ($line['title'] ?? $line['description'] ?? ''));
        $sku = trim((string) ($line['sku'] ?? $line['article_number'] ?? ''));
        $kind = CalendarArticleCatalog::normalizeKind((string) ($line['catalog_kind'] ?? ''));
        if ($title === '' && $sku === '') {
            return [];
        }

        /** @var array<int, array<string, mixed>> $byId */
        $byId = [];

        if ($sku !== '') {
            $id = CalendarArticleRepository::findIdByArticleNumber($sku);
            if ($id !== null) {
                self::addCandidate($byId, $id, 1.0, 'Artikelnummer');
            }
        }
        if ($title !== '') {
            $exactId = CalendarArticleRepository::findIdByTitle($title);
            if ($exactId !== null) {
                self::addCandidate($byId, $exactId, 0.95, 'Titel exakt');
            }
            $importId = CalendarArticleRepository::findIdByTitleForImport($title);
            if ($importId !== null) {
                self::addCandidate($byId, $importId, 0.9, 'Titel (Import)');
            }
        }

        $query = $sku !== '' ? $sku : $title;
        foreach (VoucherIncomePositions::searchArticles($query, 12) as $hit) {
            $id = (int) ($hit['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $hitKind = CalendarArticleCatalog::normalizeKind((string) ($hit['catalog_kind'] ?? ''));
            $score = 0.55;
            if ($sku !== '' && strcasecmp((string) ($hit['article_number'] ?? ''), $sku) === 0) {
                $score = 1.0;
            } elseif ($title !== '' && strcasecmp((string) ($hit['title'] ?? ''), $title) === 0) {
                $score = 0.95;
            } elseif ($kind !== '' && $hitKind === $kind) {
                $score = 0.65;
            }
            self::addCandidate($byId, $id, $score, 'Suche', $hit);
        }

        if ($kind !== '') {
            foreach ($byId as $id => $row) {
                $rowKind = CalendarArticleCatalog::normalizeKind((string) ($row['catalog_kind'] ?? ''));
                if ($rowKind !== '' && $rowKind !== $kind) {
                    $byId[$id]['score'] = round(max(0.2, (float) $row['score'] - 0.15), 2);
                }
            }
        }

        $list = array_values($byId);
        usort($list, static function (array $a, array $b): int {
            $cmp = ((float) $b['score']) <=> ((float) $a['score']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        return array_slice($list, 0, $limit);
    }

    /**
     * BO5c — Katalog-Neuanlage aus Extrakt-Zeile. Nur nach User-Klick aufrufen.
     * track_stock aus → kein Wareneingang/Anfangsbestand.
     *
     * @param array<string, mixed> $line
     * @return array<string, mixed> Artikel-Payload inkl. score/match_reason
     */
    public static function createFromExtractLine(array $line, ?string $forceKind = null): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $title = trim((string) ($line['title'] ?? $line['description'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Bezeichnung fehlt — Neuanlage nicht möglich.');
        }

        $kind = CalendarArticleCatalog::normalizeKind(
            (string) ($forceKind ?? $line['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE)
        );
        $sku = trim((string) ($line['sku'] ?? $line['article_number'] ?? ''));
        $articleNumber = '';
        if ($sku !== '' && CalendarArticleRepository::findIdByArticleNumber($sku) === null) {
            $articleNumber = $sku;
        }

        $taxRate = (int) ($line['tax_percent'] ?? $line['tax_rate'] ?? 19);
        if (!in_array($taxRate, [0, 7, 19], true)) {
            $taxRate = 19;
        }
        $taxType = VoucherIncomePositions::taxTypeFromRate($taxRate);
        $unit = trim((string) ($line['unit'] ?? 'Stück')) ?: 'Stück';

        $id = CalendarArticleRepository::save([
            'catalog_kind' => $kind,
            'article_number' => $articleNumber,
            'title' => $title,
            'description' => '',
            'note' => 'Aus Beleg-Vorschlag angelegt — bitte prüfen.',
            'unit' => $unit,
            'tax_type' => $taxType,
            'price_gross' => 0,
            'work_minutes' => $kind === CalendarArticleCatalog::KIND_SERVICE ? 15 : 0,
            'area_id' => 0,
            'sort_order' => 0,
            'is_active' => 1,
            'price_list_online' => 0,
            'track_stock' => 0,
            'import' => 1,
        ]);

        $row = CalendarArticleRepository::findById($id);
        if ($row === null) {
            throw new RuntimeException('Artikel wurde angelegt, konnte aber nicht geladen werden.');
        }
        $payload = VoucherIncomePositions::articlePayload($row);
        $payload['score'] = 1.0;
        $payload['match_reason'] = 'Neu angelegt';

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<array{line_no: int, title: string, catalog_kind: string, sku: string, candidates: list<array<string, mixed>>}>
     */
    public static function candidatesForLines(array $lines, int $perLine = 5): array
    {
        $out = [];
        $lineNo = 0;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $title = trim((string) ($line['title'] ?? $line['description'] ?? ''));
            if ($title === '') {
                continue;
            }
            $lineNo++;
            $sku = trim((string) ($line['sku'] ?? ''));
            $kind = CalendarArticleCatalog::normalizeKind((string) ($line['catalog_kind'] ?? 'service'));
            $out[] = [
                'line_no' => (int) ($line['line_no'] ?? $lineNo),
                'title' => $title,
                'catalog_kind' => $kind,
                'sku' => $sku,
                'candidates' => self::candidatesForLine([
                    'title' => $title,
                    'sku' => $sku,
                    'catalog_kind' => $kind,
                ], $perLine),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $byId
     * @param array<string, mixed>|null $payload
     */
    private static function addCandidate(array &$byId, int $id, float $score, string $reason, ?array $payload = null): void
    {
        if ($id < 1) {
            return;
        }
        if ($payload === null) {
            $row = CalendarArticleRepository::findById($id);
            if ($row === null) {
                return;
            }
            $payload = VoucherIncomePositions::articlePayload($row);
        }
        if (isset($byId[$id])) {
            if ($score > (float) $byId[$id]['score']) {
                $byId[$id]['score'] = $score;
                $byId[$id]['match_reason'] = $reason;
            }

            return;
        }
        $byId[$id] = [
            'id' => (int) ($payload['id'] ?? $id),
            'catalog_kind' => (string) ($payload['catalog_kind'] ?? ''),
            'kind_label' => (string) ($payload['kind_label'] ?? ''),
            'article_number' => (string) ($payload['article_number'] ?? ''),
            'title' => (string) ($payload['title'] ?? ''),
            'unit' => (string) ($payload['unit'] ?? 'Stück'),
            'tax_rate' => (int) ($payload['tax_rate'] ?? 19),
            'price_gross' => (float) ($payload['price_gross'] ?? 0),
            'track_stock' => !empty($payload['track_stock']),
            'score' => round($score, 2),
            'match_reason' => $reason,
        ];
    }
}
