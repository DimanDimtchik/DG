<?php
declare(strict_types=1);

/**
 * Standardkonten für das Buchungsjournal (Gegenkonten, Steuerkonten, Saldenvortrag)
 * je Kontenrahmen (SKR03/SKR04). Bewusst schlank gehalten für die EÜR-Logik des CRM.
 */
final class LedgerAccounts
{
        /**
     * Erlösrichtung (Ertrag): Einnahme, Einnahmenminderung, Kundengutschrift.
     * @param string $voucherType Belegtyp
     * @return bool
     */
    public static function isIncomeDirection(string $voucherType): bool
    {
        return in_array(
            VoucherRepository::normalizeVoucherType($voucherType),
            ['income', 'income_reduction', 'credit'],
            true
        );
    }

        /**
     * Geldkonto / Gegenkonto je Zahlungsart und Belegrichtung.
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @param string $voucherType Belegtyp
     * @param string $paymentStatus Zahlungsstatus
     * @return string
     */
    public static function contraAccount(string $skrType, string $voucherType, string $paymentStatus): string
    {
        $skr = ChartOfAccountsSettings::sanitizeSkrType($skrType);
        $isIncome = self::isIncomeDirection($voucherType);
        $kind = VoucherPaymentStatus::settlementKind($paymentStatus);

        // Kasse / Bank / Privat sind richtungsunabhängig.
        $privateAccounts = VoucherPaymentStatus::privateSettlementAccount() ?? ['skr03' => '1371', 'skr04' => '1486'];
        $shared = match ($skr) {
            'skr04' => ['cash' => '1600', 'bank' => '1800', 'private' => $privateAccounts['skr04']],
            default => ['cash' => '1000', 'bank' => '1200', 'private' => $privateAccounts['skr03']],
        };

        if ($kind === 'cash') {
            return $shared['cash'];
        }
        if ($kind === 'bank_debit') {
            return $shared['bank'];
        }
        if ($kind === 'private') {
            return $shared['private'];
        }

        // 'none' = offener Posten → Forderung (Einnahme) bzw. Verbindlichkeit (Ausgabe).
        return match ($skr) {
            'skr04' => $isIncome ? '1200' : '3300',
            default => $isIncome ? '1400' : '1600',
        };
    }

        /**
     * Steuerkonto (Vorsteuer bei Ausgabe, Umsatzsteuer bei Einnahme) je Satz.
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @param string $voucherType Belegtyp
     * @param int $taxRate Steuersatz in Prozent
     * @return string
     */
    public static function taxAccount(string $skrType, string $voucherType, int $taxRate): string
    {
        $skr = ChartOfAccountsSettings::sanitizeSkrType($skrType);
        $isIncome = self::isIncomeDirection($voucherType);

        if ($skr === 'skr04') {
            if ($isIncome) {
                return $taxRate === 7 ? '3801' : '3806';
            }

            return $taxRate === 7 ? '1401' : '1406';
        }

        if ($isIncome) {
            return $taxRate === 7 ? '1771' : '1776';
        }

        return $taxRate === 7 ? '1571' : '1576';
    }

        /**
     * Sammelkonto für Saldenvorträge (Eröffnungsbilanzwerte).
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return string
     */
    public static function carryForwardAccount(string $skrType): string
    {
        return ChartOfAccountsSettings::sanitizeSkrType($skrType) === 'skr04' ? '9000' : '9000';
    }

        /**
     * Buchungsseite der Primärzeile (Aufwand/Ertrag) je Belegart.
     * @param string $voucherType Belegtyp
     * @return 'debit'|'credit'
     */
    public static function primarySide(string $voucherType): string
    {
        return match (VoucherRepository::normalizeVoucherType($voucherType)) {
            'income' => 'credit',
            'income_reduction' => 'debit',
            'credit' => 'debit',        // Kundengutschrift mindert Ertrag
            'expense_reduction' => 'credit', // mindert Aufwand
            default => 'debit',          // expense
        };
    }

    /**
     * Liefert die gegenüberliegende Buchungsseite
     * @param string $side Buchungsseite
     * @return string
     */
    public static function oppositeSide(string $side): string
    {
        return $side === 'debit' ? 'credit' : 'debit';
    }

        /**
     * Ist das Konto ein Bestandskonto (Aktiva/Passiva → Saldenvortrag ins Folgejahr)?
     * @param string $section Kontenabschnitt
     * @return bool
     */
    public static function isBalanceSheetSection(string $section): bool
    {
        return in_array($section, ['aktiva', 'passiva'], true);
    }

        /**
     * Rechnungsabgrenzungskonten (ARAP/PRAP) über alle Kontenrahmen.
     * @return array
     */
    public static function accrualAccounts(): array
    {
        return ['0980', '0990', '1900', '3900'];
    }

        /**
     * Konto ins Folgejahr vortragen? Bestandskonten + Rechnungsabgrenzung
     * @param string $account Kontonummer
     * @param string $section Kontenabschnitt
     * @return bool
     */
    public static function carriesForward(string $account, string $section): bool
    {
        if (self::isBalanceSheetSection($section)) {
            return true;
        }

        return in_array($account, self::accrualAccounts(), true);
    }

        /**
     * Ist das Konto ein Erfolgskonto (Aufwand/Ertrag → GuV, kein Vortrag)?
     * @param string $section Kontenabschnitt
     * @return bool
     */
    public static function isProfitLossSection(string $section): bool
    {
        return in_array($section, ['aufwand', 'ertrag'], true);
    }
}
