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

    /** Ausgangsbeleg-Kette (Angebot, Rechnung, …) — nur bei Belegart Einnahmen. */
    public static function voucherTypeSupportsDocumentKind(string $voucherType): bool
    {
        return VoucherRepository::normalizeVoucherType($voucherType) === 'income';
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForVoucherType(string $voucherType): array
    {
        return self::voucherTypeSupportsDocumentKind($voucherType) ? self::options() : [];
    }

    /**
     * Angebot, AB, Lieferschein → keine Journalbuchung (Erlös erst mit Rechnung).
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
     * Bankabgleich / OPOS: Angebot und AB (Anzahlung vor Rechnung) ja, Lieferschein nein.
     */
    public static function allowsOpenItemTracking(string $kind, string $voucherType): bool
    {
        $voucherType = VoucherRepository::normalizeVoucherType($voucherType);
        if ($voucherType !== 'income') {
            return true;
        }

        $kind = self::sanitize($kind);
        if ($kind === '') {
            return true;
        }

        return in_array($kind, [
            self::OFFER,
            self::ORDER_CONFIRMATION,
            self::PARTIAL_INVOICE,
            self::INVOICE,
            self::FINAL_INVOICE,
        ], true);
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

    /**
     * Ausgangsbelege der Kette — Freitext vor/nach Positionen (Kundenansicht).
     */
    public static function usesPositionTexts(string $documentKind, string $voucherType = 'income'): bool
    {
        if (VoucherRepository::normalizeVoucherType($voucherType) !== 'income') {
            return false;
        }

        $kind = self::sanitize($documentKind);
        if ($kind === '') {
            return true;
        }

        return in_array($kind, [
            self::OFFER,
            self::ORDER_CONFIRMATION,
            self::DELIVERY_NOTE,
            self::PARTIAL_INVOICE,
            self::INVOICE,
            self::FINAL_INVOICE,
        ], true);
    }

    public static function defaultPositionIntroText(string $documentKind): string
    {
        return match (self::sanitize($documentKind)) {
            self::OFFER => 'Vielen Dank für Ihre Anfrage. Gerne unterbreiten wir Ihnen folgendes Angebot:',
            self::ORDER_CONFIRMATION => 'Wir bestätigen Ihren Auftrag wie folgt:',
            self::DELIVERY_NOTE => 'Wir liefern Ihnen folgende Artikel bzw. Leistungen:',
            self::PARTIAL_INVOICE => 'Wir berechnen Ihnen folgende Artikel bzw. Dienstleistungen (Abschlagsrechnung):',
            self::FINAL_INVOICE => 'Wir berechnen Ihnen folgende Artikel bzw. Dienstleistungen (Schlussrechnung):',
            default => 'Wir berechnen Ihnen folgende Artikel bzw. Dienstleistungen:',
        };
    }

    /**
     * Standard-Nachbemerkung (Platzhalter {valid_until} für Angebotsgültigkeit).
     */
    public static function defaultPositionFooterText(string $documentKind): string
    {
        return match (self::sanitize($documentKind)) {
            self::OFFER => 'Dieses Angebot ist gültig bis zum {valid_until}. Bis dahin sind die genannten Preise verbindlich.',
            self::ORDER_CONFIRMATION => 'Wir freuen uns auf die Zusammenarbeit und stehen für Rückfragen gerne zur Verfügung.',
            self::DELIVERY_NOTE => 'Bitte prüfen Sie die Lieferung unverzüglich auf Vollständigkeit und Unversehrtheit.',
            default => '',
        };
    }
}
