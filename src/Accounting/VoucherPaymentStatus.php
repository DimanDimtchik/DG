<?php
declare(strict_types=1);

/**
 * Zahlungsstatus für Belege — Lexoffice-Parität (Weiterverarbeitung / OPOS / Banking).
 */
final class VoucherPaymentStatus
{
    public const OPEN = 'open';
    public const PARTIAL = 'partial';
    public const CASH = 'cash';
    public const PRIVATE = 'private';
    public const DIRECT_DEBIT = 'direct_debit';
    public const BANK = 'bank';
    public const TIP = 'tip';

    /**
     * Liefert Auswahloptionen.
     *
     * @return array<string, string>
     */
        public static function options(): array
    {
        return [
            self::OPEN => 'Offen',
            self::PARTIAL => 'Teilweise bezahlt',
            self::CASH => 'Per Kasse bezahlt',
            self::TIP => 'Trinkgeld (Durchlaufende Posten)',
            self::PRIVATE => 'Privat bezahlt',
            self::DIRECT_DEBIT => 'Wird abgebucht',
            self::BANK => 'Per Überweisung bezahlt',
        ];
    }

    /**
     * Bereinigt und validiert den Eingabewert
     * @param string $status Statuswert
     * @return string
     */
    public static function sanitize(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'paid') {
            return self::CASH;
        }

        return isset(self::options()[$status]) ? $status : self::OPEN;
    }

    /**
     * Liefert die Anzeigebezeichnung
     * @param string $status Statuswert
     * @return string
     */
    public static function label(string $status): string
    {
        return self::options()[self::sanitize($status)] ?? $status;
    }

    /**
     * Liefert einen Hilfetext
     * @param string $status Statuswert
     * @return string
     */
    public static function hint(string $status): string
    {
        return match (self::sanitize($status)) {
            self::OPEN => 'Zahlung steht noch aus — Beleg erscheint bei offenen Posten.',
            self::PARTIAL => 'Teilzahlung erfasst — Restbetrag bleibt offen (OPOS).',
            self::CASH => 'Barzahlung über die Kasse — Beleg gilt als bezahlt, Kassenbuch-Buchung vorgesehen.',
            self::TIP => 'Trinkgeld an Mitarbeiter/Dritte aus der Kasse — Buchung auf Durchlaufende Posten (z. B. 1590), Kassenbuch-Ausgang.',
            self::PRIVATE => 'Privat bezahlt — Verrechnungskonto EÜR (z. B. 1371) statt Geschäftskonto.',
            self::DIRECT_DEBIT => 'Lastschrift erwartet — wird beim Bankumsatz automatisch zugeordnet und als bezahlt markiert.',
            self::BANK => 'Überweisung ausgeführt — Beleg ist bezahlt, Gegenkonto Bank.',
            default => '',
        };
    }

        /**
     * Noch keine Zahlung erfolgt (offener Posten).
     * @param string $status Statuswert
     * @return bool
     */
    public static function isOpen(string $status): bool
    {
        return self::sanitize($status) === self::OPEN;
    }

        /**
     * Zahlung abgeschlossen (Kasse oder privat).
     * @param string $status Statuswert
     * @return bool
     */
    public static function isSettled(string $status): bool
    {
        return in_array(self::sanitize($status), [self::CASH, self::PRIVATE, self::BANK, self::TIP], true);
    }

        /**
     * Lastschrift / Bankeinzug erwartet — noch nicht mit Kontoumsatz verknüpft.
     * @param string $status Statuswert
     * @return bool
     */
    public static function expectsBankDebit(string $status): bool
    {
        return self::sanitize($status) === self::DIRECT_DEBIT;
    }

        /**
     * Für Auswertungen „offene Verbindlichkeiten“ (noch zu zahlen).
     * @param string $status Statuswert
     * @return bool
     */
    public static function countsAsOpenPayable(string $status): bool
    {
        return in_array(self::sanitize($status), [self::OPEN, self::PARTIAL, self::DIRECT_DEBIT], true);
    }

      /**
     * Buchhalterische Zahlungsart für spätere Automatik (Kasse, EÜR-Verrechnung, Banking).
     * @param string $status Statuswert
     * @return 'none'|'cash'|'private'|'bank_debit'
     */
    public static function settlementKind(string $status): string
    {
        return match (self::sanitize($status)) {
            self::CASH, self::TIP => 'cash',
            self::PRIVATE => 'private',
            self::DIRECT_DEBIT => 'bank_debit',
            self::BANK => 'bank_debit',
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

    /**
     * Liefert CSS-Badge-Klasse für den Status
     * @param string $status Statuswert
     * @return string
     */
    public static function badgeClass(string $status): string
    {
        return match (self::sanitize($status)) {
            self::CASH, self::PRIVATE, self::BANK, self::TIP => 'dg-badge--ok',
            self::PARTIAL => 'dg-badge--pending',
            self::DIRECT_DEBIT => 'dg-badge--pending',
            default => 'dg-badge--muted',
        };
    }
}
