<?php
declare(strict_types=1);

/** Platzhalter für Mitarbeiter-E-Mail-Adressen (lokaler Teil + Domain). */
final class MailAddressTokens
{
    /**
     * Methode reference groups.
     * @return array<string, mixed>
     */
    public static function referenceGroups(): array
    {
        return [
            'person' => [
                'title' => 'Person',
                'items' => [
                    ['label' => 'Erster Buchstabe Vorname', 'hint' => 'm', 'codes' => ['{V1}']],
                    ['label' => 'Vorname (klein, ASCII)', 'hint' => 'max', 'codes' => ['{VN}']],
                    ['label' => 'Nachname (klein, ASCII)', 'hint' => 'mustermann', 'codes' => ['{NN}']],
                    ['label' => 'Benutzername (Login)', 'hint' => 'maxm', 'codes' => ['{LOGIN}']],
                ],
            ],
            'sonstiges' => [
                'title' => 'Sonstiges',
                'items' => [
                    ['label' => 'Trennzeichen', 'hint' => '.', 'codes' => ['{TRENNER}']],
                    ['label' => 'Zähler bei Kollision', 'hint' => '2', 'codes' => ['{NR}']],
                ],
            ],
        ];
    }

    /**
     * Liefert vordefinierte Presets.
     * @return array<string, array<string, string>>
     */
    public static function presets(): array
    {
        return [
            'v1_dot_nn' => '{V1}{TRENNER}{NN}',
            'vn_dot_nn' => '{VN}{TRENNER}{NN}',
            'vn_nn' => '{VN}{NN}',
            'login' => '{LOGIN}',
            'v1_nn' => '{V1}{NN}',
            'nn_dot_vn' => '{NN}{TRENNER}{VN}',
            'vn_underscore_nn' => '{VN}_{NN}',
        ];
    }

    /**
     * Methode preset labels.
     * @return array<string, mixed>
     */
    public static function presetLabels(): array
    {
        return [
            'v1_dot_nn' => 'm.mustermann (1. Buchstabe + Nachname)',
            'vn_dot_nn' => 'max.mustermann (Vorname + Nachname)',
            'vn_nn' => 'maxmustermann (ohne Trennzeichen)',
            'login' => 'Benutzername (Login)',
            'v1_nn' => 'mmustermann (1. Buchstabe + Nachname, ohne Punkt)',
            'nn_dot_vn' => 'mustermann.max (Nachname zuerst)',
            'vn_underscore_nn' => 'max_mustermann (Unterstrich)',
        ];
    }

    /**
     * Führt aus: resolve local part.
     * @param string $template
     * @param array $context
     * @param int $collisionNr
     * @return string
     */
    public static function resolveLocalPart(string $template, array $context, int $collisionNr = 0): string
    {
        $template = trim($template);
        if ($template === '') {
            $template = '{V1}{TRENNER}{NN}';
        }

        $separator = (string) ($context['separator'] ?? '.');
        $nr = $collisionNr > 0 ? (string) $collisionNr : '';

        $local = (string) preg_replace_callback(
            '/\{([^{}]+)\}/u',
            static function (array $matches) use ($context, $separator, $nr): string {
                $key = strtoupper(trim($matches[1]));

                return match ($key) {
                    'V1' => self::firstLetter((string) ($context['first_name'] ?? '')),
                    'VN' => self::slugAscii((string) ($context['first_name'] ?? '')),
                    'NN' => self::slugAscii((string) ($context['last_name'] ?? '')),
                    'LOGIN' => self::slugAscii((string) ($context['login'] ?? '')),
                    'TRENNER' => $separator,
                    'NR' => $nr,
                    default => '',
                };
            },
            $template
        );

        $local = strtolower(preg_replace('/[^a-z0-9._+-]/', '', $local) ?? $local);
        $local = trim($local, '.-_');

        return $local;
    }

    /**
     * Methode first letter.
     * @param string $value
     * @return string
     */
    public static function firstLetter(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return mb_substr(self::slugAscii($value), 0, 1);
    }

    /**
     * Methode slug ascii.
     * @param string $value
     * @return string
     */
    public static function slugAscii(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($translit !== false) {
            $value = $translit;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
    }
}
