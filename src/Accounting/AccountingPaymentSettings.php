<?php
declare(strict_types=1);

/** Zahlungsbedingungen (Skonto-Stufen) und Mahnwesen — Einstellungen. */
final class AccountingPaymentSettings
{
    public const STORE_KEY = 'accounting_payment';

    /**
     * @return array{payment_term_tiers: list<array{days: int, adjustment_percent: float, label: string}>, dunning: array<string, mixed>}
     */
    public static function defaults(): array
    {
        return [
            'payment_term_tiers' => [
                ['days' => 7, 'adjustment_percent' => -3.0, 'label' => 'Skonto'],
                ['days' => 30, 'adjustment_percent' => 0.0, 'label' => 'Netto'],
                ['days' => 90, 'adjustment_percent' => 1.5, 'label' => 'Verzug'],
            ],
            'dunning' => [
                'auto_send' => false,
                'fee_account' => '4970',
                'levels' => [
                    [
                        'days_after_due' => 7,
                        'label' => 'Zahlungserinnerung',
                        'fee_amount' => 0.0,
                        'subject' => 'Zahlungserinnerung — Rechnung {RECHNUNGSNR}',
                        'intro' => 'Guten Tag,

wir haben zu unserer Rechnung {RECHNUNGSNR} vom {BELEGDATUM} noch keinen Zahlungseingang feststellen können.

Offener Betrag: {OFFEN} €
Fällig seit: {FAELLIG}

Bitte überweisen Sie den Betrag zeitnah.

Mit freundlichen Grüßen
{FIRMA}',
                    ],
                    [
                        'days_after_due' => 14,
                        'label' => '1. Mahnung',
                        'fee_amount' => 5.0,
                        'subject' => '1. Mahnung — Rechnung {RECHNUNGSNR}',
                        'intro' => 'Guten Tag,

trotz unserer Zahlungserinnerung ist die Rechnung {RECHNUNGSNR} vom {BELEGDATUM} weiterhin offen.

Offener Betrag: {OFFEN} €
Mahngebühr: {MAHNGEBUEHR} €

Bitte begleichen Sie den Gesamtbetrag umgehend.

Mit freundlichen Grüßen
{FIRMA}',
                    ],
                    [
                        'days_after_due' => 28,
                        'label' => '2. Mahnung',
                        'fee_amount' => 10.0,
                        'subject' => '2. Mahnung — Rechnung {RECHNUNGSNR}',
                        'intro' => 'Guten Tag,

wir mahnen die Rechnung {RECHNUNGSNR} vom {BELEGDATUM} erneut an.

Offener Betrag: {OFFEN} €
Mahngebühr: {MAHNGEBUEHR} €

Sollte keine Zahlung eingehen, behalten wir uns weitere Schritte vor.

Mit freundlichen Grüßen
{FIRMA}',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();

        return [
            'payment_term_tiers' => PaymentTermsService::sanitizeTiers($stored['payment_term_tiers'] ?? $defaults['payment_term_tiers']),
            'dunning' => self::sanitizeDunningConfig(is_array($stored['dunning'] ?? null) ? $stored['dunning'] : $defaults['dunning']),
        ];
    }

    /**
     * @return list<array{days: int, adjustment_percent: float, label: string}>
     */
    public static function defaultTiers(): array
    {
        return PaymentTermsService::sanitizeTiers(self::forForm()['payment_term_tiers']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dunningConfig(): array
    {
        return self::forForm()['dunning'];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $tiers = PaymentTermsService::parseTiersFromRequest($input);
        $dunning = self::sanitizeDunningConfig([
            'auto_send' => !empty($input['dunning_auto_send']),
            'fee_account' => (string) ($input['dunning_fee_account'] ?? ''),
            'levels' => self::parseDunningLevelsFromRequest($input),
        ]);

        SettingsStore::set(self::STORE_KEY, [
            'payment_term_tiers' => $tiers,
            'dunning' => $dunning,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function sanitizeDunningConfig(array $config): array
    {
        $defaults = self::defaults()['dunning'];
        $levels = [];
        $rawLevels = is_array($config['levels'] ?? null) ? $config['levels'] : $defaults['levels'];
        foreach ($rawLevels as $level) {
            if (!is_array($level)) {
                continue;
            }
            $days = max(0, (int) ($level['days_after_due'] ?? 0));
            $label = trim((string) ($level['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $levels[] = [
                'days_after_due' => $days,
                'label' => mb_substr($label, 0, 120),
                'fee_amount' => round(max(0, (float) str_replace(',', '.', (string) ($level['fee_amount'] ?? '0'))), 2),
                'subject' => mb_substr(trim((string) ($level['subject'] ?? '')), 0, 255),
                'intro' => trim((string) ($level['intro'] ?? '')),
            ];
        }
        if ($levels === []) {
            $levels = $defaults['levels'];
        }

        usort($levels, static fn (array $a, array $b): int => (int) $a['days_after_due'] <=> (int) $b['days_after_due']);

        $feeAccount = preg_replace('/\D/', '', (string) ($config['fee_account'] ?? '')) ?? '';
        if ($feeAccount === '') {
            $feeAccount = (string) ($defaults['fee_account'] ?? '4970');
        }

        return [
            'auto_send' => !empty($config['auto_send']),
            'fee_account' => $feeAccount,
            'levels' => $levels,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array<string, mixed>>
     */
    private static function parseDunningLevelsFromRequest(array $input): array
    {
        $raw = $input['dunning_levels'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $levels = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $levels[] = [
                'days_after_due' => (int) ($row['days_after_due'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'fee_amount' => (string) ($row['fee_amount'] ?? '0'),
                'subject' => (string) ($row['subject'] ?? ''),
                'intro' => (string) ($row['intro'] ?? ''),
            ];
        }

        return $levels;
    }
}
