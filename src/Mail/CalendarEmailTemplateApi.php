<?php
declare(strict_types=1);

final class CalendarEmailTemplateApi
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

        $templateKey = trim((string) ($_POST['template_key'] ?? ''));
        if ($templateKey === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Vorlage fehlt.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $template = [
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'intro' => trim((string) ($_POST['intro'] ?? '')),
            'event_slug' => trim((string) ($_POST['event_slug'] ?? $templateKey)),
        ];

        $result = CalendarEmailTemplateRenderer::preview($templateKey, $template);

        echo json_encode([
            'success' => true,
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE);
    }
}
