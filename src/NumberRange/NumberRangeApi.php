<?php
declare(strict_types=1);

final class NumberRangeApi
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

        $raw = $_POST['number_range'] ?? [];
        if (!is_array($raw)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ungültige Daten.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $document = NumberRangeSettings::sanitizeDocument($raw);
        $sequence = InvoiceNumberBuilder::sequenceCounter($document);
        $base = (string) ($document['number_display'] ?? 'decimal');
        $pad = max(0, (int) ($document['number_pad'] ?? 0));
        $context = InvoiceNumberBuilder::previewContext($document);

        echo json_encode([
            'success' => true,
            'data' => [
                'preview' => InvoiceNumberBuilder::build($document, $context),
                'sequence' => $sequence,
                'sequence_display' => InvoiceNumberBuilder::formatSequenceValue($sequence, $base, $pad),
                'uses_country' => InvoiceNumberTokens::usesCountryPlaceholder($document),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
