<?php
declare(strict_types=1);

/**
 * Zahlungsstatus für Belege — Lexoffice-Parität (Weiterverarbeitung / OPOS / Banking).
 */
final class VoucherPaymentStatus
{
    public const OPEN = 'open';
    public const CASH = 'cash';
    public const PRIVATE = 'private';
    public const DIRECT_DEBIT = 'direct_debit';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::OPEN => 'Offen',
            self::CASH => 'Per Kasse bezahlt',
            self::PRIVATE => 'Privat bezahlt',
            self::DIRECT_DEBIT => 'Wird abgebucht',
        ];
    }

    public static function sanitize(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'paid') {
            return self::CASH;
        }

        return isset(self::options()[$status]) ? $status : self::OPEN;
    }

    public static function label(string $status): string
    {
        return self::options()[self::sanitize($status)] ?? $status;
    }

    public static function hint(string $status): string
    {
        return match (self::sanitize($status)) {
            self::OPEN => 'Zahlung steht noch aus — Beleg erscheint bei offenen Posten.',
            self::CASH => 'Barzahlung über die Kasse — Beleg gilt als bezahlt, Kassenbuch-Buchung vorgesehen.',
            self::PRIVATE => 'Privat bezahlt — Verrechnungskonto EÜR (z. B. 1371) statt Geschäftskonto.',
            self::DIRECT_DEBIT => 'Lastschrift erwartet — wird beim Bankumsatz automatisch zugeordnet und als bezahlt markiert.',
            default => '',
        };
    }

    /** Noch keine Zahlung erfolgt (offener Posten). */
    public static function isOpen(string $status): bool
    {
        return self::sanitize($status) === self::OPEN;
    }

    /** Zahlung abgeschlossen (Kasse oder privat). */
    public static function isSettled(string $status): bool
    {
        return in_array(self::sanitize($status), [self::CASH, self::PRIVATE], true);
    }

    /** Lastschrift / Bankeinzug erwartet — noch nicht mit Kontoumsatz verknüpft. */
    public static function expectsBankDebit(string $status): bool
    {
        return self::sanitize($status) === self::DIRECT_DEBIT;
    }

    /** Für Auswertungen „offene Verbindlichkeiten“ (noch zu zahlen). */
    public static function countsAsOpenPayable(string $status): bool
    {
        return in_array(self::sanitize($status), [self::OPEN, self::DIRECT_DEBIT], true);
    }

  /**
   * Buchhalterische Zahlungsart für spätere Automatik (Kasse, EÜR-Verrechnung, Banking).
   *
   * @return 'none'|'cash'|'private'|'bank_debit'
   */
    public static function settlementKind(string $status): string
    {
        return match (self::sanitize($status)) {
            self::CASH => 'cash',
            self::PRIVATE => 'private',
            self::DIRECT_DEBIT => 'bank_debit',
            default => 'none',
        };
    }

    /**
     * SKR-Verrechnungskonto bei Zahlart „Privat“ (Lexoffice ab 2024).
     *
     * @return array{skr03: string, skr04: string}|null
     */
    public static function privateSettlementAccount(): array
    {
        return ['skr03' => '1371', 'skr04' => '1486'];
    }

    public static function badgeClass(string $status): string
    {
        return match (self::sanitize($status)) {
            self::CASH, self::PRIVATE => 'dg-badge--ok',
            self::DIRECT_DEBIT => 'dg-badge--pending',
            default => 'dg-badge--muted',
        };
    }
}
