<?php
declare(strict_types=1);

/**
 * Lohn L4c: Ausgabe Brutto→Netto.
 */
final class PayrollCalcResult
{
    /**
     * @param array<string, float|int|string|bool|list> $amounts Katalog-Keys (EUR, 2 Dezimalen) + Meta
     * @param list<string> $warnings
     * @param list<array<string, mixed>> $lines
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $engine,
        public readonly ?int $rateSetId,
        public readonly array $amounts,
        public readonly array $warnings,
        public readonly array $lines,
        public readonly string $path,
    ) {
    }

    public function amount(string $key): float
    {
        return (float) ($this->amounts[$key] ?? 0.0);
    }
}
