<?php
declare(strict_types=1);

/**
 * Shop-Konto-API für SaaS-Kunden (nur eigene Akte).
 *
 * POST /api/kdv/account/login
 * GET  /api/kdv/account/me          Authorization: Bearer <session>
 * POST /api/kdv/account/unlock-request
 * POST /api/kdv/account/logout
 * POST /api/kdv/account/password-reset/request
 * POST /api/kdv/account/password-reset/confirm
 */
final class KdvAccountApi
{
    /** @return never */
    public static function handle(string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $suffix = trim(substr($path, strlen('/api/kdv/account')), '/');
        if ($suffix === 'login') {
            self::login();
        }
        if ($suffix === 'me') {
            self::me();
        }
        if ($suffix === 'unlock-request') {
            self::unlockRequest();
        }
        if ($suffix === 'logout') {
            self::logout();
        }
        if ($suffix === 'password-reset/request') {
            self::passwordResetRequest();
        }
        if ($suffix === 'password-reset/confirm') {
            self::passwordResetConfirm();
        }

        self::error(404, 'Unbekannter Endpunkt.');
    }

    /** @return never */
    private static function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error(405, 'Nur POST erlaubt.');
        }
        $body = self::jsonBody();
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::error(422, 'Gültige E-Mail erforderlich.');
        }
        if ($password === '') {
            self::error(422, 'Passwort erforderlich.');
        }

        $row = KdvCustomerRepository::findByContactEmail($email);
        $hash = is_array($row) ? (string) ($row['shop_password_hash'] ?? '') : '';
        if ($row === null || $hash === '' || !password_verify($password, $hash)) {
            self::error(401, 'E-Mail oder Passwort ungültig.');
        }

        $session = KdvCustomerRepository::createShopSession((int) $row['id']);
        if ($session === null) {
            self::error(500, 'Sitzung konnte nicht erstellt werden.');
        }

        echo json_encode([
            'ok' => true,
            'token' => $session['token'],
            'expires' => $session['expires'],
            'account' => KdvCustomerRepository::publicAccountView($row),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return never */
    private static function me(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            self::error(405, 'Nur GET erlaubt.');
        }
        $row = self::requireSession();
        echo json_encode([
            'ok' => true,
            'account' => KdvCustomerRepository::publicAccountView($row),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return never */
    private static function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error(405, 'Nur POST erlaubt.');
        }
        $row = self::requireSession();
        KdvCustomerRepository::clearShopSession((int) $row['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    /** @return never */
    private static function unlockRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error(405, 'Nur POST erlaubt.');
        }
        $row = self::requireSession();
        $body = self::jsonBody();
        $message = trim((string) ($body['message'] ?? ''));

        if (($row['status'] ?? '') !== 'gesperrt') {
            echo json_encode([
                'ok' => true,
                'accepted' => false,
                'reason' => 'not_blocked',
                'message' => 'Ihr Account ist derzeit nicht gesperrt. Eine Entsperr-Bitte ist nicht nötig.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $blockReason = (string) ($row['block_reason'] ?? '');
        if (KdvBlockReasons::autoReject($blockReason)) {
            echo json_encode([
                'ok' => true,
                'accepted' => false,
                'reason' => $blockReason !== '' ? $blockReason : 'auto_reject',
                'message' => KdvBlockReasons::customerMessage($blockReason, (string) ($row['block_note'] ?? '')),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($message) < 10) {
            self::error(422, 'Bitte beschreiben Sie Ihr Anliegen (mind. 10 Zeichen).');
        }

        $sent = self::sendUnlockMail($row, $message);
        echo json_encode([
            'ok' => true,
            'accepted' => true,
            'mailed' => $sent,
            'message' => $sent
                ? 'Ihre Entsperr-Bitte wurde übermittelt. Wir melden uns per E-Mail.'
                : 'Anfrage gespeichert, Versand konnte lokal nicht bestätigt werden. Bitte ggf. erneut versuchen oder direkt mailen.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return never */
    private static function passwordResetRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error(405, 'Nur POST erlaubt.');
        }
        $body = self::jsonBody();
        $email = (string) ($body['email'] ?? '');
        try {
            KdvShopPasswordReset::requestReset($email);
        } catch (InvalidArgumentException $e) {
            self::error(422, $e->getMessage());
        } catch (RuntimeException $e) {
            self::error(429, $e->getMessage());
        } catch (Throwable $e) {
            self::error(500, 'Anfrage fehlgeschlagen.');
        }
        echo json_encode([
            'ok' => true,
            'message' => KdvShopPasswordReset::REQUEST_SUCCESS_MESSAGE,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return never */
    private static function passwordResetConfirm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error(405, 'Nur POST erlaubt.');
        }
        $body = self::jsonBody();
        $token = trim((string) ($body['token'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $confirm = (string) ($body['password_confirm'] ?? $body['confirm'] ?? '');
        try {
            KdvShopPasswordReset::resetPassword($token, $password, $confirm);
        } catch (InvalidArgumentException $e) {
            self::error(422, $e->getMessage());
        } catch (Throwable $e) {
            self::error(500, 'Passwort konnte nicht gesetzt werden.');
        }
        echo json_encode([
            'ok' => true,
            'message' => 'Passwort wurde geändert. Sie können sich jetzt anmelden.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function sendUnlockMail(array $row, string $message): bool
    {
        $to = KdvConfig::supportEmail();

        $subject = 'Entsperr-Bitte SaaS: ' . ($row['company_name'] ?? '') . ' / ' . ($row['domain'] ?? '');
        $html = '<p><strong>SaaS-Kunde (KDV)</strong></p>'
            . '<p>Firma: ' . htmlspecialchars((string) ($row['company_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Domain: ' . htmlspecialchars((string) ($row['domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Status: ' . htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Sperrgrund: ' . htmlspecialchars(KdvBlockReasons::label((string) ($row['block_reason'] ?? '')), ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Kontakt: ' . htmlspecialchars((string) ($row['contact_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Nachricht:</strong></p><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';

        try {
            if (class_exists('MailService') && class_exists('MailSettings') && MailSettings::isConfigured()) {
                MailService::send(new MailMessage(
                    subject: $subject,
                    htmlBody: $html,
                    to: [$to],
                    replyTo: (string) ($row['contact_email'] ?? '')
                ));
                return true;
            }
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . 'From: noreply@' . $host . "\r\n"
                . 'Reply-To: ' . (string) ($row['contact_email'] ?? $to) . "\r\n";
            return @mail($to, $subject, $html, $headers);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private static function requireSession(): array
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            $token = trim($m[1]);
        }
        if ($token === '') {
            self::error(401, 'Sitzung fehlt.');
        }
        $row = KdvCustomerRepository::findByShopSession($token);
        if ($row === null) {
            self::error(401, 'Sitzung ungültig oder abgelaufen.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private static function jsonBody(): array
    {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        return is_array($body) ? $body : [];
    }

    /** @return never */
    private static function error(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
