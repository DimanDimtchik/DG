<?php
declare(strict_types=1);

/** Ausgangsbeleg-Typen in der Verkaufskette (Angebot → … → Schlussrechnung). */
final class VoucherDocumentKind
{
    public const OFFER = 'offer';
    public const ORDER_CONFIRMATION = 'order_confirmation';
    public const DELIVERY_NOTE = 'delivery_note';
    public const PARTIAL_INVOICE = 'partial_invoice';
    public const INVOICE = 'invoice';
    public const FINAL_INVOICE = 'final_invoice';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::OFFER => 'Angebot',
            self::ORDER_CONFIRMATION => 'Auftragsbestätigung',
            self::DELIVERY_NOTE => 'Lieferschein',
            self::PARTIAL_INVOICE => 'Abschlagsrechnung',
            self::INVOICE => 'Rechnung',
            self::FINAL_INVOICE => 'Schlussrechnung',
        ];
    }

    public static function sanitize(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return isset(self::options()[$kind]) ? $kind : '';
    }

    public static function label(string $kind): string
    {
        $kind = self::sanitize($kind);

        return $kind !== '' ? (self::options()[$kind] ?? $kind) : '';
    }

    /**
     * Nur für Einnahmen-Belege relevant.
     */
    public static function isSalesKind(string $kind): bool
    {
        return self::sanitize($kind) !== '';
    }

    /**
     * Angebot, AB, Lieferschein → keine Journalbuchung / kein OPOS.
     */
    public static function isBookable(string $kind, string $voucherType): bool
    {
        $voucherType = VoucherRepository::normalizeVoucherType($voucherType);
        if ($voucherType !== 'income') {
            return true;
        }

        $kind = self::sanitize($kind);
        if ($kind === '') {
            return true;
        }

        return in_array($kind, [self::PARTIAL_INVOICE, self::INVOICE, self::FINAL_INVOICE], true);
    }

  /**
     * Nummernkreis-Schlüssel (NumberRangeSettings).
     */
    public static function numberRangeType(string $kind): ?string
    {
        $kind = self::sanitize($kind);

        return match ($kind) {
            self::OFFER => 'offer',
            self::ORDER_CONFIRMATION => 'order_confirmation',
            self::DELIVERY_NOTE => 'delivery_note',
            self::PARTIAL_INVOICE => 'partial_invoice',
            self::INVOICE => 'invoice',
            self::FINAL_INVOICE => 'final_invoice',
            default => null,
        };
    }

    /**
     * Mögliche Folgebelege je aktuellem Typ.
     *
     * @return list<string>
     */
    public static function followUpKinds(string $kind): array
    {
        $kind = self::sanitize($kind);

        return match ($kind) {
            self::OFFER => [self::ORDER_CONFIRMATION, self::DELIVERY_NOTE, self::PARTIAL_INVOICE, self::INVOICE, self::FINAL_INVOICE],
            self::ORDER_CONFIRMATION => [self::DELIVERY_NOTE, self::PARTIAL_INVOICE, self::INVOICE, self::FINAL_INVOICE],
            self::DELIVERY_NOTE => [self::PARTIAL_INVOICE, self::INVOICE, self::FINAL_INVOICE],
            self::PARTIAL_INVOICE => [self::PARTIAL_INVOICE, self::FINAL_INVOICE],
            self::INVOICE => [],
            self::FINAL_INVOICE => [],
            default => [self::OFFER, self::INVOICE],
        };
    }

    /**
     * Sortierreihenfolge in der Kette (kleiner = früher).
     */
    public static function sortOrder(string $kind): int
    {
        return match (self::sanitize($kind)) {
            self::OFFER => 10,
            self::ORDER_CONFIRMATION => 20,
            self::DELIVERY_NOTE => 30,
            self::PARTIAL_INVOICE => 40,
            self::INVOICE => 50,
            self::FINAL_INVOICE => 60,
            default => 99,
        };
    }

    public static function defaultForIncome(): string
    {
        return self::INVOICE;
    }
}
