<?php
declare(strict_types=1);

/**
 * Speicherdauer und öffentliche Datenschutz-Texte für Bewerbungen (pro Instanz).
 * Standard 6 Monate nach Absage — Freigabe/Anpassung durch DSB bzw. Anwalt.
 */
final class RecruitingPrivacySettings
{
    public const STORE_KEY = 'recruiting_privacy';

    public const MIN_MONTHS = 1;
    public const MAX_MONTHS = 24;
    public const DEFAULT_MONTHS = JobApplicationRepository::DEFAULT_RETENTION_MONTHS;

    public const DEFAULT_CONSENT_LABEL =
        'Ich willige ein, dass meine Bewerbungsdaten für dieses Bewerbungsverfahren gespeichert und verarbeitet werden. *';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'retention_months' => self::DEFAULT_MONTHS,
            'public_retention_hint' => '',
            'public_consent_label' => '',
            'dsb_signed_off' => false,
            'dsb_signed_off_at' => '',
            'dsb_note' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return self::defaults();
        }

        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $months = self::sanitizeMonths($stored['retention_months'] ?? self::DEFAULT_MONTHS);

        return [
            'retention_months' => $months,
            'public_retention_hint' => trim((string) ($stored['public_retention_hint'] ?? '')),
            'public_consent_label' => trim((string) ($stored['public_consent_label'] ?? '')),
            'dsb_signed_off' => !empty($stored['dsb_signed_off']),
            'dsb_signed_off_at' => trim((string) ($stored['dsb_signed_off_at'] ?? '')),
            'dsb_note' => trim((string) ($stored['dsb_note'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();

        return [
            'retention_months' => (int) $cfg['retention_months'],
            'public_retention_hint' => (string) $cfg['public_retention_hint'],
            'public_consent_label' => (string) $cfg['public_consent_label'],
            'default_consent_label' => self::DEFAULT_CONSENT_LABEL,
            'default_retention_hint' => self::buildDefaultRetentionHint((int) $cfg['retention_months']),
            'dsb_signed_off' => !empty($cfg['dsb_signed_off']),
            'dsb_signed_off_at' => (string) $cfg['dsb_signed_off_at'],
            'dsb_note' => (string) $cfg['dsb_note'],
            'status_label' => !empty($cfg['dsb_signed_off'])
                ? 'DSB/Anwalt-Freigabe markiert'
                : 'Noch keine Freigabe markiert — siehe Checkliste in der Doku',
        ];
    }

    public static function retentionMonths(): int
    {
        return (int) self::config()['retention_months'];
    }

    public static function publicConsentLabel(): string
    {
        $custom = trim((string) self::config()['public_consent_label']);
        if ($custom !== '') {
            return $custom;
        }

        return self::DEFAULT_CONSENT_LABEL;
    }

    public static function publicRetentionHint(): string
    {
        $custom = trim((string) self::config()['public_retention_hint']);
        if ($custom !== '') {
            return $custom;
        }

        return self::buildDefaultRetentionHint(self::retentionMonths());
    }

    public static function buildDefaultRetentionHint(int $months): string
    {
        $months = self::sanitizeMonths($months);

        return 'Ihre Angaben speichern wir nur für dieses Bewerbungsverfahren. '
            . 'Nach Absage oder Verfahrensende löschen wir sie in der Regel nach '
            . $months . ' Monaten (laufende Verfahren ausgenommen). '
            . 'Eine längere Speicherung nur bei gesetzlicher Pflicht oder mit Ihrer gesonderten Einwilligung '
            . '(z. B. Talentpool — derzeit nicht vorgesehen).';
    }

    /**
     * Kurzer Absatz für generierte Datenschutzerklärung.
     */
    public static function privacyPolicyParagraphHtml(): string
    {
        $months = self::retentionMonths();

        return '<h3>Bewerbungen</h3>'
            . '<p>Wenn Sie sich über unsere Karriereseite bewerben, verarbeiten wir die von Ihnen übermittelten Daten '
            . '(Kontaktdaten, Bewerbungsunterlagen, Korrespondenz) zur Durchführung des Bewerbungsverfahrens. '
            . 'Rechtsgrundlage ist in der Regel Art. 6 Abs. 1 lit. b DSGVO (Anbahnung eines Beschäftigungsverhältnisses) '
            . 'bzw. § 26 BDSG; soweit wir eine Einwilligung einholen, Art. 6 Abs. 1 lit. a DSGVO.</p>'
            . '<p>Nach Absage oder Ende des Verfahrens löschen wir die Daten grundsätzlich nach '
            . (int) $months . ' Monaten, sofern keine längere Speicherung zur Rechtsverteidigung '
            . '(z. B. Ansprüche nach dem Allgemeinen Gleichbehandlungsgesetz) oder aufgrund gesetzlicher Pflichten erforderlich ist. '
            . 'Bei Einstellung gelten die Aufbewahrungsregeln für Personalakten.</p>'
            . '<p>Eine Übermittlung der Bewerbungsakte an Xing oder LinkedIn findet nicht statt; '
            . 'externe Stellenanzeigen verlinken nur auf unsere eigene Bewerbungsseite.</p>';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $months = self::sanitizeMonths($input['recruiting_retention_months'] ?? self::DEFAULT_MONTHS);
        $hint = trim((string) ($input['recruiting_public_retention_hint'] ?? ''));
        $consent = trim((string) ($input['recruiting_public_consent_label'] ?? ''));
        $note = trim((string) ($input['recruiting_dsb_note'] ?? ''));
        $signedOff = !empty($input['recruiting_dsb_signed_off']);

        $prev = self::config();
        $signedAt = (string) ($prev['dsb_signed_off_at'] ?? '');
        if ($signedOff && $signedAt === '') {
            $signedAt = date('Y-m-d');
        }
        if (!$signedOff) {
            $signedAt = '';
        }

        if (mb_strlen($hint) > 2000) {
            throw new InvalidArgumentException('Hinweistext ist zu lang (max. 2000 Zeichen).');
        }
        if (mb_strlen($consent) > 500) {
            throw new InvalidArgumentException('Einwilligungstext ist zu lang (max. 500 Zeichen).');
        }
        if (mb_strlen($note) > 2000) {
            throw new InvalidArgumentException('DSB-Notiz ist zu lang (max. 2000 Zeichen).');
        }

        SettingsStore::set(self::STORE_KEY, [
            'retention_months' => $months,
            'public_retention_hint' => $hint,
            'public_consent_label' => $consent,
            'dsb_signed_off' => $signedOff ? 1 : 0,
            'dsb_signed_off_at' => $signedAt,
            'dsb_note' => $note,
        ]);
    }

    public static function sanitizeMonths(mixed $value): int
    {
        $months = (int) $value;
        if ($months < self::MIN_MONTHS) {
            return self::DEFAULT_MONTHS;
        }
        if ($months > self::MAX_MONTHS) {
            return self::MAX_MONTHS;
        }

        return $months;
    }
}
