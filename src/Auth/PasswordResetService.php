<?php
declare(strict_types=1);

/**
 * Passwort-zurücksetzen per E-Mail-Token (Anfordern, Validieren, Neues Passwort setzen).
 */
final class PasswordResetService
{
    private const TOKEN_BYTES = 32;
    private const EXPIRY_SECONDS = 3600;
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 900;

    /** @var string Einheitliche Meldung – verrät nicht, ob Konto existiert. */
    public const REQUEST_SUCCESS_MESSAGE =
        'Falls ein passendes Konto mit hinterlegter E-Mail existiert, erhalten Sie in Kürze eine Nachricht mit weiteren Schritten.';

    /**
     * Startet den Reset-Prozess (E-Mail nur wenn Konto existiert und SMTP konfiguriert).
     *
     * @throws RuntimeException Bei fehlender DB oder E-Mail-Konfiguration.
     */
    public static function requestReset(string $identifier): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Passwort-Zurücksetzen ist nur mit Datenbankverbindung verfügbar.');
        }

        if (!MailSettings::isConfigured()) {
            throw new RuntimeException(
                'E-Mail-Versand ist nicht konfiguriert. Bitte wenden Sie sich an den Administrator (Einstellungen → E-Mail / SMTP).'
            );
        }

        self::enforceRateLimit($identifier);

        MigrationRunner::runPending();

        $user = UserRepository::findByEmailOrUsername($identifier);
        if ($user === null || !RoleResolver::canAccessCrm($user)) {
            return;
        }

        $email = trim($user->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        self::storeToken($user->id, $token);
        self::sendResetEmail($user, $email, $token);
    }

    /**
     * Prüft ein Reset-Token und liefert den zugehörigen Benutzer.
     */
    public static function validateToken(string $token): ?User
    {
        if (!Database::isConfigured()) {
            return null;
        }

        MigrationRunner::runPending();

        $hash = self::hashToken($token);
        $stmt = Database::pdo()->prepare(
            'SELECT t.user_id
             FROM dg_password_reset_tokens t
             WHERE t.token_hash = :token_hash
               AND t.used_at IS NULL
               AND t.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $hash]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId < 1) {
            return null;
        }

        $user = UserRepository::findById($userId);
        if ($user === null || !RoleResolver::canAccessCrm($user)) {
            return null;
        }

        return $user;
    }

    /**
     * Setzt das Passwort anhand eines gültigen Tokens.
     *
     * @throws InvalidArgumentException Bei ungültigem Token oder Passwortregeln.
     * @throws RuntimeException Bei fehlender Datenbank.
     */
    public static function resetPassword(string $token, string $password, string $confirm): void
    {
        if ($password === '' || $confirm === '') {
            throw new InvalidArgumentException('Bitte neues Passwort eingeben und bestätigen.');
        }
        PasswordPolicy::assertValid($password, $confirm);

        if (!Database::isConfigured()) {
            throw new RuntimeException('Passwort-Zurücksetzen ist nur mit Datenbankverbindung verfügbar.');
        }

        MigrationRunner::runPending();

        $hash = self::hashToken($token);
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT id, user_id
                 FROM dg_password_reset_tokens
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['token_hash' => $hash]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new InvalidArgumentException('Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.');
            }

            $userId = (int) $row['user_id'];
            $tokenId = (int) $row['id'];
            $user = UserRepository::findById($userId);
            if ($user === null || !RoleResolver::canAccessCrm($user)) {
                throw new InvalidArgumentException('Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.');
            }

            UserRepository::updatePassword($userId, $password);

            $mark = $pdo->prepare('UPDATE dg_password_reset_tokens SET used_at = NOW() WHERE id = :id');
            $mark->execute(['id' => $tokenId]);

            $invalidate = $pdo->prepare(
                'UPDATE dg_password_reset_tokens
                 SET used_at = NOW()
                 WHERE user_id = :user_id AND used_at IS NULL AND id <> :id'
            );
            $invalidate->execute(['user_id' => $userId, 'id' => $tokenId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Invalidiert alte Tokens und speichert einen neuen Hash.
     */
    private static function storeToken(int $userId, string $token): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL'
        )->execute(['user_id' => $userId]);

        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . self::EXPIRY_SECONDS . ' seconds');
        $stmt = $pdo->prepare(
            'INSERT INTO dg_password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => self::hashToken($token),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @throws RuntimeException Wenn keine öffentliche Basis-URL ermittelt werden kann.
     */
    private static function sendResetEmail(User $user, string $email, string $token): void
    {
        $baseUrl = App::publicBaseUrl();
        if ($baseUrl === '') {
            throw new RuntimeException('Öffentliche Basis-URL konnte nicht ermittelt werden.');
        }

        $resetUrl = $baseUrl . '/passwort-zuruecksetzen?token=' . rawurlencode($token);
        $crmName = (string) App::config('crm_name', 'DG');
        $subject = 'Passwort zurücksetzen – ' . $crmName;
        $displayName = $user->displayName !== '' ? $user->displayName : $user->username;
        $crmNameEsc = htmlspecialchars($crmName, ENT_QUOTES, 'UTF-8');
        $resetUrlEsc = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $theme = EmailLayoutSettings::emailTheme();
        $buttonBg = htmlspecialchars((string) ($theme['primary'] ?? '#2271b1'), ENT_QUOTES, 'UTF-8');
        $mutedColor = htmlspecialchars((string) ($theme['text_muted'] ?? '#666666'), ENT_QUOTES, 'UTF-8');

        $inner = '<p>Sie haben angefordert, Ihr Passwort für ' . $crmNameEsc . ' zurückzusetzen.</p>'
            . '<p style="margin:24px 0;">'
            . '<a href="' . $resetUrlEsc . '" style="display:inline-block;padding:12px 24px;background-color:' . $buttonBg
            . ';color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;">Neues Passwort festlegen</a>'
            . '</p>'
            . '<p>Der Link ist eine Stunde gültig. Falls Sie keine Anfrage gestellt haben, können Sie diese E-Mail ignorieren.</p>'
            . '<p style="font-size:12px;line-height:1.5;color:' . $mutedColor . ';">Falls der Link nicht funktioniert, kopieren Sie diese Adresse in den Browser:<br>'
            . $resetUrlEsc . '</p>';

        $footer = EmailLayoutSettings::resolvedFooter();
        $footer['opening_greeting'] = 'Hallo ' . $displayName . ',';
        $html = CalendarEmailLayout::renderPostMessage($inner, $footer);

        $text = "Hallo {$displayName},\n\n"
            . "Sie haben angefordert, Ihr Passwort für {$crmName} zurückzusetzen.\n\n"
            . "Neues Passwort festlegen:\n{$resetUrl}\n\n"
            . "Der Link ist eine Stunde gültig. Falls Sie keine Anfrage gestellt haben, können Sie diese E-Mail ignorieren.\n";
        $plainClosing = CalendarEmailLayout::closingPlainText($footer);
        if ($plainClosing !== '') {
            $text .= "\n" . $plainClosing;
        }

        $message = new MailMessage(
            subject: $subject,
            htmlBody: $html,
            to: [$email],
            textBody: $text,
        );

        MailService::send($message);
    }

    /**
     * Hasht ein Klartext-Token für den sicheren DB-Vergleich.
     */
    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Session-basiertes Rate-Limit für Reset-Anfragen.
     *
     * @throws RuntimeException Bei zu vielen Anfragen im Zeitfenster.
     */
    private static function enforceRateLimit(string $identifier): void
    {
        $now = time();
        $key = 'dg_pw_reset_attempts';
        $attempts = $_SESSION[$key] ?? [];
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $attempts = array_values(array_filter(
            $attempts,
            static fn ($entry) => is_array($entry)
                && isset($entry['at'])
                && ($now - (int) $entry['at']) < self::RATE_LIMIT_WINDOW
        ));

        if (count($attempts) >= self::RATE_LIMIT_MAX) {
            throw new RuntimeException('Zu viele Anfragen. Bitte warten Sie einige Minuten und versuchen Sie es erneut.');
        }

        $attempts[] = [
            'at' => $now,
            'id' => hash('sha256', strtolower(trim($identifier))),
        ];
        $_SESSION[$key] = $attempts;
    }
}
