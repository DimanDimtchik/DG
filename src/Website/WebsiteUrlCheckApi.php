<?php

declare(strict_types=1);

/**
 * Soft-Check für Website-URLs (Normalisierung + TLD + DNS).
 * Speichern wird nicht blockiert — nur Hinweis an die UI.
 */
final class WebsiteUrlCheckApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !RoleResolver::canEdit($user)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ungültiges Formular.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $raw = (string) ($_POST['url'] ?? '');
        $checkDns = !isset($_POST['check_dns']) || (string) $_POST['check_dns'] !== '0';
        $data = WebsiteUrlNormalizer::analyze($raw, $checkDns);

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }
}
