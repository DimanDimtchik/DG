<?php
declare(strict_types=1);

/**
 * Rechtliche Defaults und Kundentexte für Mehrzweck-Gutscheine.
 *
 * Verjährung: §§ 195, 199 BGB — drei Jahre, Beginn mit Schluss des Ausstellungsjahres
 * (Beispiel: Ausstellung Mai 2026 → Gültig bis 31.12.2029).
 *
 * Steuer: Mehrzweckgutschein — USt erst bei Einlösung (§ 3 Abs. 13–15 UStG).
 * Barauszahlung: kein gesetzlicher Anspruch, außer Leistung dauerhaft unmöglich.
 */
final class GiftVoucherLegal
{
    /** Stabile Artikelnummer für den System-Katalogartikel „Mehrzweck-Gutschein“. */
    public const CATALOG_ARTICLE_NUMBER = 'GUT-MEHRZWECK';

    public const CATALOG_ARTICLE_TITLE = 'Mehrzweck-Gutschein';

    /**
     * Standard-Ablaufdatum nach §§ 195, 199 BGB (31.12. des Jahres Ausstellung+3).
     *
     * @param string|null $issueDate Y-m-d oder null = heute
     */
    public static function defaultValidUntil(?string $issueDate = null): string
    {
        $base = self::parseDate($issueDate) ?? new DateTimeImmutable('today');
        $year = (int) $base->format('Y') + 3;

        return sprintf('%04d-12-31', $year);
    }

    /** Anzeigeformat TT.MM.JJJJ. */
    public static function formatValidUntilDisplay(string $validUntil): string
    {
        $dt = self::parseDate($validUntil);
        if ($dt === null) {
            return trim($validUntil);
        }

        return $dt->format('d.m.Y');
    }

    public static function taxHint(): string
    {
        return 'Mehrzweckgutschein: Beim Verkauf fällt keine Umsatzsteuer an; '
            . 'die USt entsteht erst bei Einlösung nach dem dann geltenden Steuersatz der Leistung '
            . '(§ 3 Abs. 13–15 UStG).';
    }

    public static function cashPayoutHint(): string
    {
        return 'Es besteht kein gesetzlicher Anspruch auf Barauszahlung des Gutscheinwerts, '
            . 'es sei denn, die geschuldete Leistung kann dauerhaft nicht mehr erbracht werden '
            . '(z. B. Geschäftsaufgabe ohne Insolvenz).';
    }

    public static function validityHint(string $validUntil): string
    {
        $display = self::formatValidUntilDisplay($validUntil);
        if ($display === '') {
            return 'Gültigkeit richtet sich nach der gesetzlichen Verjährungsfrist (§§ 195, 199 BGB).';
        }

        return 'Gültig bis ' . $display
            . ' (gesetzliche Verjährungsfrist von drei Jahren ab Schluss des Ausstellungsjahres, §§ 195, 199 BGB).';
    }

    /**
     * Kompakter Kundentext für Flyer / Rechnungsblock.
     */
    public static function customerFacingNotes(string $validUntil): string
    {
        return self::validityHint($validUntil)
            . ' ' . self::taxHint()
            . ' ' . self::cashPayoutHint();
    }

    /**
     * Beschreibungstext für den Katalogartikel (ohne konkreten Code).
     */
    public static function catalogArticleDescription(?string $issueDate = null): string
    {
        $validUntil = self::defaultValidUntil($issueDate);

        return 'Geschenkgutschein / Mehrzweckgutschein (variabler Nennwert). '
            . self::validityHint($validUntil) . ' '
            . self::taxHint() . ' '
            . self::cashPayoutHint();
    }

    /**
     * Positionsbezeichnung auf der Rechnung (Artikel Gutschein).
     */
    public static function invoiceLineDescription(string $title, string $code, string $validUntil): string
    {
        $title = trim($title);
        if ($title === '') {
            $title = 'Mehrzweck-Gutschein';
        }
        $parts = [$title];
        $code = strtoupper(trim($code));
        if ($code !== '') {
            $parts[] = 'Nr. ' . $code;
        }
        $display = self::formatValidUntilDisplay($validUntil);
        if ($display !== '') {
            $parts[] = 'Gültig bis ' . $display . ' (§§ 195, 199 BGB)';
        }
        $line = implode(' — ', $parts);

        return $line . '. ' . self::taxHint() . ' ' . self::cashPayoutHint();
    }

    private static function parseDate(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt instanceof DateTimeImmutable ? $dt : null;
    }
}
