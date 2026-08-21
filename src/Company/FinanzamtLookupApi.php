<?php
declare(strict_types=1);

/**
 * Finanzamt Lookup Api.
 */
final class FinanzamtLookupApi
{
    /**
     * HTTP-API-Einstieg
     * @return void
     */
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !RoleResolver::isAdmin($user)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ungültiges Formular.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $mode = (string) ($_POST['mode'] ?? 'tax_number');
        $options = ['reporting_period' => 'quarterly'];

        $result = match ($mode) {
            'location' => TaxOffice::resolve_by_location(
                (string) ($_POST['postal'] ?? ''),
                (string) ($_POST['city'] ?? ''),
                $options
            ),
            'search' => TaxOffice::resolve_by_search((string) ($_POST['query'] ?? ''), $options),
            default => TaxOffice::resolve((string) ($_POST['tax_number'] ?? ''), $options),
        };

        echo json_encode([
            'success' => true,
            'data' => $result,
            'registry_count' => FinanzamtRegistry::count(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
