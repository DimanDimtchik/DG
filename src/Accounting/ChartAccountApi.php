<?php
declare(strict_types=1);

/**
 * Chart Account Api.
 */
final class ChartAccountApi
{
    /**
     * HTTP-API-Einstieg
     * @return void
     */
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !MenuRegistry::canAccess($user, 'buchhaltung-konten')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            self::handlePost();
            return;
        }

        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt.'], JSON_UNESCAPED_UNICODE);
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

    /**
     * Verarbeitet POST-Anfragen
     * @return void
     */
    private static function handlePost(): void
    {
        $payload = self::readJsonPayload();
        if (!Csrf::verify(is_string($payload['_csrf'] ?? null) ? $payload['_csrf'] : null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen. Bitte Seite neu laden.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $action = trim((string) ($payload['action'] ?? ''));
        if ($action !== 'update_search_terms') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $accountNumber = trim((string) ($payload['account_number'] ?? ''));
        if ($accountNumber === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Kontonummer fehlt.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $terms = $payload['search_terms'] ?? [];
        if (!is_array($terms)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Suchbegriffe ungültig.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $skrType = ChartOfAccountsSettings::activeSkrType();
            $account = ChartAccountRepository::updateSearchTerms($accountNumber, $terms, $skrType);
            echo json_encode([
                'success' => true,
                'data' => [
                    'account' => self::publicAccount($account),
                ],
                'message' => 'Suchbegriffe gespeichert.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * readJsonPayload.
     *
     * @return array<string, mixed>
     */
        private static function readJsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $_POST;
    }

        /**
     * Reduziert Kontodaten für die API-Antwort
     * @param array $account Kontonummer
     * @return array
     */
    private static function publicAccount(array $account): array
    {
        $hints = is_array($account['hints'] ?? null) ? $account['hints'] : [];
        $searchTerms = ChartAccountHintTerms::normalizeList($hints['search_terms'] ?? []);

        return [
            'id' => (int) ($account['id'] ?? 0),
            'account_number' => (string) ($account['account_number'] ?? ''),
            'name' => (string) ($account['name'] ?? ''),
            'section' => (string) ($account['section'] ?? ''),
            'section_label' => (string) ($account['section_label'] ?? ''),
            'skr_type' => (string) ($account['skr_type'] ?? ''),
            'hints' => $hints,
            'search_terms' => $searchTerms,
            'search_terms_edited' => ($hints['search_terms_edited'] ?? false) === true,
            'digit_breakdown' => is_array($account['digit_breakdown'] ?? null) ? $account['digit_breakdown'] : [],
        ];
    }
}
