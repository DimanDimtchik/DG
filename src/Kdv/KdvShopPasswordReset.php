<?php
declare(strict_types=1);

/**
 * Passwort-Reset für SaaS-Shop-Konten (KDV-Kunden).
 */
final class KdvShopPasswordReset
{
    private const TOKEN_BYTES = 32;
    private const EXPIRY_SECONDS = 3600;
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 900;

    public const REQUEST_SUCCESS_MESSAGE =
        'Falls ein SaaS-Konto mit dieser E-Mail existiert, erhalten Sie in Kürze eine Nachricht mit einem Link zum Zurücksetzen.';

    /**
     * Startet den Reset (Antwort immer gleich – kein Konto-Leak).
     *
     * @throws RuntimeException Bei Rate-Limit oder fehlender DB.
     */
    public static function requestReset(string $email): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Passwort-Zurücksetzen ist derzeit nicht verfügbar.');
        }
        MigrationRunner::runPending();

        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse angeben.');
        }

        self::enforceRateLimit($email);

        $row = KdvCustomerRepository::findByContactEmail($email);
        $hash = is_array($row) ? (string) ($row['shop_password_hash'] ?? '') : '';
        if ($row === null || $hash === '') {
            return;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        self::storeToken((int) $row['id'], $token);
        self::sendResetEmail($row, $email, $token);
    }

    public static function validateToken(string $token): ?array
    {
        if ($token === '' || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT t.kdv_customer_id
             FROM dg_kdv_password_reset_tokens t
             WHERE t.token_hash = :h
               AND t.used_at IS NULL
               AND t.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['h' => self::hashToken($token)]);
        $id = (int) $stmt->fetchColumn();
        if ($id < 1) {
            return null;
        }

        return KdvCustomerRepository::findById($id);
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function resetPassword(string $token, string $password, string $confirm): void
    {
        PasswordPolicy::assertValid($password, $confirm);
        if (!Database::isConfigured()) {
            throw new RuntimeException('Passwort-Zurücksetzen ist derzeit nicht verfügbar.');
        }
        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, kdv_customer_id
                 FROM dg_kdv_password_reset_tokens
                 WHERE token_hash = :h
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['h' => self::hashToken($token)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.');
            }

            $customerId = (int) $row['kdv_customer_id'];
            $tokenId = (int) $row['id'];
            $customer = KdvCustomerRepository::findById($customerId);
            if ($customer === null) {
                throw new InvalidArgumentException('Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.');
            }

            KdvCustomerRepository::setShopPassword($customerId, $password);
            KdvCustomerRepository::clearShopSession($customerId);

            $pdo->prepare('UPDATE dg_kdv_password_reset_tokens SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => $tokenId]);
            $pdo->prepare(
                'UPDATE dg_kdv_password_reset_tokens
                 SET used_at = NOW()
                 WHERE kdv_customer_id = :cid AND used_at IS NULL AND id <> :id'
            )->execute(['cid' => $customerId, 'id' => $tokenId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function storeToken(int $customerId, string $token): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_kdv_password_reset_tokens SET used_at = NOW() WHERE kdv_customer_id = :cid AND used_at IS NULL'
        )->execute(['cid' => $customerId]);

        $expires = (new DateTimeImmutable('now'))->modify('+' . self::EXPIRY_SECONDS . ' seconds');
        $pdo->prepare(
            'INSERT INTO dg_kdv_password_reset_tokens (kdv_customer_id, token_hash, expires_at)
             VALUES (:cid, :h, :e)'
        )->execute([
            'cid' => $customerId,
            'h' => self::hashToken($token),
            'e' => $expires->format('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $customer */
    private static function sendResetEmail(array $customer, string $email, string $token): void
    {
        $shopUrl = KdvConfig::shopPublicUrl();
        $resetUrl = $shopUrl . '/konto/passwort-neu?token=' . rawurlencode($token);

        $name = trim((string) ($customer['contact_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($customer['company_name'] ?? 'Kunde'));
        }
        $subject = 'SaaS-Konto: Passwort zurücksetzen – Ganz Soft';
        $html = '<p>Hallo ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Sie haben angefordert, das Passwort für Ihr SaaS-Konto bei Ganz Soft zurückzusetzen.</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Neues Passwort festlegen</a></p>'
            . '<p>Der Link ist eine Stunde gültig. Falls Sie keine Anfrage gestellt haben, ignorieren Sie diese E-Mail.</p>'
            . '<p style="font-size:12px;color:#666;">Falls der Link nicht funktioniert:<br>'
            . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</p>';

        try {
            if (class_exists('MailService') && class_exists('MailSettings') && MailSettings::isConfigured()) {
                MailService::send(new MailMessage(
                    subject: $subject,
                    htmlBody: $html,
                    to: [$email],
                    textBody: "Hallo {$name},\n\nNeues Passwort festlegen:\n{$resetUrl}\n\nDer Link ist eine Stunde gültig.\n"
                ));
                return;
            }
        } catch (Throwable) {
            // fall through to mail()
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'ganz-soft.de');
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: noreply@{$host}\r\n";
        @mail($email, $subject, $html, $headers);
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @throws RuntimeException */
    private static function enforceRateLimit(string $email): void
    {
        $pdo = Database::pdo();
        $emailHash = hash('sha256', strtolower(trim($email)));
        $pdo->prepare(
            'DELETE FROM dg_kdv_password_reset_throttle
             WHERE attempted_at < (NOW() - INTERVAL ' . (int) self::RATE_LIMIT_WINDOW . ' SECOND)'
        )->execute();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dg_kdv_password_reset_throttle
             WHERE email_hash = :h
               AND attempted_at > (NOW() - INTERVAL ' . (int) self::RATE_LIMIT_WINDOW . ' SECOND)'
        );
        $stmt->execute(['h' => $emailHash]);
        if ((int) $stmt->fetchColumn() >= self::RATE_LIMIT_MAX) {
            throw new RuntimeException('Zu viele Anfragen. Bitte warten Sie einige Minuten und versuchen Sie es erneut.');
        }

        $pdo->prepare(
            'INSERT INTO dg_kdv_password_reset_throttle (email_hash, attempted_at) VALUES (:h, NOW())'
        )->execute(['h' => $emailHash]);
    }
}
