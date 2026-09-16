<?php
declare(strict_types=1);

/** Lagerplatz Positionscode: Ort-Halle-Regal-Platz */
final class StockPositionCode
{
    /**
     * @return array{ort: string, halle: string, regal: string, platz: string}
     */
    public static function fromInput(array $input): array
    {
        return [
            'ort' => self::sanitizeSegment((string) ($input['stock_ort'] ?? '')),
            'halle' => self::sanitizeSegment((string) ($input['stock_halle'] ?? '')),
            'regal' => self::sanitizeSegment((string) ($input['stock_regal'] ?? '')),
            'platz' => self::sanitizeSegment((string) ($input['stock_platz'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): string
    {
        return self::compose(
            (string) ($row['stock_ort'] ?? ''),
            (string) ($row['stock_halle'] ?? ''),
            (string) ($row['stock_regal'] ?? ''),
            (string) ($row['stock_platz'] ?? ''),
        );
    }

    public static function compose(string $ort, string $halle, string $regal, string $platz): string
    {
        return self::composeSegments([
            self::sanitizeSegment($ort),
            self::sanitizeSegment($halle),
            self::sanitizeSegment($regal),
            self::sanitizeSegment($platz),
        ]);
    }

    /** @param list<string> $segments */
    public static function composeSegments(array $segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            $segment = self::sanitizeSegment($segment);
            if ($segment !== '') {
                $parts[] = $segment;
            }
        }

        return $parts !== [] ? implode('-', $parts) : '';
    }

    public static function sanitizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s+/u', '', $value) ?? '';
        $value = preg_replace('/[^0-9A-Za-zÄÖÜäöüß._]/u', '', $value) ?? '';

        return mb_substr($value, 0, 32, 'UTF-8');
    }

    /**
     * @param array{ort: string, halle: string, regal: string, platz: string} $parts
     */
    public static function assertCompleteIfAny(array $parts): void
    {
        $filled = 0;
        foreach ($parts as $part) {
            if ($part !== '') {
                ++$filled;
            }
        }
        if ($filled === 0) {
            return;
        }
        foreach (['ort' => 'Ort', 'halle' => 'Halle', 'regal' => 'Regal', 'platz' => 'Platz'] as $key => $label) {
            if ($parts[$key] === '') {
                throw new InvalidArgumentException('Positionscode unvollständig — bitte ' . $label . ' ausfüllen (Ort-Halle-Regal-Platz).');
            }
        }
    }
}
