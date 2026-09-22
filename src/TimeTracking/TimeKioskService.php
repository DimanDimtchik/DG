<?php
declare(strict_types=1);

/**
 * Öffentliche Stempeluhr (Kiosk/Tablet) mit PIN — ohne CRM-Login.
 */
final class TimeKioskService
{
    public const COOKIE = 'dg_kiosk_session';
    public const SESSION_HOURS = 12;
    public const SET_TOKEN_HOURS = 24;
    public const MIN_PIN_LEN = 4;
    public const MAX_PIN_LEN = 8;
    public const PAGE_SLUG = 'stempeluhr';

    public static function canManagePins(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return TimeClockService::canViewTeam($user);
    }

    public static function ensureDraftWebsitePage(?int $userId = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        $existing = WebsitePageRepository::findBySlugAnyStatus(self::PAGE_SLUG);
        if ($existing !== null) {
            return;
        }
        WebsitePageRepository::save([
            'title' => 'Stempeluhr',
            'slug' => self::PAGE_SLUG,
            'status' => WebsitePageRepository::STATUS_DRAFT,
            'layout' => [
                'page_kind' => 'time_kiosk',
                'rows' => [[
                    'id' => 'row-kiosk-intro',
                    'columns' => [[
                        'id' => 'col-kiosk-intro',
                        'width' => 12,
                        'blocks' => [
                            [
                                'id' => 'blk-kiosk-h',
                                'type' => 'heading',
                                'text' => 'Stempeluhr (Kiosk)',
                                'level' => 'h1',
                            ],
                            [
                                'id' => 'blk-kiosk-t',
                                'type' => 'text',
                                'text' => 'Diese Seite ist als Entwurf angelegt. Die Stempeluhr funktioniert unter /stempeluhr ohne CRM-Login. '
                                    . 'Veröffentlichen Sie die Seite, wenn sie im Website-Menü erscheinen soll. '
                                    . 'Mitarbeiter melden sich mit Login/E-Mail und PIN an.',
                            ],
                        ],
                    ]],
                ]],
            ],
        ], null, $userId);
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function validatePinFormat(string $pin): void
    {
        $pin = trim($pin);
        $len = strlen($pin);
        if ($len < self::MIN_PIN_LEN || $len > self::MAX_PIN_LEN || !ctype_digit($pin)) {
            throw new InvalidArgumentException(
                sprintf('PIN muss %d–%d Ziffern haben.', self::MIN_PIN_LEN, self::MAX_PIN_LEN)
            );
        }
    }

    public static function setPin(int $contactId, string $pin, ?User $actor): void
    {
        self::assertStaffContact($contactId);
        self::validatePinFormat($pin);
        if ($actor !== null && !self::canManagePins($actor)) {
            $own = ContactRepository::findStaffContactIdForUser($actor);
            if ($own !== $contactId) {
                throw new RuntimeException('Keine Berechtigung, diese PIN zu ändern.');
            }
        }
        TimeKioskPinRepository::upsertPin(
            $contactId,
            password_hash($pin, PASSWORD_DEFAULT),
            $actor !== null ? (int) ($actor->id ?? 0) : null
        );
    }

    public static function verifyPin(int $contactId, string $pin): bool
    {
        $hash = TimeKioskPinRepository::findPinHash($contactId);
        if ($hash === null || $hash === '') {
            return false;
        }

        return password_verify(trim($pin), $hash);
    }

    /**
     * @return array{contact_id: int, label: string}|null
     */
    public static function currentSession(): ?array
    {
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($raw === '' || !str_contains($raw, '.')) {
            return null;
        }
        TimeKioskPinRepository::purgeExpiredSessions();
        [$idPart, $secret] = explode('.', $raw, 2);
        $sessionId = (int) $idPart;
        if ($sessionId < 1 || $secret === '') {
            return null;
        }
        $hash = self::hashToken($secret);
        $row = TimeKioskPinRepository::findValidSession($hash);
        if ($row === null || (int) ($row['id'] ?? 0) !== $sessionId) {
            return null;
        }
        $contactId = (int) ($row['contact_id'] ?? 0);
        $contact = ContactRepository::findById($contactId);
        if ($contact === null || !self::isStaffRole($contact->contactRole)) {
            return null;
        }

        return [
            'contact_id' => $contactId,
            'label' => $contact->listLabel(),
        ];
    }

    public static function login(string $identifier, string $pin): array
    {
        $contact = self::resolveStaffByIdentifier($identifier);
        if ($contact === null) {
            throw new InvalidArgumentException('Mitarbeiter nicht gefunden.');
        }
        if (!TimeKioskPinRepository::hasPin($contact->id)) {
            throw new InvalidArgumentException('Für diesen Mitarbeiter ist noch keine PIN gesetzt. Bitte HR oder CRM nutzen.');
        }
        if (!self::verifyPin($contact->id, $pin)) {
            throw new InvalidArgumentException('PIN ungültig.');
        }

        self::startSession($contact->id);

        return [
            'contact_id' => $contact->id,
            'label' => $contact->listLabel(),
        ];
    }

    public static function logout(): void
    {
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($raw !== '' && str_contains($raw, '.')) {
            [, $secret] = explode('.', $raw, 2);
            if ($secret !== '') {
                TimeKioskPinRepository::deleteSession(self::hashToken($secret));
            }
        }
        self::clearCookie();
    }

    public static function recordClockSafe(string $eventType): array
    {
        $session = self::currentSession();
        if ($session === null) {
            throw new RuntimeException('Bitte zuerst mit PIN anmelden.');
        }
        TimeClockService::recordEvent(
            $session['contact_id'],
            $eventType,
            null,
            TimeClockRepository::SOURCE_KIOSK
        );

        return [
            'status' => TimeClockService::currentStatus($session['contact_id']),
            'summary' => TimeClockService::daySummary($session['contact_id']),
            'label' => $session['label'],
        ];
    }

    public static function requestPinReset(string $identifier): void
    {
        $contact = self::resolveStaffByIdentifier($identifier);
        if ($contact === null) {
            // Keine Enumeration: gleiche Erfolgsmeldung außen
            return;
        }

        $plainHr = bin2hex(random_bytes(32));
        TimeKioskPinRepository::createResetRequest(
            $contact->id,
            self::hashToken($plainHr),
            self::clientIp()
        );

        $base = rtrim(App::publicBaseUrl(), '/');
        $hrUrl = $base . '/stempeluhr/pin-anfrage?token=' . rawurlencode($plainHr);
        $emails = OvertimeNotificationRecipients::managerEmailsForEmployee($contact->id);
        if ($emails === []) {
            return;
        }

        $label = $contact->listLabel();
        $html = '<p>PIN-Vergessen-Anfrage am Kiosk.</p>'
            . '<p><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ' (Kontakt #' . (int) $contact->id . ')</p>'
            . '<p>Wenn die Person berechtigt ist:</p>'
            . '<p><a href="' . htmlspecialchars($hrUrl, ENT_QUOTES, 'UTF-8') . '">Anfrage prüfen (Erlauben / Blockieren)</a></p>';

        try {
            MailService::send(new MailMessage(
                subject: 'Stempeluhr: PIN-Anfrage — ' . $label,
                htmlBody: $html,
                to: $emails,
                contactId: $contact->id,
            ));
        } catch (Throwable) {
            // Anfrage bleibt in CRM-Liste sichtbar
        }
    }

    /**
     * @return array{reset: array<string, mixed>, contact: Contact|null}
     */
    public static function loadHrRequest(string $plainToken): array
    {
        $row = TimeKioskPinRepository::findResetByHrTokenHash(self::hashToken($plainToken));
        if ($row === null) {
            throw new InvalidArgumentException('Anfrage ungültig oder abgelaufen.');
        }
        $contact = ContactRepository::findById((int) ($row['contact_id'] ?? 0));

        return ['reset' => $row, 'contact' => $contact];
    }

    public static function decideHrRequest(string $plainToken, string $decision, ?User $actor): void
    {
        $loaded = self::loadHrRequest($plainToken);
        $row = $loaded['reset'];
        $id = (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? '');
        if ($status !== TimeKioskPinRepository::STATUS_PENDING_HR) {
            throw new InvalidArgumentException('Anfrage wurde bereits bearbeitet.');
        }

        $decidedBy = $actor !== null ? (int) ($actor->id ?? 0) : null;
        if ($decision === 'block') {
            TimeKioskPinRepository::markBlocked($id, $decidedBy);

            return;
        }
        if ($decision !== 'allow') {
            throw new InvalidArgumentException('Ungültige Entscheidung.');
        }

        $contact = $loaded['contact'];
        if ($contact === null) {
            throw new RuntimeException('Mitarbeiter nicht gefunden.');
        }
        $empEmail = OvertimeNotificationRecipients::employeeEmailForContact($contact->id);
        if ($empEmail === null) {
            throw new RuntimeException('Mitarbeiter hat keine gültige E-Mail — PIN kann nicht zugestellt werden.');
        }

        $plainSet = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable('+' . self::SET_TOKEN_HOURS . ' hours'))->format('Y-m-d H:i:s');
        TimeKioskPinRepository::markApproved($id, self::hashToken($plainSet), $expires, $decidedBy);

        $setUrl = rtrim(App::publicBaseUrl(), '/') . '/stempeluhr/pin-setzen?token=' . rawurlencode($plainSet);
        $html = '<p>Ihre PIN-Anfrage für die Stempeluhr wurde freigegeben.</p>'
            . '<p><a href="' . htmlspecialchars($setUrl, ENT_QUOTES, 'UTF-8') . '">Neue PIN festlegen</a></p>'
            . '<p>Der Link ist ' . self::SET_TOKEN_HOURS . ' Stunden gültig.</p>';

        MailService::send(new MailMessage(
            subject: 'Stempeluhr: Neue PIN festlegen',
            htmlBody: $html,
            to: [$empEmail],
            contactId: $contact->id,
        ));
    }

    /** CRM: Freigabe ohne E-Mail-Token. */
    public static function decideHrRequestById(int $resetId, string $decision, User $actor): void
    {
        if (!self::canManagePins($actor)) {
            throw new RuntimeException('Keine Berechtigung.');
        }
        $row = TimeKioskPinRepository::findResetById($resetId);
        if ($row === null) {
            throw new InvalidArgumentException('Anfrage nicht gefunden.');
        }
        // Recreate flow using stored hash — need plain token for decideHrRequest
        // Direct path:
        $status = (string) ($row['status'] ?? '');
        if ($status !== TimeKioskPinRepository::STATUS_PENDING_HR) {
            throw new InvalidArgumentException('Anfrage wurde bereits bearbeitet.');
        }
        $id = (int) ($row['id'] ?? 0);
        $decidedBy = (int) ($actor->id ?? 0);
        if ($decision === 'block') {
            TimeKioskPinRepository::markBlocked($id, $decidedBy);

            return;
        }
        if ($decision !== 'allow') {
            throw new InvalidArgumentException('Ungültige Entscheidung.');
        }
        $contactId = (int) ($row['contact_id'] ?? 0);
        $empEmail = OvertimeNotificationRecipients::employeeEmailForContact($contactId);
        if ($empEmail === null) {
            throw new RuntimeException('Mitarbeiter hat keine gültige E-Mail.');
        }
        $plainSet = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable('+' . self::SET_TOKEN_HOURS . ' hours'))->format('Y-m-d H:i:s');
        TimeKioskPinRepository::markApproved($id, self::hashToken($plainSet), $expires, $decidedBy);
        $setUrl = rtrim(App::publicBaseUrl(), '/') . '/stempeluhr/pin-setzen?token=' . rawurlencode($plainSet);
        $html = '<p>Ihre PIN-Anfrage für die Stempeluhr wurde freigegeben.</p>'
            . '<p><a href="' . htmlspecialchars($setUrl, ENT_QUOTES, 'UTF-8') . '">Neue PIN festlegen</a></p>'
            . '<p>Der Link ist ' . self::SET_TOKEN_HOURS . ' Stunden gültig.</p>';
        MailService::send(new MailMessage(
            subject: 'Stempeluhr: Neue PIN festlegen',
            htmlBody: $html,
            to: [$empEmail],
            contactId: $contactId,
        ));
    }

    public static function completePinSet(string $plainSetToken, string $pin): void
    {
        $row = TimeKioskPinRepository::findResetBySetTokenHash(self::hashToken($plainSetToken));
        if ($row === null) {
            throw new InvalidArgumentException('Link ungültig oder bereits verwendet.');
        }
        if ((string) ($row['status'] ?? '') !== TimeKioskPinRepository::STATUS_APPROVED) {
            throw new InvalidArgumentException('Link nicht mehr gültig.');
        }
        $exp = (string) ($row['set_token_expires_at'] ?? '');
        if ($exp !== '' && strtotime($exp) !== false && strtotime($exp) < time()) {
            throw new InvalidArgumentException('Link abgelaufen. Bitte erneut „PIN vergessen“ am Kiosk nutzen.');
        }
        $contactId = (int) ($row['contact_id'] ?? 0);
        self::setPin($contactId, $pin, null);
        TimeKioskPinRepository::markCompleted((int) ($row['id'] ?? 0));
    }

    public static function resolveStaffByIdentifier(string $identifier): ?Contact
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        $byLogin = ContactRepository::findByLogin($identifier);
        if ($byLogin !== null && self::isStaffRole($byLogin->contactRole)) {
            return $byLogin;
        }
        $byEmail = ContactRepository::findByEmailMatch($identifier);
        if ($byEmail !== null && self::isStaffRole($byEmail->contactRole)) {
            return $byEmail;
        }
        // Personalnummer in employee_data
        foreach (TimeMonthReportService::staffOptions() as $opt) {
            $id = (int) ($opt['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $c = ContactRepository::findById($id);
            if ($c === null) {
                continue;
            }
            $pnr = trim((string) ($c->employeeData['datev_personnel_number'] ?? ''));
            if ($pnr !== '' && strcasecmp($pnr, $identifier) === 0) {
                return $c;
            }
            if (strcasecmp(trim($c->listLabel()), $identifier) === 0) {
                return $c;
            }
        }

        return null;
    }

    private static function startSession(int $contactId): void
    {
        $secret = bin2hex(random_bytes(24));
        $expires = (new DateTimeImmutable('+' . self::SESSION_HOURS . ' hours'))->format('Y-m-d H:i:s');
        $sid = TimeKioskPinRepository::createSession($contactId, self::hashToken($secret), $expires);
        $value = $sid . '.' . $secret;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE, $value, [
            'expires' => time() + self::SESSION_HOURS * 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }

    private static function clearCookie(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function assertStaffContact(int $contactId): void
    {
        $c = ContactRepository::findById($contactId);
        if ($c === null || !self::isStaffRole($c->contactRole)) {
            throw new InvalidArgumentException('Nur für Mitarbeiter-Kontakte.');
        }
    }

    private static function isStaffRole(string $role): bool
    {
        return in_array($role, ['dg_eigenmitarbeiter', 'administrator', 'mitarbeiter'], true);
    }

    private static function clientIp(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $ip !== '' ? substr($ip, 0, 45) : null;
    }
}
