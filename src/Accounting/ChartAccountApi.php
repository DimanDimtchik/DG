<?php
declare(strict_types=1);

final class ChartAccountApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !MenuRegistry::canAccess($user, 'buchhaltung-konten')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Nur GET erlaubt.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $skrType = ChartOfAccountsSettings::activeSkrType();
        $number = isset($_GET['number']) ? (string) $_GET['number'] : '';
        $query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

        try {
            ChartAccountRepository::ensureSeeded($skrType);
            if ($number !== '') {
                $account = ChartAccountRepository::findByNumber($number, $skrType);
                if ($account === null) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Konto nicht gefunden.',
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'account' => self::publicAccount($account),
                    ],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($query === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Parameter number oder q erforderlich.',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $results = ChartAccountRepository::search($query, $skrType);
            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => array_map(self::publicAccount(...), $results),
                    'skr_type' => $skrType,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @param array<string, mixed> $account */
    private static function publicAccount(array $account): array
    {
        return [
            'id' => (int) ($account['id'] ?? 0),
            'account_number' => (string) ($account['account_number'] ?? ''),
            'name' => (string) ($account['name'] ?? ''),
            'section' => (string) ($account['section'] ?? ''),
            'section_label' => (string) ($account['section_label'] ?? ''),
            'skr_type' => (string) ($account['skr_type'] ?? ''),
            'hints' => is_array($account['hints'] ?? null) ? $account['hints'] : [],
            'digit_breakdown' => is_array($account['digit_breakdown'] ?? null) ? $account['digit_breakdown'] : [],
        ];
    }
}
