<?php
declare(strict_types=1);

/**
 * HTTP-API für den Kichel-Assistenten (nur eingeloggte Mitarbeiter/Admins).
 */
final class KichelApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !RoleResolver::isStaff($user)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ungültiges Formular.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $query = trim((string) ($_POST['query'] ?? ''));
        $result = KichelAssistant::answer($user, $query);

        KichelProtocolRepository::record(
            $user->id,
            $query,
            (string) ($result['answer'] ?? ''),
            $result
        );

        echo json_encode([
            'success' => true,
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE);
    }
}
