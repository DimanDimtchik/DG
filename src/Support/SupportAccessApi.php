<?php
declare(strict_types=1);

/**
 * Hub-API und Signaling für Support-Freigabe.
 */
final class SupportAccessApi
{
    public static function handleHubGrant(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Nur POST']);
            exit;
        }
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ungültiges JSON']);
            exit;
        }
        $action = (string) ($data['action'] ?? '');
        try {
            if ($action === 'start') {
                KdvSupportSessionRepository::upsertStart($data);
            } elseif ($action === 'stop') {
                KdvSupportSessionRepository::markStopped((string) ($data['domain'] ?? ''));
            } else {
                throw new InvalidArgumentException('Unbekannte Aktion.');
            }
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function handleSignal(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Database::isConfigured()) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'DB']);
            exit;
        }

        $isSupport = SupportSession::isActive();
        $user = AuthService::user();
        $isAdmin = $user !== null && RoleResolver::isAdmin($user) && !SupportSession::isActive();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $grant = self::resolveGrantForSignal($isSupport, $isAdmin);
            if ($grant === null) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Keine Freigabe']);
                exit;
            }
            $direction = $isSupport ? 'customer_to_support' : 'support_to_customer';
            $after = (int) ($_GET['after'] ?? 0);
            $messages = SupportSignalStore::pull((int) $grant['id'], $direction, $after);
            echo json_encode(['ok' => true, 'messages' => $messages, 'access_id' => (int) $grant['id']]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false]);
            exit;
        }

        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        if (!Csrf::verify($data['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            exit;
        }

        $grant = self::resolveGrantForSignal($isSupport, $isAdmin);
        if ($grant === null) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Freigabe']);
            exit;
        }
        if (empty($grant['screen_share_enabled'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Bildschirmfreigabe nicht erlaubt']);
            exit;
        }

        $direction = $isSupport ? 'support_to_customer' : 'customer_to_support';
        $payload = $data['payload'] ?? null;
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'payload fehlt']);
            exit;
        }
        SupportSignalStore::push((int) $grant['id'], $direction, $payload);
        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveGrantForSignal(bool $isSupport, bool $isAdmin): ?array
    {
        if ($isSupport) {
            return SupportSession::grant();
        }
        if ($isAdmin) {
            return SupportAccessService::activeGrant();
        }

        return null;
    }
}
