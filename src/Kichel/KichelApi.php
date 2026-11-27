<?php
declare(strict_types=1);

/**
 * HTTP-API für den Kichel-Assistenten (nur eingeloggte Mitarbeiter/Admins).
 *
 * KR7: einzige Hilfe-Pipeline für Widget und Super-Kichel (`POST /api/kichel` → Assistant).
 * Kein zweites Prompt-/Ollama-System in der Super-Kichel-Seite (OpenHands = SKP, getrennt).
 * KH1: optional `action=feedback` mit log_id + rating.
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

        $action = trim((string) ($_POST['action'] ?? 'ask'));
        if ($action === 'feedback') {
            $logId = (int) ($_POST['log_id'] ?? 0);
            $rating = (string) ($_POST['rating'] ?? '');
            $result = KichelQualityService::applyUserFeedback($logId, $rating, $user->id);
            echo json_encode([
                'success' => $result['ok'],
                'message' => $result['message'],
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $query = trim((string) ($_POST['query'] ?? ''));
        $result = KichelAssistant::answer($user, $query);

        // Protokoll = volle Antwort (inkl. Roh-Fakten); Client sieht rollenabhängig (KH2).
        $logId = KichelProtocolRepository::record(
            $user->id,
            $query,
            (string) ($result['answer'] ?? ''),
            $result
        );

        $data = KichelRoleView::forUser($user, $result);
        if ($logId !== null) {
            $data['log_id'] = $logId;
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }
}
