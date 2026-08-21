<?php
declare(strict_types=1);

/**
 * Sperrgründe für SaaS-Kunden (KDV / Shop-Konto).
 * auto_reject = Entsperr-Bitte im Shop wird abgelehnt, kein Support-Mail.
 */
final class KdvBlockReasons
{
    /**
     * @return array<string, array{label: string, customer_message: string, auto_reject: bool}>
     */
    public static function all(): array
    {
        return [
            'unpaid_invoice' => [
                'label' => 'Unbezahlte Rechnung',
                'customer_message' => 'Ihr Account ist wegen einer unbezahlten Rechnung gesperrt. Die Entsperrung erfolgt automatisch, sobald der Zahlungseingang gebucht ist.',
                'auto_reject' => true,
            ],
            'dunning_open' => [
                'label' => 'Offene Mahnung',
                'customer_message' => 'Ihr Account ist wegen einer offenen Mahnung gesperrt. Bitte begleichen Sie die offenen Posten; danach wird die Sperre automatisch aufgehoben.',
                'auto_reject' => true,
            ],
            'card_failed' => [
                'label' => 'Abbuchung fehlgeschlagen',
                'customer_message' => 'Die letzte Abbuchung ist fehlgeschlagen. Bitte aktualisieren Sie Ihr Zahlungsmittel. Die Entsperrung erfolgt automatisch nach erfolgreicher Zahlung.',
                'auto_reject' => true,
            ],
            'trial_ended' => [
                'label' => 'Testphase beendet',
                'customer_message' => 'Ihre Testphase ist beendet. Bitte wählen Sie einen Tarif und schließen Sie die Bestellung ab. Eine manuelle Entsperrung per Formular ist nicht möglich.',
                'auto_reject' => true,
            ],
            'contract_ended' => [
                'label' => 'Vertrag beendet',
                'customer_message' => 'Ihr Vertrag ist beendet. Eine Reaktivierung über dieses Formular ist nicht möglich. Bitte kontaktieren Sie den Vertrieb für ein neues Angebot.',
                'auto_reject' => true,
            ],
            'abuse_tos' => [
                'label' => 'Nutzungsverstoß',
                'customer_message' => 'Ihr Account wurde wegen eines vermuteten Verstoßes gegen die Nutzungsbedingungen gesperrt. Wir prüfen Ihre Nachricht manuell.',
                'auto_reject' => false,
            ],
            'legal_hold' => [
                'label' => 'Rechtliche Sperre',
                'customer_message' => 'Ihr Account ist aus rechtlichen Gründen gesperrt. Eine Entsperrung über dieses Formular ist nicht möglich.',
                'auto_reject' => true,
            ],
            'manual' => [
                'label' => 'Manuelle Sperre',
                'customer_message' => 'Ihr Account ist vorübergehend gesperrt. Sie können eine Entsperrung anfragen; wir prüfen das manuell.',
                'auto_reject' => false,
            ],
            'technical' => [
                'label' => 'Technisch / Prüfung',
                'customer_message' => 'Ihr Account ist vorübergehend aus technischen Gründen gesperrt. Bitte schildern Sie das Problem – wir melden uns.',
                'auto_reject' => false,
            ],
        ];
    }

    public static function isValid(?string $code): bool
    {
        $code = trim((string) $code);
        return $code !== '' && isset(self::all()[$code]);
    }

    public static function label(?string $code): string
    {
        $code = trim((string) $code);
        return self::all()[$code]['label'] ?? ($code !== '' ? $code : '–');
    }

    public static function customerMessage(?string $code, ?string $fallback = null): string
    {
        $code = trim((string) $code);
        if (isset(self::all()[$code])) {
            return self::all()[$code]['customer_message'];
        }
        $fallback = trim((string) $fallback);
        if ($fallback !== '') {
            return $fallback;
        }
        return 'Ihr Account ist gesperrt. Bei Fragen wenden Sie sich an den Support.';
    }

    public static function autoReject(?string $code): bool
    {
        $code = trim((string) $code);
        return (bool) (self::all()[$code]['auto_reject'] ?? false);
    }
}
