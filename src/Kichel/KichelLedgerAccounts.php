<?php
declare(strict_types=1);

/**
 * Kontonummern in der Kontenübersicht — direkter Link zum Kontoauszug.
 */
final class KichelLedgerAccounts
{
    /**
     * @return array{
     *   kind: string,
     *   answer: string,
     *   action_links: list<array{label: string, href: string}>
     * }|null
     */
    public static function tryAnswer(string $query): ?array
    {
        $account = self::extractAccountNumber($query);
        if ($account === null) {
            return null;
        }

        $name = self::accountName($account);
        $href = '/app?page=buchhaltung-kontenuebersicht&account=' . rawurlencode($account);

        $answer = $name !== ''
            ? 'Konto ' . $account . ' (' . $name . '): alle Buchungen siehst du im Kontoauszug.'
            : 'Konto ' . $account . ': alle Buchungen siehst du im Kontoauszug.';

        return [
            'kind' => 'ledger_account',
            'answer' => $answer,
            'action_links' => [
                [
                    'label' => 'Kontoauszug Konto ' . $account,
                    'href' => $href,
                ],
            ],
        ];
    }

    private static function extractAccountNumber(string $query): ?string
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\bkont(?:o|en)\s*[#:.\-]?\s*(\d{3,6})\b/u', $normalized, $match)) {
            return $match[1];
        }

        if (preg_match('/\b(\d{3,6})\b/u', $normalized, $match)
            && preg_match(
                '/\b(kont(?:o|en)|buchung|buchungen|eintr[aä]g|saldo|kontoauszug|soll|haben|debitor|kreditor|anschauen|zeigen|übersicht|journal)\b/u',
                $normalized
            )) {
            return $match[1];
        }

        return null;
    }

    private static function accountName(string $account): string
    {
        if (!Database::isConfigured() || !class_exists('ChartAccountRepository')) {
            return '';
        }

        try {
            $row = ChartAccountRepository::findByNumber($account);

            return trim((string) ($row['name'] ?? ''));
        } catch (Throwable) {
            return '';
        }
    }
}
