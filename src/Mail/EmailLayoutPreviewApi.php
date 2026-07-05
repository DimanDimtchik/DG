<?php
declare(strict_types=1);

final class EmailLayoutPreviewApi
{
    public static function handlePreview(): void
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

        $cfg = EmailLayoutSettings::previewConfigFromInput($_POST);

        echo json_encode([
            'success' => true,
            'header_html' => CalendarEmailLayout::settingsHeaderPreview($cfg),
            'footer_html' => CalendarEmailLayout::settingsFooterPreview($cfg),
        ], JSON_UNESCAPED_UNICODE);
    }
}
