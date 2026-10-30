<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

// CRM-Login: /Login und /LOGIN → /login (Autocomplete/Lesezeichen)
if (strcasecmp($path, '/login') === 0) {
    $path = '/login';
}

if (preg_match('#^/media/training/([a-z0-9_-]+)/([a-z0-9_.-]+\.mp4)$#', $path, $trainingVideoMatch)) {
    $rel = 'media/training/' . $trainingVideoMatch[1] . '/' . $trainingVideoMatch[2];
    $file = DG_ROOT . '/storage/' . $rel;
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        echo 'Nicht gefunden.';
        exit;
    }
    header('Content-Type: video/mp4');
    header('Content-Length: ' . (string) filesize($file));
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

// App-APKs unter /downloads/ — Datei fehlt: klare Meldung (existierende Dateien liefert Apache direkt)
if (preg_match('#^/downloads/(dg-kalender|dg-mitarbeiter)\.apk$#', $path, $apkMatch)) {
    $apkFile = DG_ROOT . '/downloads/' . $apkMatch[1] . '.apk';
    if (is_file($apkFile) && is_readable($apkFile)) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Length: ' . (string) filesize($apkFile));
        header('Content-Disposition: attachment; filename="' . $apkMatch[1] . '.apk"');
        header('Cache-Control: private, max-age=0');
        readfile($apkFile);
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><title>App noch nicht hinterlegt</title></head><body style="font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem;">'
        . '<h1>App noch nicht hinterlegt</h1>'
        . '<p>Die Installationsdatei liegt noch nicht auf dem Server. Bitte wenden Sie sich an Ihre Firma oder versuchen Sie es später erneut.</p>'
        . '<p><a href="/apps">Zurück zur App-Download-Seite</a></p>'
        . '</body></html>';
    exit;
}

if (preg_match('#^/vorschau/([a-z0-9-]+)$#', $path, $previewMatch)) {
    $previewUser = AuthService::user();
    if ($previewUser === null || !MenuRegistry::canAccess($previewUser, 'website-seiten')) {
        header('Location: /login', true, 302);
        exit;
    }
    $previewPage = WebsitePageRepository::findBySlugAnyStatus($previewMatch[1]);
    if ($previewPage === null) {
        http_response_code(404);
        View::render('offline');
        exit;
    }
    View::render('website-public', [
        'page' => LegalPagePublicHelper::enrichPage($previewPage, true),
        'chrome' => WebsiteSettings::chrome(),
        'menu' => WebsiteSettings::publicMenu(true),
        'design' => WebsiteSettings::design(),
        'previewMode' => true,
        'previewFrame' => isset($_GET['frame']) && (string) $_GET['frame'] === '1',
    ]);
    exit;
}

// Mobile Apps JSON-API (ohne CRM-Session)
if ($path === '/api/mobile' || str_starts_with($path, '/api/mobile/')) {
    MobileApi::handle($path);
    exit;
}

// Postfach-Passwort selbst setzen (Einladungslink)
if ($path === '/postfach-passwort') {
    if (!Database::isConfigured()) {
        http_response_code(503);
        echo 'Nicht verfügbar (keine Datenbank).';
        exit;
    }
    MigrationRunner::runPending();
    $error = null;
    $token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
    $tokenValid = $token !== '' && MailboxPasswordTokenRepository::isValid($token);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
        } else {
            try {
                StaffAccessInviteService::completeMailboxPasswordSet(
                    $token,
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirm'] ?? '')
                );
                Flash::set('success', 'Postfach-Passwort gespeichert. Sie können diese Seite schließen.');
                header('Location: /login', true, 302);
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $tokenValid = MailboxPasswordTokenRepository::isValid($token);
            }
        }
    }
    View::render('mailbox-password-set', compact('error', 'token', 'tokenValid'));
    exit;
}

// App mit CRM-Instanz verbinden (Download + Deep-Link)
if ($path === '/app-connect') {
    $base = trim((string) ($_GET['base'] ?? ''));
    if ($base === '') {
        $base = App::publicBaseUrl();
    }
    $base = rtrim($base, '/');
    if ($base !== '' && !preg_match('#^https?://#i', $base)) {
        $base = 'https://' . $base;
    }
    $baseUrl = $base !== '' ? $base : rtrim(App::publicBaseUrl(), '/');
    $connectScheme = 'dg-mitarbeiter://setup?base=' . rawurlencode($baseUrl);
    $apkMitarbeiter = $baseUrl . '/' . MobileAppDownloadService::MITARBEITER_APK;
    $apkKalender = $baseUrl . '/' . MobileAppDownloadService::KALENDER_APK;
    View::render('app-connect', compact('baseUrl', 'connectScheme', 'apkMitarbeiter', 'apkKalender'));
    exit;
}

// Öffentliche Stempeluhr (Kiosk) — ohne CRM-Login, auch im Wartungsmodus
if ($path === '/stempeluhr' || str_starts_with($path, '/stempeluhr/')) {
    if (!Database::isConfigured()) {
        http_response_code(503);
        echo 'Stempeluhr nicht verfügbar (keine Datenbank).';
        exit;
    }
    MigrationRunner::runPending();
    TimeKioskService::ensureDraftWebsitePage();

    if ($path === '/stempeluhr/pin-anfrage') {
        $token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
        $kioskFlash = null;
        $kioskFlashType = 'info';
        $kioskDone = false;
        $kioskReset = [];
        $kioskContact = null;
        $kioskToken = $token;
        try {
            if ($token === '') {
                throw new InvalidArgumentException('Token fehlt.');
            }
            $loaded = TimeKioskService::loadHrRequest($token);
            $kioskReset = $loaded['reset'];
            $kioskContact = $loaded['contact'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $decision = (string) ($_POST['decision'] ?? '');
                TimeKioskService::decideHrRequest($token, $decision, AuthService::user());
                $kioskDone = true;
                $kioskFlashType = 'success';
                $kioskFlash = $decision === 'allow'
                    ? 'Erlaubt. Der Mitarbeiter erhält eine E-Mail mit Link zur neuen PIN.'
                    : 'Anfrage blockiert.';
                $kioskReset = TimeKioskService::loadHrRequest($token)['reset'];
            }
        } catch (Throwable $e) {
            $kioskFlash = $e->getMessage();
            $kioskFlashType = 'error';
            $kioskDone = true;
        }
        View::render('website-kiosk-hr', compact('kioskReset', 'kioskContact', 'kioskFlash', 'kioskFlashType', 'kioskDone', 'kioskToken'));
        exit;
    }

    if ($path === '/stempeluhr/pin-setzen') {
        $token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
        $kioskFlash = null;
        $kioskFlashType = 'info';
        $kioskDone = false;
        $kioskToken = $token;
        try {
            if ($token === '') {
                throw new InvalidArgumentException('Link ungültig.');
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $pin = (string) ($_POST['pin'] ?? '');
                $pin2 = (string) ($_POST['pin_confirm'] ?? '');
                if ($pin !== $pin2) {
                    throw new InvalidArgumentException('PINs stimmen nicht überein.');
                }
                TimeKioskService::completePinSet($token, $pin);
                $kioskDone = true;
                $kioskFlashType = 'success';
                $kioskFlash = 'PIN gespeichert. Sie können sich an der Stempeluhr anmelden.';
            }
        } catch (Throwable $e) {
            $kioskFlash = $e->getMessage();
            $kioskFlashType = 'error';
        }
        View::render('website-kiosk-pin-set', compact('kioskToken', 'kioskFlash', 'kioskFlashType', 'kioskDone'));
        exit;
    }

    if ($path !== '/stempeluhr') {
        http_response_code(404);
        echo 'Nicht gefunden.';
        exit;
    }

    $kioskFlash = null;
    $kioskFlashType = 'info';
    $kioskView = trim((string) ($_GET['view'] ?? ''));
    $allowedViews = ['forgot', 'absence', 'absence_form'];
    if (!in_array($kioskView, $allowedViews, true)) {
        $kioskView = '';
    }
    $kioskAbsenceType = trim((string) ($_GET['type'] ?? $_POST['type'] ?? ''));
    $kioskAbsenceTypes = TimeTrackingSettings::enabledAbsenceTypesForKiosk();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['kiosk_action'] ?? '');
        try {
            if ($action === 'login') {
                TimeKioskService::login((string) ($_POST['identifier'] ?? ''), (string) ($_POST['pin'] ?? ''));
                $kioskFlash = 'Angemeldet.';
                $kioskFlashType = 'success';
                $kioskView = '';
            } elseif ($action === 'logout') {
                TimeKioskService::logout();
                $kioskFlash = 'Abgemeldet.';
                $kioskFlashType = 'success';
            } elseif ($action === 'clock') {
                TimeKioskService::recordClockSafe((string) ($_POST['event_type'] ?? ''));
                $kioskFlash = 'Stempelung erfasst.';
                $kioskFlashType = 'success';
            } elseif ($action === 'forgot') {
                TimeKioskService::requestPinReset((string) ($_POST['identifier'] ?? ''));
                $kioskFlash = 'Wenn der Mitarbeiter bekannt ist, wurde die Personalabteilung benachrichtigt.';
                $kioskFlashType = 'success';
                $kioskView = '';
            } elseif ($action === 'absence_request') {
                $sess = TimeKioskService::currentSession();
                if ($sess === null) {
                    throw new RuntimeException('Bitte zuerst anmelden.');
                }
                $res = TimeAbsenceService::requestFromKiosk(
                    (int) ($sess['contact_id'] ?? 0),
                    (string) ($_POST['type'] ?? ''),
                    (string) ($_POST['date_from'] ?? ''),
                    (string) ($_POST['date_to'] ?? ''),
                    (string) ($_POST['reason'] ?? ''),
                    !empty($_POST['half_day']),
                    is_array($_FILES['evidence'] ?? null) ? $_FILES['evidence'] : [],
                    !empty($_POST['acknowledge'])
                );
                $kioskFlash = $res['message'];
                $kioskFlashType = 'success';
                $kioskView = '';
            }
        } catch (Throwable $e) {
            $kioskFlash = $e->getMessage();
            $kioskFlashType = 'error';
            if ($action === 'forgot') {
                $kioskView = 'forgot';
            } elseif ($action === 'absence_request') {
                $kioskView = 'absence_form';
                $kioskAbsenceType = (string) ($_POST['type'] ?? $kioskAbsenceType);
            }
        }
    }

    $kioskSession = TimeKioskService::currentSession();
    $kioskStatus = null;
    $kioskSummary = null;
    if ($kioskSession !== null) {
        $kioskStatus = TimeClockService::currentStatus($kioskSession['contact_id']);
        $kioskSummary = TimeClockService::daySummary($kioskSession['contact_id']);
        if ($kioskView === 'forgot') {
            $kioskView = 'clock';
        } elseif ($kioskView === 'absence_form') {
            if (!in_array($kioskAbsenceType, $kioskAbsenceTypes, true)) {
                $kioskView = 'absence';
                $kioskAbsenceType = '';
            }
        } elseif ($kioskView === '') {
            $kioskView = 'clock';
        }
    } elseif ($kioskView !== 'forgot') {
        $kioskView = 'login';
    }

    View::render('website-kiosk', compact(
        'kioskSession',
        'kioskSummary',
        'kioskStatus',
        'kioskFlash',
        'kioskFlashType',
        'kioskView',
        'kioskAbsenceTypes',
        'kioskAbsenceType'
    ));
    exit;
}

switch ($path) {
    case '/':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }
        if (!Database::isConfigured()) {
            WebsiteMaintenanceSettings::renderPlaceholderMaintenance();
        }
        // Show published homepage if available, otherwise redirect to login
        if (Database::isConfigured()) {
            if (WebsiteMaintenanceSettings::isActive()) {
                WebsiteMaintenanceSettings::renderAndExit();
            }
            $homepage = WebsitePageRepository::findHomepage();
            if ($homepage !== null) {
                View::render('website-public', [
                    'page' => $homepage,
                    'chrome' => WebsiteSettings::chrome(),
                    'menu' => WebsiteSettings::publicMenu(),
                    'design' => WebsiteSettings::design(),
                ]);
                break;
            }
        }
        header('Location: /login', true, 302);
        exit;

    case '/register':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }

        $error = null;
        $form = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = [
                'username' => trim((string) ($_POST['username'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            ];
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');

            try {
                if ($password !== $confirm) {
                    throw new InvalidArgumentException('Passwörter stimmen nicht überein.');
                }
                $newUser = UserRepository::register($form['username'], $form['email'], $form['display_name'], $password);
                AuthService::loginUser($newUser);
                header('Location: ' . RoleResolver::homePath($newUser), true, 302);
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        View::render('register', compact('error', 'form'));
        break;

    case '/firm-switch':
        // Multi-Firma MF1/MF5: Redirect zur verknüpften Instanz (Allowlist, optional SSO-Token).
        if (!AuthService::check()) {
            header('Location: /login', true, 302);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /app', true, 302);
            exit;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Flash::set('error', 'Ungültiges Formular (CSRF).');
            header('Location: /app', true, 302);
            exit;
        }
        if (class_exists('SupportSession') && SupportSession::isActive()) {
            Flash::set('error', 'Firmenwechsel in der Support-Session nicht erlaubt.');
            header('Location: /app', true, 302);
            exit;
        }
        $switchDomain = (string) ($_POST['domain'] ?? '');
        $switchUser = AuthService::user();
        $targetUrl = FirmSwitcherService::redirectUrlForDomainWithSso($switchDomain, $switchUser);
        if ($targetUrl === null) {
            Flash::set('error', 'Firmenwechsel nicht erlaubt oder bereits aktuelle Firma.');
            header('Location: /app', true, 302);
            exit;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['firm_switch_at'] = time();
        }
        header('Location: ' . $targetUrl, true, 302);
        exit;

    case '/login':
        // MF5c: Token nicht in Referrer weiterreichen
        SecurityHeaders::sendNoReferrer();

        // MF5b: Handoff-Token vor normalem Login prüfen
        $firmSsoToken = trim((string) ($_GET['firm_sso'] ?? ''));
        if ($firmSsoToken !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            if (AuthService::check()) {
                $u = AuthService::user();
                header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
                exit;
            }
            $sso = FirmSsoService::consume($firmSsoToken);
            if (!empty($sso['ok']) && isset($sso['user']) && $sso['user'] instanceof User) {
                if (!AuthService::loginUser($sso['user'])) {
                    Flash::set('error', 'Firmenwechsel abgelaufen oder ungültig.');
                    header('Location: /login', true, 302);
                    exit;
                }
                $_SESSION['firm_switched_notice'] = 1;
                header('Location: ' . RoleResolver::homePath($sso['user']), true, 302);
                exit;
            }
            Flash::set('error', (string) ($sso['message'] ?? 'Firmenwechsel abgelaufen oder ungültig.'));
            header('Location: /login', true, 302);
            exit;
        }

        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }

        $error = null;
        $flash = Flash::pull();
        if (is_array($flash) && ($flash['type'] ?? '') === 'error' && $error === null) {
            $error = (string) ($flash['message'] ?? '');
            $flash = null;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'Bitte Benutzername und Passwort eingeben.';
            } else {
                $result = AuthService::attempt($username, $password);
                if ($result === true) {
                    $loggedIn = UserRepository::findByEmailOrUsername($username);
                    header('Location: ' . ($loggedIn ? RoleResolver::homePath($loggedIn) : '/app'), true, 302);
                    exit;
                } elseif (is_string($result)) {
                    $error = $result;
                } else {
                    $user = UserRepository::findByEmailOrUsername($username);
                    if ($user && !RoleResolver::canAccessCrm($user)) {
                        $error = 'Für dieses Konto ist kein CRM-Zugang freigeschaltet.';
                    } else {
                        $error = 'Anmeldung fehlgeschlagen. Benutzername oder Passwort ist falsch.';
                    }
                }
            }
        } else {
            // GET: Session-Lock freigeben — sonst blockiert ein hängender POST alle weiteren Tabs.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }

        View::render('login', ['error' => $error, 'flash' => $flash]);
        break;

    case '/passwort-vergessen':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }

        $error = null;
        $success = null;
        $identifier = trim((string) ($_POST['identifier'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
            } elseif ($identifier === '') {
                $error = 'Bitte E-Mail oder Benutzername eingeben.';
            } else {
                try {
                    PasswordResetService::requestReset($identifier);
                    $success = PasswordResetService::REQUEST_SUCCESS_MESSAGE;
                    $identifier = '';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        View::render('forgot-password', compact('error', 'success', 'identifier'));
        break;

    case '/passwort-zuruecksetzen':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }

        $error = null;
        $token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
        $tokenValid = $token !== '' && PasswordResetService::validateToken($token) !== null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
            } else {
                $password = (string) ($_POST['password'] ?? '');
                $confirm = (string) ($_POST['password_confirm'] ?? '');
                try {
                    PasswordResetService::resetPassword($token, $password, $confirm);
                    Flash::set('success', 'Ihr Passwort wurde geÃ¤ndert. Sie kÃ¶nnen sich jetzt anmelden.');
                    header('Location: /login', true, 302);
                    exit;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    $tokenValid = PasswordResetService::validateToken($token) !== null;
                }
            }
        }

        View::render('reset-password', compact('error', 'token', 'tokenValid'));
        break;

    case '/konto-aktivieren':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }

        $error = null;
        $activateSuccess = false;
        $token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
        $tokenValid = $token !== '' && PasswordResetService::validateToken($token) !== null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
            } else {
                $password = (string) ($_POST['password'] ?? '');
                $confirm = (string) ($_POST['password_confirm'] ?? '');
                try {
                    PasswordResetService::resetPassword($token, $password, $confirm);
                    Flash::set('success', 'Ihr Konto ist jetzt aktiv. Sie können sich anmelden.');
                    header('Location: /login', true, 302);
                    exit;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    $tokenValid = PasswordResetService::validateToken($token) !== null;
                }
            }
        }

        $activateMode = true;
        View::render('activate-account', compact('error', 'token', 'tokenValid', 'activateMode'));
        break;

    case '/support-zugang':
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        $error = null;
        if ($token === '') {
            $error = 'Kein gültiger Support-Token.';
            View::render('support-zugang', compact('error'));
            break;
        }
        $grant = SupportAccessService::findActiveByToken($token);
        if ($grant === null) {
            $error = 'Support-Freigabe ungültig oder abgelaufen.';
            View::render('support-zugang', compact('error'));
            break;
        }
        SupportSession::login($grant, $token);
        header('Location: /app?page=support-zuschauen', true, 302);
        exit;

    case '/logout':
        AuthService::logout();
        header('Location: /', true, 302);
        exit;

    case '/robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo SitemapGenerator::robotsTxt();
        exit;

    case '/sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo SitemapGenerator::sitemapXml();
        exit;

    case '/kontakt-formular':
        // Legacy endpoint: map to default published form if possible, else old fixed fields.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            header('Location: /', true, 302);
            exit;
        }
        if (!empty($_POST['form_id'])) {
            WebsiteFormSubmitHandler::handle();
        }
        // Fall through to legacy handler below if no form_id — keep old contact block working
        $cfTo = filter_var(trim($_POST['to'] ?? ''), FILTER_VALIDATE_EMAIL);
        $cfName = trim($_POST['name'] ?? '');
        $cfEmail = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $cfMsg = trim($_POST['message'] ?? '');
        $cfSubject = trim($_POST['subject'] ?? 'Kontaktanfrage');

        if ($cfTo && $cfEmail && $cfName !== '' && $cfMsg !== '') {
            $html = '<p><strong>Name:</strong> ' . htmlspecialchars($cfName) . '</p>'
                . '<p><strong>E-Mail:</strong> ' . htmlspecialchars($cfEmail) . '</p>'
                . '<p><strong>Nachricht:</strong></p><p>' . nl2br(htmlspecialchars($cfMsg)) . '</p>';
            try {
                if (class_exists('MailService') && MailSettings::isConfigured()) {
                    MailService::send(new MailMessage(to: [$cfTo], subject: $cfSubject . ' von ' . $cfName, htmlBody: $html, replyTo: $cfEmail));
                } else {
                    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nReply-To: $cfEmail\r\nFrom: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
                    @mail($cfTo, $cfSubject . ' von ' . $cfName, $html, $headers);
                }
            } catch (Throwable $e) {
                // Silently fail
            }
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $ref, true, 302);
        exit;

    case '/formular-senden':
        WebsiteFormSubmitHandler::handle();
        exit;

    case '/api/website-form/appointments':
        WebsiteFormPublicApi::appointments();
        exit;

    case '/api/support/signal':
        SupportAccessApi::handleSignal();
        exit;

    case '/api/kdv/provision':
        KdvProvisionApi::handle();
        exit;

    case '/api/kdv/support-grant':
        SupportAccessApi::handleHubGrant();
        exit;

    case '/api/kdv/account/login':
    case '/api/kdv/account/me':
    case '/api/kdv/account/logout':
    case '/api/kdv/account/unlock-request':
    case '/api/kdv/account/password-reset/request':
    case '/api/kdv/account/password-reset/confirm':
        KdvAccountApi::handle($path);
        exit;

    case '/api/recipe-cost':
        RecipeCostApi::handle();
        exit;

    case '/api/kichel':
        KichelApi::handle();
        exit;

    case '/api/finanzamt-lookup':
        FinanzamtLookupApi::handle();
        exit;

    case '/api/website-url-check':
        WebsiteUrlCheckApi::handle();
        exit;

    case '/api/health-insurer-suggest':
        header('Content-Type: application/json; charset=utf-8');
        $apiUser = AuthService::user();
        if (!$apiUser || !RoleResolver::canEdit($apiUser)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'UngÃ¼ltiges Formular.']);
            exit;
        }
        $value = (string) ($_POST['value'] ?? '');
        $data = HealthInsurerDirectory::suggestResponse($value);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;

    case '/api/bank-suggest':
        header('Content-Type: application/json; charset=utf-8');
        $apiUser = AuthService::user();
        if (!$apiUser || !RoleResolver::canEdit($apiUser)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.']);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'UngÃ¼ltiges Formular.']);
            exit;
        }
        $field = preg_replace('/[^a-z_]/', '', (string) ($_POST['field'] ?? ''));
        $value = (string) ($_POST['value'] ?? '');
        try {
            $data = BankDirectory::suggestResponse($field, $value);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;

    case '/api/bank-match-suggest':
        header('Content-Type: application/json; charset=utf-8');
        $apiUser = AuthService::user();
        if (
            !$apiUser
            || !RoleResolver::canEdit($apiUser)
            || !MenuRegistry::canAccess($apiUser, 'buchhaltung-bankabgleich')
        ) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Nur GET erlaubt.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        echo json_encode([
            'success' => true,
            'data' => ['items' => BankReconciliationService::searchOpenVouchers($q, 15)],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case '/api/calendar-staff':
        CalendarStaffApi::handle();
        exit;

    case '/api/media':
        MediaApi::handle();
        exit;

    case '/api/writer-assist':
        WriterAssistApi::handle();
        exit;

    case '/api/website-videos':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' || (string) ($_GET['action'] ?? '') !== 'list') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Nur GET action=list'], JSON_THROW_ON_ERROR);
            exit;
        }
        $videoListUser = AuthService::user();
        if ($videoListUser === null || !RoleResolver::canEdit($videoListUser) || !MenuRegistry::canAccessWebsite($videoListUser)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Keine Berechtigung'], JSON_THROW_ON_ERROR);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['items' => WebsiteVideoLibrary::listForPicker()], JSON_UNESCAPED_UNICODE);
        exit;

    case '/api/chart-account':
        ChartAccountApi::handle();
        exit;

    case '/api/voucher':
        VoucherApi::handle();
        exit;

    case '/api/stock-scan':
        StockScanApi::handle();
        exit;

    case '/api/academy':
        AcademyApi::handle();
        exit;

    case '/api/number-range-preview':
        NumberRangeApi::handlePreview();
        exit;

    case '/api/booking-slots':
        BookingSlotsApi::handle();
        exit;

    case '/api/calendar-email-preview':
        CalendarEmailTemplateApi::handlePreview();
        exit;

    case '/api/email-layout-preview':
        EmailLayoutPreviewApi::handlePreview();
        exit;

    case '/api/mail-inbound':
        MailInboundApi::handle();
        exit;

    case '/api/post-sync':
        PostSyncApi::handle();
        exit;

    case '/api/post-compose':
        PostComposeApi::handle();
        exit;
    case '/api/complaint':
        ComplaintApi::handle();
        exit;

    case '/api/cti':
        CtiApi::handle();
        exit;

    case '/api/mail-address-preview':
        MailAddressPreviewApi::handle();
        exit;

    case '/api/public-booking':
        PublicBookingApi::handle();
        exit;

    case '/termin':
        PublicBookingPageRenderer::render();
        break;

    case '/api/calendar-articles-template.csv':
        $user = AuthService::user();
        if (!$user || !RoleResolver::isAdmin($user)) {
            http_response_code(403);
            exit('Keine Berechtigung.');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leistungen-vorlage.csv"');
        echo CalendarArticleImporter::templateCsv();
        exit;

    case '/api/calendar-articles-template.json':
        $user = AuthService::user();
        if (!$user || !RoleResolver::isAdmin($user)) {
            http_response_code(403);
            exit('Keine Berechtigung.');
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="leistungen-vorlage.json"');
        echo CalendarArticleImporter::templateJson();
        exit;

    case '/api/calendar-theme.css':
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=300');
        echo CalendarFrontendTheme::inlineCss();
        exit;

    case '/app/media':
        $mediaServeId = (string) ($_GET['id'] ?? '');
        MediaApi::serve($mediaServeId);
        exit;

    case '/app/favicon':
    case '/favicon.ico':
        $faviconSize = isset($_GET['size']) ? (int) $_GET['size'] : 32;
        MediaApi::serveFavicon($faviconSize);
        exit;

    case '/app':
        $user = AuthService::user();
        if (!$user) {
            header('Location: /login', true, 302);
            exit;
        }

        if (RoleResolver::canEdit($user) && Database::isConfigured()) {
            try {
                MigrationRunner::runOnCrmAccess();
            } catch (Throwable) {
                // Schema-Updates dÃ¼rfen CRM-Nutzung nicht blockieren
            }
            try {
                $retentionPurgedCount = EmployeeRetentionService::runOnCrmAccess();
                if ($retentionPurgedCount > 0) {
                    Flash::set(
                        'success',
                        $retentionPurgedCount === 1
                            ? 'Abgelaufene Mitarbeiterdaten bei 1 Kontakt entfernt â€” Rolle ist jetzt Kunde.'
                            : "Abgelaufene Mitarbeiterdaten bei {$retentionPurgedCount} Kontakten entfernt â€” Rolle ist jetzt Kunde."
                    );
                }
            } catch (Throwable) {
                // Bereinigung darf CRM-Nutzung nicht blockieren
            }
        }

        $navMode = RoleResolver::navMode($user);
        $departments = RoleResolver::departmentsFor($user);
        $menuItems = MenuRegistry::modules($user);
        $settingsItem = MenuRegistry::settingsItem($user);
        $buchhaltungSection = MenuRegistry::buchhaltungSection($user);
        $dienstleistungenSection = MenuRegistry::dienstleistungenSection($user);
        $hrSection = MenuRegistry::hrSection($user);
        $lohnSection = MenuRegistry::lohnSection($user);
        $warenwirtschaftSection = MenuRegistry::warenwirtschaftSection($user);
        $websiteSection = MenuRegistry::websiteSection($user);
        $kommunikationSection = MenuRegistry::kommunikationSection($user);
        $kdvSection = MenuRegistry::kdvSection($user);
        $belegeFocus = '';
        $belegeFormFocus = '';
        $flash = Flash::pull();
        $canEdit = RoleResolver::canEdit($user);
        $sidebarItems = MenuRegistry::sidebarItems($user);

        $page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['page']) : 'dashboard';
        $area = isset($_GET['area']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['area']) : null;
        $dept = isset($_GET['dept']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['dept']) : null;
        $action = isset($_GET['action']) ? preg_replace('/[^a-z]/', '', (string) $_GET['action']) : '';

        if ($area === 'settings') {
            header('Location: /app?page=einstellungen', true, 302);
            exit;
        }

        if (!RoleResolver::canAccessArea($user, $page, $area)) {
            header('Location: ' . RoleResolver::homePath($user), true, 302);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !RoleResolver::canEdit($user)) {
            Flash::set('error', 'Keine Berechtigung zum Bearbeiten.');
            header('Location: ' . RoleResolver::homePath($user), true, 302);
            exit;
        }

        if ($page === 'einstellungen' && isset($_GET['group'])) {
            $legacyGroup = preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['group']);
            $legacyTab = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['tab'] ?? ''));
            $mappedTab = SettingsRegistry::resolveLegacyTab($legacyGroup, $legacyTab !== '' ? $legacyTab : null);
            if ($mappedTab !== null) {
                header('Location: ' . SettingsRegistry::tabUrl($mappedTab), true, 302);
                exit;
            }
        }

        $settingsNav = SettingsRegistry::navigation();
        $settingsTab = SettingsRegistry::resolveActiveTab();
        $settingsSelection = SettingsRegistry::resolve($settingsTab);

        if (
            $page === 'einstellungen'
            && $settingsTab === 'lager-struktur'
            && $_SERVER['REQUEST_METHOD'] === 'GET'
            && trim((string) ($_GET['download'] ?? '')) === 'labels'
            && RoleResolver::isAdmin($user)
        ) {
            try {
                $labelIds = [];
                if (isset($_GET['ids']) && is_array($_GET['ids'])) {
                    $labelIds = array_values(array_filter(array_map('intval', $_GET['ids']), static fn (int $id): bool => $id > 0));
                } elseif (isset($_GET['id'])) {
                    $singleId = (int) $_GET['id'];
                    if ($singleId > 0) {
                        $labelIds = [$singleId];
                    }
                }
                $labels = StockLabelService::collectLabels([
                    'level' => (string) ($_GET['label_level'] ?? StockLabelService::LEVEL_PLACE),
                    'location_id' => (int) ($_GET['location_id'] ?? 0),
                    'hall_id' => (int) ($_GET['hall_id'] ?? 0),
                    'shelf_id' => (int) ($_GET['shelf_id'] ?? 0),
                    'ids' => $labelIds,
                ]);
                if ($labels === []) {
                    Flash::set('warning', 'Keine Etiketten für die gewählte Auswahl.');
                    header('Location: ' . SettingsRegistry::tabUrl('lager-struktur') . '&lager_tab=etiketten', true, 302);
                    exit;
                }
                $html = StockLabelPrintService::render(
                    $labels,
                    (string) ($_GET['label_format'] ?? '100x50'),
                );
                StockLabelPrintService::send('lager-etiketten.html', $html);
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                header('Location: ' . SettingsRegistry::tabUrl('lager-struktur') . '&lager_tab=etiketten', true, 302);
            }
            exit;
        }

        // POST: Einstellungen Datenbank
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['db_action'])
        ) {
            $redirect = SettingsRegistry::tabUrl('datenbank');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                $dbAction = (string) ($_POST['db_action'] ?? 'save');
                try {
                    if ($dbAction === 'test') {
                        Flash::set('success', DatabaseSettings::test($_POST));
                    } elseif ($dbAction === 'migrate') {
                        DatabaseSettings::save($_POST);
                        $count = DatabaseSettings::runMigrations();
                        Flash::set('success', "Gespeichert. {$count} Migration(en) ausgefÃ¼hrt.");
                    } else {
                        DatabaseSettings::save($_POST);
                        Flash::set('success', 'Datenbankverbindung gespeichert.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Datensicherung
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['backup_action'])
        ) {
            $redirect = SettingsRegistry::tabUrl('backup');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungueltiges Formular (CSRF).');
            } elseif (!Database::isConfigured()) {
                Flash::set('error', 'Datensicherung erst nach eingerichteter Datenbank moeglich.');
            } else {
                try {
                    $backupPath = BackupService::createBackup();
                    Flash::set('success', 'Datensicherung erstellt: ' . basename($backupPath));
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Firmendaten
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['company_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('firmendaten');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    CompanySettings::save($_POST);
                    CompanyExtendedSettings::saveFromPost($_POST);
                    Flash::set('success', 'Firmendaten gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Steuerkanzlei
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['tax_advisor_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('steuerkanzlei');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    TaxAdvisorSettings::saveFromPost($_POST);
                    Flash::set('success', 'Steuerkanzlei gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen ELSTER (Vorbereitung)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['elster_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('elster');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    ElsterSettings::saveFromPost($_POST);
                    Flash::set('success', 'ELSTER-Vorbereitung gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

// POST: Einstellungen Rechtliches / Mehrprodukt-Tabs
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['legal_products_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('agb');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    LegalProductSettings::saveFromPost($_POST);
                    Flash::set('success', 'Rechtstext-Einstellungen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Rechtstext-Variante (Produkt-Tab)
        if (
            $page === 'website-recht'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['legal_variant_save'])
            && MenuRegistry::canAccess($user, 'website-recht')
        ) {
            $redirect = '/app?page=website-recht&slug=' . rawurlencode((string) ($_POST['slug'] ?? ''))
                . '&product=' . rawurlencode((string) ($_POST['product'] ?? ''));
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    $slug = LegalProductSettings::sanitizeSlug((string) ($_POST['slug'] ?? ''));
                    $productKey = LegalProductSettings::sanitizeProductKey((string) ($_POST['product'] ?? ''));
                    if (!LegalProductSettings::isLegalSlug($slug) || $productKey === '') {
                        throw new InvalidArgumentException('Ungültige Rechtstext-Variante.');
                    }
                    $label = 'Allgemein';
                    foreach (LegalProductSettings::allProductTabs() as $tab) {
                        if ($tab['key'] === $productKey) {
                            $label = $tab['label'];
                            break;
                        }
                    }
                    WebsiteLegalVariantRepository::saveHtmlVariant(
                        $slug,
                        $productKey,
                        $label,
                        (string) ($_POST['status'] ?? WebsitePageRepository::STATUS_DRAFT),
                        (string) ($_POST['html'] ?? ''),
                        0,
                        false
                    );
                    Flash::set('success', 'Rechtstext gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen LDAP (Vorbereitung)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['ldap_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('ldap');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    LdapSettings::saveFromPost($_POST);
                    Flash::set('success', 'LDAP-Vorbereitung gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Kontenrahmen
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['chart_of_accounts_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('chart-of-accounts');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    ChartOfAccountsSettings::saveFromPost($_POST);
                    DatevExportSettings::saveFromPost($_POST);
                    Flash::set('success', 'Kontenrahmen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Zahlungsbedingungen & Mahnung
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['accounting_payment_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('payment-terms');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    AccountingPaymentSettings::saveFromPost($_POST);
                    Flash::set('success', 'Zahlungsbedingungen und Mahnwesen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Belegdarstellung (Kette)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['document_presentation_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('belegdarstellung');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    DocumentPresentationSettings::saveFromPost($_POST);
                    Flash::set('success', 'Belegdarstellung gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // GET: Briefpapier-Vorschau (A4 HTML für iframe / Druck)
        if (
            $page === 'einstellungen'
            && ($_GET['tab'] ?? '') === 'briefpapier'
            && isset($_GET['letterhead_preview'])
            && RoleResolver::isAdmin($user)
        ) {
            $cfg = LetterheadSettings::config();
            $guides = array_key_exists('guides', $_GET)
                ? !empty($_GET['guides'])
                : !empty($cfg['page']['show_din_guides']);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex');
            echo LetterheadRenderer::renderFullHtml(
                LetterheadRenderer::sampleDocument(),
                $cfg,
                ['show_guides' => $guides, 'title' => 'Briefpapier-Vorschau']
            );
            exit;
        }

        // POST: Einstellungen Briefpapier (Dokumentenverfasser)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && (
                isset($_POST['letterhead_save'])
                || isset($_POST['letterhead_reset_din'])
                || isset($_POST['writer_assist_save'])
            )
        ) {
            $redirect = SettingsRegistry::tabUrl('briefpapier');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['writer_assist_save'])) {
                        WriterAssistSettings::saveFromPost($_POST);
                        Flash::set('success', 'Text-Assist-Einstellungen gespeichert.');
                    } elseif (isset($_POST['letterhead_reset_din'])) {
                        LetterheadSettings::resetDinLayoutFromStore();
                        Flash::set('success', 'DIN-Standardlayout wiederhergestellt (Zonen, Ränder, empfohlene Fuß-Schalter).');
                    } else {
                        LetterheadSettings::saveFromPost($_POST);
                        $report = LetterheadSettings::complianceReport();
                        if (!$report['ok']) {
                            Flash::set('success', 'Briefpapier gespeichert. ' . $report['summary'] . ' — Speichern war erlaubt (Soft-Check).');
                        } else {
                            Flash::set('success', 'Briefpapier gespeichert. ' . $report['summary'] . '.');
                        }
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // GET: Dokumentenverfasser Export (PDF via Dompdf / TXT / MD / DOCX)
        if (
            ($page === 'dokumentenverfasser' || $page === 'dokumentenverfasser-form')
            && isset($_GET['writer_export'])
            && MenuRegistry::canAccess($user, 'dokumentenverfasser')
        ) {
            $exportId = (int) ($_GET['id'] ?? 0);
            $exportFormat = strtolower(trim((string) ($_GET['format'] ?? 'pdf')));
            try {
                WriterDocumentRepository::ensureReady();
                WriterExportService::download($exportId, $exportFormat, $user->id);
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                $back = $exportId > 0
                    ? '/app?page=dokumentenverfasser-form&action=edit&id=' . $exportId
                    : '/app?page=dokumentenverfasser';
                header('Location: ' . $back, true, 302);
                exit;
            }
        }

        // GET: Dokumentenverfasser A4-Vorschau (gespeichertes Dokument oder Muster)
        if (
            ($page === 'dokumentenverfasser' || $page === 'dokumentenverfasser-form')
            && isset($_GET['writer_preview'])
            && MenuRegistry::canAccess($user, 'dokumentenverfasser')
        ) {
            $cfg = LetterheadSettings::config();
            $docId = (int) ($_GET['id'] ?? 0);
            $renderDoc = LetterheadRenderer::sampleDocument();
            if ($docId > 0) {
                $stored = WriterDocumentRepository::findDocument($docId);
                if ($stored !== null) {
                    $renderDoc = WriterDocument::toRenderDocument($stored);
                }
            }
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex');
            echo LetterheadRenderer::renderFullHtml(
                $renderDoc,
                $cfg,
                [
                    'show_guides' => !empty($cfg['page']['show_din_guides']),
                    'title' => 'Dokument-Vorschau',
                ]
            );
            exit;
        }

        // POST: Dokumentenverfasser — Kontakt / Beleg verknüpfen (D6)
        if (
            $page === 'dokumentenverfasser-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (
                isset($_POST['writer_link_contact'])
                || isset($_POST['writer_unlink_contact'])
                || isset($_POST['writer_link_voucher'])
                || isset($_POST['writer_unlink_voucher'])
            )
            && MenuRegistry::canAccess($user, 'dokumentenverfasser-form')
        ) {
            $linkDocId = (int) ($_POST['id'] ?? 0);
            $redirect = $linkDocId > 0
                ? '/app?page=dokumentenverfasser-form&action=edit&id=' . $linkDocId
                : '/app?page=dokumentenverfasser';
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: ' . $redirect, true, 302);
                exit;
            }
            try {
                WriterDocumentRepository::ensureReady();
                if (isset($_POST['writer_unlink_contact'])) {
                    WriterDocumentLinkService::unlinkContact($user, $linkDocId);
                    Flash::set('success', 'Kontaktverknüpfung entfernt.');
                } elseif (isset($_POST['writer_link_contact'])) {
                    $ref = trim((string) ($_POST['contact_ref'] ?? ''));
                    $contact = PostMailLinkService::resolveContactRef($ref);
                    if ($contact === null) {
                        throw new InvalidArgumentException('Kontakt nicht gefunden (ID oder E-Mail).');
                    }
                    WriterDocumentLinkService::linkContact($user, $linkDocId, $contact->id, true);
                    Flash::set('success', 'Kontakt verknüpft.');
                } elseif (isset($_POST['writer_unlink_voucher'])) {
                    WriterDocumentLinkService::unlinkVoucher($user, $linkDocId);
                    Flash::set('success', 'Belegverknüpfung entfernt.');
                } else {
                    WriterDocumentLinkService::linkVoucher($user, $linkDocId, (string) ($_POST['voucher_ref'] ?? ''));
                    Flash::set('success', 'Beleg verknüpft.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Dokumentenverfasser speichern / finalisieren / öffnen / archivieren
        if (
            $page === 'dokumentenverfasser-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (
                isset($_POST['writer_save'])
                || isset($_POST['writer_finalize'])
                || isset($_POST['writer_reopen'])
                || isset($_POST['writer_archive'])
            )
            && MenuRegistry::canAccess($user, 'dokumentenverfasser-form')
        ) {
            $editId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=dokumentenverfasser', true, 302);
                exit;
            }
            try {
                WriterDocumentRepository::ensureReady();
                if (isset($_POST['writer_finalize']) && $editId > 0) {
                    WriterDocumentRepository::saveDraft($_POST, $editId, $user->id);
                    WriterDocumentRepository::finalize($editId, $user->id);
                    Flash::set('success', 'Dokument finalisiert (Version gesichert).');
                    header('Location: /app?page=dokumentenverfasser-form&action=edit&id=' . $editId, true, 302);
                    exit;
                }
                if (isset($_POST['writer_reopen']) && $editId > 0) {
                    WriterDocumentRepository::reopenAsDraft($editId, $user->id);
                    Flash::set('success', 'Dokument erneut als Entwurf geöffnet (Version erhöht).');
                    header('Location: /app?page=dokumentenverfasser-form&action=edit&id=' . $editId, true, 302);
                    exit;
                }
                if (isset($_POST['writer_archive']) && $editId > 0) {
                    WriterDocumentRepository::archive($editId, $user->id);
                    Flash::set('success', 'Dokument archiviert.');
                    header('Location: /app?page=dokumentenverfasser', true, 302);
                    exit;
                }
                $newId = WriterDocumentRepository::saveDraft(
                    $_POST,
                    $editId > 0 ? $editId : null,
                    $user->id
                );
                Flash::set('success', 'Entwurf gespeichert.');
                header('Location: /app?page=dokumentenverfasser-form&action=edit&id=' . $newId, true, 302);
                exit;
            } catch (Throwable $e) {
                $formError = $e->getMessage();
                $writerDocumentId = $editId > 0 ? $editId : null;
                $existingDoc = $editId > 0 ? WriterDocumentRepository::findDocument($editId) : null;
                $writerForm = array_merge(
                    WriterDocument::empty(),
                    WriterDocument::sanitize($_POST),
                    [
                        'id' => $editId,
                        'status' => $existingDoc['status'] ?? WriterDocument::STATUS_DRAFT,
                        'version' => (int) ($existingDoc['version'] ?? 1),
                    ]
                );
            }
        }

        // POST: Einstellungen Zeiterfassung
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['time_tracking_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('zeiterfassung');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    TimeTrackingSettings::saveFromPost($_POST);
                    Flash::set('success', 'Zeiterfassung-Einstellungen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Lohn AG-Stamm (L1c)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['payroll_lohn_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('lohn');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    $changes = PayrollEmployerSettings::saveFromPost($_POST, $user->id);
                    if ($changes === []) {
                        Flash::set('success', 'Keine Änderungen an den Lohn-Stammdaten.');
                    } else {
                        Flash::set('success', 'Lohn-Stammdaten Arbeitgeber gespeichert.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Lohnarten CRUD (L3b)
        if (
            $page === 'lohn-lohnarten'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && PayrollEmployeeAccess::canEdit($user)
        ) {
            $redirect = '/app?page=lohn-lohnarten';
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                $actionWt = trim((string) ($_POST['wage_type_action'] ?? ''));
                try {
                    if ($actionWt === 'save') {
                        $editId = (int) ($_POST['id'] ?? 0);
                        $newId = PayrollWageTypeRepository::save($_POST, $editId > 0 ? $editId : null);
                        Flash::set('success', 'Lohnart gespeichert.');
                        $redirect = '/app?page=lohn-lohnarten&edit=' . $newId;
                    } elseif ($actionWt === 'deactivate') {
                        PayrollWageTypeRepository::deactivateOrDelete((int) ($_POST['id'] ?? 0));
                        Flash::set('success', 'Lohnart deaktiviert bzw. gelöscht.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Lohnsteuer ELSTER Test-Payload (L10b)
        if (
            $page === 'lohn-elster'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && PayrollRunAccess::canView($user)
        ) {
            $monthPost = trim((string) ($_POST['month'] ?? $_GET['month'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $monthPost)) {
                $monthPost = date('Y-m');
            }
            $redirect = '/app?page=lohn-elster&month=' . rawurlencode($monthPost) . '&tab=lsta';
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } elseif (!PayrollRunAccess::canSubmitLstaTest($user)) {
                Flash::set('error', 'Keine Berechtigung für LStA-Test (lohn full).');
            } else {
                try {
                    $action = trim((string) ($_POST['elster_action'] ?? ''));
                    if ($action === 'lsta_live') {
                        PayrollElsterService::submitLiveLsta(0, 0, (int) $user->id);
                    }
                    if ($action !== 'lsta_test') {
                        throw new RuntimeException('Unbekannte ELSTER-Aktion.');
                    }
                    [$yy, $mm] = array_map('intval', explode('-', $monthPost));
                    $result = PayrollElsterService::submitTestLsta(
                        $yy,
                        $mm,
                        (int) $user->id,
                        (string) ($_POST['reason'] ?? '')
                    );
                    Flash::set(
                        'success',
                        'LStA-Test-Payload gespeichert'
                        . ($result['submission_id'] ? ' #' . $result['submission_id'] : '')
                        . ' · ' . $result['filename']
                        . ' · ' . $result['worker']
                    );
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Monatslauf Aktionen (L5c)
        if (
            $page === 'lohn-monatslauf'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && PayrollRunAccess::canView($user)
        ) {
            $monthPost = trim((string) ($_POST['month'] ?? $_GET['month'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $monthPost)) {
                $monthPost = date('Y-m');
            }
            $runIdPost = (int) ($_POST['run_id'] ?? 0);
            $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                . ($runIdPost > 0 ? '&run=' . $runIdPost : '');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                $runAction = trim((string) ($_POST['run_action'] ?? ''));
                $needsApprove = in_array($runAction, ['approve', 'lock', 'storno', 'correction'], true);
                $needsEdit = in_array($runAction, [
                    'create', 'add_employee', 'remove_employee', 'seed_assignments',
                    'calculate', 'reopen', 'delete',
                ], true);
                $needsFibu = $runAction === 'fibu_book';
                $needsSepa = $runAction === 'sepa_export';
                try {
                    if ($needsApprove && !PayrollRunAccess::canApprove($user)) {
                        throw new RuntimeException('Keine Berechtigung für Freigabe/Sperre/Storno.');
                    }
                    if ($needsEdit && !PayrollRunAccess::canEditRun($user)) {
                        throw new RuntimeException('Keine Berechtigung zum Bearbeiten des Monatslaufs.');
                    }
                    if ($needsFibu && !PayrollRunAccess::canPostFibu($user)) {
                        throw new RuntimeException('Keine Berechtigung für FiBu-Übernahme (lohn full + Buchhaltung).');
                    }
                    if ($needsSepa && !PayrollRunAccess::canExportSepa($user)) {
                        throw new RuntimeException('Keine Berechtigung für SEPA-Auszahlung (lohn full).');
                    }
                    if ($runAction === 'create') {
                        [$yy, $mm] = array_map('intval', explode('-', $monthPost));
                        $created = PayrollRunRepository::createRegular($yy, $mm, (int) $user->id);
                        Flash::set('success', 'Monatslauf angelegt.');
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                            . '&run=' . (int) $created['id'];
                    } elseif ($runAction === 'add_employee') {
                        $cid = (int) ($_POST['contact_id'] ?? 0);
                        PayrollRunRepository::addEmployee($runIdPost, $cid);
                        if (!empty($_POST['seed_assignments'])) {
                            PayrollRunRepository::seedLinesFromAssignments($runIdPost, $cid);
                        }
                        Flash::set('success', 'Mitarbeiter hinzugefügt.');
                    } elseif ($runAction === 'remove_employee') {
                        PayrollRunRepository::removeEmployee($runIdPost, (int) ($_POST['contact_id'] ?? 0));
                        Flash::set('success', 'Mitarbeiter entfernt.');
                    } elseif ($runAction === 'seed_assignments') {
                        $n = PayrollRunRepository::seedLinesFromAssignments(
                            $runIdPost,
                            (int) ($_POST['contact_id'] ?? 0)
                        );
                        Flash::set('success', $n . ' Lohnart-Position(en) aus Stamm übernommen.');
                    } elseif ($runAction === 'calculate') {
                        $stats = PayrollRunRepository::calculate($runIdPost, (int) $user->id);
                        Flash::set(
                            'success',
                            sprintf(
                                'Berechnet: %d ok, %d blocked, %d skipped.',
                                (int) $stats['ok_count'],
                                (int) $stats['blocked_count'],
                                (int) $stats['skipped_count']
                            )
                        );
                    } elseif ($runAction === 'reopen') {
                        PayrollRunRepository::reopen($runIdPost, (int) $user->id, 'UI reopen');
                        Flash::set('success', 'Lauf zurück auf Entwurf.');
                    } elseif ($runAction === 'approve') {
                        PayrollRunRepository::approve(
                            $runIdPost,
                            (int) $user->id,
                            (string) ($_POST['reason'] ?? '')
                        );
                        Flash::set('success', 'Lauf freigegeben.');
                    } elseif ($runAction === 'lock') {
                        PayrollRunRepository::lock(
                            $runIdPost,
                            (int) $user->id,
                            (string) ($_POST['reason'] ?? '')
                        );
                        Flash::set('success', 'Lauf gesperrt.');
                    } elseif ($runAction === 'storno' || $runAction === 'correction') {
                        $kind = $runAction === 'storno'
                            ? PayrollRunRepository::KIND_STORNO
                            : PayrollRunRepository::KIND_CORRECTION;
                        $newRun = PayrollRunRepository::createSuperseding(
                            $runIdPost,
                            $kind,
                            (int) $user->id,
                            (string) ($_POST['reason'] ?? '')
                        );
                        Flash::set('success', ($runAction === 'storno' ? 'Storno' : 'Korrektur') . '-Lauf angelegt.');
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                            . '&run=' . (int) $newRun['id'];
                    } elseif ($runAction === 'delete') {
                        PayrollRunRepository::deleteDraft($runIdPost);
                        Flash::set('success', 'Entwurf gelöscht.');
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost);
                    } elseif ($runAction === 'fibu_book') {
                        $forceProbe = !empty($_POST['force_probe']) && RoleResolver::isAdmin($user);
                        $postingDate = trim((string) ($_POST['posting_date'] ?? ''));
                        $result = PayrollFibuService::book(
                            $runIdPost,
                            (int) $user->id,
                            (string) ($_POST['reason'] ?? ''),
                            $postingDate !== '' ? $postingDate : null,
                            $forceProbe
                        );
                        if (!empty($result['idempotent'])) {
                            Flash::set(
                                'success',
                                'FiBu bereits vorhanden (Batch #' . (int) ($result['manual_batch_id'] ?? 0) . ') — idempotent.'
                            );
                        } else {
                            Flash::set(
                                'success',
                                'In FiBu übernommen (Batch #' . (int) ($result['manual_batch_id'] ?? 0) . ').'
                            );
                        }
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                            . '&run=' . $runIdPost . '&fibu=1';
                    } elseif ($runAction === 'sepa_export') {
                        $execDate = trim((string) ($_POST['exec_date'] ?? ''));
                        $sepaResult = PayrollSepaService::export(
                            $runIdPost,
                            (int) $user->id,
                            (string) ($_POST['reason'] ?? ''),
                            $execDate !== '' ? $execDate : null
                        );
                        $exportId = (int) ($sepaResult['meta']['id'] ?? 0);
                        $probeNote = !empty($sepaResult['meta']['is_probe']) ? ' (PROBE — nicht produktiv)' : '';
                        Flash::set(
                            'success',
                            'SEPA-XML erzeugt #' . $exportId
                            . ' · ' . (int) ($sepaResult['meta']['tx_count'] ?? 0) . ' Tx'
                            . ' · CtrlSum ' . (string) ($sepaResult['meta']['ctrl_sum'] ?? '0')
                            . $probeNote
                        );
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                            . '&run=' . $runIdPost . '&sepa=1&sepa_dl=' . $exportId;
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    if ($runAction === 'fibu_book' && $runIdPost > 0) {
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                            . '&run=' . $runIdPost . '&fibu=1';
                    }
                    if ($runAction === 'sepa_export' && $runIdPost > 0) {
                        $redirect = '/app?page=lohn-monatslauf&month=' . rawurlencode($monthPost)
                            . '&run=' . $runIdPost . '&sepa=1';
                    }
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Beitragstabellen Import/Ablösen/Patch (L4b)
        if (
            $page === 'lohn-beitragstabellen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && PayrollEmployeeAccess::canEdit($user)
        ) {
            $redirect = '/app?page=lohn-beitragstabellen';
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                $rateAction = trim((string) ($_POST['rate_action'] ?? ''));
                try {
                    if ($rateAction === 'import') {
                        $text = trim((string) ($_POST['rate_csv_text'] ?? ''));
                        if ($text === '' && isset($_FILES['rate_csv']) && is_array($_FILES['rate_csv'])) {
                            $err = (int) ($_FILES['rate_csv']['error'] ?? UPLOAD_ERR_NO_FILE);
                            if ($err === UPLOAD_ERR_OK) {
                                $tmp = (string) ($_FILES['rate_csv']['tmp_name'] ?? '');
                                $raw = $tmp !== '' ? file_get_contents($tmp) : false;
                                if ($raw === false) {
                                    throw new RuntimeException('CSV-Datei konnte nicht gelesen werden.');
                                }
                                $text = $raw;
                            } elseif ($err !== UPLOAD_ERR_NO_FILE) {
                                throw new RuntimeException('Upload fehlgeschlagen (Code ' . $err . ').');
                            }
                        }
                        if ($text === '') {
                            throw new InvalidArgumentException('Bitte CSV-Datei oder Text angeben.');
                        }
                        $newSetId = PayrollRateTableRepository::importFromText($text, (int) $user->id);
                        Flash::set('success', 'Beitragstabelle importiert.');
                        $redirect = '/app?page=lohn-beitragstabellen&view=' . $newSetId;
                    } elseif ($rateAction === 'supersede') {
                        PayrollRateTableRepository::supersede((int) ($_POST['id'] ?? 0), (int) $user->id);
                        Flash::set('success', 'Tabellensatz abgelöst (deaktiviert).');
                    } elseif ($rateAction === 'patch') {
                        $sid = (int) ($_POST['rate_set_id'] ?? 0);
                        PayrollRateTableRepository::patchParam(
                            $sid,
                            (string) ($_POST['param_key'] ?? ''),
                            (string) ($_POST['param_value'] ?? ''),
                            (int) $user->id
                        );
                        Flash::set('success', 'Parameter korrigiert (Audit).');
                        $redirect = '/app?page=lohn-beitragstabellen&view=' . $sid;
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Schriften
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['appearance_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('schriften');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    AppearanceSettings::save($_POST);
                    Flash::set('success', 'Schriften gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Abteilungen
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['departments_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('abteilungen');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    DepartmentRepository::saveFromPost($_POST);
                    NotificationTemplateSettings::saveDepartmentNotificationFromPost($_POST);
                    Flash::set('success', 'Abteilungen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Lagerstruktur
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && (
                isset($_POST['stock_location_save'])
                || isset($_POST['stock_location_delete'])
                || isset($_POST['stock_hall_save'])
                || isset($_POST['stock_hall_delete'])
                || isset($_POST['stock_shelf_save'])
                || isset($_POST['stock_shelf_delete'])
                || isset($_POST['stock_places_save'])
                || isset($_POST['stock_purchase_save'])
                || isset($_POST['amazon_business_save'])
                || isset($_POST['amazon_business_test'])
            )
        ) {
            $lagerTab = isset($_POST['lager_tab'])
                ? preg_replace('/[^a-z]/', '', (string) $_POST['lager_tab'])
                : (isset($_GET['lager_tab']) ? preg_replace('/[^a-z]/', '', (string) $_GET['lager_tab']) : '');
            if ($lagerTab === '') {
                if (isset($_POST['stock_purchase_save']) || isset($_POST['amazon_business_save']) || isset($_POST['amazon_business_test'])) {
                    $lagerTab = 'einkauf';
                } elseif (isset($_POST['stock_hall_save']) || isset($_POST['stock_hall_delete'])) {
                    $lagerTab = 'hallen';
                } elseif (isset($_POST['stock_shelf_save']) || isset($_POST['stock_shelf_delete']) || isset($_POST['stock_places_save'])) {
                    $lagerTab = 'regale';
                } else {
                    $lagerTab = 'orte';
                }
            }
            $redirect = SettingsRegistry::tabUrl('lager-struktur') . '&lager_tab=' . rawurlencode($lagerTab);
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['stock_purchase_save'])) {
                        StockPurchaseSettings::saveFromPost($_POST);
                        Flash::set('success', 'Einkaufs-Einstellungen gespeichert.');
                    } elseif (isset($_POST['amazon_business_save'])) {
                        AmazonBusinessSettings::saveFromPost($_POST);
                        Flash::set('success', 'Amazon-Business-Einstellungen gespeichert.');
                    } elseif (isset($_POST['amazon_business_test'])) {
                        AmazonBusinessSettings::saveFromPost($_POST);
                        $test = AmazonBusinessAuth::testConnection();
                        Flash::set(
                            !empty($test['ok']) ? 'success' : 'error',
                            (string) ($test['message'] ?? 'Verbindungstest fehlgeschlagen.')
                        );
                    } elseif (isset($_POST['stock_location_save'])) {
                        $newId = StockStructureRepository::saveLocation($_POST);
                        Flash::set('success', 'Lagerort gespeichert.');
                        $redirect .= '&edit=' . $newId;
                    } elseif (isset($_POST['stock_location_delete'])) {
                        StockStructureRepository::deleteLocation((int) ($_POST['id'] ?? 0));
                        Flash::set('success', 'Lagerort gelöscht.');
                    } elseif (isset($_POST['stock_hall_save'])) {
                        $newId = StockStructureRepository::saveHall($_POST);
                        Flash::set('success', 'Halle gespeichert.');
                        $redirect .= '&edit=' . $newId;
                    } elseif (isset($_POST['stock_hall_delete'])) {
                        StockStructureRepository::deleteHall((int) ($_POST['id'] ?? 0));
                        Flash::set('success', 'Halle gelöscht.');
                    } elseif (isset($_POST['stock_shelf_save'])) {
                        $newId = StockStructureRepository::saveShelf($_POST);
                        Flash::set('success', 'Regal gespeichert.');
                        $redirect .= '&edit=' . $newId;
                    } elseif (isset($_POST['stock_shelf_delete'])) {
                        StockStructureRepository::deleteShelf((int) ($_POST['id'] ?? 0));
                        Flash::set('success', 'Regal gelöscht.');
                    } elseif (isset($_POST['stock_places_save'])) {
                        $shelfId = (int) ($_POST['shelf_id'] ?? 0);
                        $places = is_array($_POST['places'] ?? null) ? $_POST['places'] : [];
                        StockStructureRepository::savePlacesFromPost($shelfId, $places);
                        Flash::set('success', 'Stellplätze gespeichert.');
                        $redirect .= '&edit=' . $shelfId;
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Rezeptur speichern/löschen (R1/R3)
        if (
            $page === 'rezeptur-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['recipe_save']) || isset($_POST['recipe_delete']))
            && MenuRegistry::canAccess($user, 'rezeptur-form')
        ) {
            $editRecipeId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=rezeptur', true, 302);
                exit;
            }
            try {
                RecipeRepository::ensureReady();
                if (isset($_POST['recipe_delete']) && $editRecipeId > 0) {
                    RecipeRepository::delete($editRecipeId);
                    Flash::set('success', 'Rezept gelöscht.');
                    header('Location: /app?page=rezeptur', true, 302);
                    exit;
                }
                $newRecipeId = RecipeRepository::save($_POST, $editRecipeId > 0 ? $editRecipeId : null, $user->id);
                Flash::set('success', 'Rezept gespeichert (Kalkulations-Snapshot angelegt).');
                header('Location: /app?page=rezeptur-form&action=edit&id=' . $newRecipeId, true, 302);
                exit;
            } catch (Throwable $e) {
                $formError = $e->getMessage();
                $recipeId = $editRecipeId > 0 ? $editRecipeId : null;
                $bomPost = is_array($_POST['bom'] ?? null) ? $_POST['bom'] : [];
                $bomLines = [];
                foreach ($bomPost as $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    $bomLines[] = [
                        'id' => 0,
                        'material_label' => (string) ($line['material_label'] ?? ''),
                        'article_id' => (int) ($line['article_id'] ?? 0),
                        'qty' => (string) ($line['qty'] ?? '1'),
                        'scrap_pct' => (string) ($line['scrap_pct'] ?? '0'),
                        'unit' => (string) ($line['unit'] ?? 'Stk'),
                        'unit_cost' => (string) ($line['unit_cost'] ?? ''),
                    ];
                }
                if ($bomLines === []) {
                    $bomLines = [RecipeRepository::emptyBomLine()];
                }
                $routingPost = is_array($_POST['routing'] ?? null) ? $_POST['routing'] : [];
                $routingLines = [];
                foreach ($routingPost as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $routingLines[] = [
                        'id' => 0,
                        'work_center_id' => (int) ($step['work_center_id'] ?? 0),
                        'setup_min' => (string) ($step['setup_min'] ?? '0'),
                        'run_min' => (string) ($step['run_min'] ?? '0'),
                        'label' => (string) ($step['label'] ?? ''),
                    ];
                }
                if ($routingLines === []) {
                    $routingLines = [RecipeRepository::emptyRoutingLine()];
                }
                $recipeForm = [
                    'title' => (string) ($_POST['title'] ?? ''),
                    'target_qty' => (string) ($_POST['target_qty'] ?? '1'),
                    'labor_minutes' => (string) ($_POST['labor_minutes'] ?? '0'),
                    'margin_pct' => (string) ($_POST['margin_pct'] ?? '0'),
                    'status' => (string) ($_POST['status'] ?? RecipeRepository::STATUS_DRAFT),
                    'version' => max(1, (int) ($_POST['version'] ?? 1)),
                    'notes' => (string) ($_POST['notes'] ?? ''),
                    'bom' => $bomLines,
                    'routing' => $routingLines,
                ];
            }
        }

        // POST: Rezeptur Zusatz-/Verpackungsmaterial (M1b)
        if (
            $page === 'rezeptur-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['recipe_auxiliary_save']) || isset($_POST['recipe_auxiliary_deactivate']))
            && MenuRegistry::canAccess($user, 'rezeptur-form')
        ) {
            $editRecipeId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user) || $editRecipeId <= 0) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=rezeptur', true, 302);
                exit;
            }
            try {
                if (RecipeRepository::find($editRecipeId) === null) {
                    throw new InvalidArgumentException('Rezept nicht gefunden.');
                }
                if (isset($_POST['recipe_auxiliary_deactivate'])) {
                    MachinePlanningRepository::deactivateAuxiliaryMaterial((int) ($_POST['auxiliary_material_id'] ?? $_POST['recipe_auxiliary_deactivate'] ?? 0));
                    Flash::set('success', 'Zusatzmaterial deaktiviert.');
                } else {
                    $data = $_POST;
                    $data['recipe_id'] = $editRecipeId;
                    $data['is_active'] = 1;
                    MachinePlanningRepository::createAuxiliaryMaterial($data);
                    Flash::set('success', 'Zusatz-/Verpackungsmaterial gespeichert.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=rezeptur-form&action=edit&id=' . $editRecipeId . '#auxiliary', true, 302);
            exit;
        }

        // POST: Rezeptur Produktionslauf-Snapshot + Soll/Ist (R3/R6)
        if (
            $page === 'rezeptur-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['recipe_run_snapshot'])
            && MenuRegistry::canAccess($user, 'rezeptur-form')
        ) {
            $runId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user) || $runId <= 0) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=rezeptur', true, 302);
                exit;
            }
            try {
                $formSnap = RecipeRepository::formForId($runId);
                if (RecipeRepository::find($runId) === null) {
                    throw new InvalidArgumentException('Rezept nicht gefunden.');
                }
                $formSnap['id'] = $runId;
                $auxiliary = MachinePlanningRepository::listAuxiliaryMaterials($runId, true);
                $calc = RecipeCostService::calculate($formSnap, $formSnap['bom'] ?? [], $formSnap['routing'] ?? [], null, $auxiliary);
                $payload = RecipeCostService::snapshotPayload($formSnap, $formSnap['bom'] ?? [], $formSnap['routing'] ?? [], $calc, $auxiliary);
                $snapshotId = RecipeSnapshotRepository::create(
                    $runId,
                    RecipeSnapshotRepository::KIND_RUN,
                    $payload['inputs'],
                    $payload['result'],
                    $user->id
                );
                $planned = RecipeActualRepository::plannedFromRecipe(
                    $formSnap,
                    $formSnap['routing'] ?? [],
                    $calc
                );
                RecipeActualRepository::createForSnapshot($snapshotId, [
                    'planned_qty' => $planned['planned_qty'],
                    'planned_setup_min' => $planned['planned_setup_min'],
                    'planned_run_min' => $planned['planned_run_min'],
                    'planned_self_cost' => $planned['planned_self_cost'],
                    'actual_qty' => $_POST['actual_qty'] ?? $planned['planned_qty'],
                    'actual_setup_min' => $_POST['actual_setup_min'] ?? $planned['planned_setup_min'],
                    'actual_run_min' => $_POST['actual_run_min'] ?? $planned['planned_run_min'],
                    'actual_self_cost' => $_POST['actual_self_cost'] ?? null,
                    'note' => (string) ($_POST['actual_note'] ?? ''),
                ], $user->id);
                Flash::set('success', 'Produktionsdurchlauf protokolliert (Soll/Ist, ohne Buchung).');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=rezeptur-form&action=edit&id=' . $runId, true, 302);
            exit;
        }

        // POST: Rezeptur Soll/Ist nachträglich anpassen (R6)
        if (
            $page === 'rezeptur-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['recipe_actual_update'])
            && MenuRegistry::canAccess($user, 'rezeptur-form')
        ) {
            $runId = (int) ($_POST['id'] ?? 0);
            $actualId = (int) ($_POST['actual_id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user) || $runId <= 0 || $actualId <= 0) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=rezeptur', true, 302);
                exit;
            }
            try {
                if (RecipeRepository::find($runId) === null) {
                    throw new InvalidArgumentException('Rezept nicht gefunden.');
                }
                $map = RecipeActualRepository::mapForRecipe($runId);
                $owned = false;
                foreach ($map as $row) {
                    if ((int) ($row['id'] ?? 0) === $actualId) {
                        $owned = true;
                        break;
                    }
                }
                if (!$owned) {
                    throw new InvalidArgumentException('Soll/Ist gehört nicht zu diesem Rezept.');
                }
                RecipeActualRepository::updateFromPost($actualId, $_POST);
                Flash::set('success', 'Soll/Ist aktualisiert (ohne Buchung).');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=rezeptur-form&action=edit&id=' . $runId, true, 302);
            exit;
        }

        // POST: Gutscheine
        if (
            ($page === 'gutscheine-form')
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'gutscheine-form')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Ungültiges Formular (CSRF) bzw. keine Berechtigung.');
                header('Location: /app?page=gutscheine', true, 302);
                exit;
            }
            try {
                if (isset($_POST['gift_cancel'])) {
                    GiftVoucherRepository::cancel((int) ($_POST['gift_voucher_id'] ?? 0));
                    Flash::set('success', 'Gutschein storniert.');
                    header('Location: /app?page=gutscheine', true, 302);
                    exit;
                }
                if (isset($_POST['gift_create'])) {
                    $newGiftId = GiftVoucherRepository::create([
                        'voucher_type' => GiftVoucherRepository::TYPE_SALE,
                        'amount' => $_POST['amount'] ?? 0,
                        'valid_until' => (string) ($_POST['valid_until'] ?? ''),
                        'title' => (string) ($_POST['title'] ?? ''),
                        'notes' => (string) ($_POST['notes'] ?? ''),
                        'contact_id' => (int) ($_POST['contact_id'] ?? 0),
                        'flyer_template_id' => (int) ($_POST['flyer_template_id'] ?? 0),
                    ], $user->id);
                    Flash::set('success', 'Gutschein ausgestellt.');
                    header('Location: /app?page=gutscheine-form&action=view&id=' . $newGiftId, true, 302);
                    exit;
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                header('Location: /app?page=gutscheine-form&action=new', true, 302);
                exit;
            }
        }

        // POST: Gutschein-Flyer-Vorlagen
        if (
            ($page === 'gutscheine-flyer-vorlagen')
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'gutscheine-flyer-vorlagen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Ungültiges Formular (CSRF) bzw. keine Berechtigung.');
                header('Location: /app?page=gutscheine-flyer-vorlagen', true, 302);
                exit;
            }
            try {
                if (isset($_POST['flyer_template_save'])) {
                    $editId = (int) ($_POST['id'] ?? 0);
                    $savedId = FlyerService::saveGiftTemplate($_POST, $editId > 0 ? $editId : null);
                    Flash::set('success', 'Flyer-Vorlage gespeichert.');
                    header('Location: /app?page=gutscheine-flyer-vorlagen&action=edit&id=' . $savedId, true, 302);
                    exit;
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                $backId = (int) ($_POST['id'] ?? 0);
                $back = $backId > 0
                    ? '/app?page=gutscheine-flyer-vorlagen&action=edit&id=' . $backId
                    : '/app?page=gutscheine-flyer-vorlagen&action=new';
                header('Location: ' . $back, true, 302);
                exit;
            }
        }

        // POST: Reklamation
        if (
            ($page === 'reklamation-form')
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'reklamation-form')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Ungültiges Formular (CSRF) bzw. keine Berechtigung.');
                header('Location: /app?page=reklamation', true, 302);
                exit;
            }
            $editComplaintId = (int) ($_POST['complaint_id'] ?? 0);
            try {
                if (isset($_POST['complaint_apology'])) {
                    $giftId = ComplaintRepository::issueApologyVoucher(
                        $editComplaintId,
                        (float) str_replace(',', '.', (string) ($_POST['apology_amount'] ?? 0)),
                        $user->id
                    );
                    Flash::set('success', 'Entschuldigungs-Gutschein ausgestellt.');
                    header('Location: /app?page=gutscheine-form&action=view&id=' . $giftId, true, 302);
                    exit;
                }
                if (isset($_POST['complaint_delete_attachment'])) {
                    $attId = (int) ($_POST['complaint_delete_attachment'] ?? 0);
                    $att = ComplaintFileStorage::find($attId);
                    if ($att === null || (int) ($att['complaint_id'] ?? 0) !== $editComplaintId) {
                        throw new InvalidArgumentException('Anhang nicht gefunden.');
                    }
                    ComplaintFileStorage::delete($attId);
                    ComplaintRepository::addEvent($editComplaintId, 'file', 'Anhang gelöscht: ' . (string) ($att['original_name'] ?? ''), $user->id);
                    Flash::set('success', 'Anhang gelöscht.');
                    header('Location: /app?page=reklamation-form&action=edit&id=' . $editComplaintId, true, 302);
                    exit;
                }
                if (isset($_POST['complaint_save'])) {
                    $savedId = ComplaintRepository::save($_POST, $editComplaintId > 0 ? $editComplaintId : null, $user->id);
                    $uploadCount = 0;
                    if (!empty($_FILES['complaint_files'])) {
                        $uploadCount = ComplaintFileStorage::storeUploads(
                            $savedId,
                            is_array($_FILES['complaint_files']) ? $_FILES['complaint_files'] : [],
                            $user->id
                        );
                    }
                    if ($uploadCount > 0) {
                        ComplaintRepository::addEvent(
                            $savedId,
                            'file',
                            $uploadCount === 1 ? '1 Datei hochgeladen' : $uploadCount . ' Dateien hochgeladen',
                            $user->id
                        );
                    }
                    Flash::set(
                        'success',
                        $uploadCount > 0
                            ? 'Reklamation gespeichert (' . $uploadCount . ' Datei' . ($uploadCount === 1 ? '' : 'en') . ').'
                            : 'Reklamation gespeichert.'
                    );
                    header('Location: /app?page=reklamation-form&action=edit&id=' . $savedId, true, 302);
                    exit;
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                $redir = $editComplaintId > 0
                    ? '/app?page=reklamation-form&action=edit&id=' . $editComplaintId
                    : '/app?page=reklamation-form&action=new';
                header('Location: ' . $redir, true, 302);
                exit;
            }
        }

        // POST: Rezeptur Kostensätze (R2)
        if (
            $page === 'rezeptur-maschinen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['recipe_cost_rates_save'])
            && MenuRegistry::canAccess($user, 'rezeptur-maschinen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    RecipeCostSettings::saveFromPost($_POST);
                    Flash::set('success', 'Kostensätze gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=rezeptur-maschinen', true, 302);
            exit;
        }

        // POST: Maschinen-Kapazitätsprofil (M1a)
        if (
            $page === 'rezeptur-maschinen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['machine_capacity_save']) || isset($_POST['machine_capacity_deactivate']))
            && MenuRegistry::canAccess($user, 'rezeptur-maschinen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    if (isset($_POST['machine_capacity_deactivate'])) {
                        MachinePlanningRepository::deactivateCapacityProfile((int) ($_POST['capacity_profile_id'] ?? 0));
                        Flash::set('success', 'Kapazitätsprofil deaktiviert.');
                    } else {
                        MachinePlanningRepository::createCapacityProfile($_POST);
                        Flash::set('success', 'Kapazitätsprofil gespeichert.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=rezeptur-maschinen#capacity', true, 302);
            exit;
        }

        // POST: Maschinen-Wartungsplan (M2a)
        if (
            $page === 'rezeptur-maschinen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['machine_maintenance_plan_save']) || isset($_POST['machine_maintenance_plan_deactivate']))
            && MenuRegistry::canAccess($user, 'rezeptur-maschinen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    if (isset($_POST['machine_maintenance_plan_deactivate'])) {
                        MachinePlanningRepository::deactivateMaintenancePlan((int) ($_POST['maintenance_plan_id'] ?? 0));
                        Flash::set('success', 'Wartungsplan deaktiviert.');
                    } else {
                        $data = $_POST;
                        $helperLines = array_values(array_filter(array_map(
                            static fn (string $line): string => trim($line),
                            preg_split('/\R/u', (string) ($_POST['helper_materials_text'] ?? '')) ?: []
                        )));
                        $data['helper_materials_json'] = $helperLines === [] ? null : $helperLines;
                        $data['is_active'] = 1;
                        MachinePlanningRepository::createMaintenancePlan($data);
                        Flash::set('success', 'Wartungsplan gespeichert.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=rezeptur-maschinen#maintenance', true, 302);
            exit;
        }

        // POST: Maschinen-Wartungsprotokoll (M2b)
        if (
            $page === 'rezeptur-maschinen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['machine_maintenance_log_save'])
            && MenuRegistry::canAccess($user, 'rezeptur-maschinen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    MachinePlanningRepository::createMaintenanceLog($_POST);
                    Flash::set('success', 'Wartung protokolliert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=rezeptur-maschinen#maintenance-log', true, 302);
            exit;
        }

        // POST: Maschinen-Belegungsfenster (M3b)
        if (
            $page === 'rezeptur-maschinen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (
                isset($_POST['machine_booking_save'])
                || isset($_POST['machine_booking_cancel'])
                || isset($_POST['machine_booking_status'])
            )
            && MenuRegistry::canAccess($user, 'rezeptur-maschinen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    $bookingWindowId = (int) ($_POST['booking_window_id'] ?? 0);
                    if (isset($_POST['machine_booking_cancel'])) {
                        MachinePlanningRepository::cancelBookingWindow($bookingWindowId);
                        Flash::set('success', 'Belegungsfenster storniert.');
                    } elseif (isset($_POST['machine_booking_status'])) {
                        MachinePlanningRepository::setBookingWindowStatus(
                            $bookingWindowId,
                            (string) ($_POST['booking_status'] ?? '')
                        );
                        Flash::set('success', 'Status aktualisiert.');
                    } else {
                        $bookingConflicts = MachinePlanningRepository::listBookingConflicts(
                            (int) ($_POST['work_center_id'] ?? 0),
                            (string) ($_POST['starts_at'] ?? ''),
                            (string) ($_POST['ends_at'] ?? ''),
                            $bookingWindowId > 0 ? $bookingWindowId : null
                        );
                        if ($bookingWindowId > 0) {
                            MachinePlanningRepository::updateBookingWindow($bookingWindowId, $_POST);
                            Flash::set(
                                $bookingConflicts !== [] ? 'warning' : 'success',
                                $bookingConflicts !== []
                                    ? 'Belegungsfenster aktualisiert, aber es gibt ' . count($bookingConflicts) . ' Überschneidung(en) für diese Maschine.'
                                    : 'Belegungsfenster aktualisiert.'
                            );
                        } else {
                            MachinePlanningRepository::createBookingWindow($_POST);
                            Flash::set(
                                $bookingConflicts !== [] ? 'warning' : 'success',
                                $bookingConflicts !== []
                                    ? 'Belegungsfenster gespeichert, aber es gibt ' . count($bookingConflicts) . ' Überschneidung(en) für diese Maschine.'
                                    : 'Belegungsfenster gespeichert.'
                            );
                        }
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=rezeptur-maschinen#booking', true, 302);
            exit;
        }

        // POST: Work Center speichern/löschen (R2)
        if (
            $page === 'rezeptur-maschine-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['work_center_save']) || isset($_POST['work_center_delete']))
            && MenuRegistry::canAccess($user, 'rezeptur-maschine-form')
        ) {
            $editWcId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=rezeptur-maschinen', true, 302);
                exit;
            }
            try {
                WorkCenterRepository::ensureReady();
                if (isset($_POST['work_center_delete']) && $editWcId > 0) {
                    WorkCenterRepository::delete($editWcId);
                    Flash::set('success', 'Maschine / Arbeitsplatz gelöscht.');
                    header('Location: /app?page=rezeptur-maschinen', true, 302);
                    exit;
                }
                $newWcId = WorkCenterRepository::save($_POST, $editWcId > 0 ? $editWcId : null);
                Flash::set('success', 'Maschine / Arbeitsplatz gespeichert.');
                header('Location: /app?page=rezeptur-maschine-form&action=edit&id=' . $newWcId, true, 302);
                exit;
            } catch (Throwable $e) {
                $formError = $e->getMessage();
                $workCenterId = $editWcId > 0 ? $editWcId : null;
                $workCenterForm = [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'purchase_price' => (string) ($_POST['purchase_price'] ?? '0'),
                    'life_hours' => (string) ($_POST['life_hours'] ?? '1'),
                    'kw' => (string) ($_POST['kw'] ?? '0'),
                    'space_m2' => (string) ($_POST['space_m2'] ?? '0'),
                    'operators' => (string) ($_POST['operators'] ?? '1'),
                    'is_active' => !empty($_POST['is_active']),
                    'notes' => (string) ($_POST['notes'] ?? ''),
                ];
            }
        }

        // POST: Einstellungen Kalender-E-Mail-Vorlagen
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && (isset($_POST['notification_templates_save']) || isset($_POST['calendar_notifications_save']))
        ) {
            $redirect = SettingsRegistry::tabUrl('benachrichtigungen');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    NotificationTemplateSettings::saveAllTemplatesFromPost($_POST);
                    CalendarNotificationSettings::saveFromPost($_POST);
                    EmailLayoutSettings::saveFromPost($_POST);
                    Flash::set('success', 'E-Mail-Einstellungen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Nummernkreise
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['number_ranges_save'])
        ) {
            $type = (string) ($_POST['number_range_type'] ?? 'invoice');
            if (!NumberRangeSettings::isValidType($type)) {
                $type = 'invoice';
            }
            $redirect = SettingsRegistry::tabUrl('nummernkreise') . '&ntype=' . rawurlencode($type);
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    NumberRangeSettings::saveFromPost($type, $_POST);
                    Flash::set('success', 'Nummernkreis gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen CRM-Farbschema
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['crm_theme_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('crm-darstellung');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    CrmThemeSettings::save($_POST);
                    Flash::set('success', 'Software Design gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Kalender-Einbindung (Online-Buchung)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['calendar_embed_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('kalender-einbindung');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    CalendarEmbedSettings::save($_POST);
                    Flash::set('success', 'Online-Terminbuchung gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Kalender-Darstellung
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['calendar_appearance_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('kalender-darstellung');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    CalendarAppearanceSettings::save($_POST);
                    Flash::set('success', 'Kalender Design gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen Arbeitszeiten (Terminkalender)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && (isset($_POST['working_hours_save']) || isset($_POST['working_hours_delete']))
        ) {
            $redirect = SettingsRegistry::tabUrl('arbeitszeiten');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['working_hours_delete'])) {
                        CalendarWorkingHoursRepository::delete((int) ($_POST['working_hours_id'] ?? 0));
                        Flash::set('success', 'Arbeitszeit gelÃ¶scht.');
                    } else {
                        CalendarWorkingHoursRepository::save($_POST);
                        Flash::set('success', 'Arbeitszeit gespeichert.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Artikel & Leistungen
        if (
            $page === 'artikel-leistungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && DepartmentAccess::userCanManageArticleCatalog($user)
            && (
                isset($_POST['articles_save'])
                || isset($_POST['articles_delete'])
                || isset($_POST['articles_import'])
                || isset($_POST['purchase_list_ignore'])
                || isset($_POST['purchase_list_restore'])
                || isset($_POST['purchase_list_ordered'])
                || isset($_POST['purchase_list_done'])
                || isset($_POST['purchase_list_qty'])
                || isset($_POST['purchase_list_manual_order'])
                || isset($_POST['purchase_list_rebuild'])
            )
        ) {
            $redirect = '/app?page=artikel-leistungen';
            $listKind = trim((string) ($_POST['list_kind'] ?? ''));
            if ($listKind === CalendarArticleCatalog::KIND_PRODUCT || $listKind === CalendarArticleCatalog::KIND_SERVICE) {
                $redirect .= '&kind=' . rawurlencode($listKind);
            }
            $listView = trim((string) ($_POST['list'] ?? $_GET['list'] ?? ''));
            if ($listView === 'purchase' || $listView === 'ignored' || $listView === 'ordered') {
                $redirect .= '&list=' . rawurlencode($listView);
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['purchase_list_rebuild'])) {
                        $n = PurchaseListService::rebuildFromStock();
                        Flash::set('success', 'Einkaufsliste aktualisiert (' . $n . ' Einträge).');
                        $redirect = '/app?page=artikel-leistungen&list=purchase';
                    } elseif (isset($_POST['purchase_list_ignore'])) {
                        PurchaseListService::ignore((int) ($_POST['purchase_list_id'] ?? 0), $user->id);
                        Flash::set('success', 'Eintrag ignoriert.');
                        $redirect = '/app?page=artikel-leistungen&list=purchase';
                    } elseif (isset($_POST['purchase_list_restore'])) {
                        PurchaseListService::restore((int) ($_POST['purchase_list_id'] ?? 0));
                        Flash::set('success', 'Eintrag wieder aktiv.');
                        $redirect = '/app?page=artikel-leistungen&list=ignored';
                    } elseif (isset($_POST['purchase_list_qty'])) {
                        $qty = PurchaseListService::parseQtyInput($_POST['suggested_qty'] ?? '');
                        if ($qty === null) {
                            Flash::set('error', 'Bitte eine gültige Menge eingeben (größer 0).');
                        } elseif (PurchaseListService::updateQty((int) ($_POST['purchase_list_id'] ?? 0), $qty)) {
                            Flash::set('success', 'Nachbestellte Menge aktualisiert.');
                        } else {
                            Flash::set('error', 'Menge konnte nicht gespeichert werden.');
                        }
                        $redirect = '/app?page=artikel-leistungen&list=ordered';
                    } elseif (isset($_POST['purchase_list_manual_order'])) {
                        $articleId = (int) ($_POST['manual_order_article_id'] ?? 0);
                        $qty = PurchaseListService::parseQtyInput($_POST['manual_order_qty'] ?? '');
                        $note = trim((string) ($_POST['manual_order_note'] ?? ''));
                        if ($articleId < 1) {
                            Flash::set('error', 'Bitte einen Artikel wählen.');
                        } elseif ($qty === null) {
                            Flash::set('error', 'Bitte eine gültige Bestellmenge eingeben (größer 0).');
                        } elseif (PurchaseListService::registerManualOrder($articleId, $qty, $note)) {
                            Flash::set('success', 'Nachbestellung eingetragen — erscheint unter „Nachbestellt“ und im Bestand.');
                        } else {
                            Flash::set('error', 'Nachbestellung konnte nicht gespeichert werden (Artikel mit Lagerführung nötig).');
                        }
                        $redirect = '/app?page=artikel-leistungen&list=ordered';
                    } elseif (isset($_POST['purchase_list_ordered'])) {
                        $orderedQty = PurchaseListService::parseQtyInput($_POST['suggested_qty'] ?? '');
                        $orderUrl = PurchaseListService::markOrdered(
                            (int) ($_POST['purchase_list_id'] ?? 0),
                            $orderedQty
                        );
                        $redirect = '/app?page=artikel-leistungen&list=ordered';
                        if ($orderUrl !== '' && preg_match('#^https?://#i', $orderUrl)) {
                            Flash::set('success', 'Als bestellt markiert — Shop wird geöffnet. Menge ggf. unter „Nachbestellt“ korrigieren.');
                            $redirect .= '&open_order_url=' . rawurlencode($orderUrl);
                        } else {
                            Flash::set(
                                'success',
                                'Als bestellt markiert. Menge ggf. unter „Nachbestellt“ korrigieren.'
                            );
                        }
                    } elseif (isset($_POST['purchase_list_done'])) {
                        PurchaseListService::markDone((int) ($_POST['purchase_list_id'] ?? 0));
                        Flash::set('success', 'Als erledigt markiert (Wareneingang verbuchen nicht vergessen).');
                        $fromList = trim((string) ($_POST['list'] ?? 'ordered'));
                        $redirect = '/app?page=artikel-leistungen&list='
                            . ($fromList === 'purchase' ? 'purchase' : 'ordered');
                    } elseif (isset($_POST['articles_delete'])) {
                        CalendarArticleRepository::delete((int) ($_POST['article_id'] ?? 0));
                        Flash::set('success', 'Eintrag gelöscht.');
                    } elseif (isset($_POST['articles_import'])) {
                        $result = CalendarArticleImporter::importUploadedFile(
                            $_FILES['import_file'] ?? [],
                            (int) ($_POST['import_area_id'] ?? 0)
                        );
                        Flash::set($result['errors'] === [] ? 'success' : 'warning', $result['message']);
                    } else {
                        CalendarArticleRepository::save($_POST);
                        Flash::set('success', 'Eintrag gespeichert.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Lager (Korrektur, Inventur)
        if (
            $page === 'lager'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'lager')
            && RoleResolver::canEdit($user)
        ) {
            $redirect = '/app?page=lager';
            $view = trim((string) ($_POST['view'] ?? $_GET['view'] ?? 'overview'));
            if ($view !== '') {
                $redirect .= '&view=' . rawurlencode($view);
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['stock_adjust'])) {
                        StockMovementService::manualAdjust(
                            (int) ($_POST['article_id'] ?? 0),
                            (float) str_replace(',', '.', (string) ($_POST['quantity_delta'] ?? '0')),
                            trim((string) ($_POST['adjust_note'] ?? '')),
                            $user->id,
                        );
                        Flash::set('success', 'Lagerkorrektur gebucht.');
                    } elseif (isset($_POST['inventory_start'])) {
                        $invId = StockInventoryService::start(
                            (string) ($_POST['inventory_date'] ?? date('Y-m-d')),
                            (string) ($_POST['inventory_note'] ?? ''),
                            $user->id,
                        );
                        Flash::set('success', 'Inventur #' . $invId . ' gestartet.');
                        $redirect = '/app?page=lager&view=inventur';
                    } elseif (isset($_POST['inventory_save'])) {
                        $invId = (int) ($_POST['inventory_id'] ?? 0);
                        $counted = is_array($_POST['counted'] ?? null) ? $_POST['counted'] : [];
                        StockInventoryService::saveCounts($invId, $counted);
                        Flash::set('success', 'Zählung gespeichert.');
                        $redirect = '/app?page=lager&view=inventur';
                    } elseif (isset($_POST['inventory_close'])) {
                        $invId = (int) ($_POST['inventory_id'] ?? 0);
                        $applied = StockInventoryService::close($invId, $user->id);
                        Flash::set('success', 'Inventur abgeschlossen — ' . $applied . ' Differenz(en) gebucht.');
                        $redirect = '/app?page=lager&view=inventur';
                    } elseif (isset($_POST['stock_receipt'])) {
                        $count = StockReceiptIssueService::receiptFromPost($_POST, $user->id);
                        Flash::set('success', $count . ' Position(en) als Wareneingang gebucht.');
                        $redirect = '/app?page=lager&view=wareneingang';
                    } elseif (isset($_POST['stock_issue'])) {
                        $count = StockReceiptIssueService::issueFromPost($_POST, $user->id);
                        Flash::set('success', $count . ' Position(en) als Warenausgang gebucht.');
                        $redirect = '/app?page=lager&view=warenausgang';
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Akademie
        if (
            $page === 'akademie'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'akademie')
        ) {
            $redirect = '/app?page=akademie';
            $view = trim((string) ($_POST['view'] ?? 'meine'));
            if ($view !== '') {
                $redirect .= '&view=' . rawurlencode($view);
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['academy_accept_rules'])) {
                        if (empty($_POST['confirm_rules'])) {
                            throw new InvalidArgumentException('Bitte Schulungsregeln bestätigen.');
                        }
                        $courseId = (int) ($_POST['course_id'] ?? 0);
                        $course = AcademyRepository::findCourseById($courseId);
                        if ($course === null) {
                            throw new InvalidArgumentException('Kurs nicht gefunden.');
                        }
                        $assignment = AcademyRepository::ensureAssignment((int) $user->id, $courseId, (int) $user->id);
                        AcademyRepository::recordRulesAcceptance(
                            (int) $user->id,
                            $courseId,
                            (string) ($course['version'] ?? '1.0'),
                            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
                        );
                        Flash::set('success', 'Schulungsregeln bestätigt.');
                        $openModule = (int) ($_POST['open_module'] ?? 0);
                        if ($openModule > 0) {
                            $allowed = AcademyRepository::moduleIdsForCourse($courseId);
                            if (in_array($openModule, $allowed, true)) {
                                $redirect = '/app?page=akademie&view=modul&slug='
                                    . rawurlencode((string) $course['slug'])
                                    . '&module_id=' . $openModule;
                            } else {
                                $redirect = '/app?page=akademie&view=kurs&slug='
                                    . rawurlencode((string) $course['slug']);
                            }
                        } else {
                            $redirect = '/app?page=akademie&view=kurs&slug='
                                . rawurlencode((string) $course['slug']);
                        }
                    } elseif (isset($_POST['academy_enroll']) && RoleResolver::isAdmin($user)) {
                        $courseId = (int) ($_POST['course_id'] ?? 0);
                        AcademyRepository::ensureAssignment((int) $user->id, $courseId, (int) $user->id);
                        Flash::set('success', 'Kurs zu „Meine Schulungen“ hinzugefügt.');
                    } elseif (isset($_POST['academy_upload_video']) && RoleResolver::isAdmin($user)) {
                        $moduleId = AcademyVideoService::saveFromUpload($_POST, $_FILES);
                        Flash::set('success', 'Video in Bibliothek gespeichert.');
                        $dept = trim((string) ($_POST['department_id'] ?? ''));
                        $redirect = '/app?page=akademie&view=admin&admin_tab=videos';
                        if ($dept !== '') {
                            $redirect .= '&department_id=' . rawurlencode($dept);
                        }
                        if ($moduleId > 0) {
                            $redirect .= '&video_id=' . $moduleId;
                        }
                    } elseif (isset($_POST['academy_save_course']) && RoleResolver::isAdmin($user)) {
                        $courseId = AcademyRepository::saveCourse($_POST);
                        $moduleIds = [];
                        if (!empty($_POST['module_ids']) && is_array($_POST['module_ids'])) {
                            $moduleIds = array_map('intval', $_POST['module_ids']);
                        }
                        AcademyRepository::saveCourseModules($courseId, $moduleIds);
                        Flash::set('success', 'Kurs gespeichert.');
                        $redirect = '/app?page=akademie&view=admin&course_id=' . $courseId;
                    } elseif (isset($_POST['academy_delete_course']) && RoleResolver::isAdmin($user)) {
                        $courseId = (int) ($_POST['course_id'] ?? $_POST['id'] ?? 0);
                        AcademyRepository::deleteCourse($courseId);
                        Flash::set('success', 'Kurs gelöscht.');
                        $redirect = '/app?page=akademie&view=admin&admin_tab=kurse';
                    } elseif (isset($_POST['academy_assign']) && RoleResolver::isAdmin($user)) {
                        AcademyRepository::assignUser(
                            (int) ($_POST['user_id'] ?? 0),
                            (int) ($_POST['course_id'] ?? 0),
                            (int) $user->id,
                            (string) ($_POST['access_mode'] ?? AcademyAccessMode::COMPARE)
                        );
                        Flash::set('success', 'Schulung zugewiesen.');
                        $redirect = '/app?page=akademie&view=admin&course_id=' . (int) ($_POST['course_id'] ?? 0);
                    } elseif (isset($_POST['academy_set_gate']) && RoleResolver::isAdmin($user)) {
                        AcademyRepository::setGate(
                            (string) ($_POST['module_key'] ?? ''),
                            (int) ($_POST['course_id'] ?? 0),
                            !empty($_POST['gate_active'])
                        );
                        Flash::set('success', 'Modul-Sperre aktualisiert.');
                        $redirect = '/app?page=akademie&view=admin&course_id=' . (int) ($_POST['course_id'] ?? 0);
                    } elseif (isset($_POST['academy_hr_approve']) && RoleResolver::isAdmin($user)) {
                        AcademyHrService::approve((int) ($_POST['assignment_id'] ?? 0), (int) $user->id, (string) ($_POST['review_note'] ?? ''));
                        Flash::set('success', 'Schulung freigegeben — E-Mail an Mitarbeiter gesendet (falls Mail konfiguriert).');
                        $redirect = '/app?page=akademie&view=hr';
                    } elseif (isset($_POST['academy_hr_reject']) && RoleResolver::isAdmin($user)) {
                        AcademyHrService::reject((int) ($_POST['assignment_id'] ?? 0), (int) $user->id, (string) ($_POST['review_note'] ?? ''));
                        Flash::set('success', 'Schulung abgelehnt — E-Mail an Mitarbeiter gesendet (falls Mail konfiguriert).');
                        $redirect = '/app?page=akademie&view=hr';
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // Legacy: alte Einstellungs-URL fÃ¼r Leistungen
        if (
            $page === 'einstellungen'
            && isset($_GET['tab'])
            && $_GET['tab'] === 'leistungen'
            && $_SERVER['REQUEST_METHOD'] === 'GET'
        ) {
            header('Location: /app?page=artikel-leistungen', true, 302);
            exit;
        }

        // POST: Einstellungen Leistungen (veraltet â€” weiterleiten)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && (isset($_POST['articles_save']) || isset($_POST['articles_delete']) || isset($_POST['articles_import']))
        ) {
            header('Location: /app?page=artikel-leistungen', true, 302);
            exit;
        }

        // GET: E-Mail-Archiv (.eml)
        if (
            $page === 'einstellungen'
            && RoleResolver::isAdmin($user)
            && isset($_GET['mail_archive'])
        ) {
            $mailId = (int) $_GET['mail_archive'];
            $row = MailLogRepository::findById($mailId);
            if ($row === null || ($row['status'] ?? '') !== 'sent' || empty($row['storage_path'])) {
                http_response_code(404);
                exit('Archiv nicht gefunden.');
            }
            $absolute = MailArchiveStorage::absolutePath((string) $row['storage_path']);
            if (!is_readable($absolute)) {
                http_response_code(404);
                exit('Datei nicht gefunden.');
            }
            $name = 'mail-' . $mailId . '.eml';
            header('Content-Type: message/rfc822');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . (string) filesize($absolute));
            readfile($absolute);
            exit;
        }

        // POST: HR-Teams
        if (
            ($page === 'hr-team-form' || $page === 'hr-teams')
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'hr-team-form')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=hr-teams', true, 302);
                exit;
            }
            $editTeamId = (int) ($_POST['id'] ?? 0);
            $existingTeam = $editTeamId > 0 ? TeamRepository::findById($editTeamId) : null;
            $departmentIdPost = trim((string) ($_POST['department_id'] ?? ($existingTeam['department_id'] ?? '')));

            if (isset($_POST['team_delete'])) {
                if ($existingTeam === null || !TeamAccess::canManageTeam($user, $existingTeam)) {
                    Flash::set('error', 'Keine Berechtigung zum Löschen.');
                    header('Location: /app?page=hr-teams', true, 302);
                    exit;
                }
                try {
                    TeamRepository::delete($editTeamId);
                    Flash::set('success', 'Team gelöscht.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
                header('Location: /app?page=hr-teams', true, 302);
                exit;
            }

            if (isset($_POST['team_save'])) {
                if ($editTeamId > 0) {
                    if ($existingTeam === null || !TeamAccess::canManageTeam($user, $existingTeam)) {
                        Flash::set('error', 'Keine Berechtigung zum Bearbeiten.');
                        header('Location: /app?page=hr-teams', true, 302);
                        exit;
                    }
                } elseif (!TeamAccess::canManageDepartment($user, $departmentIdPost)) {
                    Flash::set('error', 'Keine Berechtigung, Teams in dieser Abteilung anzulegen (Admin, HR oder Abteilungsleiter/Planner).');
                    header('Location: /app?page=hr-team-form&action=new', true, 302);
                    exit;
                }
                try {
                    $saved = TeamRepository::save($_POST, $editTeamId > 0 ? $editTeamId : null, $user->id);
                    Flash::set('success', 'Team gespeichert. Mitglieder sind am Kontakt verknüpft.');
                    header('Location: /app?page=hr-team-form&action=edit&id=' . (int) $saved['id'], true, 302);
                    exit;
                } catch (Throwable $e) {
                    Flash::set('error', 'Team nicht gespeichert: ' . $e->getMessage());
                    if ($editTeamId > 0) {
                        header('Location: /app?page=hr-team-form&action=edit&id=' . $editTeamId, true, 302);
                    } else {
                        header('Location: /app?page=hr-team-form&action=new', true, 302);
                    }
                    exit;
                }
            }
        }

        // POST: Einstellungen E-Mail / SMTP
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['mail_action'])
        ) {
            $redirect = SettingsRegistry::tabUrl('email');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                $mailAction = (string) $_POST['mail_action'];
                try {
                    if ($mailAction === 'test') {
                        $report = MailSettings::testConnectionReport($_POST);
                        SmtpTestReport::store($report);
                        if ($report['ok']) {
                            MailSettings::save($_POST);
                            $saved = MailSettings::saveSummary($_POST);
                            Flash::set(
                                'success',
                                'SMTP-Verbindung OK und gespeichert: '
                                . $saved['host'] . ':' . $saved['port'] . ' als ' . $saved['username']
                                . ($saved['password_saved'] ? ' (Passwort gespeichert).' : '.')
                            );
                        } else {
                            Flash::set('error', $report['summary']);
                        }
                    } elseif ($mailAction === 'send_test') {
                        $testTo = trim((string) ($_POST['mail_test_to'] ?? $_POST['test_recipient'] ?? ''));
                        MailService::sendTest($testTo, $user);
                        Flash::set('success', 'Test-E-Mail wurde versendet und archiviert.');
                    } else {
                        MailSettings::save($_POST);
                        $saved = MailSettings::saveSummary($_POST);
                        Flash::set(
                            'success',
                            'E-Mail-Einstellungen gespeichert: '
                            . $saved['host'] . ':' . $saved['port'] . ' als ' . $saved['username']
                            . ($saved['password_saved'] ? ' (Passwort gespeichert).' : ' (Passwort unverÃ¤ndert).')
                        );
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen â€” Mitarbeiter-Mail-Formel
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['mail_address_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('email');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    MailAddressSettings::save([
                        'enabled' => $_POST['mail_address_enabled'] ?? '',
                        'auto_on_contact_create' => $_POST['auto_on_contact_create'] ?? '',
                        'domain' => $_POST['mail_domain'] ?? '',
                        'preset' => $_POST['mail_preset'] ?? '',
                        'separator' => $_POST['mail_separator'] ?? '',
                        'local_pattern' => $_POST['local_pattern'] ?? '',
                    ]);
                    Flash::set('success', 'Formel fÃ¼r Mitarbeiter-E-Mail-Adressen gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen — Postfächer (KAS IMAP-Passwort zurücksetzen)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['postbox_repair_imap'])
        ) {
            $redirect = SettingsRegistry::tabUrl('postfaecher');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
                    $result = MailboxProvisioner::repairKasImapPassword($mailboxId);
                    Flash::set('success', $result['message'] . ' (' . $result['email'] . ')');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen — Postfächer (KAS-Nachprovisionierung)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['postbox_provision_kas'])
        ) {
            $redirect = SettingsRegistry::tabUrl('postfaecher');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
                    $result = MailboxProvisioner::provisionKasForMailbox($mailboxId);
                    Flash::set('success', $result['message'] . ' (' . $result['email'] . ')');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen — Postfach löschen
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['postbox_delete'])
        ) {
            $redirect = SettingsRegistry::tabUrl('postfaecher');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
                    $existing = MailboxRepository::findById($mailboxId);
                    if ($existing === null) {
                        throw new RuntimeException('Postfach nicht gefunden.');
                    }
                    $email = (string) ($existing['email_address'] ?? '');
                    MailboxRepository::deleteById($mailboxId);
                    Flash::set('success', 'Postfach gelöscht' . ($email !== '' ? ': ' . $email : '') . '.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen — Post-Darstellung (Mails pro Seite)
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['post_ui_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('postfaecher');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    PostUiSettings::saveFromPost($_POST);
                    Flash::set('success', 'Post-Darstellung gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Einstellungen — Postfächer
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['postbox_save'])
        ) {
            $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
            $redirect = SettingsRegistry::tabUrl('postfaecher');
            if ($mailboxId > 0) {
                $redirect .= '&edit=' . $mailboxId;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                try {
                    $members = MailboxRepository::membersFromPost($_POST);
                    if ($mailboxId > 0) {
                        $existing = MailboxRepository::findById($mailboxId);
                        if ($existing === null) {
                            throw new RuntimeException('Postfach nicht gefunden.');
                        }
                        $data = MailboxProvisioner::normalizePostInput($_POST, $existing);
                        $data['type'] = $existing['type'];
                        MailboxRepository::save($data, $mailboxId, ($existing['type'] ?? '') === 'shared' ? $members : []);
                        Flash::set('success', 'Postfach gespeichert. IMAP/SMTP-Anmeldung geprüft.');
                        $redirect = SettingsRegistry::tabUrl('postfaecher');
                    } elseif (!empty($_POST['provision_kas']) && KasSettings::isConfigured()) {
                        MailboxProvisioner::createSharedFromForm($_POST, $members);
                        Flash::set('success', 'Postfach angelegt (KAS). IMAP/SMTP-Anmeldung geprüft.');
                    } else {
                        $data = MailboxProvisioner::normalizePostInput($_POST);
                        MailboxRepository::save($data, null, $members);
                        Flash::set('success', 'Postfach angelegt. IMAP/SMTP-Anmeldung geprüft.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Post — Nachricht senden
        if (
            $page === 'post'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['post_send'])
            && MenuRegistry::canAccess($user, 'post')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
                header('Location: /app?page=post&compose=1', true, 302);
                exit;
            }
            try {
                PostMailComposer::sendFromPost($user, $_POST);
                Flash::set('success', 'Nachricht wurde gesendet.');
                $sentMailbox = (int) ($_POST['mailbox_id'] ?? 0);
                $redirect = $sentMailbox > 0
                    ? '/app?page=post&mailbox=' . $sentMailbox . '&folder=' . rawurlencode('__sent__')
                    : '/app?page=post';
                header('Location: ' . $redirect, true, 302);
                exit;
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                $_SESSION['dg_post_compose'] = [
                    'mailbox_id' => (string) ($_POST['mailbox_id'] ?? ''),
                    'to' => (string) ($_POST['to'] ?? ''),
                    'subject' => (string) ($_POST['subject'] ?? ''),
                    'body' => (string) ($_POST['body'] ?? ''),
                    'reply_to_id' => (string) ($_POST['reply_to_id'] ?? ''),
                    'forward_from_id' => (string) ($_POST['forward_from_id'] ?? ''),
                    'mode' => (string) ($_POST['compose_mode'] ?? 'new'),
                    'contact_id' => (string) ($_POST['contact_id'] ?? ''),
                ];
                header('Location: /app?page=post&compose=1', true, 302);
                exit;
            }
        }

        // POST: Post — Kontakt / Beleg verknüpfen
        if (
            $page === 'post'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (
                isset($_POST['post_link_contact'])
                || isset($_POST['post_unlink_contact'])
                || isset($_POST['post_link_voucher'])
                || isset($_POST['post_unlink_voucher'])
            )
            && MenuRegistry::canAccess($user, 'post')
        ) {
            $mailId = (int) ($_POST['mail_id'] ?? 0);
            $redirect = $mailId > 0 ? '/app?page=post&id=' . $mailId : '/app?page=post';
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
                header('Location: ' . $redirect, true, 302);
                exit;
            }
            try {
                if (isset($_POST['post_unlink_contact'])) {
                    PostMailLinkService::unlinkContact($user, $mailId);
                    Flash::set('success', 'Kontaktverknüpfung entfernt.');
                } elseif (isset($_POST['post_link_contact'])) {
                    $ref = trim((string) ($_POST['contact_ref'] ?? ''));
                    if ($ref === '' && !empty($_POST['suggested_contact_id'])) {
                        $ref = (string) (int) $_POST['suggested_contact_id'];
                    }
                    $contact = PostMailLinkService::resolveContactRef($ref);
                    if ($contact === null) {
                        throw new InvalidArgumentException('Kontakt nicht gefunden (ID oder E-Mail).');
                    }
                    PostMailLinkService::linkContact($user, $mailId, $contact->id);
                    Flash::set('success', 'Kontakt verknüpft.');
                } elseif (isset($_POST['post_unlink_voucher'])) {
                    PostMailLinkService::unlinkVoucher($user, $mailId);
                    Flash::set('success', 'Belegverknüpfung entfernt.');
                } else {
                    PostMailLinkService::linkVoucher($user, $mailId, (string) ($_POST['voucher_ref'] ?? ''));
                    Flash::set('success', 'Beleg verknüpft.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // Mitarbeiter-Dokument: Browseransicht (inline) oder Download (nur mit ?doc=)
        if (
            $page === 'kontakte'
            && in_array($action, ['view', 'download'], true)
            && isset($_GET['doc'])
            && MenuRegistry::canAccess($user, 'kontakte')
        ) {
            $docId = (int) ($_GET['id'] ?? 0);
            $docType = preg_replace('/[^a-z_]/', '', (string) ($_GET['doc'] ?? ''));
            $contact = $docId > 0 ? ContactRepository::findById($docId) : null;
            $allowedDocs = EmployeeData::allDocumentTypes();
            $fileIndex = isset($_GET['file']) ? (int) $_GET['file'] : null;
            if (
                !$contact
                || !ContactAccessResolver::canViewEmployeeHrData($user, $contact)
                || !isset($allowedDocs[$docType])
            ) {
                http_response_code(404);
                exit('Datei nicht gefunden.');
            }
            $filesEntry = $contact->employeeFiles[$docType] ?? [];
            if (ContactFileStorage::isMultiType($docType)) {
                if (!is_array($filesEntry) || $fileIndex === null || !isset($filesEntry[$fileIndex])) {
                    http_response_code(404);
                    exit('Datei nicht gefunden.');
                }
                $entry = $filesEntry[$fileIndex];
            } else {
                $entry = is_array($filesEntry) ? $filesEntry : [];
            }
            $absolute = !empty($entry['path']) ? ContactFileStorage::resolveAbsolute((string) $entry['path']) : null;
            if ($absolute === null) {
                http_response_code(404);
                exit('Datei nicht gefunden.');
            }
            $mime = (string) ($entry['mime'] ?? 'application/octet-stream');
            $name = (string) ($entry['original_name'] ?? basename($absolute));
            $safeName = str_replace(['"', "\r", "\n"], '', $name);
            $inline = $action === 'view';
            header('Content-Type: ' . $mime);
            header(
                'Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"'
            );
            header('Content-Length: ' . (string) filesize($absolute));
            header('X-Content-Type-Options: nosniff');
            readfile($absolute);
            exit;
        }

        // SV-Anmeldung: Entwurf als JSON herunterladen (nur Vorbereitung, nicht versendet)
        if (
            $page === 'kontakte'
            && $action === 'sv-draft'
            && MenuRegistry::canAccess($user, 'kontakte')
        ) {
            $docId = (int) ($_GET['id'] ?? 0);
            $contact = $docId > 0 ? ContactRepository::findById($docId) : null;
            if (
                !$contact
                || !ContactAccessResolver::canViewEmployeeHrData($user, $contact)
            ) {
                http_response_code(404);
                exit('Entwurf nicht gefunden.');
            }
            $json = trim($contact->employeeData['social_registration_draft_json'] ?? '');
            if ($json === '') {
                http_response_code(404);
                exit('Noch kein Anmeldungs-Entwurf vorhanden.');
            }
            $name = 'sv-anmeldung-entwurf-' . $contact->login . '.json';
            $safeName = str_replace(['"', "\r", "\n"], '', $name);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
            header('X-Content-Type-Options: nosniff');
            echo $json;
            exit;
        }

        // POST: SV-Anmeldung vorbereiten (Entwurf, nicht absenden)
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prepare_sv_registration'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte') || !RoleResolver::canEdit($user)) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            $editId = (int) ($_POST['id'] ?? 0);
            if ($editId <= 0) {
                Flash::set('error', 'Bitte Kontakt zuerst speichern, dann Anmeldung vorbereiten.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            $svContact = ContactRepository::findById($editId);
            if ($svContact === null || !ContactAccessResolver::canViewEmployeeHrData($user, $svContact)) {
                Flash::set('error', 'Keine Berechtigung fÃ¼r Mitarbeiterdaten.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            try {
                $uploads = is_array($_FILES['employee_files'] ?? null) ? $_FILES['employee_files'] : [];
                $result = ContactRepository::prepareSocialSecurityRegistrationDraft($_POST, $editId, $uploads);
                if ($result['ok']) {
                    $msg = 'SV-Anmeldung als Entwurf vorbereitet â€” noch nicht an die Meldestelle Ã¼bermittelt.';
                    if ($result['warnings'] !== []) {
                        $msg .= ' Hinweise: ' . implode(' ', $result['warnings']);
                    }
                    Flash::set('success', $msg);
                } else {
                    Flash::set(
                        'error',
                        'Entwurf unvollstÃ¤ndig. Fehlende Angaben: ' . implode(', ', $result['missing'])
                    );
                }
                header('Location: /app?page=kontakte&action=edit&id=' . $result['contact_id'], true, 302);
                exit;
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                header('Location: /app?page=kontakte&action=edit&id=' . $editId, true, 302);
                exit;
            }
        }

        // POST: Multi-Firma MF6b — Kontakte JSON-Export für Org-Schwester
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_org_export'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte')) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            if (!ContactExportService::isExportAllowed($user)) {
                Flash::set('error', 'Kontakt-Export für Org-Schwester ist hier nicht freigeschaltet.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            try {
                $exportSearch = trim((string) ($_POST['s'] ?? ''));
                ContactExportService::sendDownload($exportSearch, $user);
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
        }

        // GET: CSV-Vorlage Kontakt-Massenimport
        if ($page === 'kontakte' && ($_GET['action'] ?? '') === 'import-csv-template') {
            if (!MenuRegistry::canAccess($user, 'kontakte') || !ContactFileImportService::isAllowed($user)) {
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            ContactFileImportService::sendTemplateDownload();
        }

        // GET: Kontakte als CSV exportieren (Import-kompatibel)
        if ($page === 'kontakte' && ($_GET['action'] ?? '') === 'export-csv') {
            if (!MenuRegistry::canAccess($user, 'kontakte')) {
                header('Location: /app', true, 302);
                exit;
            }
            try {
                ContactFileImportService::sendCsvExport($user, trim((string) ($_GET['s'] ?? '')));
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
        }

        // POST: Multi-Firma MF6c — Kontakte JSON-Import von Org-Schwester
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_org_import'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte')) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            if (!ContactImportService::isImportAllowed($user)) {
                Flash::set('error', 'Kein Recht zum Kontakt-Import.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            try {
                $result = ContactImportService::importUpload(
                    is_array($_FILES['contact_export_file'] ?? null) ? $_FILES['contact_export_file'] : [],
                    $user,
                    !empty($_POST['overwrite_fields']),
                    !empty($_POST['confirm_warnings'])
                );
                $msg = $result['message'];
                if ($result['errors'] !== []) {
                    $msg .= ' ' . implode(' ', array_slice($result['errors'], 0, 5));
                }
                Flash::set($result['errors'] !== [] ? 'warning' : 'success', $msg);
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=kontakte', true, 302);
            exit;
        }

        // POST: Kontakte CSV/Excel-Massenimport (Install-Engine)
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_file_import'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte')) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                unset($_SESSION['dg_contact_import_errors']);
                Flash::set('error', 'Ungültiges Formular (CSRF).');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            if (!ContactFileImportService::isAllowed($user)) {
                unset($_SESSION['dg_contact_import_errors']);
                Flash::set('error', 'Kein Recht zum Kontakt-Import.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            // Vor jedem Lauf leeren — sonst bleiben Duplikat-Hinweise vom vorherigen Import stehen
            unset($_SESSION['dg_contact_import_errors']);
            try {
                $result = ContactFileImportService::importUpload(
                    is_array($_FILES['contact_import_file'] ?? null) ? $_FILES['contact_import_file'] : [],
                    $user,
                    $_POST
                );
                if ($result['errors'] !== []) {
                    $_SESSION['dg_contact_import_errors'] = array_slice($result['errors'], 0, 40);
                    Flash::set('warning', $result['message'] . ' Details siehe Hinweise unten.');
                } else {
                    Flash::set('success', $result['message']);
                }
            } catch (Throwable $e) {
                unset($_SESSION['dg_contact_import_errors']);
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=kontakte', true, 302);
            exit;
        }

        // POST: Kontakt löschen
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_delete'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte') || !RoleResolver::canEdit($user)) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            $deleteId = (int) ($_POST['id'] ?? 0);
            try {
                $deleteContact = $deleteId > 0 ? ContactRepository::findById($deleteId) : null;
                if ($deleteContact === null) {
                    throw new InvalidArgumentException('Kontakt nicht gefunden.');
                }
                ContactAccessResolver::assertCanDelete($user, $deleteContact);
                ContactRepository::delete($deleteId);
                Flash::set('success', 'Kontakt gelÃ¶scht.');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=kontakte', true, 302);
            exit;
        }

        // POST: Mitarbeiter-Zugangseinladungen (Link/Manuell)
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['staff_access_action'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte')) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            $accessContactId = (int) ($_POST['id'] ?? 0);
            $accessAction = (string) ($_POST['staff_access_action'] ?? '');
            try {
                $msg = match ($accessAction) {
                    'crm_invite' => StaffAccessInviteService::sendCrmInvite($user, $accessContactId),
                    'crm_manual' => StaffAccessInviteService::setCrmPasswordManual(
                        $user,
                        $accessContactId,
                        (string) ($_POST['password'] ?? ''),
                        (string) ($_POST['password_confirm'] ?? ''),
                        !empty($_POST['dsgvo_confirm'])
                    ),
                    'pin_invite' => StaffAccessInviteService::sendPinInvite($user, $accessContactId),
                    'pin_manual' => StaffAccessInviteService::setPinManual(
                        $user,
                        $accessContactId,
                        (string) ($_POST['pin'] ?? ''),
                        !empty($_POST['dsgvo_confirm'])
                    ),
                    'mailbox_invite' => StaffAccessInviteService::sendMailboxInvite($user, $accessContactId),
                    'mailbox_manual' => StaffAccessInviteService::setMailboxPasswordManual(
                        $user,
                        $accessContactId,
                        (string) ($_POST['mailbox_password'] ?? ''),
                        (string) ($_POST['mailbox_password_confirm'] ?? ''),
                        !empty($_POST['dsgvo_confirm'])
                    ),
                    'app_invite' => StaffAccessInviteService::sendAppInvite($user, $accessContactId),
                    default => throw new InvalidArgumentException('Unbekannte Aktion.'),
                };
                Flash::set('success', $msg);
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=kontakte&action=edit&id=' . $accessContactId . '#staff-access-invite', true, 302);
            exit;
        }

        // POST: Kontakt speichern
        if ($page === 'kontakte' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_save'])) {
            if (!MenuRegistry::canAccess($user, 'kontakte')) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular.');
                header('Location: /app?page=kontakte', true, 302);
                exit;
            }
            $editId = (int) ($_POST['id'] ?? 0);
            try {
                if (!RoleResolver::canEdit($user)) {
                    throw new RuntimeException('Keine Berechtigung zum Bearbeiten.');
                }
                $existingContact = $editId > 0 ? ContactRepository::findById($editId) : null;
                if ($existingContact !== null) {
                    ContactAccessResolver::assertCanEdit($user, $existingContact);
                } elseif (!ContactAccessResolver::canEditContact($user)) {
                    throw new RuntimeException('Keine Berechtigung zum Anlegen von Kontakten.');
                }
                $_POST = ContactAccessResolver::enforceContactRoleOnSave($user, $_POST, $existingContact);
                $uploads = is_array($_FILES['employee_files'] ?? null) ? $_FILES['employee_files'] : [];
                $isNewContact = $editId === 0;
                $newId = ContactRepository::save($_POST, $editId ?: null, $uploads);
                $flashMessage = 'Kontakt gespeichert.';
                if ($isNewContact && !empty($_POST['auto_create_mailbox'])) {
                    $savedContact = ContactRepository::findById($newId);
                    if ($savedContact !== null && CrmRole::hasEmployeeProfile($savedContact->contactRole)) {
                        try {
                            $mailboxResult = MailboxProvisioner::createPrivateForContact($savedContact);
                            if (!empty($mailboxResult['skipped'])) {
                                $flashMessage .= ' ' . $mailboxResult['message'];
                            } else {
                                ContactRepository::setPrimaryEmailIfEmpty($newId, $mailboxResult['email']);
                                $flashMessage .= ' ' . $mailboxResult['message'] . ' (' . $mailboxResult['email'] . ')';
                            }
                        } catch (Throwable $mailboxError) {
                            $flashMessage .= ' Postfach: ' . $mailboxError->getMessage();
                        }
                    }
                }
                if ($isNewContact) {
                    foreach (StaffAccessInviteService::processCreateInvites($user, $newId, $_POST) as $inviteMsg) {
                        $flashMessage .= ' ' . $inviteMsg;
                    }
                }
                Flash::set('success', $flashMessage);
                $returnTo = trim((string) ($_POST['return_to'] ?? ''));
                if ($returnTo !== '' && str_starts_with($returnTo, '/app?') && !str_contains($returnTo, '//')) {
                    $separator = str_contains($returnTo, '?') ? '&' : '?';
                    $redirectUrl = $returnTo . $separator . 'contact_id=' . $newId;
                    $savedContact = ContactRepository::findById($newId);
                    if ($savedContact !== null) {
                        $contactLabel = trim($savedContact->companyName);
                        if ($contactLabel === '') {
                            $contactLabel = trim($savedContact->displayName);
                        }
                        if ($contactLabel !== '') {
                            $redirectUrl .= '&contact_label=' . rawurlencode($contactLabel);
                        }
                    }
                    header('Location: ' . $redirectUrl, true, 302);
                    exit;
                }
                header('Location: /app?page=kontakte', true, 302);
                exit;
            } catch (Throwable $e) {
                $contentTemplate = 'modules/kontakte-form';
                $title = $editId ? 'Kontakt bearbeiten' : 'Neuer Kontakt';
                $currentPage = 'kontakte';
                $contactId = $editId ?: null;
                $form = array_merge(ContactRepository::emptyForm(), $_POST);
                $formError = $e->getMessage();
                $kontakteReturnTo = trim((string) ($_POST['return_to'] ?? ''));
                if ($kontakteReturnTo !== '' && (!str_starts_with($kontakteReturnTo, '/app?') || str_contains($kontakteReturnTo, '//'))) {
                    $kontakteReturnTo = '';
                }
                $bankAccounts = ContactRepository::parseBankAccountsFromPost($_POST);
                if ($bankAccounts === []) {
                    $bankAccounts = ContactRepository::defaultBankAccounts();
                }
                $employeeData = EmployeeData::fromPost($_POST);
                $employeeFiles = $editId > 0 && ($existing = ContactRepository::findById($editId))
                    ? $existing->employeeFiles
                    : ContactFileStorage::emptyFiles();
                $existingForForm = $editId > 0 ? ContactRepository::findById($editId) : null;
                $showEmployeeFields = CrmRole::hasEmployeeProfile(CrmRole::normalize((string) ($_POST['contact_role'] ?? 'dg_kunde')))
                    && ($existingForForm === null
                        ? ContactAccessResolver::canViewAllContactTypes($user)
                        : ContactAccessResolver::canViewEmployeeHrData($user, $existingForForm));
                $allowedContactRoles = ContactAccessResolver::allowedContactRoleOptions($user);
                $canDeleteContact = $existingForForm !== null && ContactAccessResolver::canDeleteContact($user, $existingForForm);
                $linkFormContext = ContactCompanyLinkRepository::formContext($existingForForm, $_POST);
                extract($linkFormContext);
                $payrollWageTypeOptions = PayrollWageTypeRepository::all(false);
                $payrollWageAssignments = [];
                $rawAssign = $_POST['payroll_wage_assignments'] ?? null;
                if (is_array($rawAssign)) {
                    foreach ($rawAssign as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $payrollWageAssignments[] = [
                            'id' => (int) ($row['id'] ?? 0),
                            'wage_type_id' => (int) ($row['wage_type_id'] ?? 0),
                            'amount' => (string) ($row['amount'] ?? ''),
                            'valid_from' => (string) ($row['valid_from'] ?? ''),
                            'valid_to' => (string) ($row['valid_to'] ?? ''),
                            'priority' => (int) ($row['priority'] ?? 0),
                            'notes' => (string) ($row['notes'] ?? ''),
                            'delete' => !empty($row['delete']),
                        ];
                    }
                }
                $dbConfig = DatabaseSettings::forForm();
                $dbConnected = Database::isConfigured();
                try {
                    if ($dbConnected) {
                        Database::pdo()->query('SELECT 1');
                    }
                } catch (Throwable) {
                    $dbConnected = false;
                }
                View::render('layout/app', compact(
                    'title', 'user', 'navMode', 'departments', 'contentTemplate', 'area', 'dept',
                    'menuItems', 'settingsItem', 'currentPage', 'settingsNav', 'settingsSelection',
                    'flash', 'dbConfig', 'dbConnected', 'canEdit', 'sidebarItems', 'contactId', 'form', 'formError', 'bankAccounts', 'employeeData', 'employeeFiles', 'showEmployeeFields', 'allowedContactRoles', 'canDeleteContact', 'companyEmployees', 'employerForm', 'companyContactOptions', 'personContactOptions', 'kontakteReturnTo', 'payrollWageAssignments', 'payrollWageTypeOptions'
                ));
                break;
            }
        }

        // GET: Beleg-Datei anzeigen / herunterladen
        if (
            $page === 'buchhaltung-beleg-form'
            && ($_GET['action'] ?? '') === 'beleg-file'
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $fileMeta = VoucherFileStorage::resolveForDownload((int) ($_GET['file'] ?? 0));
            if ($fileMeta === null) {
                http_response_code(404);
                exit('Datei nicht gefunden.');
            }
            $inline = ($_GET['disp'] ?? '') !== 'download';
            $safeName = str_replace(['"', "\r", "\n"], '', $fileMeta['name']);
            header('Content-Type: ' . $fileMeta['mime']);
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"');
            header('Content-Length: ' . (string) filesize($fileMeta['path']));
            header('X-Content-Type-Options: nosniff');
            readfile($fileMeta['path']);
            exit;
        }

        // POST: Kontakt-Stammdaten aus Beleg-Import ergänzen (nur leere Felder)
        if (
            $page === 'buchhaltung-beleg-form'
            && ($_GET['action'] ?? '') === 'contact-patch'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            header('Content-Type: application/json; charset=utf-8');
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Keine Berechtigung bzw. ungültiges Formular.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $contactId = (int) ($_POST['contact_id'] ?? 0);
            if ($contactId < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Kein Kontakt ausgewählt.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $updated = ContactRepository::patchMasterDataIfEmpty($contactId, [
                    'tax_number' => (string) ($_POST['tax_number'] ?? ''),
                    'vat_id' => (string) ($_POST['vat_id'] ?? ''),
                    'commercial_register' => (string) ($_POST['commercial_register'] ?? ''),
                    'weee_registration' => (string) ($_POST['weee_registration'] ?? ''),
                    'supplier_customer_number' => (string) ($_POST['supplier_customer_number'] ?? ''),
                    'website' => (string) ($_POST['website'] ?? ''),
                    'phone_1' => (string) ($_POST['phone_1'] ?? ''),
                    'address1_street' => (string) ($_POST['address1_street'] ?? ''),
                    'address1_postal' => (string) ($_POST['address1_postal'] ?? ''),
                    'address1_city' => (string) ($_POST['address1_city'] ?? ''),
                ]);
                echo json_encode(['success' => true, 'updated' => $updated], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        // POST: Beleg-Datei löschen
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_file_delete'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $fileId = (int) ($_POST['file_id'] ?? 0);
            $backVoucherId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                $fileMeta = VoucherFileStorage::resolveForDownload($fileId);
                if ($fileMeta !== null) {
                    $backVoucherId = $fileMeta['voucher_id'];
                    VoucherFileStorage::deleteFile($fileId);
                    Flash::set('success', 'Datei gelöscht.');
                }
            }
            header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $backVoucherId, true, 302);
            exit;
        }

        // POST: Beleg speichern
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_save'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=buchhaltung-belege', true, 302);
                exit;
            }
            if (!RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung zum Bearbeiten.');
                header('Location: /app?page=buchhaltung-belege', true, 302);
                exit;
            }
            $editId = (int) ($_POST['id'] ?? 0);
            $postedVoucherId = $editId;
            $draftVoucherId = (int) ($_POST['draft_voucher_id'] ?? 0);
            if ($editId < 1 && $draftVoucherId > 0) {
                $editId = $draftVoucherId;
            }
            $previousStatus = '';
            if ($editId > 0) {
                $beforeSave = VoucherRepository::findById($editId);
                if ($beforeSave !== null) {
                    $previousStatus = VoucherDocumentStatus::sanitize((string) ($beforeSave['document_status'] ?? ''));
                }
            }
            try {
                $beforeSave = $editId > 0 ? VoucherRepository::findById($editId) : null;
                if (
                    VoucherDocumentRevisionService::isImmutable($beforeSave)
                    && is_array($beforeSave)
                    && !VoucherDocumentRevisionService::isStatusOnlyChange($beforeSave, $_POST)
                ) {
                    $revision = VoucherDocumentRevisionService::reviseInsteadOfOverwrite(
                        $editId,
                        $_POST,
                        $user->id
                    );
                    Flash::set('success', $revision['message']);
                    header(
                        'Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . (int) $revision['new_id'],
                        true,
                        302
                    );
                    exit;
                }
                $newId = VoucherRepository::save($_POST, $editId > 0 ? $editId : null, $user->id);
                $uploadWarning = '';
                if (isset($_FILES['voucher_files']) && is_array($_FILES['voucher_files'])) {
                    try {
                        VoucherFileStorage::processUploads($newId, $_FILES['voucher_files'], $user->id);
                    } catch (Throwable $fileError) {
                        $uploadWarning = ' Hinweis zum Datei-Upload: ' . $fileError->getMessage();
                    }
                }
                $stockWarnings = StockReservationService::takeLastWarnings();
                $stockNote = $stockWarnings !== [] ? ' ' . implode(' ', $stockWarnings) : '';
                if ($uploadWarning !== '' || $stockNote !== '') {
                    Flash::set('warning', 'Beleg gespeichert.' . $uploadWarning . $stockNote);
                } else {
                    Flash::set('success', 'Beleg gespeichert.');
                }
                $savedRow = VoucherRepository::findById($newId);
                $savedKind = VoucherDocumentKind::sanitize((string) ($savedRow['document_kind'] ?? $_POST['document_kind'] ?? ''));
                $savedStatus = VoucherDocumentStatus::sanitize((string) ($savedRow['document_status'] ?? $_POST['document_status'] ?? ''));
                $wantPrint = isset($_POST['voucher_save_and_print']) || isset($_POST['voucher_save_print']);

                // Angebot → angenommen: direkt zur Auftragsbestätigung
                if (
                    !$wantPrint
                    && $savedKind === VoucherDocumentKind::OFFER
                    && $savedStatus === VoucherDocumentStatus::ACCEPTED
                    && $previousStatus !== VoucherDocumentStatus::ACCEPTED
                    && !VoucherDocumentStatus::isClosed($previousStatus)
                ) {
                    Flash::set('success', 'Angebot als angenommen gespeichert — bitte Auftragsbestätigung prüfen/speichern.');
                    header(
                        'Location: ' . VoucherDocumentChain::orderConfirmationFollowUpUrl($newId),
                        true,
                        302
                    );
                    exit;
                }

                // Neue manuelle Auftragsbestätigung → sofort Druck/Unterschrift
                if ($wantPrint || ($postedVoucherId < 1 && $savedKind === VoucherDocumentKind::ORDER_CONFIRMATION)) {
                    header(
                        'Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $newId . '&download=print',
                        true,
                        302
                    );
                    exit;
                }
                header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $newId, true, 302);
                exit;
            } catch (Throwable $e) {
                $contentTemplate = 'modules/buchhaltung-beleg-form';
                $belegeFormFocus = VoucherBelegeBoard::sanitizeFocus((string) ($_POST['focus'] ?? $_GET['focus'] ?? ''));
                $title = $editId > 0
                    ? 'Beleg bearbeiten'
                    : ($belegeFormFocus === VoucherBelegeBoard::FOCUS_DIENSTLEISTUNGEN
                        ? 'Neues Auftrag/Angebot'
                        : 'Neuer Beleg');
                $currentPage = 'buchhaltung-belege';
                $voucherId = $editId > 0 ? $editId : null;
                $form = array_merge(VoucherRepository::emptyForm(), $_POST);
                if ($editId > 0) {
                    $form['files'] = VoucherFileStorage::listForVoucher($editId);
                }
                $formError = $e->getMessage();
                Flash::set('error', 'Beleg nicht gespeichert: ' . $e->getMessage());
                $flash = Flash::get();
                $chartOfAccountsConfig = ChartOfAccountsSettings::forForm();
                $dbConfig = DatabaseSettings::forForm();
                $dbConnected = Database::isConfigured();
                try {
                    if ($dbConnected) {
                        Database::pdo()->query('SELECT 1');
                    }
                } catch (Throwable) {
                    $dbConnected = false;
                }
                $voucherChain = ['documents' => [], 'current_id' => 0];
                $followUpKinds = [];
                $chainSummary = null;
                $calendarAreas = CalendarStaffRepository::getAreas();
                View::render('layout/app', compact(
                    'title', 'user', 'navMode', 'departments', 'contentTemplate', 'area', 'dept',
                    'menuItems', 'settingsItem', 'dienstleistungenSection', 'hrSection', 'lohnSection', 'warenwirtschaftSection', 'buchhaltungSection', 'websiteSection', 'kommunikationSection', 'kdvSection',
                    'currentPage', 'settingsNav', 'settingsSelection',
                    'flash', 'dbConfig', 'dbConnected', 'canEdit', 'sidebarItems', 'voucherId', 'form', 'formError',
                    'chartOfAccountsConfig', 'voucherChain', 'followUpKinds', 'chainSummary', 'calendarAreas',
                    'belegeFormFocus'
                ));
                break;
            }
        }

        $guardWebsitePost = static function () use ($user, $page): void {
            if (!MenuRegistry::canAccess($user, $page)) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=' . $page, true, 302);
                exit;
            }
            if (!RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung zum Bearbeiten.');
                header('Location: /app?page=' . $page, true, 302);
                exit;
            }
        };

        // Image upload for website builder → Mediathek (öffentlich über /app/media)
        if ($page === 'website-seite-form' && $action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_image_upload'])) {
            $guardWebsitePost();
            header('Content-Type: application/json; charset=utf-8');
            try {
                if (empty($_FILES['file']) || (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Upload fehlgeschlagen.');
                }
                MediaRepository::ensureTables();
                $mediaId = MediaId::generate();
                $stored = MediaStorage::storeUpload($mediaId, $_FILES['file']);
                $stored['source_note'] = 'Website-Builder';
                $stored['title'] = pathinfo((string) ($stored['original_name'] ?? ''), PATHINFO_FILENAME);
                $stored['alt_text'] = '';
                MediaRepository::insert($mediaId, $stored, $user->id);
                echo json_encode([
                    'url' => MediaStorage::publicUrl($mediaId),
                    'media_id' => $mediaId,
                    'alt' => (string) ($stored['alt_text'] ?? ''),
                ], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if ($page === 'website-seite-form' && $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['website_page_save']) || isset($_POST['website_page_delete']))) {
            $guardWebsitePost();
            $editId = (int) ($_POST['id'] ?? 0);
            try {
                if (isset($_POST['website_page_delete']) && $editId > 0) {
                    WebsitePageRepository::delete($editId);
                    Flash::set('success', 'Seite gelöscht.');
                    header('Location: /app?page=website-seiten', true, 302);
                    exit;
                }
                $newId = WebsitePageRepository::save($_POST, $editId > 0 ? $editId : null, $user->id);
                Flash::set('success', 'Seite gespeichert.');
                header('Location: /app?page=website-seite-form&action=edit&id=' . $newId, true, 302);
                exit;
            } catch (Throwable $e) {
                $contentTemplate = 'modules/website-seite-form';
                $title = $editId > 0 ? 'Seite bearbeiten' : 'Neue Seite';
                $currentPage = 'website-seite-form';
                $websitePageId = $editId > 0 ? $editId : null;
                $form = [
                    'title' => (string) ($_POST['title'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'layout' => json_decode((string) ($_POST['layout'] ?? ''), true) ?: WebsitePageRepository::emptyLayout(),
                ];
                $formError = $e->getMessage();
                $websiteFormOptions = WebsiteFormRepository::listPublishedOptions();
            }
        }

        if (
            $page === 'website-seiten'
            && $_SERVER['REQUEST_METHOD'] === 'GET'
            && isset($_GET['legal_ensure'])
            && Database::isConfigured()
            && MenuRegistry::canAccess($user, 'website-seiten')
        ) {
            $legalSlug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string) $_GET['legal_ensure']))) ?? '';
            if ($legalSlug !== '' && in_array($legalSlug, LegalPageGenerator::legalSlugs(), true)) {
                try {
                    $legalPageId = LegalPageGenerator::ensurePage($legalSlug, $user->id);
                    if ($legalPageId !== null && $legalPageId > 0) {
                        Flash::set('success', 'Pflichtseite angelegt — bitte Inhalt prüfen und anpassen.');
                        header('Location: /app?page=website-seite-form&action=edit&id=' . $legalPageId, true, 302);
                        exit;
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=website-seiten', true, 302);
            exit;
        }

        if ($page === 'website-seiten' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_bootstrap_defaults'])) {
            $guardWebsitePost();
            try {
                $overwrite = !empty($_POST['bootstrap_overwrite']);
                // Wartungsmodus nur über das eigene Formular (name=enabled) — Bootstrap ändert ihn nicht.
                $result = WebsiteBootstrapService::bootstrap($user->id, [
                    'overwrite' => $overwrite,
                    'maintenance' => false,
                ]);
                $summary = [];
                foreach ($result['legal'] as $legalPage) {
                    $summary[] = $legalPage['title'] . ': ' . $legalPage['action'];
                }
                if (is_array($result['homepage'])) {
                    $summary[] = 'Startseite: ' . $result['homepage']['action'];
                }
                if (is_array($result['contact_page'])) {
                    $summary[] = 'Kontakt: ' . $result['contact_page']['action'];
                }
                if (is_array($result['terminkalender_page'])) {
                    $summary[] = 'Terminkalender: ' . $result['terminkalender_page']['action'];
                }
                Flash::set('success', 'Pflichtseiten eingerichtet. ' . implode(' · ', $summary));
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=website-seiten', true, 302);
            exit;
        }

        if ($page === 'website-seiten' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_maintenance_save'])) {
            $guardWebsitePost();
            try {
                $upload = null;
                if (!empty($_FILES['maintenance_image']) && is_array($_FILES['maintenance_image'])) {
                    $upload = $_FILES['maintenance_image'];
                }
                WebsiteMaintenanceSettings::save($_POST, $upload, $user->id);
                Flash::set('success', 'Wartungsmodus gespeichert.');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=website-seiten', true, 302);
            exit;
        }

        if ($page === 'support-freigabe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::isAdmin($user) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=support-freigabe', true, 302);
                exit;
            }
            try {
                if (isset($_POST['support_access_stop'])) {
                    SupportAccessService::stop($user->id, 'manual');
                    Flash::set('success', 'Support-Freigabe beendet.');
                } elseif (isset($_POST['support_access_start'])) {
                    $hours = (int) ($_POST['duration_hours'] ?? SupportAccessService::DEFAULT_HOURS);
                    $screen = !empty($_POST['screen_share']);
                    $started = SupportAccessService::start($hours, $user->id, $screen);
                    Flash::set('success', 'Support-Freigabe gestartet (' . $hours . ' Std.).');
                    $supportTokenOnce = $started['token'];
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            if (!isset($supportTokenOnce)) {
                header('Location: /app?page=support-freigabe', true, 302);
                exit;
            }
        }

        if ($page === 'website-menu' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_menu_save'])) {
            $guardWebsitePost();
            try {
                WebsiteSettings::saveMenu($_POST);
                Flash::set('success', 'Menü gespeichert.');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=website-menu', true, 302);
            exit;
        }

        if ($page === 'website-chrome' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_chrome_save'])) {
            $guardWebsitePost();
            try {
                WebsiteSettings::saveChrome($_POST);
                Flash::set('success', 'Kopf und Fuß gespeichert.');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=website-chrome', true, 302);
            exit;
        }

        if ($page === 'website-formular-form' && $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['website_form_save']) || isset($_POST['website_form_delete']))) {
            $guardWebsitePost();
            $editFormId = (int) ($_POST['id'] ?? 0);
            try {
                if (isset($_POST['website_form_delete']) && $editFormId > 0) {
                    WebsiteFormRepository::delete($editFormId);
                    Flash::set('success', 'Formular gelöscht.');
                    header('Location: /app?page=website-formulare', true, 302);
                    exit;
                }
                $newFormId = WebsiteFormRepository::save($_POST, $editFormId > 0 ? $editFormId : null, $user->id);
                Flash::set('success', 'Formular gespeichert.');
                header('Location: /app?page=website-formular-form&action=edit&id=' . $newFormId, true, 302);
                exit;
            } catch (Throwable $e) {
                $contentTemplate = 'modules/website-formular-form';
                $title = $editFormId > 0 ? 'Formular bearbeiten' : 'Neues Formular';
                $currentPage = 'website-formular-form';
                $websiteFormId = $editFormId > 0 ? $editFormId : null;
                $form = [
                    'title' => (string) ($_POST['title'] ?? ''),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'definition' => json_decode((string) ($_POST['definition'] ?? ''), true) ?: WebsiteFormRepository::emptyDefinition(),
                ];
                $formError = $e->getMessage();
            }
        }

        if ($page === 'website-formular-inbox' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_form_submission_delete'])) {
            $guardWebsitePost();
            $inboxFormId = (int) ($_GET['id'] ?? $_POST['form_id'] ?? 0);
            $subId = (int) ($_POST['submission_id'] ?? 0);
            if ($subId > 0) {
                WebsiteFormSubmissionRepository::delete($subId);
                Flash::set('success', 'Einsendung gelöscht.');
            }
            header('Location: /app?page=website-formular-inbox&id=' . $inboxFormId, true, 302);
            exit;
        }

        if ($page === 'website-design' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['website_design_save'])) {
            $guardWebsitePost();
            try {
                WebsiteSettings::saveDesign($_POST);
                Flash::set('success', 'Website-Design gespeichert.');
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=website-design', true, 302);
            exit;
        }

        // POST: Ausgangsbeleg per E-Mail senden
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_email_send'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $voucherId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    VoucherDocumentMailService::send(
                        $voucherId,
                        (string) ($_POST['email_to'] ?? ''),
                        (string) ($_POST['email_subject'] ?? ''),
                        (string) ($_POST['email_intro'] ?? ''),
                        $user,
                        !empty($_POST['email_mark_sent']),
                        !empty($_POST['email_attach_document']),
                    );
                    Flash::set('success', 'Dokument per E-Mail versendet.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
            exit;
        }

        // POST: Teilzahlung erfassen
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_payment_add'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $voucherId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    $amount = round((float) str_replace(',', '.', (string) ($_POST['payment_new_amount'] ?? '0')), 2);
                    VoucherPaymentRepository::addPayment(
                        $voucherId,
                        $amount,
                        (string) ($_POST['payment_new_date'] ?? ''),
                        (string) ($_POST['payment_new_method'] ?? VoucherPaymentRepository::METHOD_BANK),
                        (string) ($_POST['payment_new_reference'] ?? ''),
                        null,
                        null,
                        $user->id,
                    );
                    Flash::set('success', 'Zahlung erfasst.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
            exit;
        }

        // POST: Teilzahlung löschen
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_payment_delete'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $voucherId = (int) ($_POST['id'] ?? 0);
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    VoucherPaymentRepository::deletePayment($paymentId, $voucherId);
                    Flash::set('success', 'Zahlung entfernt.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
            exit;
        }

        // POST: Mahnung senden
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_dunning_send'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $voucherId = (int) ($_POST['id'] ?? 0);
            $level = max(1, (int) ($_POST['dunning_level'] ?? 0));
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    DunningService::sendLevel($voucherId, $level, $user);
                    Flash::set('success', 'Mahnung versendet.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
            exit;
        }

        // POST: Dokumentstatus schnell ändern (Workflow)
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_status_change'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $voucherId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
                exit;
            }
            try {
                $before = VoucherRepository::findById($voucherId);
                $previousStatus = $before !== null
                    ? VoucherDocumentStatus::sanitize((string) ($before['document_status'] ?? ''))
                    : '';
                $kind = $before !== null
                    ? VoucherDocumentKind::sanitize((string) ($before['document_kind'] ?? ''))
                    : '';
                $newStatus = VoucherDocumentStatus::sanitize((string) ($_POST['document_status'] ?? ''));
                VoucherRepository::updateDocumentStatus($voucherId, $newStatus, $user->id, 'workflow');
                $stockWarnings = StockReservationService::takeLastWarnings();
                if (
                    $kind === VoucherDocumentKind::OFFER
                    && $newStatus === VoucherDocumentStatus::ACCEPTED
                    && $previousStatus !== VoucherDocumentStatus::ACCEPTED
                ) {
                    Flash::set(
                        $stockWarnings !== [] ? 'warning' : 'success',
                        'Angebot als angenommen markiert — bitte Auftragsbestätigung prüfen/speichern.'
                            . ($stockWarnings !== [] ? ' ' . implode(' ', $stockWarnings) : '')
                    );
                    header(
                        'Location: ' . VoucherDocumentChain::orderConfirmationFollowUpUrl($voucherId),
                        true,
                        302
                    );
                    exit;
                }
                if ($stockWarnings !== []) {
                    Flash::set('warning', 'Dokumentstatus aktualisiert. ' . implode(' ', $stockWarnings));
                } else {
                    Flash::set('success', 'Dokumentstatus aktualisiert.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
            exit;
        }

        // POST: Überweisung aus Beleg vorbereiten
        if (
            $page === 'buchhaltung-beleg-form'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['voucher_transfer_prepare'])
            && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')
        ) {
            $voucherId = (int) ($_POST['id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
                exit;
            }
            try {
                $existing = BankTransferRepository::findByVoucher($voucherId);
                $transferId = $existing !== null
                    ? (int) $existing['id']
                    : BankTransferRepository::prepareFromVoucher($voucherId, $user->id);
                Flash::set('success', $existing !== null
                    ? 'Überweisung ist bereits vorbereitet.'
                    : 'Überweisung vorbereitet.');
                header('Location: /app?page=buchhaltung-ueberweisungen&open=' . $transferId . '#transfer-' . $transferId, true, 302);
                exit;
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
                exit;
            }
        }

        // POST: Überweisung Status ändern / löschen
        if (
            $page === 'buchhaltung-ueberweisungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-ueberweisungen')
        ) {
            $transferId = (int) ($_POST['transfer_id'] ?? 0);
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } elseif (isset($_POST['transfer_mark_executed'])) {
                try {
                    VoucherSettlementService::settleFromTransfer($transferId);
                    Flash::set('success', 'Überweisung ausgeführt — Beleg als bezahlt verbucht.');
                } catch (Throwable $e) {
                    Flash::set('error', 'Ausführung fehlgeschlagen: ' . $e->getMessage());
                }
            } elseif (isset($_POST['transfer_mark_prepared'])) {
                BankTransferRepository::markPrepared($transferId);
                Flash::set('success', 'Überweisung zurück auf „vorbereitet“ gesetzt.');
            } elseif (isset($_POST['transfer_delete'])) {
                BankTransferRepository::delete($transferId);
                Flash::set('success', 'Überweisung gelöscht.');
            }
            header('Location: /app?page=buchhaltung-ueberweisungen', true, 302);
            exit;
        }

        // POST: Manuelle Buchung
        if (
            $page === 'buchhaltung-manuelle-buchung'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-manuelle-buchung')
        ) {
            $manualYear = max(2000, (int) ($_GET['year'] ?? $_POST['year'] ?? (int) date('Y')));
            $redirect = '/app?page=buchhaltung-manuelle-buchung&year=' . $manualYear;
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } elseif (isset($_POST['manual_journal_delete'])) {
                try {
                    ManualLedgerService::deleteBatch((int) ($_POST['manual_batch_id'] ?? 0));
                    Flash::set('success', 'Manuelle Buchung gelöscht.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            } elseif (isset($_POST['manual_journal_save'])) {
                try {
                    $lines = is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [];
                    ManualLedgerService::createBatch(
                        (string) ($_POST['batch_date'] ?? date('Y-m-d')),
                        (string) ($_POST['batch_description'] ?? ''),
                        $lines,
                        (int) $user->id
                    );
                    Flash::set('success', 'Manuelle Buchung gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Bankabgleich (CAMT / manuelle Zuordnung)
        if (
            $page === 'buchhaltung-bankabgleich'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-bankabgleich')
        ) {
            $redirect = '/app?page=buchhaltung-bankabgleich';
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } elseif (isset($_POST['camt_import'])) {
                try {
                    $file = $_FILES['camt_file'] ?? null;
                    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new InvalidArgumentException('Bitte eine gültige CAMT.053 XML-Datei wählen.');
                    }
                    $xml = (string) file_get_contents((string) ($file['tmp_name'] ?? ''));
                    $res = Camt053Importer::import($xml);
                    $message = sprintf(
                        'CAMT importiert: %d Umsätze (%d übersprungen',
                        $res['imported'],
                        $res['skipped']
                    );
                    if (($res['duplicates'] ?? 0) > 0) {
                        $message .= sprintf(', %d Duplikate ausgeblendet', (int) $res['duplicates']);
                    }
                    $message .= '). Automatischer Abgleich durchgeführt.';
                    Flash::set('success', $message);
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            } elseif (isset($_POST['mt940_import'])) {
                try {
                    $file = $_FILES['mt940_file'] ?? null;
                    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new InvalidArgumentException('Bitte eine gültige MT940-Datei wählen.');
                    }
                    $content = (string) file_get_contents((string) ($file['tmp_name'] ?? ''));
                    $res = Mt940Importer::import($content);
                    $message = sprintf(
                        'MT940 importiert: %d Umsätze (%d übersprungen',
                        $res['imported'],
                        $res['skipped']
                    );
                    if (($res['duplicates'] ?? 0) > 0) {
                        $message .= sprintf(', %d Duplikate ausgeblendet', (int) $res['duplicates']);
                    }
                    $message .= '). Automatischer Abgleich durchgeführt.';
                    Flash::set('success', $message);
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            } elseif (isset($_POST['bank_match_manual'])) {
                try {
                    BankReconciliationService::matchManually(
                        (int) ($_POST['bank_tx_id'] ?? 0),
                        (int) ($_POST['bank_match_voucher_id'] ?? 0)
                    );
                    Flash::set('success', 'Bankumsatz zugeordnet — Beleg als bezahlt verbucht.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            } elseif (isset($_POST['bank_tx_ignore'])) {
                BankTransactionRepository::markIgnored((int) ($_POST['bank_tx_id'] ?? 0));
                Flash::set('success', 'Umsatz ignoriert.');
            } elseif (isset($_POST['bank_ghost_hide'])) {
                // Geisterumsätze: nur manuelle Nutzeraktion — kein Cron, keine Automatik.
                try {
                    BankGhostDetectionService::hideGhost((int) ($_POST['bank_tx_id'] ?? 0));
                    Flash::set('success', 'Geisterumsatz ausgeblendet.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            } elseif (isset($_POST['bank_ghost_hide_all'])) {
                $hidden = BankGhostDetectionService::hideAllGhosts();
                Flash::set('success', $hidden > 0
                    ? sprintf('%d Geisterumsätze ausgeblendet.', $hidden)
                    : 'Keine Geisterumsätze gefunden.');
            } elseif (isset($_POST['bank_ghost_link'])) {
                try {
                    BankGhostDetectionService::linkExistingSettlement((int) ($_POST['bank_tx_id'] ?? 0));
                    Flash::set('success', 'Geisterumsatz mit bestehender Zahlung verknüpft.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Kassenbuch Tagesabschluss
        if (
            $page === 'buchhaltung-kassenbuch'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-kassenbuch')
        ) {
            $redirect = '/app?page=buchhaltung-kassenbuch&year=' . max(2000, (int) ($_POST['year'] ?? date('Y')));
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } elseif (isset($_POST['cash_day_close'])) {
                try {
                    CashDayCloseService::closeDay(
                        trim((string) ($_POST['closing_date'] ?? '')),
                        (float) ($_POST['counted_balance'] ?? 0),
                        trim((string) ($_POST['closing_note'] ?? '')),
                        (int) ($user->id ?? 0) ?: null
                    );
                    Flash::set('success', 'Kassentagesabschluss gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Jahresabschluss (Jahr abschließen / Abschluss zurücknehmen)
        if (
            $page === 'buchhaltung-jahresabschluss'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-jahresabschluss')
        ) {
            $closeYear = max(2000, (int) ($_POST['year'] ?? 0));
            $redirect = '/app?page=buchhaltung-jahresabschluss&year=' . $closeYear;
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } elseif (isset($_POST['fiscal_year_close'])) {
                if (!RoleResolver::isAdmin($user)) {
                    Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                } else {
                try {
                    $res = FiscalYearService::closeYear($closeYear, (int) $user->id);
                    Flash::set('success', sprintf(
                        'Geschäftsjahr %d abgeschlossen. %d Bestandskonten nach %d vorgetragen.',
                        $closeYear,
                        $res['carried'],
                        $res['next_year']
                    ));
                } catch (Throwable $e) {
                    Flash::set('error', 'Abschluss fehlgeschlagen: ' . $e->getMessage());
                }
                }
            } elseif (isset($_POST['fiscal_year_reopen'])) {
                if (!RoleResolver::isAdmin($user)) {
                    Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                } else {
                try {
                    FiscalYearService::reopenYear($closeYear);
                    Flash::set('success', 'Abschluss ' . $closeYear . ' zurückgenommen.');
                } catch (Throwable $e) {
                    Flash::set('error', 'Rücknahme fehlgeschlagen: ' . $e->getMessage());
                }
                }
            } elseif (isset($_POST['fiscal_close_mark_na'])) {
                try {
                    if (!RoleResolver::canEdit($user)) {
                        throw new RuntimeException('Keine Berechtigung.');
                    }
                    FiscalCloseService::markNa(
                        (string) ($_POST['checklist_item'] ?? ''),
                        $closeYear,
                        (string) ($_POST['na_note'] ?? ''),
                        (int) ($user->id ?? 0)
                    );
                    Flash::set('success', 'Checklisten-Punkt als n. a. markiert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            } elseif (isset($_POST['fiscal_close_clear_na'])) {
                try {
                    if (!RoleResolver::canEdit($user)) {
                        throw new RuntimeException('Keine Berechtigung.');
                    }
                    FiscalCloseService::clearNa(
                        (string) ($_POST['checklist_item'] ?? ''),
                        $closeYear
                    );
                    Flash::set('success', 'n. a.-Markierung entfernt.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Verwendungszweck-Formel speichern
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['payment_reference_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('nummernkreise');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular (CSRF).');
            } else {
                PaymentReferenceFormula::save((string) ($_POST['payment_reference_formula'] ?? ''));
                Flash::set('success', 'Verwendungszweck-Formel gespeichert.');
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Zeiterfassung Stempel
        if (
            $page === 'zeiterfassung'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['time_clock_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    $contactId = ContactRepository::findStaffContactIdForUser($user);
                    if ($contactId === null) {
                        throw new InvalidArgumentException('Kein Mitarbeiter-Kontakt verknüpft.');
                    }
                    TimeClockService::recordEvent($contactId, (string) ($_POST['time_clock_action'] ?? ''), $user);
                    Flash::set('success', 'Stempelung erfasst.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=zeiterfassung', true, 302);
            exit;
        }

        // POST: Eigene Kiosk-PIN ändern
        if (
            $page === 'zeiterfassung'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['kiosk_own_pin'])
            && MenuRegistry::canAccess($user, 'zeiterfassung')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::canEdit($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } else {
                try {
                    $contactId = ContactRepository::findStaffContactIdForUser($user);
                    if ($contactId === null) {
                        throw new InvalidArgumentException('Kein Mitarbeiter-Kontakt verknüpft.');
                    }
                    $pin = (string) ($_POST['pin'] ?? '');
                    $pin2 = (string) ($_POST['pin_confirm'] ?? '');
                    if ($pin !== $pin2) {
                        throw new InvalidArgumentException('PINs stimmen nicht überein.');
                    }
                    TimeKioskService::setPin($contactId, $pin, $user);
                    Flash::set('success', 'Stempeluhr-PIN gespeichert.');
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: /app?page=zeiterfassung', true, 302);
            exit;
        }

        // POST: HR Kiosk-PIN / Freigaben
        if (
            $page === 'zeiterfassung-kiosk'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'zeiterfassung-kiosk')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !TimeKioskService::canManagePins($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-kiosk', true, 302);
                exit;
            }
            try {
                if (isset($_POST['kiosk_hr_decide'])) {
                    TimeKioskService::decideHrRequestById(
                        (int) ($_POST['reset_id'] ?? 0),
                        (string) ($_POST['decision'] ?? ''),
                        $user
                    );
                    Flash::set('success', 'Anfrage bearbeitet.');
                } elseif (isset($_POST['kiosk_set_pin'])) {
                    $pin = (string) ($_POST['pin'] ?? '');
                    $pin2 = (string) ($_POST['pin_confirm'] ?? '');
                    if ($pin !== $pin2) {
                        throw new InvalidArgumentException('PINs stimmen nicht überein.');
                    }
                    TimeKioskService::setPin((int) ($_POST['contact_id'] ?? 0), $pin, $user);
                    Flash::set('success', 'PIN für Mitarbeiter gespeichert.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=zeiterfassung-kiosk', true, 302);
            exit;
        }

        // POST: Arbeitsstunden Excel/CSV-Import (Shiftbase/Crewmeister/…)
        if (
            $page === 'zeiterfassung-stundenimport'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['time_hours_import'])
        ) {
            if (!MenuRegistry::canAccess($user, 'zeiterfassung-stundenimport') || !TimeHoursImportService::canImport($user)) {
                Flash::set('error', 'Kein Recht zum Stunden-Import.');
                header('Location: /app?page=zeiterfassung', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                unset($_SESSION['dg_time_hours_import_errors']);
                Flash::set('error', 'Ungültiges Formular (CSRF).');
                header('Location: /app?page=zeiterfassung-stundenimport', true, 302);
                exit;
            }
            // Vor jedem Lauf leeren — sonst bleiben Duplikat-/Skip-Hinweise vom vorherigen Import stehen
            unset($_SESSION['dg_time_hours_import_errors']);
            try {
                $result = TimeHoursImportService::importUpload(
                    is_array($_FILES['time_hours_file'] ?? null) ? $_FILES['time_hours_file'] : [],
                    $user,
                    $_POST
                );
                $msg = $result['message'];
                if ($result['errors'] !== []) {
                    $_SESSION['dg_time_hours_import_errors'] = array_slice($result['errors'], 0, 40);
                    $msg .= ' Details siehe Hinweise unten.';
                    Flash::set('warning', $msg);
                } else {
                    Flash::set('success', $msg);
                }
            } catch (Throwable $e) {
                unset($_SESSION['dg_time_hours_import_errors']);
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=zeiterfassung-stundenimport', true, 302);
            exit;
        }

        // POST: Lohn-Export Überstunden-Auszahlung (Z6e)
        if (
            $page === 'zeiterfassung-lohnexport'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['payroll_ot_payout_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-lohnexport')
        ) {
            $ymPost = TimeMonthReportService::normalizeYearMonth(
                isset($_POST['month']) ? (string) $_POST['month'] : (isset($_GET['month']) ? (string) $_GET['month'] : null)
            );
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-lohnexport&month=' . rawurlencode($ymPost), true, 302);
                exit;
            }
            try {
                $raw = $_POST['auszahlung'] ?? [];
                if (!is_array($raw)) {
                    $raw = [];
                }
                $changed = TimePayrollExportService::saveOtPayoutDrafts($user, $ymPost, $raw);
                $_SESSION['dg_payroll_month'] = $ymPost;
                Flash::set(
                    'success',
                    $changed > 0
                        ? sprintf('Auszahlung gespeichert (%d Einträge). Beim Export wird vom Überstundenkonto abgebucht.', $changed)
                        : 'Keine Änderungen an der Auszahlung.'
                );
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header(
                'Location: /app?page=zeiterfassung-lohnexport&month=' . rawurlencode($ymPost) . '&saved=1',
                true,
                302
            );
            exit;
        }

        // POST: Zeiterfassung Korrektur / Überstunden-Abbau (Z2e)
        if (
            $page === 'zeiterfassung-konto'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['time_konto_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-konto')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-konto', true, 302);
                exit;
            }
            $kontoContactId = (int) ($_POST['contact_id'] ?? 0);
            $actionKonto = (string) ($_POST['time_konto_action'] ?? '');
            try {
                if ($actionKonto === 'correction') {
                    $res = TimeCorrectionService::addWorkedMinutesCorrection(
                        $user,
                        $kontoContactId,
                        (string) ($_POST['work_date'] ?? ''),
                        (int) ($_POST['delta_minutes'] ?? 0),
                        (string) ($_POST['reason'] ?? ''),
                        isset($_FILES['evidence']) && is_array($_FILES['evidence']) ? $_FILES['evidence'] : null
                    );
                    Flash::set('success', $res['message']);
                } elseif ($actionKonto === 'reduce') {
                    $res = TimeCorrectionService::reduceOvertime(
                        $user,
                        $kontoContactId,
                        (int) ($_POST['minutes'] ?? 0),
                        (string) ($_POST['reason'] ?? '')
                    );
                    Flash::set('success', $res['message']);
                } else {
                    Flash::set('error', 'Unbekannte Aktion.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header(
                'Location: /app?page=zeiterfassung-konto&contact_id=' . max(0, $kontoContactId),
                true,
                302
            );
            exit;
        }

        // POST: Schicht-Vorlagen (Z3b)
        if (
            $page === 'zeiterfassung-schicht-vorlagen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['shift_template_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-schicht-vorlagen')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-schicht-vorlagen', true, 302);
                exit;
            }
            $tplAction = (string) ($_POST['shift_template_action'] ?? '');
            $tplId = (int) ($_POST['id'] ?? 0);
            try {
                if ($tplAction === 'save') {
                    $savedId = TimeShiftTemplateRepository::save($_POST, $tplId > 0 ? $tplId : null);
                    Flash::set('success', $tplId > 0 ? 'Vorlage gespeichert.' : 'Vorlage angelegt.');
                    header('Location: /app?page=zeiterfassung-schicht-vorlagen&id=' . $savedId, true, 302);
                    exit;
                }
                if ($tplAction === 'toggle') {
                    TimeShiftTemplateRepository::setActive($tplId, !empty($_POST['active']));
                    Flash::set('success', 'Status aktualisiert.');
                } elseif ($tplAction === 'delete') {
                    TimeShiftTemplateRepository::delete($tplId);
                    Flash::set('success', 'Vorlage gelöscht.');
                } else {
                    Flash::set('error', 'Unbekannte Aktion.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=zeiterfassung-schicht-vorlagen', true, 302);
            exit;
        }

        // POST: Schichtplan Woche (Z3c)
        if (
            $page === 'zeiterfassung-schichten'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['shift_plan_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-schichten')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-schichten', true, 302);
                exit;
            }
            $weekMonday = TimeShiftAssignmentRepository::mondayOfWeek(
                (string) ($_POST['week_monday'] ?? date('Y-m-d'))
            );
            try {
                if ((string) ($_POST['shift_plan_action'] ?? '') === 'save_week') {
                    $grid = is_array($_POST['assignments'] ?? null) ? $_POST['assignments'] : [];
                    $n = TimeShiftAssignmentRepository::saveWeekGrid($grid);
                    Flash::set('success', 'Schichtplan gespeichert (' . $n . ' Zellen).');
                } else {
                    Flash::set('error', 'Unbekannte Aktion.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: /app?page=zeiterfassung-schichten&week=' . rawurlencode($weekMonday), true, 302);
            exit;
        }

        // POST: Urlaub Antrag/Freigabe (Z4c)
        if (
            $page === 'zeiterfassung-urlaub'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['vacation_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-urlaub')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-urlaub', true, 302);
                exit;
            }
            $vacAction = (string) ($_POST['vacation_action'] ?? '');
            $redirectYear = max(2000, min(2100, (int) ($_POST['year'] ?? date('Y'))));
            $redirectEnt = max(0, (int) ($_POST['contact_id'] ?? $_POST['ent_contact_id'] ?? 0));
            try {
                if ($vacAction === 'request') {
                    $ownCid = ContactRepository::findStaffContactIdForUser($user);
                    if ($ownCid === null) {
                        throw new InvalidArgumentException('Kein Mitarbeiter-Kontakt verknüpft.');
                    }
                    $res = TimeVacationService::request(
                        $user,
                        $ownCid,
                        (string) ($_POST['date_from'] ?? ''),
                        (string) ($_POST['date_to'] ?? ''),
                        (string) ($_POST['reason'] ?? ''),
                        !empty($_POST['half_day']),
                        !empty($_POST['acknowledge'])
                    );
                    Flash::set('success', $res['message']);
                } elseif ($vacAction === 'cancel') {
                    TimeVacationService::cancelOwn($user, (int) ($_POST['absence_id'] ?? 0));
                    Flash::set('success', 'Antrag zurückgezogen.');
                } elseif ($vacAction === 'approve') {
                    $res = TimeVacationService::approve($user, (int) ($_POST['absence_id'] ?? 0));
                    Flash::set('success', $res['message']);
                } elseif ($vacAction === 'reject') {
                    TimeVacationService::reject(
                        $user,
                        (int) ($_POST['absence_id'] ?? 0),
                        (string) ($_POST['reason'] ?? '')
                    );
                    Flash::set('success', 'Antrag abgelehnt.');
                } elseif ($vacAction === 'save_entitlement') {
                    TimeVacationService::saveEntitlement(
                        $user,
                        (int) ($_POST['contact_id'] ?? 0),
                        $redirectYear,
                        [
                            'days_entitled' => $_POST['days_entitled'] ?? 0,
                            'days_carried' => $_POST['days_carried'] ?? 0,
                            'note' => $_POST['note'] ?? null,
                        ]
                    );
                    Flash::set('success', 'Urlaubsanspruch gespeichert.');
                } elseif ($vacAction === 'send_planning_reminder') {
                    $onlyCid = isset($_POST['reminder_contact_id']) ? (int) $_POST['reminder_contact_id'] : 0;
                    $res = VacationPlanningReminderService::sendManual(
                        $user,
                        $redirectYear,
                        $onlyCid > 0 ? $onlyCid : null
                    );
                    $msg = sprintf(
                        'Erinnerungen: %d gesendet, %d übersprungen.',
                        (int) ($res['sent'] ?? 0),
                        (int) ($res['skipped'] ?? 0)
                    );
                    if (($res['errors'] ?? []) !== []) {
                        $msg .= ' ' . implode(' ', array_slice($res['errors'], 0, 3));
                    }
                    Flash::set(($res['sent'] ?? 0) > 0 ? 'success' : 'error', $msg);
                } else {
                    Flash::set('error', 'Unbekannte Aktion.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            $loc = '/app?page=zeiterfassung-urlaub&year=' . $redirectYear;
            if ($redirectEnt > 0) {
                $loc .= '&ent_contact_id=' . $redirectEnt;
            }
            header('Location: ' . $loc, true, 302);
            exit;
        }

        // POST: Abwesenheit Krankheit/Team (Z4d)
        if (
            $page === 'zeiterfassung-abwesenheit'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['absence_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-abwesenheit')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: /app?page=zeiterfassung-abwesenheit', true, 302);
                exit;
            }
            $absAction = (string) ($_POST['absence_action'] ?? '');
            $redirectMonth = TimeMonthReportService::normalizeYearMonth(
                isset($_POST['month'])
                    ? (string) $_POST['month']
                    : (isset($_GET['month']) ? (string) $_GET['month'] : null)
            );
            try {
                if ($absAction === 'request_sick') {
                    $res = TimeAbsenceService::requestOwnSick(
                        $user,
                        (string) ($_POST['date_from'] ?? ''),
                        (string) ($_POST['date_to'] ?? ''),
                        (string) ($_POST['reason'] ?? ''),
                        trim((string) ($_POST['document_ref'] ?? '')) !== ''
                            ? (string) $_POST['document_ref']
                            : null,
                        is_array($_FILES['evidence'] ?? null) ? $_FILES['evidence'] : [],
                        !empty($_POST['acknowledge'])
                    );
                    Flash::set('success', $res['message']);
                } elseif ($absAction === 'cancel') {
                    TimeAbsenceService::cancelOwn($user, (int) ($_POST['absence_id'] ?? 0));
                    Flash::set('success', 'Meldung zurückgezogen.');
                } elseif ($absAction === 'record') {
                    $res = TimeAbsenceService::recordApproved(
                        $user,
                        (int) ($_POST['contact_id'] ?? 0),
                        (string) ($_POST['type'] ?? 'sick'),
                        (string) ($_POST['date_from'] ?? ''),
                        (string) ($_POST['date_to'] ?? ''),
                        (string) ($_POST['reason'] ?? ''),
                        trim((string) ($_POST['document_ref'] ?? '')) !== ''
                            ? (string) $_POST['document_ref']
                            : null,
                        !empty($_POST['half_day']),
                        is_array($_FILES['evidence'] ?? null) ? $_FILES['evidence'] : []
                    );
                    Flash::set('success', $res['message']);
                } elseif ($absAction === 'approve') {
                    $res = TimeAbsenceService::approve($user, (int) ($_POST['absence_id'] ?? 0));
                    Flash::set('success', $res['message']);
                } elseif ($absAction === 'reject') {
                    TimeAbsenceService::reject(
                        $user,
                        (int) ($_POST['absence_id'] ?? 0),
                        (string) ($_POST['reason'] ?? '')
                    );
                    Flash::set('success', 'Meldung abgelehnt.');
                } elseif ($absAction === 'hr_update') {
                    $res = TimeAbsenceService::updateByHr(
                        $user,
                        (int) ($_POST['absence_id'] ?? 0),
                        (string) ($_POST['date_from'] ?? ''),
                        (string) ($_POST['date_to'] ?? ''),
                        (string) ($_POST['type'] ?? ''),
                        (string) ($_POST['status'] ?? ''),
                        (string) ($_POST['reason'] ?? '')
                    );
                    Flash::set('success', $res['message']);
                    $hrLoc = '/app?page=zeiterfassung-abwesenheit&month=' . rawurlencode($redirectMonth)
                        . '&hr_contact=' . (int) ($_POST['hr_contact'] ?? 0)
                        . '&hr_type=' . rawurlencode((string) ($_POST['hr_type'] ?? ''))
                        . '&hr_status=' . rawurlencode((string) ($_POST['hr_status'] ?? ''))
                        . '#hr-abwesenheiten';
                    header('Location: ' . $hrLoc, true, 302);
                    exit;
                } else {
                    Flash::set('error', 'Unbekannte Aktion.');
                }
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header(
                'Location: /app?page=zeiterfassung-abwesenheit&month=' . rawurlencode($redirectMonth),
                true,
                302
            );
            exit;
        }

        // POST: Rückstellung buchen (Z5c)
        if (
            $page === 'zeiterfassung-rueckstellung'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['provision_action'])
            && MenuRegistry::canAccess($user, 'zeiterfassung-rueckstellung')
        ) {
            $provYear = max(2000, min(2100, (int) ($_POST['year'] ?? date('Y'))));
            $loc = '/app?page=zeiterfassung-rueckstellung&year=' . $provYear;
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'Ungültiges Formular.');
                header('Location: ' . $loc, true, 302);
                exit;
            }
            try {
                if ((string) ($_POST['provision_action'] ?? '') !== 'book') {
                    throw new InvalidArgumentException('Unbekannte Aktion.');
                }
                if (empty($_POST['confirm_book'])) {
                    throw new InvalidArgumentException('Bitte die Buchung ausdrücklich bestätigen.');
                }
                $res = TimeProvisionService::confirmBooking($user, $provYear);
                Flash::set('success', $res['message']);
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
            }
            header('Location: ' . $loc, true, 302);
            exit;
        }

        // POST: Termin speichern
        if ($page === 'terminkalender' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_save'])) {
            if (!MenuRegistry::canAccess($user, 'terminkalender')) {
                header('Location: /app', true, 302);
                exit;
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular.');
                header('Location: /app?page=terminkalender', true, 302);
                exit;
            }
            $editId = (int) ($_POST['id'] ?? 0);
            try {
                $previousBooking = $editId > 0 ? BookingRepository::findById($editId) : null;
                $newId = BookingRepository::save($_POST, $editId ?: null);
                $savedBooking = BookingRepository::findById($newId);
                if ($savedBooking !== null) {
                    BookingEmailNotifier::afterSave($previousBooking, $savedBooking, $user);
                }
                Flash::set('success', 'Termin gespeichert.');
                header('Location: /app?page=terminkalender', true, 302);
                exit;
            } catch (Throwable $e) {
                $contentTemplate = 'modules/terminkalender-form';
                $title = $editId ? 'Termin bearbeiten' : 'Neuer Termin';
                $currentPage = 'terminkalender';
                $bookingId = $editId ?: null;
                $form = array_merge(BookingRepository::emptyForm(), $_POST);
                $formError = $e->getMessage();
                $dbConfig = DatabaseSettings::forForm();
                $dbConnected = Database::isConfigured();
                try {
                    if ($dbConnected) {
                        Database::pdo()->query('SELECT 1');
                    }
                } catch (Throwable) {
                    $dbConnected = false;
                }
                View::render('layout/app', compact(
                    'title', 'user', 'navMode', 'departments', 'contentTemplate', 'area', 'dept',
                    'menuItems', 'settingsItem', 'currentPage', 'settingsNav', 'settingsSelection',
                    'flash', 'dbConfig', 'dbConnected', 'canEdit', 'sidebarItems', 'bookingId', 'form', 'formError',
                    'bookingArticleOptions', 'bookingEmployeeOptions'
                ));
                break;
            }
        }

        $contentTemplate = 'dashboard';
        $title = 'Dashboard';
        $currentPage = 'dashboard';
        $dbConfig = DatabaseSettings::forForm();
        $dbConnected = false;
        if (Database::isConfigured()) {
            try {
                Database::pdo()->query('SELECT 1');
                $dbConnected = true;
            } catch (Throwable) {
                $dbConnected = false;
            }
        }
        $mailConfig = MailSettings::forForm();
        $backupInfo = BackupService::info();
        $mailReady = MailSettings::isConfigured();
        $mailRecent = MailLogRepository::recent();
        $mailAddressConfig = MailAddressSettings::forForm();
        $postboxes = MailboxRepository::allForAdmin();
        $postboxMemberOptions = MailboxMemberResolver::staffOptions();
        $kasConfigured = KasSettings::isConfigured();
        $postUiConfig = PostUiSettings::config();
        $smtpTestReport = SmtpTestReport::pull();
        $appearanceConfig = AppearanceSettings::forForm();
        $crmThemeConfig = CrmThemeSettings::forForm();
        $departmentsData = DepartmentRepository::allWithMembers();
        $departmentStaffContacts = DepartmentRepository::assignableStaffContacts();
        $departmentEmployees = DepartmentRepository::assignableEmployees();
        $lagerStrukturTab = isset($_GET['lager_tab']) && in_array($_GET['lager_tab'], ['orte', 'hallen', 'regale', 'etiketten', 'einkauf'], true)
            ? (string) $_GET['lager_tab']
            : 'orte';
        $stockPurchaseForm = StockPurchaseSettings::forForm();
        $amazonBusinessForm = AmazonBusinessSettings::forForm();
        $stockLocations = StockStructureRepository::allLocations();
        $stockHalls = StockStructureRepository::allHalls();
        $stockShelves = StockStructureRepository::allShelves();
        $stockLocationOptions = StockStructureRepository::locationOptions();
        if (Database::isConfigured()) {
            try {
                CalendarStaffRepository::ensureSeeded();
                CalendarWorkingHoursRepository::ensureSeeded();
            } catch (Throwable) {
                // Kalender-Tabellen optional bis Migration
            }
        }
        $calendarTeamTab = isset($_GET['ctab']) && $_GET['ctab'] === 'mitarbeiter' ? 'mitarbeiter' : 'bereiche';
        $calendarAreas = CalendarStaffRepository::getAreas();
        $calendarEmployees = CalendarStaffRepository::getEmployees();
        $calendarAbsences = CalendarStaffRepository::getAllAbsences();
        $calendarLinkUsers = CalendarStaffRepository::linkableUsers();
        $companyConfig = CompanySettings::forForm();
        $companyExtended = CompanyExtendedSettings::forForm();
        $taxAdvisorConfig = TaxAdvisorSettings::forForm();
        $taxAdvisorCompanyOptions = ContactCompanyLinkRepository::companyOptions();
        $elsterConfig = ElsterSettings::forForm();
$legalProductsConfig = LegalProductSettings::config();
        $ldapConfig = LdapSettings::forForm();
        $accountingPaymentSettings = AccountingPaymentSettings::forForm();
        $documentPresentationSettings = DocumentPresentationSettings::forForm();
        $letterheadSettings = LetterheadSettings::forForm();
        $letterheadCompany = LetterheadSettings::resolvedCompany($letterheadSettings);
        $letterheadMissingMandatory = LetterheadSettings::missingMandatoryFields($letterheadSettings);
        $letterheadCompliance = LetterheadSettings::complianceReport($letterheadSettings);
        $timeTrackingSettings = TimeTrackingSettings::forForm();
        $payrollEmployerForm = PayrollEmployerSettings::forForm();
        $chartOfAccountsConfig = ChartOfAccountsSettings::forForm();
        if (Database::isConfigured()) {
            try {
                ChartAccountRepository::ensureSeeded($chartOfAccountsConfig['skr_type']);
            } catch (Throwable) {
                // Konten optional bis Migration
            }
        }
        $numberRangeTypes = NumberRangeSettings::documentTypes();
        $numberRangeType = isset($_GET['ntype']) && is_string($_GET['ntype']) && NumberRangeSettings::isValidType($_GET['ntype'])
            ? $_GET['ntype']
            : 'invoice';
        $numberRangeDoc = NumberRangeSettings::document($numberRangeType);
        $numberRangeHistory = NumberRangeHistory::listAll();
        $calendarEmailTemplates = NotificationTemplateSettings::forForm();
        $notificationTemplateData = $calendarEmailTemplates;
        $emailLayout = EmailLayoutSettings::forForm();
        $calendarNotificationDelivery = CalendarNotificationSettings::forForm();
        $calendarWorkingHours = CalendarWorkingHoursRepository::all();
        $calendarAppearanceConfig = CalendarAppearanceSettings::forForm();
        $calendarEmbedConfig = CalendarEmbedSettings::forForm();
        $calendarArticles = CalendarArticleRepository::all();
        $catalogFilter = 'all';
        $calendarDepartmentOptions = DepartmentRepository::optionsForSelect();
        $calendarLinkContacts = CalendarStaffRepository::linkableContacts();
        $calendarDepartmentSuggestions = CalendarStaffRepository::departmentMemberSuggestions();
        $bookingArticleOptions = CalendarArticleRepository::bookingOptions();
        $bookingEmployeeOptions = CalendarStaffRepository::bookingEmployeeOptions();

        if ($area === 'profile') {
            $contentTemplate = 'modules/profile';
            $title = 'Mein Profil';
            $currentPage = 'profile';
        } elseif ($area === 'users') {
            if (!RoleResolver::isAdmin($user)) {
                header('Location: /app', true, 302);
                exit;
            }
            $contentTemplate = 'modules/users';
            $title = 'Benutzer & Rollen';
            $currentPage = 'dashboard';
            $crmUsers = UserRepository::all();
        } elseif ($area === 'departments') {
            if (!RoleResolver::isAdmin($user)) {
                header('Location: /app', true, 302);
                exit;
            }
            header('Location: ' . SettingsRegistry::tabUrl('abteilungen'), true, 302);
            exit;
        } elseif ($page === 'artikel-leistungen' && MenuRegistry::canAccess($user, 'artikel-leistungen')) {
            if (MenuRegistry::isGutscheinEnabled()) {
                GiftVoucherRepository::ensureReady();
            }
            PurchaseListService::rebuildFromStock();
            $rawList = strtolower(trim((string) ($_GET['list'] ?? $_GET['catalog_view'] ?? '')));
            $catalogView = match ($rawList) {
                'purchase', 'einkauf' => 'purchase',
                'ordered', 'nachbestellt' => 'ordered',
                'ignored', 'ignore' => 'ignored',
                default => 'catalog',
            };
            $rawKind = isset($_GET['kind']) ? strtolower(trim((string) $_GET['kind'])) : 'all';
            if ($rawKind === 'product' || $rawKind === 'article') {
                $catalogFilter = CalendarArticleCatalog::KIND_PRODUCT;
            } elseif ($rawKind === 'service' || $rawKind === 'leistung') {
                $catalogFilter = CalendarArticleCatalog::KIND_SERVICE;
            } else {
                $catalogFilter = 'all';
            }
            $calendarArticles = CalendarArticleRepository::all(false, $catalogFilter === 'all' ? null : $catalogFilter);
            $articleIds = array_map(static fn (array $a): int => (int) ($a['id'] ?? 0), $calendarArticles);
            $purchaseSourcesByArticle = ArticlePurchaseSourceRepository::forArticles($articleIds);
            foreach ($calendarArticles as &$articleRow) {
                $aid = (int) ($articleRow['id'] ?? 0);
                $sources = $purchaseSourcesByArticle[$aid] ?? [];
                $articleRow['purchase_sources'] = $sources;
                $preferred = null;
                foreach ($sources as $src) {
                    if (!empty($src['is_preferred'])) {
                        $preferred = $src;
                        break;
                    }
                }
                if ($preferred === null && $sources !== []) {
                    $preferred = $sources[0];
                }
                $articleRow['reorder_url'] = (string) ($preferred['order_url'] ?? '');
                $articleRow['reorder_label'] = (string) ($preferred['label'] ?? '');
                $articleRow['reorder_price'] = $preferred !== null ? (float) ($preferred['purchase_price'] ?? 0) : null;
                $articleRow['has_purchase_source'] = $preferred !== null;
            }
            unset($articleRow);
            $supplierContactOptions = ArticlePurchaseSourceRepository::supplierContactOptions();
            $purchaseListOpen = $catalogView === 'purchase' ? PurchaseListService::listOpen() : [];
            $purchaseListOrdered = $catalogView === 'ordered' ? PurchaseListService::listOrdered() : [];
            $purchaseListIgnored = $catalogView === 'ignored' ? PurchaseListService::listIgnored() : [];
            $purchaseOrderArticleOptions = [];
            if ($catalogView === 'ordered') {
                foreach (CalendarArticleRepository::all(true, CalendarArticleCatalog::KIND_PRODUCT) as $prod) {
                    if (empty($prod['track_stock'])) {
                        continue;
                    }
                    $purchaseOrderArticleOptions[] = [
                        'id' => (int) ($prod['id'] ?? 0),
                        'label' => trim((string) ($prod['article_number'] ?? '')) . ' — ' . trim((string) ($prod['title'] ?? '')),
                    ];
                }
            }
            $openOrderUrl = '';
            $rawOpenOrder = trim((string) ($_GET['open_order_url'] ?? ''));
            if ($rawOpenOrder !== '' && preg_match('#^https?://#i', $rawOpenOrder) && filter_var($rawOpenOrder, FILTER_VALIDATE_URL)) {
                $openOrderUrl = $rawOpenOrder;
            }
            $contentTemplate = 'modules/artikel-leistungen';
            $title = 'Artikel & Leistungen';
            $currentPage = 'artikel-leistungen';
        } elseif ($page === 'artikel-leistungen') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'lager' && MenuRegistry::canAccess($user, 'lager')) {
            $lagerGate = AcademyGateService::gateStatus($user, 'lager');
            if ($lagerGate !== null && ($lagerGate['blocked'] ?? false)) {
                header(
                    'Location: /app?page=akademie&view=kurs&slug=' . rawurlencode((string) ($lagerGate['course_slug'] ?? '')),
                    true,
                    302
                );
                exit;
            }
            PurchaseListService::rebuildFromStock();
            $lagerView = trim((string) ($_GET['view'] ?? 'overview'));
            if (!in_array($lagerView, ['overview', 'bewegungen', 'wareneingang', 'warenausgang', 'platz-check', 'inventur'], true)) {
                $lagerView = 'overview';
            }
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download === 'csv') {
                $rows = StockMovementService::exportOverviewCsvRows();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="lagerbestand-' . date('Y-m-d') . '.csv"');
                $out = fopen('php://output', 'w');
                if ($out !== false) {
                    fprintf($out, "\xEF\xBB\xBF");
                    fputcsv($out, ['Artikelnummer', 'Bezeichnung', 'Positionscode', 'Einheit', 'Bestand', 'Reserviert', 'In Auslieferung', 'Nachbestellt', 'Verfügbar', 'Mindestbestand', 'Unter Mindest'], ';');
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row['article_number'],
                            $row['title'],
                            $row['position_code'],
                            $row['unit'],
                            $row['stock_qty'],
                            $row['reserved_qty'] ?? '0',
                            $row['in_transit_qty'] ?? '0',
                            $row['on_order_qty'] ?? '0',
                            $row['available_qty'] ?? $row['stock_qty'],
                            $row['min_stock'],
                            $row['low_stock'],
                        ], ';');
                    }
                    fclose($out);
                }
                exit;
            }
            if ($download === 'inventory') {
                $invId = (int) ($_GET['id'] ?? 0);
                $rows = StockInventoryService::exportInventoryCsv($invId);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="inventur-' . $invId . '.csv"');
                $out = fopen('php://output', 'w');
                if ($out !== false) {
                    fprintf($out, "\xEF\xBB\xBF");
                    fputcsv($out, ['Artikelnummer', 'Bezeichnung', 'Positionscode', 'Einheit', 'Buchbestand', 'Gezählt', 'Differenz'], ';');
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row['article_number'],
                            $row['title'],
                            $row['position_code'],
                            $row['unit'],
                            $row['book_quantity'],
                            $row['counted_quantity'],
                            $row['diff_quantity'],
                        ], ';');
                    }
                    fclose($out);
                }
                exit;
            }
            if ($download === 'inventur-print') {
                $mode = trim((string) ($_GET['mode'] ?? 'blank'));
                $invId = (int) ($_GET['id'] ?? 0);
                $ort = trim((string) ($_GET['ort'] ?? ''));
                $halle = trim((string) ($_GET['halle'] ?? ''));
                $regal = trim((string) ($_GET['regal'] ?? ''));
                try {
                    StockInventoryPrintService::send(
                        $invId > 0 ? $invId : null,
                        $mode,
                        $ort,
                        $halle,
                        $regal
                    );
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    header('Location: /app?page=lager&view=inventur', true, 302);
                }
                exit;
            }
            $canEdit = RoleResolver::canEdit($user);
            $stockItems = StockMovementService::stockOverview(false);
            $stockReorderMap = ArticlePurchaseSourceRepository::forArticles(
                array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $stockItems)
            );
            foreach ($stockItems as &$stockItem) {
                $sid = (int) ($stockItem['id'] ?? 0);
                $sources = $stockReorderMap[$sid] ?? [];
                $preferred = null;
                foreach ($sources as $src) {
                    if (!empty($src['is_preferred'])) {
                        $preferred = $src;
                        break;
                    }
                }
                if ($preferred === null && $sources !== []) {
                    $preferred = $sources[0];
                }
                $stockItem['reorder_url'] = (string) ($preferred['order_url'] ?? '');
                $stockItem['reorder_label'] = (string) ($preferred['label'] ?? '');
                $stockItem['reorder_price'] = $preferred !== null ? (float) ($preferred['purchase_price'] ?? 0) : null;
                $stockItem['has_purchase_source'] = $preferred !== null;
            }
            unset($stockItem);
            $stockMovements = StockMovementRepository::recent(50);
            $openInventories = StockInventoryService::openInventories();
            $activeInventory = $openInventories[0] ?? null;
            $activeInventoryLines = $activeInventory !== null
                ? StockInventoryService::linesForInventory((int) $activeInventory['id'])
                : [];
            $stockInventories = Database::isConfigured()
                ? (Database::pdo()->query(
                    "SELECT * FROM dg_stock_inventories ORDER BY inventory_date DESC, id DESC LIMIT 20"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [])
                : [];
            $stockOutboundVouchers = in_array($lagerView, ['warenausgang'], true)
                ? StockReceiptIssueService::outboundVoucherOptions()
                : [];
            if ($lagerView === 'platz-check') {
                $stockPlaces = StockStructureRepository::allPlaces();
            }
            $contentTemplate = 'modules/lager';
            $title = 'Lager';
            $currentPage = 'lager';
        } elseif ($page === 'lager') {
            header('Location: /app', true, 302);
            exit;
        } elseif (
            (
                $page === 'rezeptur'
                || $page === 'rezeptur-form'
                || $page === 'rezeptur-maschinen'
                || $page === 'rezeptur-maschine-form'
            )
            && MenuRegistry::canAccess($user, $page)
        ) {
            RecipeRepository::ensureReady();
            WorkCenterRepository::ensureReady();
            if ($page === 'rezeptur-form') {
                if (empty($formError)) {
                    $recipeId = (int) ($_GET['id'] ?? 0);
                    $action = (string) ($_GET['action'] ?? ($recipeId > 0 ? 'edit' : 'new'));
                    if ($action === 'edit' && $recipeId > 0) {
                        if (RecipeRepository::find($recipeId) === null) {
                            Flash::set('error', 'Rezept nicht gefunden.');
                            header('Location: /app?page=rezeptur', true, 302);
                            exit;
                        }
                        $recipeForm = RecipeRepository::formForId($recipeId);
                    } else {
                        $recipeId = null;
                        $recipeForm = RecipeRepository::emptyForm();
                    }
                }
                $recipeArticleOptions = [];
                $recipeWorkCenterOptions = [];
                if ($dbConnected) {
                    foreach (CalendarArticleRepository::all(true) as $artRow) {
                        if (!is_array($artRow)) {
                            continue;
                        }
                        $aid = (int) ($artRow['id'] ?? 0);
                        if ($aid <= 0) {
                            continue;
                        }
                        $recipeArticleOptions[] = [
                            'id' => $aid,
                            'title' => (string) ($artRow['title'] ?? ('#' . $aid)),
                        ];
                    }
                    foreach (WorkCenterRepository::listAll(true) as $wcRow) {
                        if (!is_array($wcRow)) {
                            continue;
                        }
                        $wid = (int) ($wcRow['id'] ?? 0);
                        if ($wid <= 0) {
                            continue;
                        }
                        $recipeWorkCenterOptions[] = [
                            'id' => $wid,
                            'name' => (string) ($wcRow['name'] ?? ('#' . $wid)),
                        ];
                    }
                }
                if (!isset($recipeForm) || !is_array($recipeForm)) {
                    $recipeForm = RecipeRepository::emptyForm();
                }
                $recipeCalc = null;
                $recipeSnapshots = [];
                $recipeActuals = [];
                $recipeAuxiliaryMaterials = [];
                if (($recipeId ?? 0) > 0) {
                    try {
                        $recipeAuxiliaryMaterials = MachinePlanningRepository::listAuxiliaryMaterials((int) $recipeId, false);
                    } catch (Throwable) {
                        $recipeAuxiliaryMaterials = [];
                    }
                    try {
                        $recipeForm['id'] = (int) $recipeId;
                        $recipeCalc = RecipeCostService::calculate(
                            $recipeForm,
                            $recipeForm['bom'] ?? [],
                            $recipeForm['routing'] ?? [],
                            null,
                            array_values(array_filter($recipeAuxiliaryMaterials, static fn (array $row): bool => !empty($row['is_active'])))
                        );
                    } catch (Throwable) {
                        $recipeCalc = null;
                    }
                    try {
                        $recipeSnapshots = RecipeSnapshotRepository::listForRecipe((int) $recipeId, 15);
                        $recipeActuals = RecipeActualRepository::mapForRecipe((int) $recipeId);
                    } catch (Throwable) {
                        $recipeSnapshots = [];
                        $recipeActuals = [];
                    }
                }
                $contentTemplate = 'modules/rezeptur-form';
                $title = (($recipeId ?? 0) > 0) ? 'Rezept bearbeiten' : 'Neues Rezept';
                $currentPage = 'rezeptur';
            } elseif ($page === 'rezeptur-maschinen') {
                $workCenterList = WorkCenterRepository::listAll(false);
                $machineCapacityProfiles = MachinePlanningRepository::listCapacityProfiles(null, false);
                $machineMaintenancePlans = MachinePlanningRepository::listMaintenancePlans(null, false);
                $machineMaintenanceLogs = MachinePlanningRepository::listMaintenanceLogs();
                $machineBookingFilterWorkCenterId = max(0, (int) ($_GET['booking_work_center_id'] ?? 0));
                $machineBookingFilterFrom = trim((string) ($_GET['booking_from'] ?? ''));
                $machineBookingFilterTo = trim((string) ($_GET['booking_to'] ?? ''));
                $machineBookingStartsBefore = $machineBookingFilterTo !== '' ? $machineBookingFilterTo . ' 23:59:59' : null;
                $machineBookingEndsAfter = $machineBookingFilterFrom !== '' ? $machineBookingFilterFrom . ' 00:00:00' : null;
                $machineBookingWindows = MachinePlanningRepository::listBookingWindows(
                    $machineBookingFilterWorkCenterId > 0 ? $machineBookingFilterWorkCenterId : null,
                    $machineBookingStartsBefore,
                    $machineBookingEndsAfter,
                    false
                );
                $machineBookingCalMode = trim((string) ($_GET['booking_cal_mode'] ?? 'month'));
                if (!in_array($machineBookingCalMode, ['month', 'week'], true)) {
                    $machineBookingCalMode = 'month';
                }
                $machineBookingCalYm = trim((string) ($_GET['booking_cal_ym'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}$/', $machineBookingCalYm)) {
                    $machineBookingCalYm = $machineBookingFilterFrom !== ''
                        ? substr($machineBookingFilterFrom, 0, 7)
                        : date('Y-m');
                }
                $machineBookingCalWeek = trim((string) ($_GET['booking_cal_week'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $machineBookingCalWeek)) {
                    $machineBookingCalWeek = $machineBookingFilterFrom !== ''
                        ? $machineBookingFilterFrom
                        : (($machineBookingCalYm !== '' ? $machineBookingCalYm . '-01' : date('Y-m-d')));
                }
                if ($machineBookingCalMode === 'week') {
                    $calWeekTs = strtotime($machineBookingCalWeek . ' 12:00:00') ?: time();
                    $calDow = (int) date('N', $calWeekTs);
                    $calWeekStartTs = strtotime('-' . ($calDow - 1) . ' days', strtotime(date('Y-m-d', $calWeekTs) . ' 12:00:00'));
                    $calRangeStart = date('Y-m-d 00:00:00', $calWeekStartTs);
                    $calRangeEnd = date('Y-m-d 23:59:59', strtotime('+6 days', $calWeekStartTs));
                    $machineBookingCalYm = date('Y-m', $calWeekStartTs);
                } else {
                    $calMonthTs = strtotime($machineBookingCalYm . '-01 12:00:00') ?: time();
                    $machineBookingCalYm = date('Y-m', $calMonthTs);
                    $calRangeStart = date('Y-m-01 00:00:00', $calMonthTs);
                    $calRangeEnd = date('Y-m-t 23:59:59', $calMonthTs);
                }
                $machineBookingCalendarWindows = MachinePlanningRepository::listBookingWindows(
                    $machineBookingFilterWorkCenterId > 0 ? $machineBookingFilterWorkCenterId : null,
                    $calRangeEnd,
                    $calRangeStart,
                    false
                );
                $machineBookingCalendar = MachinePlanningRepository::buildBookingCalendarGrid(
                    $machineBookingCalendarWindows,
                    $machineBookingCalMode,
                    $machineBookingCalYm,
                    $machineBookingCalWeek
                );
                $machineBookingEditId = max(0, (int) ($_GET['booking_edit_id'] ?? 0));
                $machineBookingEdit = $machineBookingEditId > 0
                    ? MachinePlanningRepository::findBookingWindow($machineBookingEditId)
                    : null;
                if ($machineBookingEditId > 0 && $machineBookingEdit === null) {
                    Flash::set('error', 'Belegungsfenster zum Bearbeiten nicht gefunden.');
                    header('Location: /app?page=rezeptur-maschinen#booking', true, 302);
                    exit;
                }
                if (
                    is_array($machineBookingEdit)
                    && (string) ($machineBookingEdit['booking_status'] ?? '') === 'cancelled'
                ) {
                    Flash::set('error', 'Stornierte Belegungsfenster können nicht bearbeitet werden.');
                    header('Location: /app?page=rezeptur-maschinen#booking', true, 302);
                    exit;
                }
                $machineRecipeOptions = RecipeRepository::listAll();
                if (($_GET['booking_export'] ?? '') === 'csv') {
                    $exportRates = RecipeCostSettings::get();
                    $workCenterNames = [];
                    $workCenterHourlyCosts = [];
                    foreach ($workCenterList as $center) {
                        $centerId = (int) ($center['id'] ?? 0);
                        $workCenterNames[$centerId] = (string) ($center['name'] ?? '');
                        $workCenterHourlyCosts[$centerId] = RecipeCostService::breakdown($center, $exportRates);
                    }
                    $maintenancePlanNames = [];
                    $maintenancePlanEstimatedCosts = [];
                    foreach ($machineMaintenancePlans as $plan) {
                        $planId = (int) ($plan['id'] ?? 0);
                        $maintenancePlanNames[$planId] = (string) ($plan['title'] ?? '');
                        $maintenancePlanEstimatedCosts[$planId] = (float) ($plan['estimated_material_cost'] ?? 0) + (float) ($plan['estimated_service_cost'] ?? 0);
                    }
                    $recipeNames = [];
                    foreach ($machineRecipeOptions as $recipe) {
                        $recipeNames[(int) ($recipe['id'] ?? 0)] = (string) ($recipe['title'] ?? '');
                    }
                    $typeLabels = [
                        'production' => 'Produktion',
                        'maintenance' => 'Wartung',
                        'blocked' => 'Sperrzeit',
                        'other' => 'Sonstiges',
                    ];
                    $statusLabels = [
                        'planned' => 'geplant',
                        'done' => 'erledigt',
                        'cancelled' => 'storniert',
                    ];
                    $csvCell = static function (mixed $value): string {
                        $text = (string) $value;
                        $trimmed = ltrim($text);
                        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
                            return "'" . $text;
                        }

                        return $text;
                    };
                    $exportSummary = [
                        'total_hours' => 0.0,
                        'production_hours' => 0.0,
                        'maintenance_hours' => 0.0,
                        'blocked_hours' => 0.0,
                        'other_hours' => 0.0,
                        'estimated_maintenance_cost' => 0.0,
                        'machine_cost' => 0.0,
                        'loaded_cost' => 0.0,
                    ];
                    foreach ($machineBookingWindows as $booking) {
                        if (($booking['booking_status'] ?? '') === 'cancelled') {
                            continue;
                        }
                        $bookingStart = strtotime((string) ($booking['starts_at'] ?? ''));
                        $bookingEnd = strtotime((string) ($booking['ends_at'] ?? ''));
                        if ($bookingStart === false || $bookingEnd === false || $bookingEnd <= $bookingStart) {
                            continue;
                        }
                        $hours = ($bookingEnd - $bookingStart) / 3600;
                        $exportSummary['total_hours'] += $hours;
                        $hourlyCosts = $workCenterHourlyCosts[(int) ($booking['work_center_id'] ?? 0)] ?? null;
                        if (is_array($hourlyCosts)) {
                            $exportSummary['machine_cost'] += $hours * (float) ($hourlyCosts['machine_per_h'] ?? 0);
                            $exportSummary['loaded_cost'] += $hours * (float) ($hourlyCosts['loaded_per_h'] ?? 0);
                        }
                        $type = (string) ($booking['booking_type'] ?? 'other');
                        if ($type === 'production') {
                            $exportSummary['production_hours'] += $hours;
                        } elseif ($type === 'maintenance') {
                            $exportSummary['maintenance_hours'] += $hours;
                        } elseif ($type === 'blocked') {
                            $exportSummary['blocked_hours'] += $hours;
                        } else {
                            $exportSummary['other_hours'] += $hours;
                        }
                        $planId = (int) ($booking['maintenance_plan_id'] ?? 0);
                        if ($planId > 0) {
                            $exportSummary['estimated_maintenance_cost'] += $maintenancePlanEstimatedCosts[$planId] ?? 0.0;
                        }
                    }
                    $exportMachineCount = $machineBookingFilterWorkCenterId > 0 ? 1 : max(0, count($workCenterList));
                    $exportAvailableHours = MachinePlanningRepository::bookingAvailableHours(
                        $machineBookingWindows,
                        $machineBookingFilterFrom,
                        $machineBookingFilterTo,
                        $exportMachineCount
                    );
                    $exportUtilizationPct = MachinePlanningRepository::bookingUtilizationPercent(
                        (float) $exportSummary['total_hours'],
                        $exportAvailableHours
                    );
                    $exportProductionUtilizationPct = MachinePlanningRepository::bookingUtilizationPercent(
                        (float) $exportSummary['production_hours'],
                        $exportAvailableHours
                    );
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="maschinen-belegung.csv"');
                    $out = fopen('php://output', 'wb');
                    if ($out === false) {
                        exit;
                    }
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, ['Auswertung', 'Maschinenbelegung'], ';');
                    fputcsv($out, ['Filter Maschine', $csvCell($machineBookingFilterWorkCenterId > 0 ? ($workCenterNames[$machineBookingFilterWorkCenterId] ?? ('#' . $machineBookingFilterWorkCenterId)) : 'Alle')], ';');
                    fputcsv($out, ['Filter von', $machineBookingFilterFrom], ';');
                    fputcsv($out, ['Filter bis', $machineBookingFilterTo], ';');
                    fputcsv($out, ['Maschinenanzahl Auslastung', (string) $exportMachineCount], ';');
                    fputcsv($out, ['Gesamtstunden', number_format($exportSummary['total_hours'], 2, ',', '.')], ';');
                    fputcsv($out, ['Produktionsstunden', number_format($exportSummary['production_hours'], 2, ',', '.')], ';');
                    fputcsv($out, ['Wartungsstunden', number_format($exportSummary['maintenance_hours'], 2, ',', '.')], ';');
                    fputcsv($out, ['Sperrzeitstunden', number_format($exportSummary['blocked_hours'], 2, ',', '.')], ';');
                    fputcsv($out, ['Sonstige Stunden', number_format($exportSummary['other_hours'], 2, ',', '.')], ';');
                    fputcsv($out, ['Verfügbare Stunden', $exportAvailableHours !== null ? number_format($exportAvailableHours, 2, ',', '.') : ''], ';');
                    fputcsv($out, ['Auslastung belegt %', $exportUtilizationPct !== null ? number_format($exportUtilizationPct, 1, ',', '.') : ''], ';');
                    fputcsv($out, ['Auslastung Produktion %', $exportProductionUtilizationPct !== null ? number_format($exportProductionUtilizationPct, 1, ',', '.') : ''], ';');
                    fputcsv($out, ['Wartungskosten geschätzt', number_format($exportSummary['estimated_maintenance_cost'], 2, ',', '.')], ';');
                    fputcsv($out, ['Maschinenkosten Vorschlag', number_format($exportSummary['machine_cost'], 2, ',', '.')], ';');
                    fputcsv($out, ['inkl. Lohn Vorschlag', number_format($exportSummary['loaded_cost'], 2, ',', '.')], ';');
                    fputcsv($out, [], ';');
                    fputcsv($out, ['Maschine', 'Typ', 'Titel', 'Start', 'Ende', 'Rezeptur', 'Wartungsplan', 'Output', 'Einheit', 'Status', 'Notiz'], ';');
                    foreach ($machineBookingWindows as $booking) {
                        $recipeId = (int) ($booking['recipe_id'] ?? 0);
                        $planId = (int) ($booking['maintenance_plan_id'] ?? 0);
                        $type = (string) ($booking['booking_type'] ?? 'other');
                        $status = (string) ($booking['booking_status'] ?? 'planned');
                        fputcsv($out, [
                            $csvCell($workCenterNames[(int) ($booking['work_center_id'] ?? 0)] ?? ('#' . (int) ($booking['work_center_id'] ?? 0))),
                            $csvCell($typeLabels[$type] ?? $type),
                            $csvCell($booking['title'] ?? ''),
                            (string) ($booking['starts_at'] ?? ''),
                            (string) ($booking['ends_at'] ?? ''),
                            $csvCell($recipeId > 0 ? ($recipeNames[$recipeId] ?? ('#' . $recipeId)) : ''),
                            $csvCell($planId > 0 ? ($maintenancePlanNames[$planId] ?? ('#' . $planId)) : ''),
                            (string) ($booking['planned_output_qty'] ?? '0'),
                            $csvCell($booking['unit'] ?? ''),
                            $csvCell($statusLabels[$status] ?? $status),
                            $csvCell($booking['notes'] ?? ''),
                        ], ';');
                    }
                    exit;
                }
                $recipeCostRates = RecipeCostSettings::get();
                $recipeCostRatesForm = RecipeCostSettings::forForm();
                $contentTemplate = 'modules/rezeptur-maschinen';
                $title = 'Maschinen & Arbeitsplätze';
                $currentPage = 'rezeptur';
            } elseif ($page === 'rezeptur-maschine-form') {
                if (empty($formError)) {
                    $workCenterId = (int) ($_GET['id'] ?? 0);
                    $action = (string) ($_GET['action'] ?? ($workCenterId > 0 ? 'edit' : 'new'));
                    if ($action === 'edit' && $workCenterId > 0) {
                        if (WorkCenterRepository::find($workCenterId) === null) {
                            Flash::set('error', 'Maschine / Arbeitsplatz nicht gefunden.');
                            header('Location: /app?page=rezeptur-maschinen', true, 302);
                            exit;
                        }
                        $workCenterForm = WorkCenterRepository::formForId($workCenterId);
                    } else {
                        $workCenterId = null;
                        $workCenterForm = WorkCenterRepository::emptyForm();
                    }
                }
                if (!isset($workCenterForm) || !is_array($workCenterForm)) {
                    $workCenterForm = WorkCenterRepository::emptyForm();
                }
                $recipeCostRates = RecipeCostSettings::get();
                $contentTemplate = 'modules/rezeptur-maschine-form';
                $title = (($workCenterId ?? 0) > 0) ? 'Maschine bearbeiten' : 'Maschine hinzufügen';
                $currentPage = 'rezeptur';
            } else {
                $recipeList = RecipeRepository::listAll();
                $contentTemplate = 'modules/rezeptur';
                $title = 'Rezeptur';
                $currentPage = 'rezeptur';
            }
        } elseif (
            $page === 'rezeptur'
            || $page === 'rezeptur-form'
            || $page === 'rezeptur-maschinen'
            || $page === 'rezeptur-maschine-form'
        ) {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'akademie' && MenuRegistry::canAccess($user, 'akademie')) {
            $academyDownload = trim((string) ($_GET['download'] ?? ''));
            if ($academyDownload === 'video' || $academyDownload === 'vtt') {
                $moduleId = (int) ($_GET['module_id'] ?? 0);
                $module = AcademyRepository::findModule($moduleId);
                if ($module === null) {
                    http_response_code(404);
                    exit;
                }
                $rel = $academyDownload === 'vtt'
                    ? (string) ($module['subtitle_vtt_path'] ?? '')
                    : (string) ($module['video_path'] ?? '');
                $rel = ltrim(str_replace(['..', '\\'], '', $rel), '/');
                if ($rel === '' || !str_starts_with($rel, 'media/training/')) {
                    http_response_code(404);
                    exit;
                }
                $path = DG_ROOT . '/storage/' . $rel;
                if (!is_file($path)) {
                    http_response_code(404);
                    exit;
                }
                header('Content-Type: ' . ($academyDownload === 'vtt' ? 'text/vtt; charset=utf-8' : 'video/mp4'));
                header('Content-Length: ' . (string) filesize($path));
                readfile($path);
                exit;
            }
            $academyView = trim((string) ($_GET['view'] ?? 'meine'));
            if (!in_array($academyView, ['meine', 'katalog', 'kurs', 'modul', 'admin', 'hr', 'video-vorschau'], true)) {
                $academyView = 'meine';
            }
            if ($academyView === 'admin' || $academyView === 'hr' || $academyView === 'video-vorschau') {
                if (!RoleResolver::isAdmin($user)) {
                    header('Location: /app?page=akademie&view=meine', true, 302);
                    exit;
                }
            }
            $canManageAcademy = RoleResolver::isAdmin($user);
            $canAcademyHr = RoleResolver::isAdmin($user);
            $academyTierPlan = AcademyTier::currentPlan();
            $academyAreas = AcademyRepository::allDepartments();
            $academyDepartments = $academyAreas;
            $academyAssignments = AcademyRepository::assignmentsForUser((int) $user->id);
            $academyCatalog = array_values(array_filter(
                AcademyRepository::publishedCoursesWithPlayableModules(),
                static fn (array $c): bool => AcademyTier::allows((string) ($c['min_tier'] ?? AcademyTier::STARTER))
            ));
            $academyPendingHr = $canAcademyHr ? AcademyRepository::pendingHrReviews() : [];
            $academyCourse = null;
            $academyModule = null;
            $academyAssignment = null;
            $academySummary = null;
            $academyRulesAccepted = false;
            $academyCourseSlug = trim((string) ($_GET['slug'] ?? ''));
            if ($academyCourseSlug !== '') {
                $academyCourse = AcademyRepository::findCourseBySlug($academyCourseSlug);
            }
            if ($academyCourse !== null) {
                $academyAssignment = AcademyRepository::findAssignment((int) $user->id, (int) $academyCourse['id']);
                if ($academyAssignment === null && $academyView !== 'katalog') {
                    $academyAssignment = AcademyRepository::ensureAssignment((int) $user->id, (int) $academyCourse['id'], (int) $user->id);
                }
                $academyRulesAccepted = AcademyRepository::hasAcceptedRules(
                    (int) $user->id,
                    (int) $academyCourse['id'],
                    (string) ($academyCourse['version'] ?? '1.0')
                );
                if ($academyAssignment !== null) {
                    $academySummary = AcademyProgressService::assignmentSummary((int) $academyAssignment['id']);
                }
            }
            $academyModuleId = (int) ($_GET['module_id'] ?? 0);
            if ($academyModuleId > 0) {
                $academyModule = AcademyRepository::findModule($academyModuleId);
            }
            if ($academyView === 'modul') {
                $kursRedirect = $academyCourseSlug !== ''
                    ? '/app?page=akademie&view=kurs&slug=' . rawurlencode($academyCourseSlug)
                    : '/app?page=akademie&view=katalog';
                if ($academyCourse === null || $academyModule === null) {
                    Flash::set('error', 'Video oder Kurs nicht gefunden.');
                    header('Location: ' . $kursRedirect, true, 302);
                    exit;
                }
                $courseModuleIds = AcademyRepository::moduleIdsForCourse((int) $academyCourse['id']);
                if (!in_array($academyModuleId, $courseModuleIds, true)) {
                    Flash::set('error', 'Dieses Video gehört nicht zu diesem Kurs.');
                    header('Location: ' . $kursRedirect, true, 302);
                    exit;
                }
                if (!$academyRulesAccepted) {
                    Flash::set(
                        'info',
                        'Bitte zuerst die Schulungsregeln bestätigen — danach öffnet sich das Video.'
                    );
                    header(
                        'Location: ' . $kursRedirect . '&open_module=' . $academyModuleId,
                        true,
                        302
                    );
                    exit;
                }
                if ($academyAssignment === null) {
                    Flash::set('error', 'Kurszuweisung fehlt.');
                    header('Location: ' . $kursRedirect, true, 302);
                    exit;
                }
            }
            if ($academyView === 'video-vorschau') {
                if ($academyModule === null) {
                    Flash::set('error', 'Video nicht gefunden.');
                    header('Location: /app?page=akademie&view=admin&admin_tab=videos', true, 302);
                    exit;
                }
            }
            $academyAdminTab = trim((string) ($_GET['admin_tab'] ?? 'kurse'));
            if (!in_array($academyAdminTab, ['kurse', 'videos', 'kurs'], true)) {
                $academyAdminTab = 'kurse';
            }
            $academyAdminDepartmentId = trim((string) ($_GET['department_id'] ?? ''));
            $academyAdminCourseId = (int) ($_GET['course_id'] ?? 0);
            $academyAdminCourse = null;
            if (array_key_exists('course_id', $_GET) && $academyAdminTab !== 'videos') {
                $academyAdminTab = 'kurs';
                if ($academyAdminCourseId > 0) {
                    $academyAdminCourse = AcademyRepository::findCourseById($academyAdminCourseId);
                } else {
                    $defaultDept = $academyAdminDepartmentId !== ''
                        ? $academyAdminDepartmentId
                        : (string) (($academyDepartments[0]['id'] ?? '') ?: '');
                    $academyAdminCourse = [
                        'id' => 0,
                        'department_id' => $defaultDept,
                        'title' => '',
                        'slug' => '',
                        'description' => '',
                        'version' => '1.0',
                        'min_tier' => AcademyTier::STARTER,
                        'access_mode_default' => AcademyAccessMode::COMPARE,
                        'certificate_enabled' => 0,
                        'is_published' => 0,
                    ];
                }
            }
            $academyAdminVideoId = (int) ($_GET['video_id'] ?? 0);
            $academyAdminVideo = $academyAdminVideoId > 0 ? AcademyRepository::findModule($academyAdminVideoId) : null;
            $academyUserOptions = $canManageAcademy ? AcademyRepository::userOptions() : [];
            $academyCoursesByDepartment = $canManageAcademy ? AcademyRepository::coursesGroupedByDepartment() : [];
            $academyAllCourses = $canManageAcademy ? AcademyRepository::allCourses() : [];
            $academyCourseModuleIds = $academyAdminCourse !== null && (int) ($academyAdminCourse['id'] ?? 0) > 0
                ? AcademyRepository::moduleIdsForCourse((int) $academyAdminCourse['id'])
                : [];
            $academyCourseDepartmentIds = $academyAdminCourse !== null && (int) ($academyAdminCourse['id'] ?? 0) > 0
                ? AcademyRepository::courseDepartmentIds((int) $academyAdminCourse['id'])
                : [];
            $academyModuleDepartmentIds = $academyAdminVideo !== null
                ? AcademyRepository::moduleDepartmentIds((int) ($academyAdminVideo['id'] ?? 0))
                : [];
            $academyLibraryVideos = $canManageAcademy ? AcademyRepository::libraryVideos(true, true) : [];
            $academyAllVideos = ($canManageAcademy && $academyAdminTab === 'videos')
                ? AcademyRepository::allVideos()
                : [];
            $contentTemplate = 'modules/akademie';
            $title = 'Akademie';
            $currentPage = 'akademie';
        } elseif ($page === 'akademie') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'hr-abteilungen' && MenuRegistry::canAccess($user, 'hr-abteilungen')) {
            MigrationRunner::runPending();
            $hrDepartments = HrDepartmentBoard::build();
            $canManageTeams = TeamAccess::canManageAny($user);
            $contentTemplate = 'modules/hr-abteilungen';
            $title = 'Abteilungen';
            $currentPage = 'hr-abteilungen';
        } elseif ($page === 'hr-abteilungen') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'hr-teams' && MenuRegistry::canAccess($user, 'hr-teams')) {
            MigrationRunner::runPending();
            $hrTeams = TeamRepository::list();
            $canManageTeams = TeamAccess::canManageAny($user);
            $contentTemplate = 'modules/hr-teams';
            $title = 'Teams';
            $currentPage = 'hr-teams';
        } elseif ($page === 'hr-teams') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'hr-team-form' && MenuRegistry::canAccess($user, 'hr-team-form')) {
            MigrationRunner::runPending();
            $action = trim((string) ($_GET['action'] ?? 'new'));
            $teamId = (int) ($_GET['id'] ?? 0);
            $departmentOptions = DepartmentRepository::optionsForSelect();
            if (!RoleResolver::isAdmin($user) && !DepartmentAccess::userInHrDepartment($user)) {
                $departmentOptions = array_values(array_filter(
                    $departmentOptions,
                    static fn (array $d): bool => TeamAccess::canManageDepartment($user, (string) ($d['id'] ?? ''))
                ));
            }
            $employeeContactOptions = HrDepartmentBoard::employeeContactOptions();
            $formError = $formError ?? null;
            if ($action === 'edit' && $teamId > 0) {
                $team = TeamRepository::findById($teamId);
                if ($team === null) {
                    Flash::set('error', 'Team nicht gefunden.');
                    header('Location: /app?page=hr-teams', true, 302);
                    exit;
                }
                if (!isset($form)) {
                    $form = TeamRepository::toForm($team);
                }
                $canEditTeam = TeamAccess::canManageTeam($user, $team);
                $readOnly = !$canEditTeam;
                $title = $readOnly ? 'Team anzeigen' : 'Team bearbeiten';
            } else {
                if (!isset($form)) {
                    $form = TeamRepository::emptyForm();
                    $prefillDept = trim((string) ($_GET['department_id'] ?? ''));
                    if ($prefillDept !== '') {
                        $form['department_id'] = $prefillDept;
                    }
                }
                $teamId = null;
                $canEditTeam = TeamAccess::canManageAny($user);
                $readOnly = !$canEditTeam;
                $title = 'Neues Team';
                if (!$canEditTeam) {
                    Flash::set('error', 'Keine Berechtigung, Teams anzulegen.');
                    header('Location: /app?page=hr-teams', true, 302);
                    exit;
                }
            }
            $contentTemplate = 'modules/hr-team-form';
            $currentPage = 'hr-teams';
        } elseif ($page === 'hr-team-form') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-konten' && MenuRegistry::canAccess($user, 'buchhaltung-konten')) {
            $chartAccountCount = 0;
            $chartCatalogCount = ChartAccountCatalog::catalogCount(ChartOfAccountsSettings::activeSkrType());
            $chartHintCount = ChartAccountRepository::countWithDetailedHints(ChartOfAccountsSettings::activeSkrType());
            if (Database::isConfigured()) {
                try {
                    ChartAccountRepository::ensureSeeded(ChartOfAccountsSettings::activeSkrType());
                    $chartAccountCount = ChartAccountRepository::countForSkr();
                } catch (Throwable) {
                    // Konten optional bis Migration
                }
            }
            $chartOfAccountsConfig = ChartOfAccountsSettings::forForm();
            $contentTemplate = 'modules/buchhaltung-konten';
            $title = 'Konten';
            $currentPage = 'buchhaltung-konten';
        } elseif ($page === 'buchhaltung-konten') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-belege' && MenuRegistry::canAccess($user, 'buchhaltung-belege')) {
            $voucherSearch = trim((string) ($_GET['s'] ?? ''));
            $voucherPage = max(1, (int) ($_GET['paged'] ?? 1));
            $voucherPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $voucherYear = $voucherPeriod->year;

            // Legacy-Deep-Links: draft=1 / doc_kind / doc_status → Board-Parameter
            $sectionParam = trim((string) ($_GET['section'] ?? ''));
            $statusParam = trim((string) ($_GET['status'] ?? ''));
            $belegeFocus = VoucherBelegeBoard::sanitizeFocus((string) ($_GET['focus'] ?? ''));
            $legacyDraft = (string) ($_GET['draft'] ?? '');
            $legacyDocKind = VoucherDocumentKind::sanitize((string) ($_GET['doc_kind'] ?? ''));
            $legacyDocStatus = VoucherDocumentStatus::sanitize((string) ($_GET['doc_status'] ?? ''));
            if ($sectionParam === '' && $legacyDraft === '1') {
                $sectionParam = VoucherBelegeBoard::SECTION_ACTION;
                $statusParam = 'drafts';
            } elseif ($sectionParam === '' && $legacyDocKind !== '') {
                $sectionParam = match ($legacyDocKind) {
                    VoucherDocumentKind::OFFER => VoucherBelegeBoard::SECTION_OFFERS,
                    VoucherDocumentKind::ORDER_CONFIRMATION => VoucherBelegeBoard::SECTION_ORDER_CONFIRMATIONS,
                    VoucherDocumentKind::DELIVERY_NOTE => VoucherBelegeBoard::SECTION_DELIVERY_NOTES,
                    VoucherDocumentKind::PARTIAL_INVOICE,
                    VoucherDocumentKind::INVOICE,
                    VoucherDocumentKind::FINAL_INVOICE => VoucherBelegeBoard::SECTION_INVOICES,
                    default => VoucherBelegeBoard::SECTION_ACTION,
                };
                if ($legacyDocStatus !== '') {
                    $statusParam = $legacyDocStatus;
                }
                if (in_array($legacyDocKind, [
                    VoucherDocumentKind::PARTIAL_INVOICE,
                    VoucherDocumentKind::INVOICE,
                    VoucherDocumentKind::FINAL_INVOICE,
                ], true)) {
                    $_GET['invoice_kind'] = $legacyDocKind;
                }
            }

            $voucherBoard = VoucherBelegeBoard::build([
                'date_from' => $voucherPeriod->dateFrom,
                'date_to' => $voucherPeriod->dateTo,
                'year' => $voucherPeriod->year,
                'month' => $voucherPeriod->month,
                'date_from_raw' => (!$voucherPeriod->isFullYear() || $voucherPeriod->month !== null)
                    ? $voucherPeriod->dateFrom : '',
                'date_to_raw' => (!$voucherPeriod->isFullYear() || $voucherPeriod->month !== null)
                    ? $voucherPeriod->dateTo : '',
                'search' => $voucherSearch,
                'contact_id' => (int) ($_GET['contact_id'] ?? 0),
                'amount_min' => trim((string) ($_GET['amount_min'] ?? '')),
                'amount_max' => trim((string) ($_GET['amount_max'] ?? '')),
                'section' => $sectionParam,
                'status' => $statusParam,
                'invoice_kind' => (string) ($_GET['invoice_kind'] ?? ''),
                'pay' => (string) ($_GET['pay'] ?? ''),
                'page' => $voucherPage,
                'actionable_only' => (string) ($_GET['actionable'] ?? ''),
                'focus' => $belegeFocus,
            ]);
            $voucherList = $voucherBoard['list'];
            $voucherYears = VoucherRepository::availableYears();
            $voucherDraftCount = VoucherRepository::countDrafts();
            $voucherImportPending = SettingsStore::get('install_voucher_import_pending', []);
            $voucherFileCounts = VoucherFileStorage::countsForVouchers(
                array_map(static fn (array $v): int => (int) ($v['id'] ?? 0), $voucherList['items'] ?? [])
            );
            $voucherTypeFilter = '';
            $voucherDocumentKindFilter = '';
            $voucherDocumentStatusFilter = '';
            $voucherDraftFilter = '';
            $contentTemplate = 'modules/buchhaltung-belege';
            $title = $belegeFocus === VoucherBelegeBoard::FOCUS_DIENSTLEISTUNGEN
                ? 'Auftrag/Angebot'
                : 'Belege';
            $currentPage = 'buchhaltung-belege';
        } elseif ($page === 'buchhaltung-belege') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-gutscheine') {
            // Alt-URL: Gutscheine liegen jetzt unter /app?page=gutscheine
            header('Location: /app?page=gutscheine', true, 302);
            exit;
        } elseif (
            ($page === 'gutscheine' || $page === 'gutscheine-form')
            && MenuRegistry::canAccess($user, $page)
        ) {
            GiftVoucherRepository::ensureReady();
            if ($page === 'gutscheine-form') {
                $giftVoucherId = (int) ($_GET['id'] ?? 0);
                $giftFormMode = (string) ($_GET['action'] ?? ($giftVoucherId > 0 ? 'view' : 'new'));
                $giftFlyerTemplates = FlyerService::listGiftTemplates(true);
                $giftFlyerTemplate = null;
                if ($giftFormMode === 'view' && $giftVoucherId > 0) {
                    $giftVoucherForm = GiftVoucherRepository::find($giftVoucherId);
                    if ($giftVoucherForm === null) {
                        Flash::set('error', 'Gutschein nicht gefunden.');
                        header('Location: /app?page=gutscheine', true, 302);
                        exit;
                    }
                    $giftFlyerTemplate = FlyerService::findTemplate((int) ($giftVoucherForm['flyer_template_id'] ?? 0));
                } else {
                    $giftVoucherId = null;
                    $giftVoucherForm = [];
                    $giftFormMode = 'new';
                }
                $contentTemplate = 'modules/gutscheine-form';
                $title = $giftFormMode === 'view' ? 'Gutschein' : 'Gutschein ausstellen';
                $currentPage = 'gutscheine';
            } else {
                $giftVoucherList = GiftVoucherRepository::listAll();
                $contentTemplate = 'modules/gutscheine';
                $title = 'Gutscheine';
                $currentPage = 'gutscheine';
            }
        } elseif ($page === 'gutscheine' || $page === 'gutscheine-form') {
            header('Location: /app', true, 302);
            exit;
        } elseif (
            $page === 'gutscheine-flyer-vorlagen'
            && MenuRegistry::canAccess($user, 'gutscheine-flyer-vorlagen')
        ) {
            FlyerService::ensureReady();
            $flyerTemplateAction = (string) ($_GET['action'] ?? 'list');
            $flyerTemplateId = (int) ($_GET['id'] ?? 0);
            $flyerTemplateList = FlyerService::listGiftTemplates(false);
            $flyerTemplateForm = null;
            $flyerTemplateMode = 'list';
            if ($flyerTemplateAction === 'new') {
                $flyerTemplateMode = 'new';
                $flyerTemplateId = null;
                $flyerTemplateForm = [
                    'title' => '',
                    'occasion' => 'general',
                    'sort_order' => 40,
                    'is_active' => 1,
                    'layout_json' => null,
                ];
            } elseif ($flyerTemplateAction === 'edit' && $flyerTemplateId > 0) {
                $flyerTemplateForm = FlyerService::findTemplate($flyerTemplateId);
                if ($flyerTemplateForm === null || (string) ($flyerTemplateForm['purpose'] ?? '') !== FlyerService::PURPOSE_GIFT_FORM) {
                    Flash::set('error', 'Vorlage nicht gefunden.');
                    header('Location: /app?page=gutscheine-flyer-vorlagen', true, 302);
                    exit;
                }
                $flyerTemplateMode = 'edit';
            } else {
                $flyerTemplateId = null;
            }
            $contentTemplate = 'modules/gutscheine-flyer-vorlagen';
            $title = $flyerTemplateMode === 'list' ? 'Flyer-Vorlagen' : ($flyerTemplateMode === 'new' ? 'Neue Flyer-Vorlage' : 'Flyer-Vorlage bearbeiten');
            $currentPage = 'gutscheine-flyer-vorlagen';
        } elseif ($page === 'gutscheine-flyer-vorlagen') {
            header('Location: /app', true, 302);
            exit;
        } elseif (
            ($page === 'reklamation' || $page === 'reklamation-form')
            && MenuRegistry::canAccess($user, $page)
        ) {
            ComplaintRepository::ensureReady();
            if ($page === 'reklamation-form') {
                $complaintId = (int) ($_GET['id'] ?? 0);
                $action = (string) ($_GET['action'] ?? ($complaintId > 0 ? 'edit' : 'new'));
                $downloadAtt = (int) ($_GET['download_attachment'] ?? 0);
                if ($downloadAtt > 0 && $complaintId > 0) {
                    $att = ComplaintFileStorage::find($downloadAtt);
                    if ($att === null || (int) ($att['complaint_id'] ?? 0) !== $complaintId) {
                        Flash::set('error', 'Anhang nicht gefunden.');
                        header('Location: /app?page=reklamation-form&action=edit&id=' . $complaintId, true, 302);
                        exit;
                    }
                    $abs = ComplaintFileStorage::absolutePath($att);
                    if (!is_file($abs)) {
                        Flash::set('error', 'Datei fehlt auf dem Server.');
                        header('Location: /app?page=reklamation-form&action=edit&id=' . $complaintId, true, 302);
                        exit;
                    }
                    $safeName = preg_replace('/[^\w.\- ()äöüÄÖÜß]+/u', '_', (string) ($att['original_name'] ?? 'anhang')) ?: 'anhang';
                    header('Content-Type: ' . ((string) ($att['mime'] ?? '') !== '' ? (string) $att['mime'] : 'application/octet-stream'));
                    header('Content-Length: ' . (string) filesize($abs));
                    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $safeName) . '"');
                    readfile($abs);
                    exit;
                }
                if ($action === 'edit' && $complaintId > 0) {
                    if (ComplaintRepository::find($complaintId) === null) {
                        Flash::set('error', 'Reklamation nicht gefunden.');
                        header('Location: /app?page=reklamation', true, 302);
                        exit;
                    }
                    $complaintForm = ComplaintRepository::formForId($complaintId);
                    $complaintEvents = ComplaintRepository::events($complaintId);
                    $complaintAttachments = ComplaintFileStorage::listForComplaint($complaintId);
                    $complaintContactLabel = ComplaintRepository::contactLabel((int) ($complaintForm['contact_id'] ?? 0));
                    $complaintVoucherLabel = ComplaintRepository::voucherLabel((int) ($complaintForm['source_voucher_id'] ?? 0));
                } else {
                    $complaintId = null;
                    $complaintForm = ComplaintRepository::emptyForm();
                    $complaintEvents = [];
                    $complaintAttachments = [];
                    $complaintContactLabel = '';
                    $complaintVoucherLabel = '';
                }
                $contentTemplate = 'modules/reklamation-form';
                $title = ($complaintId ?? 0) > 0 ? 'Reklamation bearbeiten' : 'Neue Reklamation';
                $currentPage = 'reklamation';
            } else {
                $complaintList = ComplaintRepository::listAll();
                $contentTemplate = 'modules/reklamation';
                $title = 'Reklamation';
                $currentPage = 'reklamation';
            }
        } elseif ($page === 'reklamation' || $page === 'reklamation-form') {
            header('Location: /app', true, 302);
            exit;
        } elseif (
            $page === 'website-gutschein-flyer'
            && MenuRegistry::canAccess($user, 'website-gutschein-flyer')
        ) {
            GiftVoucherRepository::ensureReady();
            FlyerService::ensureReady();
            $giftVoucherList = GiftVoucherRepository::listAll(GiftVoucherRepository::STATUS_ACTIVE);
            $flyerId = (int) ($_GET['id'] ?? 0);
            $previewTemplateId = (int) ($_GET['preview_template'] ?? 0);
            $flyerGift = $flyerId > 0 ? GiftVoucherRepository::find($flyerId) : null;
            $companyName = '';
            try {
                $firm = CompanySettings::config();
                $companyName = (string) ($firm['name'] ?? '');
            } catch (Throwable) {
                $companyName = '';
            }
            $companyLogoUrl = class_exists('AppearanceSettings') ? (string) AppearanceSettings::logoUrl() : '';
            $flyerPrint = null;
            if (is_array($flyerGift)) {
                $tpl = FlyerService::findTemplate((int) ($flyerGift['flyer_template_id'] ?? 0));
                $flyerPrint = FlyerService::giftPrintModel($flyerGift, $tpl, $companyName, $companyLogoUrl);
            } elseif ($previewTemplateId > 0) {
                $tpl = FlyerService::findTemplate($previewTemplateId);
                if ($tpl !== null) {
                    $flyerGift = [
                        'id' => 0,
                        'code' => 'VORSCHAU-DEMO',
                        'face_value_gross' => 50.0,
                        'amount' => 50.0,
                        'title' => (string) ($tpl['title'] ?? 'Gutschein'),
                        'valid_until' => date('Y-m-d', strtotime('+1 year')),
                        'flyer_template_id' => $previewTemplateId,
                        'status' => 'active',
                    ];
                    $flyerPrint = FlyerService::giftPrintModel($flyerGift, $tpl, $companyName, $companyLogoUrl);
                }
            }
            $contentTemplate = 'modules/website-gutschein-flyer';
            $title = 'Gutschein-Flyer';
            $currentPage = 'website-gutschein-flyer';
        } elseif ($page === 'website-gutschein-flyer') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-beleg-form' && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')) {
            if (MenuRegistry::isGutscheinEnabled()) {
                GiftVoucherRepository::ensureReady();
            }
            $applyBelegContactPrefill = static function (array &$form): void {
                $prefillContactId = (int) ($_GET['contact_id'] ?? 0);
                if ($prefillContactId < 1) {
                    return;
                }
                $prefillContact = ContactRepository::findById($prefillContactId);
                if ($prefillContact === null) {
                    return;
                }
                $label = trim($prefillContact->companyName);
                if ($label === '') {
                    $label = trim($prefillContact->displayName);
                }
                if ($label === '') {
                    $label = trim((string) ($_GET['contact_label'] ?? ''));
                }
                $form['contact_id'] = (string) $prefillContactId;
                $form['contact_label'] = $label;
                $supplierName = trim((string) ($form['supplier_name'] ?? ''));
                $getLabel = trim((string) ($_GET['contact_label'] ?? ''));
                if ($supplierName === '' || ($getLabel !== '' && strcasecmp($supplierName, $getLabel) === 0)) {
                    $form['supplier_name'] = $label;
                }
            };
            $voucherId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $voucherChain = ['documents' => [], 'current_id' => 0];
            $followUpKinds = [];
            $chainSummary = null;
            $voucherMailConfigured = MailSettings::isConfigured();
            $voucherMailCanSend = false;
            $voucherMailTo = '';
            $voucherMailSubject = '';
            $voucherMailIntro = '';
            $voucherDunningCanSend = false;
            $voucherDunningNextLevel = 0;
            $voucherDunningNextLabel = '';
            $voucherDunningFee = 0.0;
            if ($action === 'new') {
                if (!$canEdit) {
                    header('Location: ' . RoleResolver::homePath($user), true, 302);
                    exit;
                }
                $contentTemplate = 'modules/buchhaltung-beleg-form';
                $title = 'Neuer Beleg';
                $currentPage = 'buchhaltung-belege';
                $isDraftVoucher = false;
                $form = VoucherRepository::emptyForm();
                $applyBelegContactPrefill($form);
                $belegeFormFocus = VoucherBelegeBoard::sanitizeFocus((string) ($_GET['focus'] ?? ''));
                $prefillKind = VoucherDocumentKind::sanitize((string) ($_GET['document_kind'] ?? ''));
                $prefillVoucherType = VoucherRepository::normalizeVoucherType((string) ($_GET['voucher_type'] ?? 'income'));
                if ($belegeFormFocus === VoucherBelegeBoard::FOCUS_DIENSTLEISTUNGEN) {
                    $form['voucher_type'] = 'income';
                    if ($prefillKind === '' || !VoucherDocumentKind::isDienstleistungenKind($prefillKind)) {
                        $prefillKind = VoucherDocumentKind::WORK_ORDER;
                    }
                    $form['document_kind'] = $prefillKind;
                    $title = 'Neues Auftrag/Angebot';
                } elseif ($prefillKind !== '' && VoucherDocumentKind::voucherTypeSupportsDocumentKind($prefillVoucherType)) {
                    $form['voucher_type'] = $prefillVoucherType;
                    $form['document_kind'] = $prefillKind;
                    if ($prefillKind === VoucherDocumentKind::OFFER) {
                        $title = 'Neues Angebot';
                    } elseif ($prefillKind === VoucherDocumentKind::WORK_ORDER) {
                        $title = 'Neuer Auftrag';
                    }
                }
                $formError = null;
                $ledgerPostings = [];
                $followFromId = (int) ($_GET['follow_from'] ?? 0);
                $followDocumentKind = VoucherDocumentKind::sanitize((string) ($_GET['document_kind'] ?? ''));
                $reanimate = trim((string) ($_GET['reanimate'] ?? '')) === '1';
                if ($followFromId > 0 && $followDocumentKind !== '') {
                    try {
                        if ($reanimate && $followDocumentKind === VoucherDocumentKind::OFFER) {
                            $form = VoucherDocumentChain::prefillReanimatedOffer($followFromId);
                            $title = 'Neu anbieten (aktuelle Preise)';
                        } else {
                            $followPaymentId = (int) ($_GET['payment_id'] ?? 0);
                            $form = VoucherDocumentChain::prefillFollowUp(
                                $followFromId,
                                $followDocumentKind,
                                $followPaymentId > 0 ? $followPaymentId : null
                            );
                            $title = 'Folgebeleg: ' . VoucherDocumentKind::label($followDocumentKind);
                        }
                        $chainSummary = is_array($form['chain_summary'] ?? null) ? $form['chain_summary'] : null;
                        unset($form['chain_summary']);
                        $voucherChain = VoucherDocumentChain::chainView($followFromId);
                    } catch (Throwable $e) {
                        $formError = $e->getMessage();
                    }
                }
            } elseif ($action === 'edit' && $voucherId > 0) {
                $voucher = VoucherRepository::findById($voucherId);
                if ($voucher === null) {
                    Flash::set('error', 'Beleg nicht gefunden.');
                    header('Location: /app?page=buchhaltung-belege', true, 302);
                    exit;
                }
                $voucher = VoucherDocumentStatus::markOfferExpiredIfDue($voucher);
                if (trim((string) ($_GET['download'] ?? '')) === 'print') {
                    try {
                        VoucherDocumentRevisionService::markSentOnPrint($voucherId, $user->id);
                        $voucher = VoucherRepository::findById($voucherId) ?? $voucher;
                        $showChainInternal = trim((string) ($_GET['show_chain'] ?? '')) === '1';
                        $html = VoucherDocumentPrintService::render($voucher, [
                            'show_chain' => $showChainInternal,
                            'customer_facing' => !$showChainInternal,
                        ]);
                        AccountingPrintService::send(
                            VoucherDocumentPrintService::attachmentFilename($voucher),
                            $html
                        );
                    } catch (Throwable $printError) {
                        Flash::set('error', 'Druckansicht konnte nicht erzeugt werden: ' . $printError->getMessage());
                        header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $voucherId, true, 302);
                    }
                    exit;
                }
                $isDraftVoucher = !empty($voucher['is_draft']);
                $contentTemplate = 'modules/buchhaltung-beleg-form';
                $title = $isDraftVoucher ? 'Neuer Beleg' : ($canEdit ? 'Beleg bearbeiten' : 'Beleg anzeigen');
                $currentPage = 'buchhaltung-belege';
                $form = VoucherRepository::toForm($voucher);
                $form['files'] = VoucherFileStorage::listForVoucher($voucherId);
                $ledgerPostings = $isDraftVoucher ? [] : LedgerRepository::postingsForVoucher($voucherId);
                $applyBelegContactPrefill($form);
                $formError = null;
                $voucherChain = VoucherDocumentChain::chainView($voucherId);
                $documentKind = (string) ($form['document_kind'] ?? '');
                $documentStatus = VoucherDocumentStatus::sanitize((string) ($form['document_status'] ?? ''));
                if (
                    $canEdit
                    && !$isDraftVoucher
                    && VoucherRepository::normalizeVoucherType((string) ($form['voucher_type'] ?? '')) === 'income'
                    && !VoucherDocumentStatus::isClosed($documentStatus)
                ) {
                    $followUpKinds = VoucherDocumentKind::followUpKinds($documentKind);
                }
                $canReanimateOffer = $canEdit
                    && !$isDraftVoucher
                    && $documentKind === VoucherDocumentKind::OFFER
                    && VoucherDocumentStatus::isClosed($documentStatus);
                if ($documentKind === VoucherDocumentKind::FINAL_INVOICE) {
                    $parentId = (int) ($form['parent_voucher_id'] ?? 0);
                    if ($parentId > 0) {
                        $chainSummary = VoucherDocumentChain::finalInvoiceSummary($parentId, $voucherId);
                    }
                }
                $voucherMailCanSend = VoucherDocumentMailService::canSend($voucher);
                $voucherMailTo = VoucherDocumentMailService::defaultRecipient((int) ($form['contact_id'] ?? 0));
                if ($voucherMailCanSend) {
                    $voucherMailSubject = VoucherDocumentPrintService::defaultEmailSubject($voucher);
                    $voucherMailIntro = VoucherDocumentPrintService::defaultEmailIntro($voucher);
                }
                $voucherLinkedMails = MailLogRepository::listForVoucher($voucherId, 30);
                $dunningConfig = AccountingPaymentSettings::dunningConfig();
                $dunningLevels = is_array($dunningConfig['levels'] ?? null) ? $dunningConfig['levels'] : [];
                $currentDunningLevel = (int) ($form['dunning_level'] ?? 0);
                $dueDate = (string) ($form['payment_due_date'] ?? '');
                $isOpenReceivable = VoucherRepository::normalizeVoucherType((string) ($form['voucher_type'] ?? '')) === 'income'
                    && VoucherPaymentStatus::countsAsOpenPayable(
                        VoucherPaymentStatus::sanitize((string) ($form['payment_status'] ?? ''))
                    );
                if (
                    $canEdit
                    && !$isDraftVoucher
                    && $isOpenReceivable
                    && $dueDate !== ''
                    && PaymentTermsService::daysOverdue($dueDate) > 0
                    && $currentDunningLevel < count($dunningLevels)
                    && MailSettings::isConfigured()
                ) {
                    $voucherDunningCanSend = true;
                    $voucherDunningNextLevel = $currentDunningLevel + 1;
                    $nextLevelConfig = $dunningLevels[$currentDunningLevel];
                    $voucherDunningNextLabel = (string) ($nextLevelConfig['label'] ?? 'Mahnung');
                    $voucherDunningFee = (float) ($nextLevelConfig['fee_amount'] ?? 0);
                }
            } else {
                header('Location: /app?page=buchhaltung-belege', true, 302);
                exit;
            }
        } elseif ($page === 'buchhaltung-beleg-form') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-ueberweisungen' && MenuRegistry::canAccess($user, 'buchhaltung-ueberweisungen')) {
            $transfersPrepared = BankTransferRepository::list('prepared');
            $transfersExecuted = BankTransferRepository::list('executed');
            $openTransferId = (int) ($_GET['open'] ?? 0);
            $contentTemplate = 'modules/buchhaltung-ueberweisungen';
            $title = 'Überweisungen';
            $currentPage = 'buchhaltung-ueberweisungen';
        } elseif ($page === 'buchhaltung-ueberweisungen') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-kontenuebersicht' && MenuRegistry::canAccess($user, 'buchhaltung-kontenuebersicht')) {
            $ledgerYears = LedgerRepository::availableYears();
            $ledgerPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $ledgerYear = $ledgerPeriod->year;
            $ledgerAccount = preg_replace('/[^0-9A-Za-z]/', '', (string) ($_GET['account'] ?? '')) ?? '';
            $ledgerSearch = trim((string) ($_GET['s'] ?? ''));
            $ledgerShowEmpty = !empty($_GET['empty']);
            $ledgerYearStatus = FiscalYearService::status($ledgerYear);
            $periodOpts = [
                'search' => $ledgerSearch,
                'show_empty' => $ledgerShowEmpty,
                'date_from' => $ledgerPeriod->dateFrom,
                'date_to' => $ledgerPeriod->dateTo,
            ];
            if ($ledgerAccount !== '') {
                $ledgerStatement = LedgerRepository::accountStatement($ledgerAccount, $ledgerYear, $periodOpts);
            } else {
                $ledgerOverview = LedgerRepository::accountOverview($ledgerYear, $periodOpts);
            }
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download === 'print' && $ledgerAccount !== '' && Database::isConfigured()) {
                $html = AccountingPrintService::render('kontoauszug', [
                    'statement' => $ledgerStatement ?? [],
                    'periodLabel' => $ledgerPeriod->label,
                ], 'Kontoauszug ' . $ledgerAccount);
                AccountingPrintService::send('Kontoauszug_' . $ledgerAccount . '.html', $html);
                exit;
            }
            $contentTemplate = 'modules/buchhaltung-kontenuebersicht';
            $title = 'Kontenübersicht';
            $currentPage = 'buchhaltung-kontenuebersicht';
        } elseif ($page === 'buchhaltung-kontenuebersicht') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-jahresabschluss' && MenuRegistry::canAccess($user, 'buchhaltung-jahresabschluss')) {
            $ledgerYears = LedgerRepository::availableYears();
            $jaYear = max(2000, (int) ($_GET['year'] ?? (int) date('Y')));
            $jaPreview = FiscalYearService::profitLossPreview($jaYear);
            $jaYearStatus = FiscalYearService::status($jaYear);
            $fiscalYears = FiscalYearService::list();
            $closeChecklist = FiscalCloseService::checklist($jaYear);
            $closeSummary = FiscalCloseService::summary($jaYear);
            $canCloseYear = FiscalCloseService::canClose($jaYear);
            $isDiyMode = TaxAdvisorSettings::isDiyMode();
            $isAdmin = RoleResolver::isAdmin($user);
            $contentTemplate = 'modules/buchhaltung-jahresabschluss';
            $title = 'Jahresabschluss';
            $currentPage = 'buchhaltung-jahresabschluss';
        } elseif ($page === 'buchhaltung-jahresabschluss') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-anschaffungsrechner' && MenuRegistry::canAccess($user, 'buchhaltung-anschaffungsrechner')) {
            $acqAreas = AfaCatalog::areas();
            $acqPresets = AfaCatalog::presets();
            $acqCompanyCfg = CompanyExtendedSettings::config();
            $acqCompany = [
                'company_type' => (string) ($acqCompanyCfg['company_type'] ?? ''),
                'company_type_label' => CompanyTypes::labels()[(string) ($acqCompanyCfg['company_type'] ?? '')] ?? '',
                'tax_rates' => CompanyExtendedSettings::taxRates(),
            ];
            $presetId = trim((string) ($_GET['preset'] ?? 'pc'));
            $preset = AfaCatalog::presetById($presetId) ?? AfaCatalog::presetById('pc');
            $acqInput = [
                'area' => trim((string) ($_GET['area'] ?? ($preset['area'] ?? 'it'))),
                'preset' => (string) ($preset['id'] ?? 'pc'),
                'net' => isset($_GET['net']) ? (float) $_GET['net'] : (float) ($preset['default_net'] ?? 1200),
                'vat_rate' => isset($_GET['vat_rate']) ? (float) $_GET['vat_rate'] : 19.0,
                'useful_life_years' => isset($_GET['useful_life_years'])
                    ? (int) $_GET['useful_life_years']
                    : (int) ($preset['useful_life_years'] ?? 3),
                'non_deductible_share' => isset($_GET['non_deductible_share']) ? (float) $_GET['non_deductible_share'] : 0.0,
                'down_payment' => isset($_GET['down_payment']) ? (float) $_GET['down_payment'] : 0.0,
                'term_months' => isset($_GET['term_months']) ? (int) $_GET['term_months'] : 36,
                'interest_pa' => isset($_GET['interest_pa']) ? (float) $_GET['interest_pa'] : 6.0,
                'lease_rate' => isset($_GET['lease_rate']) ? (float) $_GET['lease_rate'] : 0.0,
                'rent_rate' => isset($_GET['rent_rate']) ? (float) $_GET['rent_rate'] : 0.0,
                'residual' => isset($_GET['residual']) ? (float) $_GET['residual'] : 0.0,
            ];
            $acqResult = null;
            if (!empty($_GET['calc'])) {
                try {
                    $acqResult = AcquisitionCompareService::compare($acqInput, $acqCompanyCfg);
                } catch (Throwable $e) {
                    Flash::set('error', 'Berechnung fehlgeschlagen: ' . $e->getMessage());
                }
            }
            $contentTemplate = 'modules/buchhaltung-anschaffungsrechner';
            $title = 'Anschaffungsrechner';
            $currentPage = 'buchhaltung-anschaffungsrechner';
        } elseif ($page === 'buchhaltung-anschaffungsrechner') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-ustva' && MenuRegistry::canAccess($user, 'buchhaltung-ustva')) {
            $ustvaPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $ustvaYear = $ustvaPeriod->year;
            $ustvaMonth = $ustvaPeriod->month;
            $ustvaBerichtigung = !empty($_GET['berichtigung']);
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download !== '' && Database::isConfigured()) {
                try {
                    if ($download === 'print') {
                        $report = UstvaReportService::report(
                            $ustvaYear,
                            $ustvaMonth,
                            ['berichtigung' => $ustvaBerichtigung]
                        );
                        $html = AccountingPrintService::render('ustva', ['report' => $report], 'UStVA ' . $report['period_label']);
                        AccountingPrintService::send('UStVA_' . $ustvaYear . '.html', $html);
                        exit;
                    }
                    $export = match ($download) {
                        'ustva' => ElsterExportService::exportUstva(
                            $ustvaYear,
                            $ustvaMonth
                        ),
                        'euer' => ElsterExportService::exportEuer($ustvaYear),
                        default => throw new InvalidArgumentException('Unbekannter Export-Typ.'),
                    };
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
                    echo $export['content'];
                    exit;
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    header(
                        'Location: /app?page=buchhaltung-ustva&year=' . $ustvaYear . '&month=' . ($ustvaMonth ?? 0),
                        true,
                        302
                    );
                    exit;
                }
            }
            $ustvaYears = LedgerRepository::availableYears();
            $ustvaReport = UstvaReportService::report(
                $ustvaYear,
                $ustvaMonth,
                ['berichtigung' => $ustvaBerichtigung]
            );
            $isDiyMode = TaxAdvisorSettings::isDiyMode();
            $contentTemplate = 'modules/buchhaltung-ustva';
            $title = 'UStVA';
            $currentPage = 'buchhaltung-ustva';
        } elseif ($page === 'buchhaltung-ustva') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-opos' && MenuRegistry::canAccess($user, 'buchhaltung-opos')) {
            $oposDirection = trim((string) ($_GET['direction'] ?? ''));
            $oposSearch = trim((string) ($_GET['s'] ?? ''));
            $oposData = OpenItemsRepository::list([
                'direction' => $oposDirection,
                'search' => $oposSearch,
            ]);
            $contentTemplate = 'modules/buchhaltung-opos';
            $title = 'Offene Posten';
            $currentPage = 'buchhaltung-opos';
        } elseif ($page === 'buchhaltung-opos') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-datev-export') {
            header('Location: /app?page=buchhaltung-steuerberater-export' . (isset($_GET['year']) ? '&year=' . (int) $_GET['year'] : ''), true, 302);
            exit;
        } elseif ($page === 'buchhaltung-steuerberater-export' && MenuRegistry::canAccess($user, 'buchhaltung-steuerberater-export')) {
            $exportYear = max(2000, (int) ($_GET['year'] ?? (int) date('Y')));
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download !== '' && Database::isConfigured()) {
                try {
                    if ($download === 'belege') {
                        $zip = DatevBelegExportService::buildZip($exportYear);
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="' . $zip['filename'] . '"');
                        readfile($zip['path']);
                        @unlink($zip['path']);
                        exit;
                    }
                    if ($download === 'paket') {
                        $zip = SteuerberaterPaketService::buildZip($exportYear);
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="' . $zip['filename'] . '"');
                        readfile($zip['path']);
                        @unlink($zip['path']);
                        exit;
                    }
                    $export = match ($download) {
                        'datev' => DatevExtfExporter::export($exportYear, includeManual: true),
                        'agenda' => AgendaExporter::export($exportYear),
                        'addison' => AddisonExporter::export($exportYear),
                        'stammdaten' => DatevStammdatenExporter::exportAccounts($exportYear),
                        'personen' => DatevStammdatenExporter::exportPersonAccounts(),
                        default => throw new InvalidArgumentException('Unbekannter Export-Typ.'),
                    };
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
                    echo $export['content'];
                    exit;
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    header('Location: /app?page=buchhaltung-steuerberater-export&year=' . $exportYear, true, 302);
                    exit;
                }
            }
            $datevExportYear = $exportYear;
            $datevExportSettings = DatevExportSettings::forForm();
            $datevExportYears = LedgerRepository::availableYears();
            $contentTemplate = 'modules/buchhaltung-steuerberater-export';
            $title = 'Steuerberater-Export';
            $currentPage = 'buchhaltung-steuerberater-export';
        } elseif ($page === 'buchhaltung-steuerberater-export') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-manuelle-buchung' && MenuRegistry::canAccess($user, 'buchhaltung-manuelle-buchung')) {
            $manualYear = max(2000, (int) ($_GET['year'] ?? (int) date('Y')));
            $manualYears = LedgerRepository::availableYears();
            $manualBatches = ManualLedgerService::listBatches($manualYear);
            $contentTemplate = 'modules/buchhaltung-manuelle-buchung';
            $title = 'Manuelle Buchungen';
            $currentPage = 'buchhaltung-manuelle-buchung';
        } elseif ($page === 'buchhaltung-manuelle-buchung') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-auswertungen' && MenuRegistry::canAccess($user, 'buchhaltung-auswertungen')) {
            $reportPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $reportYear = $reportPeriod->year;
            $reportYears = LedgerRepository::availableYears();
            $reportType = in_array($_GET['type'] ?? '', ['bilanz', 'guv'], true) ? (string) $_GET['type'] : 'guv';
            $balanceSheet = FinancialReportsService::balanceSheet($reportYear);
            $profitLoss = FinancialReportsService::profitLoss($reportYear);
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download === 'print' && Database::isConfigured()) {
                $html = AccountingPrintService::render('auswertungen', [
                    'reportType' => $reportType,
                    'balanceSheet' => $balanceSheet,
                    'profitLoss' => $profitLoss,
                    'periodLabel' => $reportPeriod->label,
                ], ($reportType === 'bilanz' ? 'Bilanz ' : 'GuV ') . $reportPeriod->label);
                AccountingPrintService::send('Auswertung_' . $reportYear . '.html', $html);
                exit;
            }
            $contentTemplate = 'modules/buchhaltung-auswertungen';
            $title = 'Bilanz & GuV';
            $currentPage = 'buchhaltung-auswertungen';
        } elseif ($page === 'buchhaltung-auswertungen') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-bwa' && MenuRegistry::canAccess($user, 'buchhaltung-bwa')) {
            $bwaPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $bwaYears = LedgerRepository::availableYears();
            $bwaReport = BwaReportService::report($bwaPeriod);
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download === 'print' && Database::isConfigured()) {
                $html = AccountingPrintService::render('bwa', ['report' => $bwaReport], 'BWA ' . $bwaPeriod->label);
                AccountingPrintService::send('BWA_' . $bwaPeriod->year . '.html', $html);
                exit;
            }
            $contentTemplate = 'modules/buchhaltung-bwa';
            $title = 'BWA';
            $currentPage = 'buchhaltung-bwa';
        } elseif ($page === 'buchhaltung-bwa') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-susa' && MenuRegistry::canAccess($user, 'buchhaltung-susa')) {
            $susaPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $susaYears = LedgerRepository::availableYears();
            $susaReport = SusaReportService::report($susaPeriod);
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download === 'print' && Database::isConfigured()) {
                $html = AccountingPrintService::render('susa', ['report' => $susaReport], 'SuSa ' . $susaPeriod->label);
                AccountingPrintService::send('SuSa_' . $susaPeriod->year . '.html', $html);
                exit;
            }
            $contentTemplate = 'modules/buchhaltung-susa';
            $title = 'SuSa';
            $currentPage = 'buchhaltung-susa';
        } elseif ($page === 'buchhaltung-susa') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-bankabgleich' && MenuRegistry::canAccess($user, 'buchhaltung-bankabgleich')) {
            BankTransactionRepository::backfillFingerprints();
            // Nur Klassifikation zur Anzeige — Geisterumsätze werden nie automatisch ausgeblendet.
            $bankTxClassified = BankGhostDetectionService::classifyOpenTransactions();
            $bankTransactionsOpen = $bankTxClassified['open'];
            $bankTransactionsGhosts = $bankTxClassified['ghosts'];
            $bankTransactionsMatched = BankTransactionRepository::list('matched');
            $contentTemplate = 'modules/buchhaltung-bankabgleich';
            $title = 'Bankabgleich';
            $currentPage = 'buchhaltung-bankabgleich';
        } elseif ($page === 'buchhaltung-bankabgleich') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-kassenbuch' && MenuRegistry::canAccess($user, 'buchhaltung-kassenbuch')) {
            $cashPeriod = AccountingPeriodFilter::fromRequest($_GET, (int) date('Y'));
            $cashYear = $cashPeriod->year;
            $cashYears = LedgerRepository::availableYears();
            $cashEntries = CashJournalRepository::listForPeriod($cashPeriod);
            $cashTotals = CashJournalRepository::totalsForPeriod($cashPeriod);
            $cashClosings = CashDayCloseService::listClosings($cashYear);
            $cashCloseDate = trim((string) ($_GET['close_date'] ?? date('Y-m-d')));
            $cashDaySummary = CashDayCloseService::daySummary($cashCloseDate);
            $download = trim((string) ($_GET['download'] ?? ''));
            if ($download === 'print' && Database::isConfigured()) {
                $html = AccountingPrintService::render('kassenbuch', [
                    'entries' => $cashEntries,
                    'totals' => $cashTotals,
                    'periodLabel' => $cashPeriod->label,
                ], 'Kassenbuch ' . $cashPeriod->label);
                AccountingPrintService::send('Kassenbuch_' . $cashYear . '.html', $html);
                exit;
            }
            $contentTemplate = 'modules/buchhaltung-kassenbuch';
            $title = 'Kassenbuch';
            $currentPage = 'buchhaltung-kassenbuch';
        } elseif ($page === 'buchhaltung-kassenbuch') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'einstellungen') {
            if (!MenuRegistry::canAccess($user, 'einstellungen')) {
                header('Location: /app', true, 302);
                exit;
            }
            $contentTemplate = 'modules/einstellungen';
            $title = 'Einstellungen';
            $currentPage = 'einstellungen';
        } elseif ($page === 'sicherheitsprotokoll') {
            if (!RoleResolver::isAdmin($user)) {
                header('Location: /app', true, 302);
                exit;
            }
            $auditEntries = AuditLog::recent(200);
            $contentTemplate = 'modules/sicherheitsprotokoll';
            $title = 'Sicherheitsprotokoll';
            $currentPage = 'einstellungen';
        } elseif ($page === 'kontakte' && MenuRegistry::canAccess($user, 'kontakte')) {
            $contactId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $contactSearch = trim((string) ($_GET['s'] ?? ''));
            $contactPage = max(1, (int) ($_GET['paged'] ?? 1));
            $contactRoleFilter = strtolower(trim((string) ($_GET['role'] ?? '')));
            if (!in_array($contactRoleFilter, ['mitarbeiter', 'kunde', 'lieferant'], true)) {
                $contactRoleFilter = '';
            }
            $allowedContactRoles = ContactAccessResolver::allowedContactRoleOptions($user);
            $canDeleteContact = false;
            $contact = null;

            if ($action === 'new') {
                if (!ContactAccessResolver::canEditContact($user)) {
                    header('Location: ' . RoleResolver::homePath($user), true, 302);
                    exit;
                }
                $contentTemplate = 'modules/kontakte-form';
                $title = 'Neuer Kontakt';
                $currentPage = 'kontakte';
                $form = ContactRepository::emptyForm();
                $kontakteReturnTo = trim((string) ($_GET['return_to'] ?? ''));
                if ($kontakteReturnTo !== '' && (!str_starts_with($kontakteReturnTo, '/app?') || str_contains($kontakteReturnTo, '//'))) {
                    $kontakteReturnTo = '';
                }
                $kontakteSupplierNumberPreview = '';
                if ($kontakteReturnTo !== '' && str_contains($kontakteReturnTo, 'buchhaltung-beleg-form')) {
                    try {
                        $kontakteSupplierNumberPreview = NumberRangeSettings::preview('supplier');
                    } catch (Throwable) {
                        $kontakteSupplierNumberPreview = '';
                    }
                }
                $formError = null;
                $bankAccounts = ContactRepository::defaultBankAccounts();
                $employeeData = EmployeeData::empty();
                $employeeFiles = ContactFileStorage::emptyFiles();
                $showEmployeeFields = ContactAccessResolver::canEditContact($user);
                $linkFormContext = ContactCompanyLinkRepository::formContext(null, []);
                extract($linkFormContext);
                $payrollWageTypeOptions = PayrollWageTypeRepository::all(true);
                $payrollWageAssignments = PayrollEmployeeWageAssignmentRepository::formRows([]);
            } elseif ($action === 'edit' && $contactId > 0) {
                if (!RoleResolver::canEdit($user)) {
                    header('Location: /app?page=kontakte&id=' . $contactId, true, 302);
                    exit;
                }
                $contact = ContactRepository::findById($contactId);
                if (ContactRepository::consumeRetentionPurged()) {
                    Flash::set(
                        'success',
                        'Mitarbeiterdaten wurden nach Ablauf der 10-jÃ¤hrigen Aufbewahrungsfrist entfernt. Rolle ist jetzt Kunde.'
                    );
                }
                if (!$contact || !ContactAccessResolver::canViewContact($user, $contact)) {
                    Flash::set('error', $contact ? 'Keine Berechtigung fÃ¼r diesen Kontakt.' : 'Kontakt nicht gefunden.');
                    header('Location: /app?page=kontakte', true, 302);
                    exit;
                }
                if (!ContactAccessResolver::canEditContact($user, $contact)) {
                    header('Location: /app?page=kontakte&id=' . $contactId, true, 302);
                    exit;
                }
                $contentTemplate = 'modules/kontakte-form';
                $title = 'Kontakt bearbeiten';
                $currentPage = 'kontakte';
                $form = ContactRepository::toForm($contact);
                $formError = null;
                $kontakteReturnTo = trim((string) ($_GET['return_to'] ?? ''));
                if ($kontakteReturnTo !== '' && (!str_starts_with($kontakteReturnTo, '/app?') || str_contains($kontakteReturnTo, '//'))) {
                    $kontakteReturnTo = '';
                }
                $bankAccounts = $contact->bankAccounts !== [] ? $contact->bankAccounts : ContactRepository::defaultBankAccounts();
                $employeeData = $contact->employeeData;
                $employeeFiles = $contact->employeeFiles;
                $showEmployeeFields = ContactAccessResolver::canViewEmployeeHrData($user, $contact);
                $canDeleteContact = ContactAccessResolver::canDeleteContact($user, $contact);
                $linkFormContext = ContactCompanyLinkRepository::formContext($contact, []);
                extract($linkFormContext);
                $payrollWageTypeOptions = PayrollWageTypeRepository::all(false);
                $payrollWageAssignments = PayrollEmployeeWageAssignmentRepository::formRows(
                    PayrollEmployeeWageAssignmentRepository::forContact($contactId)
                );
                $contactTeams = TeamRepository::teamsForContact($contactId);
                $staffAccessStatus = StaffAccessInviteService::status($contactId);
                $uidForMb = MailboxMemberResolver::findUserIdForContact($contact);
                $staffAccessHasMailbox = ($uidForMb !== null && MailboxRepository::findPrivateForUser($uidForMb) !== null)
                    || MailboxRepository::findPrivateForContact($contactId) !== null;
            } elseif ($contactId > 0) {
                $contact = ContactRepository::findById($contactId);
                if (ContactRepository::consumeRetentionPurged()) {
                    Flash::set(
                        'success',
                        'Mitarbeiterdaten wurden nach Ablauf der 10-jÃ¤hrigen Aufbewahrungsfrist entfernt. Rolle ist jetzt Kunde.'
                    );
                }
                if (!$contact || !ContactAccessResolver::canViewContact($user, $contact)) {
                    Flash::set('error', $contact ? 'Keine Berechtigung fÃ¼r diesen Kontakt.' : 'Kontakt nicht gefunden.');
                    header('Location: /app?page=kontakte', true, 302);
                    exit;
                }
                // Explizite Ansicht (action=view) nie auf Bearbeiten umleiten —
                // sonst wirkt „Zurück“ / „Anzeigen“ wie ein toter Link.
                if (
                    $action !== 'view'
                    && $canEdit
                    && ContactAccessResolver::canEditContact($user, $contact)
                ) {
                    header('Location: /app?page=kontakte&action=edit&id=' . $contactId, true, 302);
                    exit;
                }
                $contentTemplate = 'modules/kontakte-detail';
                $title = $contact->listLabel();
                $currentPage = 'kontakte';
                $showEmployeeFields = ContactAccessResolver::canViewEmployeeHrData($user, $contact);
                $canDeleteContact = false;
                $companyEmployees = $contact->isCompany()
                    ? ContactCompanyLinkRepository::employeesForCompany($contact->id)
                    : [];
                $employerLink = !$contact->isCompany()
                    ? ContactCompanyLinkRepository::employerForPerson($contact->id)
                    : null;
                $payrollWageAssignments = $showEmployeeFields
                    ? PayrollEmployeeWageAssignmentRepository::forContact($contact->id)
                    : [];
                $payrollCrmDocuments = [];
                if (
                    $showEmployeeFields
                    && PayrollEmployeeAccess::canView($user)
                    && class_exists('PayrollDocumentRepository')
                ) {
                    PayrollDocumentRepository::ensureReady();
                    if (PayrollDocumentRepository::tableReady()) {
                        $payrollCrmDocuments = array_values(array_filter(
                            PayrollDocumentRepository::listForContact($contact->id),
                            static fn (array $d): bool => (string) ($d['kind'] ?? '') === PayrollDocumentRepository::KIND_PAYSLIP
                                && (int) ($d['is_final'] ?? 0) === 1
                        ));
                    }
                }
            } else {
                $contactList = ContactRepository::paginate($contactSearch, $contactPage, $user, $contactRoleFilter);
                $contactImportErrors = [];
                if (isset($_SESSION['dg_contact_import_errors']) && is_array($_SESSION['dg_contact_import_errors'])) {
                    $contactImportErrors = $_SESSION['dg_contact_import_errors'];
                    unset($_SESSION['dg_contact_import_errors']);
                }
                $contentTemplate = 'modules/kontakte';
                $title = 'Kontakte';
                $currentPage = 'kontakte';
            }
        } elseif (
            (
                $page === 'website-seiten'
                || $page === 'website-seite-form'
                || $page === 'website-recht'
                || $page === 'website-formulare'
                || $page === 'website-formular-form'
                || $page === 'website-formular-inbox'
                || $page === 'website-statistik'
                || $page === 'website-menu'
                || $page === 'website-chrome'
                || $page === 'website-design'
            )
            && MenuRegistry::canAccess($user, $page)
        ) {
            if ($page === 'website-seiten') {
                if (Database::isConfigured()) {
                    WebsiteFormRepository::ensureTables();
                    try {
                        TimeKioskService::ensureDraftWebsitePage((int) ($user->id ?? 0));
                    } catch (Throwable) {
                    }
                    $migratedPages = WebsiteFormRepository::migrateLegacyContactBlocksInPages($user->id);
                    if ($migratedPages > 0) {
                        Flash::set('success', $migratedPages . ' Seite(n): klassische Kontaktblöcke → Formulare umgestellt.');
                        header('Location: /app?page=website-seiten', true, 302);
                        exit;
                    }
                }
                $websitePageList = WebsitePageRepository::list();
                $websiteMaintenance = WebsiteMaintenanceSettings::config();
                $contentTemplate = 'modules/website-seiten';
                $title = 'Seiten';
                $currentPage = 'website-seiten';
            } elseif ($page === 'website-recht') {
                $legalSlug = LegalProductSettings::sanitizeSlug((string) ($_GET['slug'] ?? ''));
                $legalProductKey = LegalProductSettings::sanitizeProductKey((string) ($_GET['product'] ?? LegalProductSettings::DEFAULT_PRODUCT_KEY));
                if (!LegalProductSettings::isLegalSlug($legalSlug)) {
                    Flash::set('error', 'Unbekannte Rechtstext-Seite.');
                    header('Location: ' . SettingsRegistry::tabUrl('agb'), true, 302);
                    exit;
                }
                WebsiteLegalVariantRepository::ensureDefaultsForPage($legalSlug);
                WebsiteLegalVariantRepository::ensureVariant(
                    $legalSlug,
                    $legalProductKey,
                    $legalProductKey === LegalProductSettings::DEFAULT_PRODUCT_KEY
                        ? 'Allgemein'
                        : ucfirst(str_replace('-', ' ', $legalProductKey))
                );
                $variant = WebsiteLegalVariantRepository::find($legalSlug, $legalProductKey);
                $legalPageTitle = LegalProductSettings::LEGAL_PAGES[$legalSlug] ?? $legalSlug;
                $legalProductLabel = 'Allgemein';
                foreach (LegalProductSettings::allProductTabs() as $tab) {
                    if ($tab['key'] === $legalProductKey) {
                        $legalProductLabel = $tab['label'];
                        break;
                    }
                }
                $legalHtml = $variant !== null
                    ? WebsiteLegalVariantRepository::extractHtmlFromLayout($variant['layout'])
                    : '';
                $legalStatus = $variant['status'] ?? WebsitePageRepository::STATUS_DRAFT;
                $canEdit = RoleResolver::isAdmin($user) || MenuRegistry::canAccessWebsite($user);
                $contentTemplate = 'modules/website-recht-variant';
                $title = $legalPageTitle;
                $currentPage = 'website-recht';
            } elseif ($page === 'website-seite-form') {
                if (empty($formError)) {
                    $websitePageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                    if ($action === 'edit' && $websitePageId > 0) {
                        $pageRow = WebsitePageRepository::findById($websitePageId);
                        if ($pageRow === null) {
                            Flash::set('error', 'Seite nicht gefunden.');
                            header('Location: /app?page=website-seiten', true, 302);
                            exit;
                        }
                        $form = $pageRow;
                        $websiteFormOptions = WebsiteFormRepository::listPublishedOptions();
                        $contentTemplate = 'modules/website-seite-form';
                        $title = 'Seite bearbeiten';
                        $currentPage = 'website-seite-form';
                    } elseif ($action === 'new') {
                        $websitePageId = null;
                        $form = WebsitePageRepository::emptyForm();
                        $websiteFormOptions = WebsiteFormRepository::listPublishedOptions();
                        $contentTemplate = 'modules/website-seite-form';
                        $title = 'Neue Seite';
                        $currentPage = 'website-seite-form';
                    } else {
                        header('Location: /app?page=website-seiten', true, 302);
                        exit;
                    }
                }
            } elseif ($page === 'website-formulare') {
                if (Database::isConfigured()) {
                    WebsiteFormRepository::ensureTables();
                    $migratedPages = WebsiteFormRepository::migrateLegacyContactBlocksInPages($user->id);
                    if ($migratedPages > 0) {
                        Flash::set('success', $migratedPages . ' Seite(n): klassische Kontaktblöcke → Formulare umgestellt.');
                        header('Location: /app?page=website-formulare', true, 302);
                        exit;
                    }
                }
                $websiteFormList = WebsiteFormRepository::list();
                $contentTemplate = 'modules/website-formulare';
                $title = 'Formulare';
                $currentPage = 'website-formulare';
            } elseif ($page === 'website-formular-form') {
                if (empty($formError)) {
                    $websiteFormId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                    if ($action === 'edit' && $websiteFormId > 0) {
                        $formRow = WebsiteFormRepository::findById($websiteFormId);
                        if ($formRow === null) {
                            Flash::set('error', 'Formular nicht gefunden.');
                            header('Location: /app?page=website-formulare', true, 302);
                            exit;
                        }
                        $form = $formRow;
                        $contentTemplate = 'modules/website-formular-form';
                        $title = 'Formular bearbeiten';
                        $currentPage = 'website-formular-form';
                    } elseif ($action === 'new') {
                        $websiteFormId = null;
                        $form = WebsiteFormRepository::emptyForm();
                        $contentTemplate = 'modules/website-formular-form';
                        $title = 'Neues Formular';
                        $currentPage = 'website-formular-form';
                    } else {
                        header('Location: /app?page=website-formulare', true, 302);
                        exit;
                    }
                }
            } elseif ($page === 'website-formular-inbox') {
                $websiteFormId = (int) ($_GET['id'] ?? 0);
                $websiteForm = WebsiteFormRepository::findById($websiteFormId);
                if ($websiteForm === null) {
                    Flash::set('error', 'Formular nicht gefunden.');
                    header('Location: /app?page=website-formulare', true, 302);
                    exit;
                }
                if ($action === 'download') {
                    $subId = (int) ($_GET['submission'] ?? 0);
                    $fileName = basename((string) ($_GET['file'] ?? ''));
                    $sub = WebsiteFormSubmissionRepository::find($subId);
                    if ($sub === null || (int) $sub['form_id'] !== $websiteFormId || $fileName === '') {
                        http_response_code(404);
                        echo 'Datei nicht gefunden.';
                        exit;
                    }
                    $rel = $websiteFormId . '/' . $subId . '/' . $fileName;
                    $abs = WebsiteFormFileStorage::absolutePath($rel);
                    $meta = null;
                    foreach ((array) ($sub['files'] ?? []) as $f) {
                        if (is_array($f) && (string) ($f['stored_name'] ?? '') === $fileName) {
                            $meta = $f;
                            break;
                        }
                    }
                    if ($abs === null || $meta === null) {
                        http_response_code(404);
                        echo 'Datei nicht gefunden.';
                        exit;
                    }
                    header('Content-Type: ' . (string) ($meta['mime'] ?? 'application/octet-stream'));
                    header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) ($meta['original_name'] ?? $fileName)) . '"');
                    header('Content-Length: ' . (string) filesize($abs));
                    readfile($abs);
                    exit;
                }
                $submissionId = (int) ($_GET['submission'] ?? 0);
                $websiteFormSubmission = null;
                if ($submissionId > 0) {
                    $websiteFormSubmission = WebsiteFormSubmissionRepository::find($submissionId);
                    if ($websiteFormSubmission !== null && (int) $websiteFormSubmission['form_id'] === $websiteFormId) {
                        WebsiteFormSubmissionRepository::markRead($submissionId, true);
                    } else {
                        $websiteFormSubmission = null;
                    }
                }
                $websiteFormSubmissions = WebsiteFormSubmissionRepository::listForForm($websiteFormId);
                $contentTemplate = 'modules/website-formular-inbox';
                $title = 'Formulareingänge';
                $currentPage = 'website-formular-inbox';
            } elseif ($page === 'website-statistik') {
                $websiteStatsDays = (int) ($_GET['days'] ?? 30);
                if (!in_array($websiteStatsDays, [7, 30, 90], true)) {
                    $websiteStatsDays = 30;
                }
                $websiteStatsSummary = ['total' => 0, 'today' => 0, 'days7' => 0, 'days30' => 0];
                $websiteStatsByDay = [];
                $websiteStatsTopPaths = [];
                $websiteStatsTopReferrers = [];
                $websiteAnalyticsLinks = WebsitePageviewTracker::externalDashboardLinks();
                if (Database::isConfigured()) {
                    WebsitePageviewRepository::ensureTables();
                    $websiteStatsSummary = WebsitePageviewRepository::summary();
                    $websiteStatsByDay = WebsitePageviewRepository::viewsByDay($websiteStatsDays);
                    $websiteStatsTopPaths = WebsitePageviewRepository::topPaths($websiteStatsDays);
                    $websiteStatsTopReferrers = WebsitePageviewRepository::topReferrers($websiteStatsDays);
                }
                $contentTemplate = 'modules/website-statistik';
                $title = 'Statistik';
                $currentPage = 'website-statistik';
            } elseif ($page === 'website-menu') {
                $websiteMenuForm = WebsiteSettings::menu();
                $websiteMenuSuggestions = WebsitePageRepository::unusedInMenu($websiteMenuForm);
                $contentTemplate = 'modules/website-menu';
                $title = 'Menü';
                $currentPage = 'website-menu';
            } elseif ($page === 'website-chrome') {
                $websiteChromeForm = WebsiteSettings::chrome();
                $contentTemplate = 'modules/website-chrome';
                $title = 'Kopf & Fuß';
                $currentPage = 'website-chrome';
            } else {
                $websiteDesignForm = WebsiteSettings::design();
                $contentTemplate = 'modules/website-design';
                $title = 'Design';
                $currentPage = 'website-design';
            }
        } elseif ($page === 'website-seiten' || $page === 'website-seite-form' || $page === 'website-formulare' || $page === 'website-formular-form' || $page === 'website-formular-inbox' || $page === 'website-statistik' || $page === 'website-menu' || $page === 'website-chrome' || $page === 'website-design') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'kichel-protokoll' && RoleResolver::isAdmin($user)) {
            $kichelLogRows = KichelProtocolRepository::recent(200);
            $contentTemplate = 'modules/kichel-protokoll';
            $title = 'Kichel-Protokoll';
            $currentPage = 'kichel-protokoll';
        } elseif ($page === 'support-freigabe' && MenuRegistry::canAccess($user, 'support-freigabe')) {
            $supportGrant = SupportAccessService::activeGrant();
            $contentTemplate = 'modules/support-freigabe';
            $title = 'Support-Freigabe';
            $currentPage = 'support-freigabe';
        } elseif ($page === 'support-zuschauen' && MenuRegistry::canAccess($user, 'support-zuschauen')) {
            $supportGrant = SupportSession::grant();
            $contentTemplate = 'modules/support-zuschauen';
            $title = 'Bildschirm zuschauen';
            $currentPage = 'support-zuschauen';
        } elseif ($page === 'kdv-dashboard' && MenuRegistry::canAccessKdv($user)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::verify($_POST['_csrf'] ?? null)) {
                if (isset($_POST['kdv_generate_api_key'])) {
                    KdvProvisionApi::generateApiKey();
                    Flash::set('success', 'Neuer API-Schlüssel wurde generiert.');
                    header('Location: /app?page=kdv-dashboard', true, 302);
                    exit;
                }
                if (isset($_POST['kdv_save_kas'])) {
                    $kasL = trim($_POST['kdv_kas_login'] ?? '');
                    $kasP = trim($_POST['kdv_kas_pass'] ?? '');
                    if ($kasL !== '') {
                        KdvConfig::set('kas_login', $kasL);
                    }
                    if ($kasP !== '') {
                        KdvConfig::set('kas_pass', $kasP);
                    }
                    Flash::set('success', 'KAS-Zugangsdaten gespeichert.');
                    header('Location: /app?page=kdv-dashboard', true, 302);
                    exit;
                }
                if (isset($_POST['kdv_save_license_server'])) {
                    $licUrl = trim((string) ($_POST['kdv_license_server_url'] ?? ''));
                    $licToken = trim((string) ($_POST['kdv_license_admin_token'] ?? ''));
                    $supportEmail = trim((string) ($_POST['kdv_support_email'] ?? ''));
                    $shopPublicUrl = trim((string) ($_POST['kdv_shop_public_url'] ?? ''));
                    if ($licUrl !== '') {
                        KdvConfig::set('license_server_url', rtrim($licUrl, '/'));
                    }
                    if ($licToken !== '') {
                        KdvConfig::set('license_admin_token', $licToken);
                    }
                    if ($supportEmail !== '') {
                        KdvConfig::set('support_email', $supportEmail);
                    }
                    if ($shopPublicUrl !== '') {
                        KdvConfig::set('shop_public_url', rtrim($shopPublicUrl, '/'));
                    }
                    Flash::set('success', 'Lizenzserver-Einstellungen gespeichert.');
                    header('Location: /app?page=kdv-dashboard', true, 302);
                    exit;
                }
            }
            $customers = KdvCustomerRepository::list();
            $stats = KdvCustomerRepository::stats();
            $contentTemplate = 'modules/kdv-dashboard';
            $title = 'KDV – SaaS-Kunden';
            $currentPage = 'kdv-dashboard';
        } elseif ($page === 'kdv-support' && MenuRegistry::canAccessKdv($user)) {
            $kdvSupportSessions = KdvSupportSessionRepository::listActive();
            $contentTemplate = 'modules/kdv-support';
            $title = 'Support-Freigaben';
            $currentPage = 'kdv-support';
        } elseif ($page === 'kdv-rumpf-wj' && MenuRegistry::canAccessKdv($user)) {
            $rumpfOrgId = (int) ($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
            if (
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && isset($_POST['mf_share_contacts_save'])
                && Csrf::verify($_POST['_csrf'] ?? null)
                && RoleResolver::canEdit($user)
                && $rumpfOrgId > 0
                && KdvOrgRepository::shareContactsColumnReady()
            ) {
                $orgRow = KdvOrgRepository::findById($rumpfOrgId);
                if ($orgRow !== null) {
                    KdvOrgRepository::save([
                        'name' => (string) ($orgRow['name'] ?? ''),
                        'billing_email' => (string) ($orgRow['billing_email'] ?? ''),
                        'notes' => (string) ($orgRow['notes'] ?? ''),
                        'share_contacts' => !empty($_POST['share_contacts']) ? 1 : 0,
                    ], $rumpfOrgId);
                    Flash::set('success', 'Shared-Contacts-Kennzeichnung gespeichert.');
                }
                header('Location: /app?page=kdv-rumpf-wj&org_id=' . $rumpfOrgId, true, 302);
                exit;
            }
            $report = RumpfWjReportService::forOrg($rumpfOrgId);
            $rumpfOrg = $report['org'];
            $rumpfFirms = $report['firms'];
            $rumpfPairs = $report['pairs'];
            $kdvOrgOptions = KdvOrgRepository::options();
            $contentTemplate = 'modules/kdv-rumpf-wj';
            $title = 'Rumpf-WJ / Umfirmierung';
            $currentPage = 'kdv-kunden';
        } elseif ($page === 'kdv-umfirmierung' && MenuRegistry::canAccessKdv($user)) {
            $formError = null;
            $fromId = (int) ($_GET['from_id'] ?? $_POST['from_id'] ?? 0);
            $predecessor = $fromId > 0 ? KdvCustomerRepository::findById($fromId) : null;
            if ($predecessor === null) {
                Flash::set('error', 'Vorgänger-Firma für Umfirmierung nicht gefunden.');
                header('Location: /app?page=kdv-kunden', true, 302);
                exit;
            }
            $umfirmForm = [
                'stichtag' => (string) ($_POST['stichtag'] ?? ''),
                'company_name' => (string) ($_POST['company_name'] ?? ''),
                'domain' => (string) ($_POST['domain'] ?? ''),
                'company_type' => (string) ($_POST['company_type'] ?? 'GmbH'),
                'gewinnermittlung' => (string) ($_POST['gewinnermittlung'] ?? 'bilanz'),
                'tax_number_note' => (string) ($_POST['tax_number_note'] ?? ''),
                'tariff' => (string) ($_POST['tariff'] ?? ($predecessor['tariff'] ?? 'basic')),
                'db_name' => (string) ($_POST['db_name'] ?? ''),
                'contact_name' => (string) ($_POST['contact_name'] ?? ($predecessor['contact_name'] ?? '')),
                'contact_email' => (string) ($_POST['contact_email'] ?? ($predecessor['contact_email'] ?? '')),
                'contact_phone' => (string) ($_POST['contact_phone'] ?? ($predecessor['contact_phone'] ?? '')),
                'provision_now' => !empty($_POST['provision_now']),
                'kas_login' => (string) ($_POST['kas_login'] ?? ($predecessor['kas_login'] ?? '')),
                'confirm_dns' => !empty($_POST['confirm_dns']),
            ];
            if (
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && isset($_POST['mf_umfirmierung_start'])
                && Csrf::verify($_POST['_csrf'] ?? null)
                && RoleResolver::canEdit($user)
            ) {
                try {
                    $result = UmfirmierungService::start($fromId, $_POST);
                    $flashMsg = 'Umfirmierung angelegt: Nachfolger #' . $result['successor_id']
                        . ' · Vorgänger #' . $result['predecessor_id'] . ' ist Archiv-Slot.';
                    if (!empty($_POST['provision_now'])) {
                        $prov = KdvProvisionGateService::run((int) $result['successor_id'], [
                            'mode' => 'auto',
                            'kas_login' => trim((string) ($_POST['kas_login'] ?? '')),
                            'kas_pass' => (string) ($_POST['kas_pass'] ?? ''),
                            'confirm_dns' => !empty($_POST['confirm_dns']),
                        ]);
                        if ($prov['ok']) {
                            $flashMsg .= ' Provision OK.';
                            if (!empty($prov['result']['install_url'])) {
                                $flashMsg .= ' Install: ' . $prov['result']['install_url'];
                            }
                            Flash::set('success', $flashMsg);
                        } else {
                            Flash::set(
                                'warning',
                                $flashMsg . ' Provision nicht ausgeführt: ' . $prov['message']
                                . ' — bitte unter CRM bereitstellen nachziehen.'
                            );
                        }
                    } else {
                        Flash::set('success', $flashMsg . ' Bitte Instanz bei Bedarf manuell provisionieren.');
                    }
                    header('Location: /app?page=kdv-kunden&action=edit&id=' . (int) $result['successor_id'], true, 302);
                    exit;
                } catch (Throwable $e) {
                    $formError = $e->getMessage();
                }
            }
            $umfirmChecklist = UmfirmierungService::checklist();
            $contentTemplate = 'modules/kdv-umfirmierung';
            $title = 'Umfirmierung';
            $currentPage = 'kdv-kunden';
        } elseif ($page === 'kdv-kunden' && MenuRegistry::canAccessKdv($user)) {
            $formError = null;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::verify($_POST['_csrf'] ?? null)) {
                $editId = ($action === 'edit' && !empty($_GET['id'])) ? (int) $_GET['id'] : null;
                $licenseAction = trim((string) ($_POST['kdv_license_action'] ?? ''));

                if ($editId && $licenseAction !== '') {
                    try {
                        if ($licenseAction === 'issue') {
                            $res = KdvLicenseService::issueNew($editId, trim((string) ($_POST['valid_to'] ?? '')) ?: null);
                            if (!$res['ok']) {
                                throw new RuntimeException($res['error'] ?? 'Lizenzanlage fehlgeschlagen.');
                            }
                            Flash::set('success', 'Neuer Lizenzschlüssel: ' . ($res['license_key'] ?? ''));
                        } elseif ($licenseAction === 'assign') {
                            $res = KdvLicenseService::assignExisting($editId, (string) ($_POST['license_key'] ?? ''));
                            if (!$res['ok']) {
                                throw new RuntimeException($res['error'] ?? 'Zuweisung fehlgeschlagen.');
                            }
                            Flash::set('success', 'Lizenzschlüssel zugewiesen.');
                        } elseif ($licenseAction === 'suspend') {
                            $res = KdvLicenseService::suspend(
                                $editId,
                                (string) ($_POST['block_reason'] ?? 'manual'),
                                trim((string) ($_POST['block_note'] ?? '')) ?: null,
                                !isset($_POST['skip_license_suspend'])
                            );
                            if (!$res['ok']) {
                                throw new RuntimeException($res['error'] ?? 'Sperre fehlgeschlagen.');
                            }
                            Flash::set('success', 'SaaS-Kunde und Lizenz gesperrt.');
                        } elseif ($licenseAction === 'unsuspend') {
                            $res = KdvLicenseService::unsuspend($editId, !isset($_POST['skip_license_activate']));
                            if (!$res['ok']) {
                                throw new RuntimeException($res['error'] ?? 'Entsperrung fehlgeschlagen.');
                            }
                            Flash::set('success', 'SaaS-Kunde und Lizenz entsperrt.');
                        } else {
                            throw new InvalidArgumentException('Unbekannte Lizenz-Aktion.');
                        }
                        header('Location: /app?page=kdv-kunden&action=edit&id=' . $editId, true, 302);
                        exit;
                    } catch (Throwable $e) {
                        $formError = $e->getMessage();
                    }
                } else {
                    try {
                        KdvCustomerRepository::save($_POST, $editId);
                        Flash::set('success', $editId ? 'SaaS-Kunde aktualisiert.' : 'SaaS-Kunde angelegt.');
                        header('Location: /app?page=kdv-kunden', true, 302);
                        exit;
                    } catch (Throwable $e) {
                        $formError = $e->getMessage();
                    }
                }
            }

            if ($action === 'delete' && !empty($_GET['id']) && Csrf::verify($_GET['_csrf'] ?? null)) {
                KdvCustomerRepository::delete((int) $_GET['id']);
                Flash::set('success', 'SaaS-Kunde gelöscht.');
                header('Location: /app?page=kdv-kunden', true, 302);
                exit;
            }

            if ($action === 'new' || $action === 'edit') {
                $customer = ($action === 'edit' && !empty($_GET['id']))
                    ? KdvCustomerRepository::findById((int) $_GET['id'])
                    : null;
                if ($action === 'edit' && $customer === null && empty($formError)) {
                    Flash::set('error', 'SaaS-Kunde nicht gefunden.');
                    header('Location: /app?page=kdv-kunden', true, 302);
                    exit;
                }
                if ($formError !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    $customer = array_merge(is_array($customer) ? $customer : [], $_POST);
                }
                $kdvOrgOptions = KdvOrgRepository::options();
                $kdvFirmOptions = KdvCustomerRepository::list();
                $contentTemplate = 'modules/kdv-kunde-form';
                $title = $action === 'edit' ? 'SaaS-Kunde bearbeiten' : 'Neuer SaaS-Kunde';
                $currentPage = 'kdv-kunden';
            } else {
                $customers = KdvCustomerRepository::list();
                $contentTemplate = 'modules/kdv-kunden';
                $title = 'SaaS-Kunden';
                $currentPage = 'kdv-kunden';
            }
        } elseif ($page === 'kdv-provision' && MenuRegistry::canAccessKdv($user)) {
            $provisionId = (int) ($_GET['id'] ?? 0);
            $customer = KdvCustomerRepository::findById($provisionId);
            $result = null;
            $provisionGateError = null;

            if ($customer === null) {
                Flash::set('error', 'SaaS-Kunde nicht gefunden.');
                header('Location: /app?page=kdv-kunden', true, 302);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::verify($_POST['_csrf'] ?? null)) {
                if (!RoleResolver::canEdit($user)) {
                    Flash::set('error', 'Keine Berechtigung.');
                    header('Location: /app?page=kdv-provision&id=' . $provisionId, true, 302);
                    exit;
                }
                $prov = KdvProvisionGateService::run($provisionId, [
                    'mode' => ((string) ($customer['firm_relation'] ?? '') === 'nachfolger') ? 'auto' : 'manual',
                    'kas_login' => trim((string) ($_POST['kas_login'] ?? '')),
                    'kas_pass' => (string) ($_POST['kas_pass'] ?? ''),
                    'confirm_dns' => !empty($_POST['confirm_dns']),
                ]);
                if ($prov['result'] !== null) {
                    $result = $prov['result'];
                } elseif (!$prov['ok']) {
                    $provisionGateError = $prov['message'];
                }
            }

            $contentTemplate = 'modules/kdv-provision';
            $title = 'CRM bereitstellen';
            $currentPage = 'kdv-kunden';
        } elseif ($page === 'bilder' && RoleResolver::isAdmin($user)) {
            MediaRepository::ensureTables();
            $mediaId = trim((string) ($_GET['id'] ?? ''));
            $mediaIsNew = false;

            if ($action === 'new') {
                $mediaIsNew = true;
                $mediaItem = null;
                $contentTemplate = 'modules/bilder-edit';
                $title = 'Bild hochladen';
                $currentPage = 'bilder';
            } elseif ($action === 'preview' && MediaId::isValid($mediaId)) {
                MediaApi::streamForEditor($user, $mediaId);
            } elseif ($action === 'edit' && MediaId::isValid($mediaId)) {
                $mediaItem = MediaRepository::find($mediaId);
                if (!$mediaItem) {
                    Flash::set('error', 'Bild nicht gefunden.');
                    header('Location: /app?page=bilder', true, 302);
                    exit;
                }
                $contentTemplate = 'modules/bilder-edit';
                $title = 'Bild bearbeiten';
                $currentPage = 'bilder';
            } else {
                $mediaList = MediaRepository::listWithUsage();
                $contentTemplate = 'modules/bilder';
                $title = 'Media';
                $currentPage = 'bilder';
            }
        } elseif ($page === 'zeiterfassung-urlaub' && MenuRegistry::canAccess($user, 'zeiterfassung-urlaub')) {
            MigrationRunner::runPending();
            $timeVacYear = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));
            $timeVacCanTeam = TimeClockService::canViewTeam($user);
            $timeVacContactId = ContactRepository::findStaffContactIdForUser($user);
            $timeVacContactLabel = '';
            $timeVacBalance = [
                'days_entitled' => 0.0,
                'days_carried' => 0.0,
                'days_used' => 0.0,
                'days_rest' => 0.0,
            ];
            $timeVacOwnList = [];
            if ($timeVacContactId !== null && $timeVacContactId > 0) {
                $sc = ContactRepository::findById($timeVacContactId);
                $timeVacContactLabel = $sc !== null
                    ? (trim($sc->displayName) !== ''
                        ? trim($sc->displayName)
                        : trim($sc->firstName . ' ' . $sc->lastName))
                    : '';
                $timeVacBalance = TimeVacationEntitlementRepository::balance($timeVacContactId, $timeVacYear);
                $timeVacOwnList = TimeAbsenceRepository::listForContact($timeVacContactId, $timeVacYear);
            }
            $timeVacPending = [];
            foreach (TimeAbsenceRepository::listByStatus('requested', 200) as $pend) {
                if ((string) ($pend['type'] ?? '') !== 'vacation') {
                    continue;
                }
                $pendCid = (int) ($pend['contact_id'] ?? 0);
                if ($pendCid > 0 && AbsenceApprovalService::canDecide($user, $pendCid)) {
                    $timeVacPending[] = $pend;
                }
            }
            $timeVacCanApprove = $timeVacPending !== [];
            $timeVacStaffOptions = ($timeVacCanTeam || $timeVacCanApprove)
                ? TimeMonthReportService::staffOptions()
                : [];
            $timeVacEntContactId = isset($_GET['ent_contact_id']) ? (int) $_GET['ent_contact_id'] : 0;
            $timeVacEntBalance = null;
            if ($timeVacCanTeam && $timeVacEntContactId > 0) {
                $timeVacEntBalance = TimeVacationEntitlementRepository::balance($timeVacEntContactId, $timeVacYear);
            }
            $timeVacPlanningStats = $timeVacCanTeam
                ? VacationPlanningReminderService::statsForYear($timeVacYear)
                : [];
            $timeVacPlanningThreshold = VacationPlanningReminderService::thresholdPercent();
            $contentTemplate = 'modules/zeiterfassung-urlaub';
            $title = 'Urlaub';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-abwesenheit' && MenuRegistry::canAccess($user, 'zeiterfassung-abwesenheit')) {
            MigrationRunner::runPending();
            $attDl = (int) ($_GET['download_attachment'] ?? 0);
            if ($attDl > 0) {
                try {
                    TimeAbsenceEvidenceStorage::sendDownload($user, $attDl);
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    header('Location: /app?page=zeiterfassung-abwesenheit', true, 302);
                    exit;
                }
            }
            $timeAbsCanTeam = TimeClockService::canViewTeam($user);
            $timeAbsYearMonth = TimeMonthReportService::normalizeYearMonth(
                isset($_GET['month']) ? (string) $_GET['month'] : null
            );
            $timeAbsContactId = ContactRepository::findStaffContactIdForUser($user);
            $timeAbsContactLabel = '';
            $timeAbsOwnList = [];
            if ($timeAbsContactId !== null && $timeAbsContactId > 0) {
                $sc = ContactRepository::findById($timeAbsContactId);
                $timeAbsContactLabel = $sc !== null
                    ? (trim($sc->displayName) !== ''
                        ? trim($sc->displayName)
                        : trim($sc->firstName . ' ' . $sc->lastName))
                    : '';
                $timeAbsOwnList = TimeAbsenceRepository::listForContact(
                    $timeAbsContactId,
                    (int) substr($timeAbsYearMonth, 0, 4)
                );
            }
            $timeAbsPending = [];
            foreach (TimeAbsenceRepository::listByStatus('requested', 200) as $pend) {
                if ((string) ($pend['type'] ?? '') === 'vacation') {
                    continue;
                }
                $pendCid = (int) ($pend['contact_id'] ?? 0);
                if ($pendCid > 0 && AbsenceApprovalService::canDecide($user, $pendCid)) {
                    $timeAbsPending[] = $pend;
                }
            }
            $timeAbsCanApprove = $timeAbsPending !== [];
            $timeAbsStaffOptions = ($timeAbsCanTeam || $timeAbsCanApprove)
                ? TimeMonthReportService::staffOptions()
                : [];
            $timeAbsHrCanEdit = $timeAbsCanTeam;
            if (!$timeAbsHrCanEdit) {
                foreach ($timeAbsStaffOptions as $opt) {
                    if (AbsenceApprovalService::canDecide($user, (int) ($opt['id'] ?? 0))) {
                        $timeAbsHrCanEdit = true;
                        break;
                    }
                }
            }
            if ($timeAbsHrCanEdit && $timeAbsStaffOptions === []) {
                $timeAbsStaffOptions = TimeMonthReportService::staffOptions();
            }
            $timeAbsHrFilterContact = (int) ($_GET['hr_contact'] ?? 0);
            $timeAbsHrFilterType = trim((string) ($_GET['hr_type'] ?? ''));
            $timeAbsHrFilterStatus = trim((string) ($_GET['hr_status'] ?? ''));
            $timeAbsHrEditId = (int) ($_GET['edit_id'] ?? 0);
            $timeAbsHrList = [];
            $timeAbsHrEdit = null;
            if ($timeAbsHrCanEdit) {
                $timeAbsHrList = TimeAbsenceRepository::listForHrFilter(
                    $timeAbsYearMonth,
                    $timeAbsHrFilterContact,
                    $timeAbsHrFilterType,
                    $timeAbsHrFilterStatus
                );
                if ($timeAbsHrEditId > 0) {
                    $timeAbsHrEdit = TimeAbsenceRepository::findById($timeAbsHrEditId);
                    if ($timeAbsHrEdit !== null) {
                        $editCid = (int) ($timeAbsHrEdit['contact_id'] ?? 0);
                        if (!AbsenceApprovalService::canDecide($user, $editCid) && !TimeClockService::canViewTeam($user)) {
                            $timeAbsHrEdit = null;
                        }
                    }
                }
            }
            $timeAbsCalendar = null;
            if ($timeAbsCanTeam) {
                $timeAbsCalendar = TimeAbsenceService::monthCalendar($timeAbsYearMonth, $timeAbsStaffOptions);
            }
            $absIdsForAtt = [];
            foreach ($timeAbsOwnList as $row) {
                if (is_array($row)) {
                    $absIdsForAtt[] = (int) ($row['id'] ?? 0);
                }
            }
            foreach ($timeAbsPending as $row) {
                if (is_array($row)) {
                    $absIdsForAtt[] = (int) ($row['id'] ?? 0);
                }
            }
            foreach ($timeAbsHrList as $row) {
                if (is_array($row)) {
                    $absIdsForAtt[] = (int) ($row['id'] ?? 0);
                }
            }
            $timeAbsAttachments = TimeAbsenceEvidenceStorage::mapForAbsences($absIdsForAtt);
            $contentTemplate = 'modules/zeiterfassung-abwesenheit';
            $title = 'Abwesenheit';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-rueckstellung' && MenuRegistry::canAccess($user, 'zeiterfassung-rueckstellung')) {
            MigrationRunner::runPending();
            $timeProvisionYear = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));
            $timeProvisionPreview = TimeProvisionService::preview($timeProvisionYear);
            $timeProvisionConfig = TimeTrackingSettings::config();
            $timeProvisionCanBook = TimeProvisionService::canBook($user);
            $timeProvisionExistingBatchId = TimeProvisionService::existingBatchId($timeProvisionYear);
            $timeProvisionDraft = null;
            try {
                if ($timeProvisionExistingBatchId === null) {
                    $timeProvisionDraft = TimeProvisionService::bookingDraft($timeProvisionPreview);
                }
            } catch (Throwable) {
                $timeProvisionDraft = null;
            }
            if (trim((string) ($_GET['download'] ?? '')) === 'csv') {
                $csv = TimeProvisionService::toCsv($timeProvisionPreview);
                $fname = sprintf('rueckstellung-urlaub-%d.csv', $timeProvisionYear);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $fname . '"');
                echo $csv;
                exit;
            }
            $contentTemplate = 'modules/zeiterfassung-rueckstellung';
            $title = 'Rückstellungen';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'lohn-lohnarten' && MenuRegistry::canAccess($user, 'lohn-lohnarten')) {
            MigrationRunner::runPending();
            PayrollWageTypeRepository::ensureReady();
            $payrollWageTypes = PayrollWageTypeRepository::all(false);
            $editWageId = (int) ($_GET['edit'] ?? 0);
            $payrollWageTypeEdit = $editWageId > 0 ? PayrollWageTypeRepository::find($editWageId) : null;
            $payrollWageCanEdit = PayrollEmployeeAccess::canEdit($user);
            $contentTemplate = 'modules/lohn-lohnarten';
            $title = 'Lohnarten';
            $currentPage = 'lohn-lohnarten';
        } elseif ($page === 'lohn-beitragstabellen' && MenuRegistry::canAccess($user, 'lohn-beitragstabellen')) {
            MigrationRunner::runPending();
            PayrollRateTableRepository::ensureReady();
            if (trim((string) ($_GET['download'] ?? '')) === 'example') {
                $csv = PayrollRateTableRepository::exampleImportCsv();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="payroll-rate-example.csv"');
                echo $csv;
                exit;
            }
            $payrollRateSets = PayrollRateTableRepository::all(false);
            $viewRateId = (int) ($_GET['view'] ?? 0);
            $payrollRateSetView = $viewRateId > 0 ? PayrollRateTableRepository::find($viewRateId) : null;
            $payrollRateParams = $payrollRateSetView !== null
                ? PayrollRateTableRepository::paramsForSet($viewRateId)
                : [];
            $payrollRateCanEdit = PayrollEmployeeAccess::canEdit($user);
            $contentTemplate = 'modules/lohn-beitragstabellen';
            $title = 'Beitragstabellen';
            $currentPage = 'lohn-beitragstabellen';
        } elseif ($page === 'lohn-monatslauf' && MenuRegistry::canAccess($user, 'lohn-monatslauf')) {
            MigrationRunner::runPending();
            PayrollRunRepository::ensureReady();
            $payrollRunMonth = trim((string) ($_GET['month'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $payrollRunMonth)) {
                $payrollRunMonth = date('Y-m');
            }
            [$py, $pm] = array_map('intval', explode('-', $payrollRunMonth));
            $payrollRuns = PayrollRunRepository::listForPeriod($py, $pm);
            $runViewId = (int) ($_GET['run'] ?? 0);
            $payrollRun = $runViewId > 0 ? PayrollRunRepository::find($runViewId) : null;
            if ($payrollRun !== null
                && ((int) ($payrollRun['period_year'] ?? 0) !== $py
                    || (int) ($payrollRun['period_month'] ?? 0) !== $pm)
            ) {
                // Run aus anderem Monat: Monat anpassen
                $payrollRunMonth = sprintf(
                    '%04d-%02d',
                    (int) $payrollRun['period_year'],
                    (int) $payrollRun['period_month']
                );
                $payrollRuns = PayrollRunRepository::listForPeriod(
                    (int) $payrollRun['period_year'],
                    (int) $payrollRun['period_month']
                );
            }
            $payrollRunEmployees = $payrollRun !== null
                ? PayrollRunRepository::employees((int) $payrollRun['id'])
                : [];
            $payrollRunEvents = $payrollRun !== null
                ? PayrollRunRepository::events((int) $payrollRun['id'])
                : [];
            $payrollRunLinesByContact = [];
            foreach ($payrollRunEmployees as $empRow) {
                $cid = (int) ($empRow['contact_id'] ?? 0);
                if ($cid > 0 && $payrollRun !== null) {
                    $payrollRunLinesByContact[$cid] = PayrollRunRepository::linesForEmployee(
                        (int) $payrollRun['id'],
                        $cid
                    );
                }
            }
            $payrollStaffOptions = PayrollRunRepository::staffContactOptions();
            $payrollRunCanEdit = PayrollRunAccess::canEditRun($user);
            $payrollRunCanApprove = PayrollRunAccess::canApprove($user);
            $payrollRunCanPostFibu = PayrollRunAccess::canPostFibu($user);
            $payrollRunCanExportSepa = PayrollRunAccess::canExportSepa($user);
            $payrollFibuLive = PayrollFibuService::isLive();
            $payrollFibuMeta = null;
            $payrollFibuPreview = null;
            $payrollFibuPreviewError = null;
            $payrollFibuShow = trim((string) ($_GET['fibu'] ?? '')) === '1'
                || ($payrollRun !== null
                    && (string) ($payrollRun['status'] ?? '') === PayrollRunRepository::STATUS_LOCKED);
            if ($payrollRun !== null) {
                PayrollFibuService::ensureReady();
                $payrollFibuMeta = PayrollFibuService::findMetaByRunId((int) $payrollRun['id']);
                if ($payrollFibuShow || $payrollFibuMeta !== null) {
                    try {
                        $st = (string) ($payrollRun['status'] ?? '');
                        if (in_array($st, [
                            PayrollRunRepository::STATUS_APPROVED,
                            PayrollRunRepository::STATUS_LOCKED,
                        ], true)) {
                            $payrollFibuPreview = PayrollFibuService::preview((int) $payrollRun['id']);
                        }
                    } catch (Throwable $e) {
                        $payrollFibuPreviewError = $e->getMessage();
                    }
                }
            }
            $payrollFibuRoleLabels = PayrollFibuService::roleLabels();
            $payrollFibuChecklist = PayrollFibuService::auditorChecklist();

            $payrollSepaLive = PayrollSepaService::isLive();
            $payrollSepaExports = [];
            $payrollSepaPreview = null;
            $payrollSepaPreviewError = null;
            $payrollSepaShow = trim((string) ($_GET['sepa'] ?? '')) === '1'
                || ($payrollRun !== null
                    && (string) ($payrollRun['status'] ?? '') === PayrollRunRepository::STATUS_LOCKED);
            $payrollSepaChecklist = PayrollSepaService::auditorChecklist();
            if ($payrollRun !== null) {
                PayrollSepaService::ensureReady();
                $payrollSepaExports = PayrollSepaService::listExportsForRun((int) $payrollRun['id']);
                $sepaDlId = (int) ($_GET['sepa_dl'] ?? 0);
                if ($sepaDlId > 0 && PayrollRunAccess::canExportSepa($user)) {
                    $sepaMeta = PayrollSepaService::findExport($sepaDlId);
                    if (
                        $sepaMeta !== null
                        && (int) ($sepaMeta['run_id'] ?? 0) === (int) $payrollRun['id']
                    ) {
                        try {
                            $xml = PayrollSepaService::readStoredXml($sepaDlId);
                        } catch (Throwable $e) {
                            Flash::set('error', $e->getMessage());
                            header(
                                'Location: /app?page=lohn-monatslauf&month=' . rawurlencode($payrollRunMonth)
                                . '&run=' . (int) $payrollRun['id'] . '&sepa=1',
                                true,
                                302
                            );
                            exit;
                        }
                        if ($xml === null) {
                            http_response_code(404);
                            exit('SEPA-Datei nicht gefunden.');
                        }
                        $dlName = (string) ($sepaMeta['filename'] ?? ('lohn-sepa-' . $sepaDlId . '.xml'));
                        header('Content-Type: application/xml; charset=UTF-8');
                        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $dlName) . '"');
                        header('Content-Length: ' . (string) strlen($xml));
                        header('X-Content-Type-Options: nosniff');
                        echo $xml;
                        exit;
                    }
                }
                if ($payrollSepaShow || $payrollSepaExports !== []) {
                    try {
                        $st = (string) ($payrollRun['status'] ?? '');
                        if ($st === PayrollRunRepository::STATUS_LOCKED) {
                            $execQ = trim((string) ($_GET['exec_date'] ?? ''));
                            $payrollSepaPreview = PayrollSepaService::preview(
                                (int) $payrollRun['id'],
                                $execQ !== '' ? $execQ : null
                            );
                        }
                    } catch (Throwable $e) {
                        $payrollSepaPreviewError = $e->getMessage();
                    }
                }
            }

            $contentTemplate = 'modules/lohn-monatslauf';
            $title = 'Monatslauf';
            $currentPage = 'lohn-monatslauf';
        } elseif ($page === 'lohn-lohnkonto' && MenuRegistry::canAccess($user, 'lohn-lohnkonto')) {
            MigrationRunner::runPending();
            PayrollDocumentRepository::ensureReady();
            $lohnkontoYear = (int) ($_GET['year'] ?? date('Y'));
            if ($lohnkontoYear < 2000 || $lohnkontoYear > 2100) {
                $lohnkontoYear = (int) date('Y');
            }
            $lohnkontoContactId = (int) ($_GET['contact_id'] ?? 0);
            $lohnkontoIncludeDrafts = trim((string) ($_GET['drafts'] ?? '')) === '1';
            $lohnkontoStaffOptions = PayrollRunRepository::staffContactOptions();
            $lohnkontoContactLabel = '';
            if ($lohnkontoContactId > 0) {
                $lohnkontoContactLabel = PayrollRunRepository::contactLabel($lohnkontoContactId);
            }

            $dl = trim((string) ($_GET['download'] ?? ''));
            if ($dl === 'pdf') {
                $docId = (int) ($_GET['doc'] ?? 0);
                $doc = $docId > 0 ? PayrollDocumentRepository::find($docId) : null;
                if ($doc === null || (int) ($doc['is_final'] ?? 0) !== 1) {
                    http_response_code(404);
                    exit('PDF nicht gefunden.');
                }
                try {
                    $bin = PayrollDocumentRepository::readBinary($docId);
                } catch (Throwable $e) {
                    http_response_code(500);
                    exit($e->getMessage());
                }
                if ($bin === null) {
                    http_response_code(404);
                    exit('PDF-Datei fehlt.');
                }
                $fname = basename((string) ($doc['storage_path'] ?? ('lohn-' . $docId . '.pdf')));
                $safe = str_replace(['"', "\r", "\n"], '', $fname);
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $safe . '"');
                header('Content-Length: ' . (string) strlen($bin));
                header('X-Content-Type-Options: nosniff');
                echo $bin;
                exit;
            }
            if ($dl === 'csv') {
                if ($lohnkontoContactId < 1) {
                    Flash::set('error', 'Für CSV bitte Mitarbeiter wählen.');
                    header('Location: /app?page=lohn-lohnkonto&year=' . $lohnkontoYear, true, 302);
                    exit;
                }
                $csvRows = PayrollLohnkontoService::rowsForContactYear(
                    $lohnkontoContactId,
                    $lohnkontoYear,
                    $lohnkontoIncludeDrafts
                );
                $csvTotals = PayrollLohnkontoService::yearTotals($csvRows);
                $csv = PayrollLohnkontoService::toCsv(
                    $lohnkontoContactId,
                    $lohnkontoContactLabel !== '' ? $lohnkontoContactLabel : ('#' . $lohnkontoContactId),
                    $lohnkontoYear,
                    $csvRows,
                    $csvTotals
                );
                $csvName = PayrollLohnkontoService::csvFilename($lohnkontoContactId, $lohnkontoYear);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $csvName . '"');
                echo $csv;
                exit;
            }

            $lohnkontoRows = $lohnkontoContactId > 0
                ? PayrollLohnkontoService::rowsForContactYear(
                    $lohnkontoContactId,
                    $lohnkontoYear,
                    $lohnkontoIncludeDrafts
                )
                : [];
            $lohnkontoTotals = PayrollLohnkontoService::yearTotals($lohnkontoRows);
            $contentTemplate = 'modules/lohn-lohnkonto';
            $title = 'Lohnkonto';
            $currentPage = 'lohn-lohnkonto';
        } elseif ($page === 'lohn-auswertungen' && MenuRegistry::canAccess($user, 'lohn-auswertungen')) {
            MigrationRunner::runPending();
            PayrollRunRepository::ensureReady();
            PayrollSvPortalService::ensureReady();
            $payrollSvMonth = trim((string) ($_GET['month'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $payrollSvMonth)) {
                $payrollSvMonth = date('Y-m');
            }
            [$svY, $svM] = array_map('intval', explode('-', $payrollSvMonth));
            $payrollSvRuns = PayrollRunRepository::listForPeriod($svY, $svM);
            $payrollSvRunId = (int) ($_GET['run'] ?? 0);
            $payrollSvRun = $payrollSvRunId > 0 ? PayrollRunRepository::find($payrollSvRunId) : null;
            if ($payrollSvRun === null) {
                foreach ($payrollSvRuns as $cand) {
                    if (
                        (string) ($cand['status'] ?? '') === PayrollRunRepository::STATUS_LOCKED
                        && empty($cand['is_superseded'])
                    ) {
                        $payrollSvRun = $cand;
                        $payrollSvRunId = (int) ($cand['id'] ?? 0);
                        break;
                    }
                }
            }
            $payrollSvTab = trim((string) ($_GET['tab'] ?? 'beitrag'));
            if (!in_array($payrollSvTab, ['beitrag', 'stamm'], true)) {
                $payrollSvTab = 'beitrag';
            }
            $payrollSvBeitrag = null;
            $payrollSvStamm = null;
            $payrollSvError = null;
            $payrollSvExports = [];
            $payrollSvChecklist = PayrollSvPortalService::auditorChecklist();

            if ($payrollSvRun !== null) {
                $payrollSvExports = PayrollSvPortalService::listExportsForRun((int) $payrollSvRun['id']);
                $svDl = (int) ($_GET['sv_dl'] ?? 0);
                if ($svDl > 0) {
                    $svMeta = PayrollSvPortalService::findExport($svDl);
                    if (
                        $svMeta !== null
                        && (int) ($svMeta['run_id'] ?? 0) === (int) $payrollSvRun['id']
                    ) {
                        try {
                            $csvBin = PayrollSvPortalService::readStoredCsv($svDl);
                        } catch (Throwable $e) {
                            Flash::set('error', $e->getMessage());
                            header(
                                'Location: /app?page=lohn-auswertungen&month=' . rawurlencode($payrollSvMonth)
                                . '&run=' . (int) $payrollSvRun['id'],
                                true,
                                302
                            );
                            exit;
                        }
                        if ($csvBin === null) {
                            http_response_code(404);
                            exit('SV-CSV nicht gefunden.');
                        }
                        $dlName = (string) ($svMeta['filename'] ?? ('lohn-sv-' . $svDl . '.csv'));
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $dlName) . '"');
                        header('X-Content-Type-Options: nosniff');
                        echo $csvBin;
                        exit;
                    }
                }
                if (trim((string) ($_GET['download'] ?? '')) === 'csv') {
                    $kind = trim((string) ($_GET['kind'] ?? PayrollSvPortalService::KIND_COMBINED));
                    try {
                        $exported = PayrollSvPortalService::exportCsv(
                            (int) $payrollSvRun['id'],
                            $kind,
                            (int) $user->id,
                            'Kontroll-CSV SV-Portal'
                        );
                        header('Content-Type: text/csv; charset=utf-8');
                        header(
                            'Content-Disposition: attachment; filename="'
                            . str_replace('"', '', (string) $exported['filename']) . '"'
                        );
                        header('X-Content-Type-Options: nosniff');
                        echo $exported['csv'];
                        exit;
                    } catch (Throwable $e) {
                        $payrollSvError = $e->getMessage();
                    }
                }
                try {
                    $combined = PayrollSvPortalService::previewCombined((int) $payrollSvRun['id']);
                    $payrollSvBeitrag = $combined['beitrag'];
                    $payrollSvStamm = $combined['stamm'];
                } catch (Throwable $e) {
                    $payrollSvError = $e->getMessage();
                }
            }

            $contentTemplate = 'modules/lohn-auswertungen';
            $title = 'SV-Portal / Auswertungen';
            $currentPage = 'lohn-auswertungen';
        } elseif ($page === 'lohn-elster' && MenuRegistry::canAccess($user, 'lohn-elster')) {
            MigrationRunner::runPending();
            PayrollRunRepository::ensureReady();
            PayrollElsterService::ensureReady();
            $payrollElsterMonth = trim((string) ($_GET['month'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $payrollElsterMonth)) {
                $payrollElsterMonth = date('Y-m');
            }
            [$elY, $elM] = array_map('intval', explode('-', $payrollElsterMonth));
            $payrollElsterTab = trim((string) ($_GET['tab'] ?? 'lsta'));
            if (!in_array($payrollElsterTab, ['lsta', 'lstb'], true)) {
                $payrollElsterTab = 'lsta';
            }
            $payrollElsterLsta = null;
            $payrollElsterLstb = null;
            $payrollElsterError = null;
            $payrollElsterChecklist = PayrollElsterService::auditorChecklist();
            $payrollElsterCanTest = PayrollRunAccess::canSubmitLstaTest($user);
            $payrollElsterLstaLive = PayrollElsterService::isLstaLiveFlag();
            $payrollElsterServerOk = class_exists('ElsterSettings') && ElsterSettings::serverSupportsEric();
            $payrollElsterEricReady = PayrollElsterService::canAttemptEricTest();
            $payrollElsterSubmissions = PayrollElsterService::listRecentSubmissions(30);

            if (trim((string) ($_GET['download'] ?? '')) === 'csv') {
                try {
                    if ($payrollElsterTab === 'lstb') {
                        $prev = PayrollElsterService::previewLstb($elY);
                        $csv = PayrollElsterService::toCsvLstb($prev);
                        $fname = sprintf('lohn-lstb-%04d.csv', $elY);
                    } else {
                        $prev = PayrollElsterService::previewLsta($elY, $elM);
                        $csv = PayrollElsterService::toCsvLsta($prev);
                        $fname = sprintf('lohn-lsta-%04d-%02d.csv', $elY, $elM);
                    }
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $fname . '"');
                    header('X-Content-Type-Options: nosniff');
                    echo $csv;
                    exit;
                } catch (Throwable $e) {
                    $payrollElsterError = $e->getMessage();
                }
            }

            try {
                $payrollElsterLsta = PayrollElsterService::previewLsta($elY, $elM);
            } catch (Throwable $e) {
                $payrollElsterError = $e->getMessage();
            }
            try {
                $payrollElsterLstb = PayrollElsterService::previewLstb($elY);
            } catch (Throwable $e) {
                if ($payrollElsterError === null) {
                    $payrollElsterError = $e->getMessage();
                }
            }

            $contentTemplate = 'modules/lohn-elster';
            $title = 'Lohnsteuer ELSTER';
            $currentPage = 'lohn-elster';
        } elseif ($page === 'zeiterfassung-lohnexport' && MenuRegistry::canAccess($user, 'zeiterfassung-lohnexport')) {
            MigrationRunner::runPending();
            $rawMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : '';
            if ($rawMonth === '' && isset($_SESSION['dg_payroll_month'])) {
                $rawMonth = (string) $_SESSION['dg_payroll_month'];
            }
            $timePayrollYearMonth = TimeMonthReportService::normalizeYearMonth($rawMonth !== '' ? $rawMonth : null);
            $_SESSION['dg_payroll_month'] = $timePayrollYearMonth;

            $isFetchDl = trim((string) ($_GET['fetch_download'] ?? '')) === '1';
            $isExportDl = in_array(trim((string) ($_GET['download'] ?? '')), ['csv', 'datev', 'lexoffice'], true);
            $getMonthRaw = isset($_GET['month']) ? trim((string) $_GET['month']) : null;
            if (!$isFetchDl && !$isExportDl && ($getMonthRaw === null || $getMonthRaw === '')) {
                $qs = 'page=zeiterfassung-lohnexport&month=' . rawurlencode($timePayrollYearMonth);
                if (trim((string) ($_GET['autodl'] ?? '')) === '1') {
                    $qs .= '&autodl=1';
                }
                if (trim((string) ($_GET['saved'] ?? '')) === '1') {
                    $qs .= '&saved=1';
                }
                header('Location: /app?' . $qs, true, 302);
                exit;
            }

            // Fertige Export-Datei aus Session ausliefern (nach Redirect, damit Protokoll sichtbar ist)
            if ($isFetchDl) {
                $pending = $_SESSION['dg_payroll_pending_download'] ?? null;
                unset($_SESSION['dg_payroll_pending_download']);
                if (is_array($pending)
                    && isset($pending['csv'], $pending['filename'])
                    && is_string($pending['csv'])
                    && is_string($pending['filename'])
                    && $pending['filename'] !== ''
                ) {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $pending['filename'] . '"');
                    header('Cache-Control: no-store');
                    echo $pending['csv'];
                    exit;
                }
                header(
                    'Location: /app?page=zeiterfassung-lohnexport&month=' . rawurlencode($timePayrollYearMonth),
                    true,
                    302
                );
                exit;
            }

            $dl = trim((string) ($_GET['download'] ?? ''));
            $timePayrollAutoDownload = false;
            if ($dl === 'csv' || $dl === 'datev' || $dl === 'lexoffice') {
                try {
                    $exported = match ($dl) {
                        'datev' => TimePayrollExportService::exportDatev($user, $timePayrollYearMonth),
                        'lexoffice' => TimePayrollExportService::exportLexoffice($user, $timePayrollYearMonth),
                        default => TimePayrollExportService::exportCsv($user, $timePayrollYearMonth),
                    };
                    $_SESSION['dg_payroll_pending_download'] = [
                        'filename' => (string) $exported['filename'],
                        'csv' => (string) $exported['csv'],
                    ];
                    Flash::set(
                        'success',
                        sprintf(
                            'Export „%s“ erstellt (%d Zeilen). Überstunden-Auszahlung abgebucht (soweit vorgesehen).',
                            (string) $exported['filename'],
                            (int) ($exported['row_count'] ?? 0)
                        )
                    );
                    header(
                        'Location: /app?page=zeiterfassung-lohnexport&month='
                        . rawurlencode($timePayrollYearMonth)
                        . '&autodl=1',
                        true,
                        302
                    );
                    exit;
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    header(
                        'Location: /app?page=zeiterfassung-lohnexport&month=' . rawurlencode($timePayrollYearMonth),
                        true,
                        302
                    );
                    exit;
                }
            }

            header('Cache-Control: no-store, no-cache, must-revalidate');
            $timePayrollDataset = TimePayrollExportService::monthDataset($timePayrollYearMonth);
            $timePayrollExports = TimePayrollExportRepository::listRecent(40);
            $timePayrollDatevSettings = DatevExportSettings::config();
            $timePayrollDatevConfigured = DatevExportSettings::isConfigured();
            $timePayrollAutoDownload = trim((string) ($_GET['autodl'] ?? '')) === '1'
                && isset($_SESSION['dg_payroll_pending_download']);
            $contentTemplate = 'modules/zeiterfassung-lohnexport';
            $title = 'Lohn-Export';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-stundenimport' && MenuRegistry::canAccess($user, 'zeiterfassung-stundenimport')) {
            MigrationRunner::runPending();
            if (trim((string) ($_GET['action'] ?? '')) === 'template') {
                TimeHoursImportService::sendTemplateDownload();
            }
            $timeHoursImportErrors = [];
            if (isset($_SESSION['dg_time_hours_import_errors']) && is_array($_SESSION['dg_time_hours_import_errors'])) {
                $timeHoursImportErrors = $_SESSION['dg_time_hours_import_errors'];
                unset($_SESSION['dg_time_hours_import_errors']);
            }
            $contentTemplate = 'modules/zeiterfassung-stundenimport';
            $title = 'Stunden-Import';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-schichten' && MenuRegistry::canAccess($user, 'zeiterfassung-schichten')) {
            MigrationRunner::runPending();
            $weekRaw = isset($_GET['week']) ? (string) $_GET['week'] : date('Y-m-d');
            $timeShiftWeekMonday = TimeShiftAssignmentRepository::mondayOfWeek($weekRaw);
            $timeShiftWeekDates = TimeShiftAssignmentRepository::weekDates($timeShiftWeekMonday);
            $timeShiftStaff = TimeMonthReportService::staffOptions();
            $timeShiftActiveTemplates = TimeShiftTemplateRepository::all(true);
            $sunday = $timeShiftWeekDates[6] ?? $timeShiftWeekMonday;
            $timeShiftAssignmentMap = TimeShiftAssignmentRepository::mapForRange($timeShiftWeekMonday, $sunday);
            $contentTemplate = 'modules/zeiterfassung-schichten';
            $title = 'Schichtplan';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-schicht-vorlagen' && MenuRegistry::canAccess($user, 'zeiterfassung-schicht-vorlagen')) {
            MigrationRunner::runPending();
            $editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $timeShiftTemplateEdit = $editId > 0 ? TimeShiftTemplateRepository::findById($editId) : null;
            $timeShiftTemplates = TimeShiftTemplateRepository::all(false);
            $contentTemplate = 'modules/zeiterfassung-schicht-vorlagen';
            $title = 'Schicht-Vorlagen';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-konto' && MenuRegistry::canAccess($user, 'zeiterfassung-konto')) {
            MigrationRunner::runPending();
            $attDl = (int) ($_GET['download_attachment'] ?? 0);
            if ($attDl > 0) {
                try {
                    TimeCorrectionEvidenceStorage::sendDownload($user, $attDl);
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    $backCid = (int) ($_GET['contact_id'] ?? 0);
                    header(
                        'Location: /app?page=zeiterfassung-konto'
                        . ($backCid > 0 ? '&contact_id=' . $backCid : ''),
                        true,
                        302
                    );
                    exit;
                }
            }
            $timeKontoStaffOptions = TimeMonthReportService::staffOptions();
            $requested = isset($_GET['contact_id']) ? (int) $_GET['contact_id'] : 0;
            $timeKontoContactId = TimeMonthReportService::resolveContactId(
                $user,
                $requested > 0 ? $requested : null
            );
            if ($timeKontoContactId === null && $timeKontoStaffOptions !== []) {
                $timeKontoContactId = (int) ($timeKontoStaffOptions[0]['id'] ?? 0);
            }
            $timeKontoContactLabel = '';
            $timeKontoBalanceMinutes = 0;
            $timeKontoLots = [];
            $timeKontoCorrections = [];
            $timeKontoReductions = [];
            if ($timeKontoContactId !== null && $timeKontoContactId > 0) {
                foreach ($timeKontoStaffOptions as $opt) {
                    if ((int) ($opt['id'] ?? 0) === $timeKontoContactId) {
                        $timeKontoContactLabel = (string) ($opt['label'] ?? '');
                        break;
                    }
                }
                OvertimeLotRepository::syncAccrualsFromWorkDays($timeKontoContactId);
                $timeKontoBalanceMinutes = OvertimeLotRepository::sumRemainingMinutes($timeKontoContactId);
                $timeKontoLots = OvertimeLotRepository::listOpenLots($timeKontoContactId);
                $timeKontoCorrections = TimeCorrectionRepository::listForContact($timeKontoContactId);
                $corrIds = [];
                foreach ($timeKontoCorrections as $corrRow) {
                    $corrIds[] = (int) ($corrRow['id'] ?? 0);
                }
                $attMap = TimeCorrectionEvidenceStorage::mapForCorrections($corrIds);
                foreach ($timeKontoCorrections as $i => $corrRow) {
                    $cid = (int) ($corrRow['id'] ?? 0);
                    $timeKontoCorrections[$i]['attachments'] = $attMap[$cid] ?? [];
                }
                $timeKontoReductions = OvertimeLotRepository::listReductions($timeKontoContactId);
            }
            $contentTemplate = 'modules/zeiterfassung-konto';
            $title = 'Korrektur & Überstundenkonto';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-monat' && MenuRegistry::canAccess($user, 'zeiterfassung-monat')) {
            $timeMonthYearMonth = TimeMonthReportService::normalizeYearMonth(
                isset($_GET['month']) ? (string) $_GET['month'] : null
            );
            $timeMonthCanTeam = TimeClockService::canViewTeam($user);
            $timeMonthStaffOptions = $timeMonthCanTeam ? TimeMonthReportService::staffOptions() : [];
            $requestedContact = isset($_GET['contact_id']) ? (int) $_GET['contact_id'] : 0;
            $timeMonthContactId = TimeMonthReportService::resolveContactId(
                $user,
                $requestedContact > 0 ? $requestedContact : null
            );
            $timeMonthReport = null;
            if ($timeMonthContactId !== null && $timeMonthContactId > 0) {
                try {
                    $timeMonthReport = TimeMonthReportService::monthReport($timeMonthContactId, $timeMonthYearMonth);
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                    $timeMonthReport = null;
                }
            }
            if (
                trim((string) ($_GET['download'] ?? '')) === 'csv'
                && is_array($timeMonthReport)
            ) {
                $csv = TimeMonthReportService::toCsv($timeMonthReport);
                $fname = sprintf(
                    'zeiterfassung-%s-%d.csv',
                    $timeMonthYearMonth,
                    (int) ($timeMonthReport['contact_id'] ?? 0)
                );
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $fname . '"');
                echo $csv;
                exit;
            }
            $contentTemplate = 'modules/zeiterfassung-monat';
            $title = 'Monatsblatt';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-team' && MenuRegistry::canAccess($user, 'zeiterfassung-team')) {
            $contentTemplate = 'modules/zeiterfassung-team';
            $title = 'Team heute';
            $currentPage = 'zeiterfassung';
            $timeClockTeam = TimeClockService::teamToday();
            $overtimeReminders = OvertimeReminderService::pendingForUi();
        } elseif ($page === 'zeiterfassung' && MenuRegistry::canAccess($user, 'zeiterfassung')) {
            $timeClockContactId = ContactRepository::findStaffContactIdForUser($user);
            $timeClockEmployeeLabel = '';
            if ($timeClockContactId !== null) {
                $staffContact = ContactRepository::findById($timeClockContactId);
                if ($staffContact !== null) {
                    $timeClockEmployeeLabel = trim($staffContact->displayName);
                    if ($timeClockEmployeeLabel === '') {
                        $timeClockEmployeeLabel = trim($staffContact->companyName);
                    }
                }
            }
            $timeClockSummary = $timeClockContactId !== null
                ? TimeClockService::daySummary($timeClockContactId)
                : ['events' => [], 'worked_display' => '0:00', 'break_display' => '0:00', 'scheduled_display' => '0:00', 'warnings' => [], 'status' => ['state' => 'off', 'label' => 'Nicht eingestempelt', 'since_display' => null]];
            $timeClockStatus = is_array($timeClockSummary['status'] ?? null)
                ? $timeClockSummary['status']
                : TimeClockService::currentStatus((int) $timeClockContactId);
            $timeClockCanTeam = TimeClockService::canViewTeam($user);
            $timeClockHasKioskPin = $timeClockContactId !== null && TimeKioskPinRepository::hasPin($timeClockContactId);
            $contentTemplate = 'modules/zeiterfassung';
            $title = 'Zeiterfassung';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'zeiterfassung-kiosk' && MenuRegistry::canAccess($user, 'zeiterfassung-kiosk')) {
            MigrationRunner::runPending();
            TimeKioskService::ensureDraftWebsitePage((int) ($user->id ?? 0));
            $kioskPendingResets = TimeKioskPinRepository::listPending(50);
            $kioskStaffOptions = TimeMonthReportService::staffOptions();
            $contentTemplate = 'modules/zeiterfassung-kiosk';
            $title = 'Stempeluhr-PIN';
            $currentPage = 'zeiterfassung';
        } elseif ($page === 'terminkalender' && MenuRegistry::canAccess($user, 'terminkalender')) {
            $bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $bookingSearch = trim((string) ($_GET['s'] ?? ''));
            $bookingPage = max(1, (int) ($_GET['paged'] ?? 1));

            if ($action === 'new') {
                if (!$canEdit) {
                    header('Location: ' . RoleResolver::homePath($user), true, 302);
                    exit;
                }
                $contentTemplate = 'modules/terminkalender-form';
                $title = 'Neuer Termin';
                $currentPage = 'terminkalender';
                $form = BookingRepository::emptyForm();
                $formError = null;
            } elseif ($action === 'edit' && $bookingId > 0) {
                if (!$canEdit) {
                    header('Location: /app?page=terminkalender&id=' . $bookingId, true, 302);
                    exit;
                }
                $booking = BookingRepository::findById($bookingId);
                if (!$booking) {
                    header('Location: /app?page=terminkalender', true, 302);
                    exit;
                }
                $contentTemplate = 'modules/terminkalender-form';
                $title = 'Termin bearbeiten';
                $currentPage = 'terminkalender';
                $form = BookingRepository::toForm($booking);
                $formError = null;
            } elseif ($bookingId > 0) {
                if ($canEdit) {
                    header('Location: /app?page=terminkalender&action=edit&id=' . $bookingId, true, 302);
                    exit;
                }
                $booking = BookingRepository::findById($bookingId);
                if (!$booking) {
                    header('Location: /app?page=terminkalender', true, 302);
                    exit;
                }
                $contentTemplate = 'modules/terminkalender-detail';
                $title = $booking->customerName;
                $currentPage = 'terminkalender';
            } else {
                $bookingList = BookingRepository::paginate($bookingSearch, $bookingPage);
                $contentTemplate = 'modules/terminkalender';
                $title = 'Terminkalender';
                $currentPage = 'terminkalender';
            }
        } elseif ($page === 'post') {
            if (!MenuRegistry::canAccess($user, 'post')) {
                header('Location: /app', true, 302);
                exit;
            }

            if (isset($_GET['mail_archive'])) {
                $mailId = (int) $_GET['mail_archive'];
                $row = MailLogRepository::findById($mailId);
                $mailboxId = (int) ($row['mailbox_id'] ?? 0);
                if (
                    $row === null
                    || empty($row['storage_path'])
                    || !MailboxRepository::userCanAccess($user, $mailboxId)
                ) {
                    http_response_code(404);
                    header('Content-Type: text/plain; charset=utf-8');
                    exit('Archiv nicht gefunden.');
                }
                $absolute = MailArchiveStorage::absolutePath((string) $row['storage_path']);
                MailAttachmentGuard::sendFile($absolute, 'mail-' . $mailId . '.eml', 'message/rfc822');
            }

            if (isset($_GET['mail_attachment'])) {
                $partIndex = max(0, (int) $_GET['mail_attachment']);
                $mailId = (int) ($_GET['id'] ?? 0);
                $imapUid = (int) ($_GET['uid'] ?? 0);
                $attMailboxId = max(0, (int) ($_GET['mailbox'] ?? 0));
                $attFolder = trim((string) ($_GET['folder'] ?? 'INBOX'));
                $attachments = [];
                if ($mailId > 0) {
                    $row = MailLogRepository::findById($mailId);
                    $mailboxId = (int) ($row['mailbox_id'] ?? 0);
                    if ($row === null || empty($row['storage_path']) || !MailboxRepository::userCanAccess($user, $mailboxId)) {
                        http_response_code(404);
                        header('Content-Type: text/plain; charset=utf-8');
                        exit('Anhang nicht gefunden.');
                    }
                    $absolute = MailArchiveStorage::absolutePath((string) $row['storage_path']);
                    $attachments = MailMimeReader::parseFile($absolute)['attachments'] ?? [];
                } elseif ($imapUid > 0 && $attMailboxId > 0 && MailboxRepository::userCanAccess($user, $attMailboxId)) {
                    $imapMailbox = MailboxRepository::findById($attMailboxId);
                    if ($imapMailbox !== null) {
                        $imapPath = MailFolderCatalog::imapPathForView($attFolder !== '' ? $attFolder : 'INBOX', $imapMailbox);
                        $raw = ImapMailboxClient::fetchRawMessage($imapMailbox, $imapPath, $imapUid);
                        if ($raw !== null) {
                            if (strlen($raw) > MailAttachmentGuard::MAX_BYTES * 2) {
                                http_response_code(413);
                                header('Content-Type: text/plain; charset=utf-8');
                                exit('Nachricht zu groß für Anhang-Download.');
                            }
                            $attachments = MailMimeReader::parse($raw)['attachments'] ?? [];
                        }
                    }
                }
                if (!isset($attachments[$partIndex])) {
                    http_response_code(404);
                    header('Content-Type: text/plain; charset=utf-8');
                    exit('Anhang nicht gefunden.');
                }
                $att = $attachments[$partIndex];
                MailAttachmentGuard::sendContent(
                    (string) ($att['filename'] ?? 'anhang.bin'),
                    (string) ($att['mime'] ?? 'application/octet-stream'),
                    (string) ($att['content'] ?? '')
                );
            }

            $contentTemplate = 'modules/post';
            $title = 'E-Mail-Postfächer';
            $currentPage = 'post';
            $postMailboxes = MailboxRepository::accessibleForUser($user);
            $postSendableMailboxes = MailboxRepository::sendableForUser($user);
            $mailboxIds = array_values(array_filter(array_map(
                static fn(array $b): int => (int) ($b['id'] ?? 0),
                $postMailboxes
            )));
            $postMailboxFilter = max(0, (int) ($_GET['mailbox'] ?? 0));
            if ($postMailboxFilter > 0 && !MailboxRepository::userCanAccess($user, $postMailboxFilter)) {
                Flash::set('error', 'Kein Zugriff auf dieses Postfach.');
                header('Location: /app?page=post', true, 302);
                exit;
            }
            $postFolder = trim((string) ($_GET['folder'] ?? 'INBOX'));
            if ($postFolder === '') {
                $postFolder = 'INBOX';
            }
            $postCompose = !empty($_GET['compose']);
            $postSearchQuery = trim((string) ($_GET['q'] ?? ''));
            $postSearchContactId = max(0, (int) ($_GET['contact'] ?? 0));
            $postSearchMode = $postSearchQuery !== '' || $postSearchContactId > 0;
            $postListMode = !$postCompose
                && !$postSearchMode
                && $postMailboxFilter <= 0
                && (int) ($_GET['id'] ?? 0) <= 0
                && (int) ($_GET['uid'] ?? 0) <= 0
                && (int) ($_GET['reply'] ?? 0) <= 0
                && (int) ($_GET['forward'] ?? 0) <= 0;
            $postLinkContext = [
                'contact' => null,
                'suggested' => null,
                'voucher' => null,
                'party_email' => '',
            ];
            $postMailboxCards = $postListMode ? MailboxListService::cardsForUser($user) : [];
            $postFolders = $postListMode ? [] : MailFolderCatalog::foldersForView($postMailboxes, $postMailboxFilter);
            $postFolderLabel = MailFolderLabels::labelForPath($postFolder);
            $postIsSentFolder = MailFolderCatalog::usesLocalSent($postFolder);
            $postImapLive = false;
            $postUnreadCount = MailLogRepository::countUnreadForMailboxes($mailboxIds);
            $postMessage = null;
            $postInbox = [];
            $postComposeForm = [];
            if ($postCompose && isset($_SESSION['dg_post_compose']) && is_array($_SESSION['dg_post_compose'])) {
                $postComposeForm = $_SESSION['dg_post_compose'];
                unset($_SESSION['dg_post_compose']);
            }

            $replyId = (int) ($_GET['reply'] ?? 0);
            if ($replyId > 0) {
                $replyMsg = MailLogRepository::findById($replyId);
                $replyMailboxId = (int) ($replyMsg['mailbox_id'] ?? 0);
                if (
                    $replyMsg !== null
                    && ($replyMsg['direction'] ?? '') === 'in'
                    && MailboxRepository::userCanAccess($user, $replyMailboxId)
                ) {
                    $postCompose = true;
                    $postComposeForm = PostMailComposer::composeFromMessage($replyMsg, 'reply');
                } else {
                    header('Location: /app?page=post', true, 302);
                    exit;
                }
            }

            $forwardId = (int) ($_GET['forward'] ?? 0);
            if ($forwardId > 0) {
                $forwardMsg = MailLogRepository::findById($forwardId);
                $forwardMailboxId = (int) ($forwardMsg['mailbox_id'] ?? 0);
                if (
                    $forwardMsg !== null
                    && MailboxRepository::userCanAccess($user, $forwardMailboxId)
                ) {
                    $postCompose = true;
                    $postComposeForm = PostMailComposer::composeFromMessage($forwardMsg, 'forward');
                } else {
                    header('Location: /app?page=post', true, 302);
                    exit;
                }
            }

            if ($postCompose && $postComposeForm === []) {
                $composeTo = EmailNormalizer::normalize((string) ($_GET['to'] ?? ''));
                $defaultMailbox = '';
                if ($postMailboxFilter > 0 && MailboxRepository::userCanAccess($user, $postMailboxFilter, 'send')) {
                    $defaultMailbox = (string) $postMailboxFilter;
                } elseif (count($postSendableMailboxes) === 1) {
                    $defaultMailbox = (string) (int) ($postSendableMailboxes[0]['id'] ?? 0);
                }
                $postComposeForm = [
                    'mailbox_id' => $defaultMailbox,
                    'to' => $composeTo !== '' && EmailNormalizer::isValidSyntax($composeTo) ? $composeTo : '',
                    'subject' => '',
                    'body' => '',
                    'reply_to_id' => '',
                    'forward_from_id' => '',
                    'mode' => 'new',
                ];
            }

            $mailViewId = (int) ($_GET['id'] ?? 0);
            $imapUid = (int) ($_GET['uid'] ?? 0);
            if (!$postCompose && $imapUid > 0 && $postMailboxFilter > 0) {
                $imapMailbox = null;
                foreach ($postMailboxes as $box) {
                    if ((int) ($box['id'] ?? 0) === $postMailboxFilter) {
                        $imapMailbox = $box;
                        break;
                    }
                }
                if ($imapMailbox === null || !MailboxRepository::userCanAccess($user, $postMailboxFilter)) {
                    header('Location: /app?page=post', true, 302);
                    exit;
                }
                $imapPath = MailFolderCatalog::imapPathForView($postFolder, $imapMailbox);
                $postMessage = ImapMailboxClient::fetchMessage($imapMailbox, $imapPath, $imapUid);
                if ($postMessage === null) {
                    header(
                        'Location: /app?page=post&mailbox=' . $postMailboxFilter . '&folder=' . rawurlencode($postFolder),
                        true,
                        302
                    );
                    exit;
                }
                $postImapLive = true;
                if (!empty($postMessage['body_html'])) {
                    $postMessage['body_html'] = MailHtmlSanitizer::clean((string) $postMessage['body_html']);
                }
                if (!empty($postMessage['attachments']) && is_array($postMessage['attachments'])) {
                    $postMessage['attachments'] = array_map(
                        static fn(array $a): array => [
                            'filename' => (string) ($a['filename'] ?? 'anhang.bin'),
                            'mime' => (string) ($a['mime'] ?? 'application/octet-stream'),
                            'size' => (int) ($a['size'] ?? strlen((string) ($a['content'] ?? ''))),
                        ],
                        $postMessage['attachments']
                    );
                }
            } elseif (!$postCompose && $mailViewId > 0) {
                $postMessage = MailLogRepository::findById($mailViewId);
                $msgMailboxId = (int) ($postMessage['mailbox_id'] ?? 0);
                if (
                    $postMessage === null
                    || !MailboxRepository::userCanAccess($user, $msgMailboxId)
                    || (
                        ($postMessage['direction'] ?? '') === 'in'
                        && ($postMessage['status'] ?? '') !== 'received'
                    )
                    || (
                        ($postMessage['direction'] ?? '') === 'out'
                        && ($postMessage['status'] ?? '') !== 'sent'
                    )
                    || !in_array($postMessage['direction'] ?? '', ['in', 'out'], true)
                ) {
                    header('Location: /app?page=post', true, 302);
                    exit;
                }
                $wasUnread = empty($postMessage['is_read']);
                MailLogRepository::markRead($mailViewId, ($postMessage['direction'] ?? '') === 'in');
                PostMailLinkService::recordOpen($user, $postMessage);
                if ($wasUnread && ($postMessage['direction'] ?? '') === 'in') {
                    AuditLog::record('mail_mark_read', $user->id, json_encode([
                        'mail_log_id' => $mailViewId,
                        'mailbox_id' => $msgMailboxId,
                    ], JSON_UNESCAPED_UNICODE));
                }
                $postLinkContext = PostMailLinkService::contextForMessage($postMessage);
                $postMessage['body_html'] = '';
                $postMessage['body_text'] = '';
                $postMessage['attachments'] = [];
                if (!empty($postMessage['storage_path'])) {
                    try {
                        $archivePath = MailArchiveStorage::absolutePath((string) $postMessage['storage_path']);
                        $parsed = MailMimeReader::parseFile($archivePath);
                        $postMessage['body_html'] = MailHtmlSanitizer::clean((string) ($parsed['html'] ?? ''));
                        $postMessage['body_text'] = (string) ($parsed['text'] ?? '');
                        $postMessage['attachments'] = array_map(
                            static fn(array $a): array => [
                                'filename' => (string) ($a['filename'] ?? 'anhang.bin'),
                                'mime' => (string) ($a['mime'] ?? 'application/octet-stream'),
                                'size' => (int) ($a['size'] ?? 0),
                            ],
                            $parsed['attachments'] ?? []
                        );
                    } catch (Throwable) {
                        // Archiv optional — Vorschau bleibt Fallback
                    }
                }
                if ($postMessage['body_html'] === '' && $postMessage['body_text'] === '') {
                    $postMessage['body_text'] = MailMessage::bodyPreview((string) ($postMessage['body_preview'] ?? ''));
                } else {
                    $postMessage['body_html'] = MailHtmlSanitizer::clean((string) $postMessage['body_html']);
                }
                $messageId = (string) ($postMessage['message_id'] ?? '');
                if (
                    $postMessage !== null
                    && str_starts_with($messageId, 'imap:')
                    && trim((string) ($postMessage['body_preview'] ?? '')) === ''
                ) {
                    $imapMailbox = MailboxRepository::findById($msgMailboxId);
                    if ($imapMailbox !== null && preg_match('/^imap:\d+:(.+):\d+$/', $messageId, $imapMatch) === 1) {
                        $imapFolder = (string) ($postMessage['imap_folder'] ?? $imapMatch[1]);
                        $imapUid = (int) substr($messageId, (int) strrpos($messageId, ':') + 1);
                        $imapBody = ImapMailboxClient::fetchMessage($imapMailbox, $imapFolder, $imapUid);
                        if ($imapBody !== null) {
                            $postMessage['body_preview'] = (string) ($imapBody['body_preview'] ?? '');
                            $postMessage['body_html'] = MailHtmlSanitizer::clean((string) ($imapBody['body_html'] ?? ''));
                            $postMessage['body_text'] = (string) ($imapBody['body_text'] ?? '');
                            $postMessage['attachments'] = array_map(
                                static fn(array $a): array => [
                                    'filename' => (string) ($a['filename'] ?? 'anhang.bin'),
                                    'mime' => (string) ($a['mime'] ?? 'application/octet-stream'),
                                    'size' => (int) ($a['size'] ?? 0),
                                ],
                                is_array($imapBody['attachments'] ?? null) ? $imapBody['attachments'] : []
                            );
                        }
                    }
                }
            } elseif (!$postCompose && !$postListMode) {
                $filter = $postMailboxFilter > 0 ? $postMailboxFilter : null;
                $postPageLimit = PostUiSettings::messagesPerPage();
                $postPageNum = max(1, (int) ($_GET['p'] ?? 1));
                $postPageOffset = ($postPageNum - 1) * $postPageLimit;
                $postTotalCount = 0;
                if ($postSearchMode) {
                    // Eine Seite + „hat mehr“; Gesamtsumme nicht exakt nötig für Suche
                    $postInbox = MailLogRepository::searchForMailboxes(
                        $mailboxIds,
                        $postSearchQuery,
                        $postSearchContactId > 0 ? $postSearchContactId : null,
                        $filter,
                        $postPageLimit + 1,
                        $postPageOffset
                    );
                    $postHasMore = count($postInbox) > $postPageLimit;
                    if ($postHasMore) {
                        $postInbox = array_slice($postInbox, 0, $postPageLimit);
                    }
                    $postTotalCount = $postPageOffset + count($postInbox) + ($postHasMore ? 1 : 0);
                    $postImapAsync = false;
                    $postFolderLabel = $postSearchQuery !== ''
                        ? ('Suche: ' . $postSearchQuery)
                        : ('Kontakt #' . $postSearchContactId);
                } else {
                    $postTotalCount = MailLogRepository::countFolderMessagesForMailboxes(
                        $mailboxIds,
                        $postFolder,
                        $filter
                    );
                    $postPageCount = max(1, (int) ceil($postTotalCount / $postPageLimit));
                    if ($postPageNum > $postPageCount) {
                        $postPageNum = $postPageCount;
                        $postPageOffset = ($postPageNum - 1) * $postPageLimit;
                    }
                    $postInbox = MailLogRepository::folderMessagesForMailboxes(
                        $mailboxIds,
                        $postFolder,
                        $postPageLimit,
                        $filter,
                        $postPageOffset
                    );
                    $postHasMore = $postPageNum < $postPageCount;
                    $postImapAsync = false;
                    if ($postMailboxFilter > 0) {
                        foreach ($postMailboxes as $box) {
                            if ((int) ($box['id'] ?? 0) === $postMailboxFilter
                                && ImapMailboxClient::hasCredentials($box)) {
                                $postImapAsync = true;
                                break;
                            }
                        }
                    }
                }
                if (!isset($postPageCount)) {
                    $postPageCount = max(1, (int) ceil(max($postTotalCount, 1) / $postPageLimit));
                    if (!$postHasMore && $postPageNum >= $postPageCount) {
                        $postPageCount = max(1, $postPageNum);
                    } elseif ($postHasMore) {
                        $postPageCount = max($postPageCount, $postPageNum + 1);
                    }
                }
            }
        } elseif ($page === 'telefon' && MenuRegistry::canAccess($user, 'telefon')) {
            if (
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && RoleResolver::isAdmin($user)
                && isset($_POST['cti_save'])
            ) {
                if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                    Flash::set('error', 'Ungültiges Formular (CSRF).');
                } else {
                    try {
                        CtiSettings::saveFromPost($_POST);
                        Flash::set('success', 'CTI-Einstellungen gespeichert.');
                    } catch (Throwable $e) {
                        Flash::set('error', $e->getMessage());
                    }
                }
                header('Location: /app?page=telefon', true, 302);
                exit;
            }
            CallLogRepository::ensureReady();
            $ctiConfig = CtiSettings::config();
            $callLog = CallLogRepository::recent(50);
            $telefonDial = trim((string) ($_GET['dial'] ?? ''));
            $contentTemplate = 'modules/telefon';
            $title = 'Telefon';
            $currentPage = 'telefon';
        } elseif (
            ($page === 'dokumentenverfasser' || $page === 'dokumentenverfasser-form')
            && MenuRegistry::canAccess($user, $page === 'dokumentenverfasser-form' ? 'dokumentenverfasser-form' : 'dokumentenverfasser')
        ) {
            WriterDocumentRepository::ensureReady();
            $dbConnected = Database::isConfigured();
            $canEdit = RoleResolver::canEdit($user);
            $writerExportFormats = WriterExportService::enabledFormats();
            if ($page === 'dokumentenverfasser-form') {
                if (empty($formError)) {
                    $writerDocumentId = (int) ($_GET['id'] ?? 0);
                    $action = (string) ($_GET['action'] ?? ($writerDocumentId > 0 ? 'edit' : 'new'));
                    if ($action === 'edit' && $writerDocumentId > 0) {
                        $writerForm = WriterDocumentRepository::findDocument($writerDocumentId);
                        if ($writerForm === null) {
                            Flash::set('error', 'Dokument nicht gefunden.');
                            header('Location: /app?page=dokumentenverfasser', true, 302);
                            exit;
                        }
                        $writerVersions = WriterDocumentRepository::listVersions($writerDocumentId);
                    } else {
                        $writerDocumentId = null;
                        $writerForm = WriterDocument::empty();
                        $writerVersions = [];
                    }
                    // ?contact= / ?contact_id= — Empfänger + Verknüpfung (auch nach Rückkehr vom Kontaktformular)
                    $prefillContactId = (int) ($_GET['contact'] ?? $_GET['contact_id'] ?? 0);
                    if ($prefillContactId > 0 && Database::isConfigured() && is_array($writerForm ?? null)) {
                        $prefillContact = ContactRepository::findById($prefillContactId);
                        if (
                            $prefillContact !== null
                            && ContactAccessResolver::canViewContact($user, $prefillContact)
                        ) {
                            $writerForm['contact_id'] = $prefillContact->id;
                            $writerForm['recipient_lines'] = WriterDocumentLinkService::recipientLinesFromContact(
                                $prefillContact
                            );
                            $currentGreeting = trim((string) ($writerForm['greeting'] ?? ''));
                            if (
                                $currentGreeting === ''
                                || str_contains($currentGreeting, '…')
                                || $currentGreeting === 'Sehr geehrte/r '
                                || $currentGreeting === 'Sehr geehrte Damen und Herren,'
                            ) {
                                $writerForm['greeting'] = WriterDocument::defaultGreetingText($prefillContact);
                            }
                        }
                    }
                } else {
                    $writerDocumentId = $writerDocumentId ?? null;
                    $writerForm = $writerForm ?? WriterDocument::empty();
                    $writerVersions = ($writerDocumentId ?? 0) > 0
                        ? WriterDocumentRepository::listVersions((int) $writerDocumentId)
                        : [];
                }
                $writerLinkContext = WriterDocumentLinkService::contextForDocument(
                    is_array($writerForm ?? null) ? $writerForm : WriterDocument::empty()
                );
                $writerCanLinkVoucher = MenuRegistry::canAccess($user, 'buchhaltung-belege')
                    || MenuRegistry::canAccess($user, 'buchhaltung-beleg-form');
                $contentTemplate = 'modules/dokumentenverfasser-form';
                $title = (($writerDocumentId ?? 0) > 0) ? 'Dokument bearbeiten' : 'Neues Dokument';
                $currentPage = 'dokumentenverfasser';
            } else {
                $writerStatusFilter = trim((string) ($_GET['status'] ?? ''));
                if ($writerStatusFilter !== '' && !isset(WriterDocument::statusOptions()[$writerStatusFilter])) {
                    $writerStatusFilter = '';
                }
                $writerContactFilter = (int) ($_GET['contact'] ?? 0);
                if ($writerContactFilter < 1) {
                    $writerContactFilter = 0;
                }
                $writerDocumentList = $dbConnected
                    ? WriterDocumentRepository::listRecent(
                        100,
                        0,
                        $writerStatusFilter !== '' ? $writerStatusFilter : null,
                        $writerContactFilter > 0 ? $writerContactFilter : null
                    )
                    : [];
                $writerFilterContact = $writerContactFilter > 0
                    ? ContactRepository::findById($writerContactFilter)
                    : null;
                $contentTemplate = 'modules/dokumentenverfasser';
                $title = 'Dokumentenverfasser';
                $currentPage = 'dokumentenverfasser';
            }
        } elseif ($page !== 'dashboard' && MenuRegistry::canAccess($user, $page)) {
            $contentTemplate = 'modules/' . $page;
            $title = ucfirst($page);
            $currentPage = $page;
        } elseif ($page !== 'dashboard') {
            header('Location: /app', true, 302);
            exit;
        }

        $contactSearch = $contactSearch ?? '';
        $contactPage = $contactPage ?? 1;
        $contactList = $contactList ?? [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 1,
        ];
        $bookingSearch = $bookingSearch ?? '';
        $bookingPage = $bookingPage ?? 1;
        $bookingList = $bookingList ?? [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 1,
        ];
        $contactId = $contactId ?? null;
        $contact = $contact ?? null;
        $form = $form ?? null;
        $hrDepartments = $hrDepartments ?? [];
        $hrTeams = $hrTeams ?? [];
        $canManageTeams = $canManageTeams ?? false;
        $teamId = $teamId ?? null;
        $employeeContactOptions = $employeeContactOptions ?? [];
        $canEditTeam = $canEditTeam ?? false;
        $readOnly = $readOnly ?? false;
        $departmentOptions = $departmentOptions ?? [];
        $formError = $formError ?? null;
        $contactTeams = $contactTeams ?? [];
        $formError = $formError ?? null;
        $bankAccounts = $bankAccounts ?? ContactRepository::defaultBankAccounts();
        $employeeData = $employeeData ?? EmployeeData::empty();
        $employeeFiles = $employeeFiles ?? ContactFileStorage::emptyFiles();
        $showEmployeeFields = $showEmployeeFields ?? false;
        $payrollWageAssignments = $payrollWageAssignments ?? [];
        $payrollWageTypeOptions = $payrollWageTypeOptions ?? [];
        $staffAccessStatus = $staffAccessStatus ?? [];
        $staffAccessHasMailbox = $staffAccessHasMailbox ?? false;
        $bookingId = $bookingId ?? null;
        $booking = $booking ?? null;
        $smtpTestReport = $smtpTestReport ?? null;
        $appearanceConfig = $appearanceConfig ?? AppearanceSettings::forForm();
        $crmThemeConfig = $crmThemeConfig ?? CrmThemeSettings::forForm();
        $departmentsData = $departmentsData ?? DepartmentRepository::allWithMembers();
        $departmentStaffContacts = $departmentStaffContacts ?? DepartmentRepository::assignableStaffContacts();
        $departmentEmployees = $departmentEmployees ?? DepartmentRepository::assignableEmployees();
        $lagerStrukturTab = $lagerStrukturTab ?? 'orte';
        $stockPurchaseForm = $stockPurchaseForm ?? StockPurchaseSettings::forForm();
        $amazonBusinessForm = $amazonBusinessForm ?? AmazonBusinessSettings::forForm();
        $stockLocations = $stockLocations ?? StockStructureRepository::allLocations();
        $stockHalls = $stockHalls ?? StockStructureRepository::allHalls();
        $stockShelves = $stockShelves ?? StockStructureRepository::allShelves();
        $stockLocationOptions = $stockLocationOptions ?? StockStructureRepository::locationOptions();
        $stockPlaces = $stockPlaces ?? [];
        $calendarTeamTab = $calendarTeamTab ?? 'bereiche';
        $calendarAreas = $calendarAreas ?? [];
        $calendarEmployees = $calendarEmployees ?? [];
        $calendarAbsences = $calendarAbsences ?? [];
        $calendarLinkUsers = $calendarLinkUsers ?? [];
        $calendarDepartmentOptions = $calendarDepartmentOptions ?? DepartmentRepository::optionsForSelect();
        $calendarLinkContacts = $calendarLinkContacts ?? CalendarStaffRepository::linkableContacts();
        $calendarDepartmentSuggestions = $calendarDepartmentSuggestions ?? CalendarStaffRepository::departmentMemberSuggestions();
        $calendarWorkingHours = $calendarWorkingHours ?? CalendarWorkingHoursRepository::all();
        $calendarAppearanceConfig = $calendarAppearanceConfig ?? CalendarAppearanceSettings::forForm();
        $calendarEmbedConfig = $calendarEmbedConfig ?? CalendarEmbedSettings::forForm();
        $bookingArticleOptions = $bookingArticleOptions ?? CalendarArticleRepository::bookingOptions();
        $bookingEmployeeOptions = $bookingEmployeeOptions ?? CalendarStaffRepository::bookingEmployeeOptions();
        $mediaList = $mediaList ?? [];
        $mediaItem = $mediaItem ?? null;
        $mediaIsNew = $mediaIsNew ?? false;
        $mailAddressConfig = $mailAddressConfig ?? MailAddressSettings::forForm();
        $postboxes = $postboxes ?? [];
        $postboxMemberOptions = $postboxMemberOptions ?? [];
        $kasConfigured = $kasConfigured ?? KasSettings::isConfigured();
        $postMailboxes = $postMailboxes ?? [];
        $postInbox = $postInbox ?? [];
        $postMailboxFilter = $postMailboxFilter ?? 0;
        $postUnreadCount = $postUnreadCount ?? 0;
        $postMessage = $postMessage ?? null;
        $postCompose = $postCompose ?? false;
        $postComposeForm = $postComposeForm ?? [];
        $postListMode = $postListMode ?? false;
        $postMailboxCards = $postMailboxCards ?? [];
        $postHasMore = $postHasMore ?? false;
        $postPageNum = $postPageNum ?? 1;
        $postPageCount = $postPageCount ?? 1;
        $postPageLimit = $postPageLimit ?? PostUiSettings::messagesPerPage();
        $postTotalCount = $postTotalCount ?? 0;
        $postUiConfig = $postUiConfig ?? PostUiSettings::config();
        $postSendableMailboxes = $postSendableMailboxes ?? [];
        $postFolder = $postFolder ?? 'INBOX';
        $postFolders = $postFolders ?? [];
        $postFolderLabel = $postFolderLabel ?? 'Posteingang';
        $postIsSentFolder = $postIsSentFolder ?? false;
        $postImapLive = $postImapLive ?? false;
        $postImapAsync = $postImapAsync ?? false;
        $postSearchQuery = $postSearchQuery ?? '';
        $postSearchContactId = $postSearchContactId ?? 0;
        $postSearchMode = $postSearchMode ?? false;
        $postLinkContext = $postLinkContext ?? [
            'contact' => null,
            'suggested' => null,
            'voucher' => null,
            'party_email' => '',
        ];
        $voucherLinkedMails = $voucherLinkedMails ?? [];
        $buchhaltungSection = $buchhaltungSection ?? MenuRegistry::buchhaltungSection($user);
        $dienstleistungenSection = $dienstleistungenSection ?? MenuRegistry::dienstleistungenSection($user);
        $hrSection = $hrSection ?? MenuRegistry::hrSection($user);
        $lohnSection = $lohnSection ?? MenuRegistry::lohnSection($user);
        $warenwirtschaftSection = $warenwirtschaftSection ?? MenuRegistry::warenwirtschaftSection($user);
        $websiteSection = $websiteSection ?? MenuRegistry::websiteSection($user);
        $kommunikationSection = $kommunikationSection ?? MenuRegistry::kommunikationSection($user);
        $kdvSection = $kdvSection ?? MenuRegistry::kdvSection($user);
        $belegeFocus = $belegeFocus ?? '';
        $belegeFormFocus = $belegeFormFocus ?? '';
        $websitePageList = $websitePageList ?? [];
        $websitePageId = $websitePageId ?? null;
        $websiteMenuForm = $websiteMenuForm ?? ['items' => [['label' => '', 'url' => '']]];
        $websiteMenuSuggestions = $websiteMenuSuggestions ?? [];
        $websiteChromeForm = $websiteChromeForm ?? [];
        $websiteDesignForm = $websiteDesignForm ?? [];
        $chartOfAccountsConfig = $chartOfAccountsConfig ?? ChartOfAccountsSettings::forForm();
        $chartAccountCount = $chartAccountCount ?? 0;
        $chartCatalogCount = $chartCatalogCount ?? ChartAccountCatalog::catalogCount(ChartOfAccountsSettings::activeSkrType());
        $chartHintCount = $chartHintCount ?? ChartAccountRepository::countWithDetailedHints(ChartOfAccountsSettings::activeSkrType());
        $voucherSearch = $voucherSearch ?? '';
        $voucherPage = $voucherPage ?? 1;
        $voucherYear = $voucherYear ?? (int) date('Y');
        $voucherTypeFilter = $voucherTypeFilter ?? '';
        $voucherDocumentKindFilter = $voucherDocumentKindFilter ?? '';
        $voucherDocumentStatusFilter = $voucherDocumentStatusFilter ?? '';
        $voucherYears = $voucherYears ?? [(int) date('Y')];
        $voucherFileCounts = $voucherFileCounts ?? [];
        $voucherList = $voucherList ?? [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 25,
            'total_pages' => 1,
            'gross_sum' => 0.0,
        ];
        $voucherBoard = $voucherBoard ?? [
            'section' => VoucherBelegeBoard::SECTION_ACTION,
            'status' => '',
            'invoice_kind' => '',
            'pay' => '',
            'actionable_only' => false,
            'contact_id' => 0,
            'contact_label' => '',
            'amount_min' => '',
            'amount_max' => '',
            'search' => '',
            'sections' => [],
            'chips' => [],
            'invoice_kind_chips' => [],
            'pay_chips' => [],
            'list' => $voucherList,
            'contacts' => [],
            'action_total' => 0,
        ];
        $voucherPeriod = $voucherPeriod ?? AccountingPeriodFilter::fromRequest(['year' => $voucherYear]);
        $voucherDraftCount = $voucherDraftCount ?? 0;
        $voucherImportPending = $voucherImportPending ?? [];
        $voucherId = $voucherId ?? null;
        $transfersPrepared = $transfersPrepared ?? [];
        $transfersExecuted = $transfersExecuted ?? [];
        $openTransferId = $openTransferId ?? 0;
        $ledgerYear = $ledgerYear ?? (int) date('Y');
        $ledgerYears = $ledgerYears ?? [(int) date('Y')];
        $ledgerSearch = $ledgerSearch ?? '';
        $ledgerShowEmpty = $ledgerShowEmpty ?? false;
        $ledgerAccount = $ledgerAccount ?? '';
        $ledgerYearStatus = $ledgerYearStatus ?? 'open';
        $ledgerOverview = $ledgerOverview ?? ['accounts' => [], 'totals' => ['debit' => 0.0, 'credit' => 0.0, 'opening' => 0.0, 'balance' => 0.0]];
        $ledgerStatement = $ledgerStatement ?? ['account' => ['account_number' => '', 'name' => '', 'section' => ''], 'opening' => 0.0, 'rows' => [], 'closing' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
        $jaYear = $jaYear ?? (int) date('Y');
        $jaPreview = $jaPreview ?? ['income' => 0.0, 'expense' => 0.0, 'result' => 0.0];
        $fiscalYears = $fiscalYears ?? [];
        $jaYearStatus = $jaYearStatus ?? 'open';
        $ledgerPostings = $ledgerPostings ?? [];
        $voucherChain = $voucherChain ?? ['documents' => [], 'current_id' => 0];
        $followUpKinds = $followUpKinds ?? [];
        $canReanimateOffer = $canReanimateOffer ?? false;
        $chainSummary = $chainSummary ?? null;
        $voucherMailConfigured = $voucherMailConfigured ?? MailSettings::isConfigured();
        $voucherMailCanSend = $voucherMailCanSend ?? false;
        $voucherMailTo = $voucherMailTo ?? '';
        $voucherMailSubject = $voucherMailSubject ?? '';
        $voucherMailIntro = $voucherMailIntro ?? '';
        $voucherDunningCanSend = $voucherDunningCanSend ?? false;
        $voucherDunningNextLevel = $voucherDunningNextLevel ?? 0;
        $voucherDunningNextLabel = $voucherDunningNextLabel ?? '';
        $voucherDunningFee = $voucherDunningFee ?? 0.0;
        $oposDirection = $oposDirection ?? '';
        $oposSearch = $oposSearch ?? '';
        $oposData = $oposData ?? ['items' => [], 'totals' => ['receivable' => 0.0, 'payable' => 0.0]];
        $datevExportYear = $datevExportYear ?? (int) date('Y');
        $datevExportSettings = $datevExportSettings ?? DatevExportSettings::forForm();
        $accountingPaymentSettings = $accountingPaymentSettings ?? AccountingPaymentSettings::forForm();
        $documentPresentationSettings = $documentPresentationSettings ?? DocumentPresentationSettings::forForm();
        $letterheadSettings = $letterheadSettings ?? LetterheadSettings::forForm();
        $letterheadCompany = $letterheadCompany ?? LetterheadSettings::resolvedCompany($letterheadSettings);
        $letterheadMissingMandatory = $letterheadMissingMandatory ?? LetterheadSettings::missingMandatoryFields($letterheadSettings);
        $letterheadCompliance = $letterheadCompliance ?? LetterheadSettings::complianceReport($letterheadSettings);
        $timeTrackingSettings = $timeTrackingSettings ?? TimeTrackingSettings::forForm();
        $payrollEmployerForm = $payrollEmployerForm ?? PayrollEmployerSettings::forForm();
        $datevExportYears = $datevExportYears ?? [(int) date('Y')];
        $cashYear = $cashYear ?? (int) date('Y');
        $cashYears = $cashYears ?? [(int) date('Y')];
        $cashEntries = $cashEntries ?? [];
        $cashTotals = $cashTotals ?? ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];
        $manualYear = $manualYear ?? (int) date('Y');
        $manualYears = $manualYears ?? [(int) date('Y')];
        $manualBatches = $manualBatches ?? [];
        $reportYear = $reportYear ?? (int) date('Y');
        $reportYears = $reportYears ?? [(int) date('Y')];
        $reportType = $reportType ?? 'guv';
        $balanceSheet = $balanceSheet ?? ['aktiva' => [], 'passiva' => [], 'totals' => ['aktiva' => 0.0, 'passiva' => 0.0], 'result' => 0.0];
        $profitLoss = $profitLoss ?? ['income' => [], 'expense' => [], 'totals' => ['income' => 0.0, 'expense' => 0.0, 'result' => 0.0]];
        $bankTransactionsOpen = $bankTransactionsOpen ?? [];
        $bankTransactionsGhosts = $bankTransactionsGhosts ?? [];
        $bankTransactionsMatched = $bankTransactionsMatched ?? [];
        $isAdmin = $isAdmin ?? RoleResolver::isAdmin($user);
        $kontakteReturnTo = $kontakteReturnTo ?? '';
        $kontakteSupplierNumberPreview = $kontakteSupplierNumberPreview ?? '';
        $isDraftVoucher = $isDraftVoucher ?? false;
        $websiteStatsSummary = $websiteStatsSummary ?? ['total' => 0, 'today' => 0, 'days7' => 0, 'days30' => 0];
        $websiteStatsByDay = $websiteStatsByDay ?? [];
        $websiteStatsTopPaths = $websiteStatsTopPaths ?? [];
        $websiteStatsTopReferrers = $websiteStatsTopReferrers ?? [];
        $websiteAnalyticsLinks = $websiteAnalyticsLinks ?? [];
        $websiteStatsDays = $websiteStatsDays ?? 30;
        $timeClockSummary = $timeClockSummary ?? null;
        $timeClockStatus = $timeClockStatus ?? null;
        $timeClockContactId = $timeClockContactId ?? null;
        $timeClockEmployeeLabel = $timeClockEmployeeLabel ?? '';
        $timeClockCanTeam = $timeClockCanTeam ?? false;
        $timeClockTeam = $timeClockTeam ?? [];
        $timeClockHasKioskPin = $timeClockHasKioskPin ?? false;
        $kioskPendingResets = $kioskPendingResets ?? [];
        $kioskStaffOptions = $kioskStaffOptions ?? [];
        $overtimeReminders = $overtimeReminders ?? ['violations' => []];
        $timeMonthReport = $timeMonthReport ?? null;
        $timeMonthYearMonth = $timeMonthYearMonth ?? date('Y-m');
        $timeMonthContactId = $timeMonthContactId ?? null;
        $timeMonthCanTeam = $timeMonthCanTeam ?? false;
        $timeMonthStaffOptions = $timeMonthStaffOptions ?? [];
        $timeKontoContactId = $timeKontoContactId ?? null;
        $timeKontoContactLabel = $timeKontoContactLabel ?? '';
        $timeKontoBalanceMinutes = $timeKontoBalanceMinutes ?? 0;
        $timeKontoLots = $timeKontoLots ?? [];
        $timeKontoCorrections = $timeKontoCorrections ?? [];
        $timeKontoReductions = $timeKontoReductions ?? [];
        $timeKontoStaffOptions = $timeKontoStaffOptions ?? [];
        $timeShiftTemplates = $timeShiftTemplates ?? [];
        $payrollWageTypes = $payrollWageTypes ?? [];
        $payrollWageTypeEdit = $payrollWageTypeEdit ?? null;
        $payrollWageCanEdit = $payrollWageCanEdit ?? false;
        $payrollRateSets = $payrollRateSets ?? [];
        $payrollRateSetView = $payrollRateSetView ?? null;
        $payrollRateParams = $payrollRateParams ?? [];
        $payrollRateCanEdit = $payrollRateCanEdit ?? false;
        $payrollRunMonth = $payrollRunMonth ?? date('Y-m');
        $payrollRuns = $payrollRuns ?? [];
        $payrollRun = $payrollRun ?? null;
        $payrollRunEmployees = $payrollRunEmployees ?? [];
        $payrollRunEvents = $payrollRunEvents ?? [];
        $payrollRunLinesByContact = $payrollRunLinesByContact ?? [];
        $payrollStaffOptions = $payrollStaffOptions ?? [];
        $payrollRunCanEdit = $payrollRunCanEdit ?? false;
        $payrollRunCanApprove = $payrollRunCanApprove ?? false;
        $payrollRunCanPostFibu = $payrollRunCanPostFibu ?? false;
        $payrollFibuLive = $payrollFibuLive ?? false;
        $payrollFibuMeta = $payrollFibuMeta ?? null;
        $payrollFibuPreview = $payrollFibuPreview ?? null;
        $payrollFibuPreviewError = $payrollFibuPreviewError ?? null;
        $payrollFibuShow = $payrollFibuShow ?? false;
        $payrollFibuRoleLabels = $payrollFibuRoleLabels ?? [];
        $payrollFibuChecklist = $payrollFibuChecklist ?? [];
        $lohnkontoYear = $lohnkontoYear ?? (int) date('Y');
        $lohnkontoContactId = $lohnkontoContactId ?? 0;
        $lohnkontoContactLabel = $lohnkontoContactLabel ?? '';
        $lohnkontoIncludeDrafts = $lohnkontoIncludeDrafts ?? false;
        $lohnkontoRows = $lohnkontoRows ?? [];
        $lohnkontoTotals = $lohnkontoTotals ?? [
            'gross_total' => 0.0, 'lst' => 0.0, 'sv_an' => 0.0, 'net_pay' => 0.0, 'count' => 0,
        ];
        $lohnkontoStaffOptions = $lohnkontoStaffOptions ?? [];
        $payrollCrmDocuments = $payrollCrmDocuments ?? [];
        $timeShiftTemplateEdit = $timeShiftTemplateEdit ?? null;
        $timeShiftWeekMonday = $timeShiftWeekMonday ?? date('Y-m-d');
        $timeShiftWeekDates = $timeShiftWeekDates ?? [];
        $timeShiftStaff = $timeShiftStaff ?? [];
        $timeShiftActiveTemplates = $timeShiftActiveTemplates ?? [];
        $timeShiftAssignmentMap = $timeShiftAssignmentMap ?? [];
        $timeVacContactId = $timeVacContactId ?? null;
        $timeVacContactLabel = $timeVacContactLabel ?? '';
        $timeVacYear = $timeVacYear ?? (int) date('Y');
        $timeVacBalance = $timeVacBalance ?? [];
        $timeVacOwnList = $timeVacOwnList ?? [];
        $timeVacPending = $timeVacPending ?? [];
        $timeVacStaffOptions = $timeVacStaffOptions ?? [];
        $timeVacEntContactId = $timeVacEntContactId ?? null;
        $timeVacEntBalance = $timeVacEntBalance ?? null;
        $timeVacCanTeam = $timeVacCanTeam ?? false;
        $timeVacCanApprove = $timeVacCanApprove ?? false;
        $timeAbsContactId = $timeAbsContactId ?? null;
        $timeAbsContactLabel = $timeAbsContactLabel ?? '';
        $timeAbsOwnList = $timeAbsOwnList ?? [];
        $timeAbsPending = $timeAbsPending ?? [];
        $timeAbsStaffOptions = $timeAbsStaffOptions ?? [];
        $timeAbsCanTeam = $timeAbsCanTeam ?? false;
        $timeAbsCanApprove = $timeAbsCanApprove ?? false;
        $timeAbsYearMonth = $timeAbsYearMonth ?? date('Y-m');
        $timeAbsCalendar = $timeAbsCalendar ?? null;
        $timeAbsAttachments = $timeAbsAttachments ?? [];
        $timeProvisionYear = $timeProvisionYear ?? (int) date('Y');
        $timeProvisionPreview = $timeProvisionPreview ?? [];
        $timeProvisionConfig = $timeProvisionConfig ?? [];
        $timeProvisionDraft = $timeProvisionDraft ?? null;
        $timeProvisionExistingBatchId = $timeProvisionExistingBatchId ?? null;
        $timeProvisionCanBook = $timeProvisionCanBook ?? false;
        $timePayrollYearMonth = $timePayrollYearMonth ?? date('Y-m');
        $timePayrollDataset = $timePayrollDataset ?? ['rows' => [], 'totals' => []];
        $timePayrollExports = $timePayrollExports ?? [];
        $timePayrollDatevSettings = $timePayrollDatevSettings ?? DatevExportSettings::defaults();
        $timePayrollDatevConfigured = $timePayrollDatevConfigured ?? false;
        $timePayrollAutoDownload = $timePayrollAutoDownload ?? false;
        $timeHoursImportErrors = $timeHoursImportErrors ?? [];
        $contactImportErrors = $contactImportErrors ?? [];
        $recipeList = $recipeList ?? [];
        $recipeForm = $recipeForm ?? null;
        $recipeId = $recipeId ?? null;
        $recipeArticleOptions = $recipeArticleOptions ?? [];
        $recipeWorkCenterOptions = $recipeWorkCenterOptions ?? [];
        $recipeCalc = $recipeCalc ?? null;
        $recipeSnapshots = $recipeSnapshots ?? [];
        $recipeActuals = $recipeActuals ?? [];
        $recipeAuxiliaryMaterials = $recipeAuxiliaryMaterials ?? [];
        $workCenterList = $workCenterList ?? [];
        $machineCapacityProfiles = $machineCapacityProfiles ?? [];
        $machineMaintenancePlans = $machineMaintenancePlans ?? [];
        $machineMaintenanceLogs = $machineMaintenanceLogs ?? [];
        $machineBookingWindows = $machineBookingWindows ?? [];
        $machineRecipeOptions = $machineRecipeOptions ?? [];
        $machineBookingFilterWorkCenterId = $machineBookingFilterWorkCenterId ?? 0;
        $machineBookingFilterFrom = $machineBookingFilterFrom ?? '';
        $machineBookingFilterTo = $machineBookingFilterTo ?? '';
        $machineBookingEdit = $machineBookingEdit ?? null;
        $machineBookingCalendar = $machineBookingCalendar ?? null;
        $machineBookingCalMode = $machineBookingCalMode ?? 'month';
        $machineBookingCalYm = $machineBookingCalYm ?? date('Y-m');
        $machineBookingCalWeek = $machineBookingCalWeek ?? date('Y-m-d');
        $workCenterForm = $workCenterForm ?? null;
        $workCenterId = $workCenterId ?? null;
        $recipeCostRates = $recipeCostRates ?? null;
        $recipeCostRatesForm = $recipeCostRatesForm ?? null;
        $catalogFilter = $catalogFilter ?? 'all';
        $catalogView = $catalogView ?? 'catalog';
        $purchaseListOpen = $purchaseListOpen ?? [];
        $purchaseListOrdered = $purchaseListOrdered ?? [];
        $purchaseListIgnored = $purchaseListIgnored ?? [];
        $purchaseOrderArticleOptions = $purchaseOrderArticleOptions ?? [];
        $openOrderUrl = $openOrderUrl ?? '';
        $supplierContactOptions = $supplierContactOptions ?? [];
        $lagerView = $lagerView ?? 'overview';
        $academyView = $academyView ?? 'meine';
        $academyAreas = $academyAreas ?? [];
        $academyAssignments = $academyAssignments ?? [];
        $academyCatalog = $academyCatalog ?? [];
        $academyPendingHr = $academyPendingHr ?? [];
        $academyCourse = $academyCourse ?? null;
        $academyModule = $academyModule ?? null;
        $academyAssignment = $academyAssignment ?? null;
        $academySummary = $academySummary ?? null;
        $academyRulesAccepted = $academyRulesAccepted ?? false;
        $academyTierPlan = $academyTierPlan ?? AcademyTier::currentPlan();
        $canManageAcademy = $canManageAcademy ?? false;
        $canAcademyHr = $canAcademyHr ?? false;
        $academyAdminCourse = $academyAdminCourse ?? null;
        $academyAllCourses = $academyAllCourses ?? [];
        $academyUserOptions = $academyUserOptions ?? [];
        $academyAdminTab = $academyAdminTab ?? 'kurse';
        $academyDepartments = $academyDepartments ?? ($academyAreas ?? []);
        $academyCoursesByDepartment = $academyCoursesByDepartment ?? [];
        $academyAdminDepartmentId = $academyAdminDepartmentId ?? '';
        $academyDepartmentVideos = $academyDepartmentVideos ?? [];
        $academyAllVideos = $academyAllVideos ?? [];
        $academyCourseModuleIds = $academyCourseModuleIds ?? [];
        $academyAdminVideo = $academyAdminVideo ?? null;
        $academyLibraryVideos = $academyLibraryVideos ?? [];
        $academyCourseDepartmentIds = $academyCourseDepartmentIds ?? [];
        $academyModuleDepartmentIds = $academyModuleDepartmentIds ?? [];
        $stockItems = $stockItems ?? [];
        $stockMovements = $stockMovements ?? [];
        $stockInventories = $stockInventories ?? [];
        $stockOutboundVouchers = $stockOutboundVouchers ?? [];
        $activeInventory = $activeInventory ?? null;
        $activeInventoryLines = $activeInventoryLines ?? [];
        $websiteFormList = $websiteFormList ?? [];
        $websiteFormId = $websiteFormId ?? null;
        $websiteForm = $websiteForm ?? null;
        $websiteFormSubmissions = $websiteFormSubmissions ?? [];
        $websiteFormSubmission = $websiteFormSubmission ?? null;
        $websiteFormOptions = $websiteFormOptions ?? [];
        $crmUsers = $crmUsers ?? [];
        $allowedContactRoles = $allowedContactRoles ?? ContactAccessResolver::allowedContactRoleOptions($user);
        $canDeleteContact = $canDeleteContact ?? false;
        $companyEmployees = $companyEmployees ?? [];
        $employerForm = $employerForm ?? ContactCompanyLinkRepository::emptyEmployerForm();
        $employerLink = $employerLink ?? null;
        $companyContactOptions = $companyContactOptions ?? [];
        $personContactOptions = $personContactOptions ?? [];
        $websiteMaintenance = $websiteMaintenance ?? WebsiteMaintenanceSettings::config();
        $supportGrant = $supportGrant ?? null;
        $supportTokenOnce = $supportTokenOnce ?? null;
        $kdvSupportSessions = $kdvSupportSessions ?? [];
        $customer = $customer ?? null;
        $customers = $customers ?? [];
        $kdvOrgOptions = $kdvOrgOptions ?? [];
        $kdvFirmOptions = $kdvFirmOptions ?? [];
        $predecessor = $predecessor ?? null;
        $umfirmForm = $umfirmForm ?? [];
        $umfirmChecklist = $umfirmChecklist ?? [];
        $rumpfOrg = $rumpfOrg ?? null;
        $rumpfFirms = $rumpfFirms ?? [];
        $rumpfPairs = $rumpfPairs ?? [];
        $rumpfOrgId = $rumpfOrgId ?? 0;
        $result = $result ?? null;
        $provisionGateError = $provisionGateError ?? null;
        $acqInput = $acqInput ?? null;
        $acqResult = $acqResult ?? null;
        $acqCompany = $acqCompany ?? null;
        $acqAreas = $acqAreas ?? null;
        $acqPresets = $acqPresets ?? null;
        $writerDocumentId = $writerDocumentId ?? null;
        $writerForm = $writerForm ?? null;
        $writerVersions = $writerVersions ?? [];
        $writerExportFormats = $writerExportFormats ?? null;
        $writerLinkContext = $writerLinkContext ?? null;
        $writerCanLinkVoucher = $writerCanLinkVoucher ?? false;
        $writerDocumentList = $writerDocumentList ?? [];
        $writerStatusFilter = $writerStatusFilter ?? '';
        $writerContactFilter = $writerContactFilter ?? 0;
        $writerFilterContact = $writerFilterContact ?? null;
        $giftVoucherList = $giftVoucherList ?? [];
        $giftVoucherForm = $giftVoucherForm ?? null;
        $giftVoucherId = $giftVoucherId ?? null;
        $giftFormMode = $giftFormMode ?? 'new';
        $giftFlyerTemplates = $giftFlyerTemplates ?? [];
        $giftFlyerTemplate = $giftFlyerTemplate ?? null;
        $flyerTemplateList = $flyerTemplateList ?? [];
        $flyerTemplateForm = $flyerTemplateForm ?? null;
        $flyerTemplateId = $flyerTemplateId ?? null;
        $flyerTemplateMode = $flyerTemplateMode ?? 'list';
        $flyerGift = $flyerGift ?? null;
        $flyerPrint = $flyerPrint ?? null;
        $companyName = $companyName ?? '';
        $companyLogoUrl = $companyLogoUrl ?? '';
        $complaintList = $complaintList ?? [];
        $complaintForm = $complaintForm ?? null;
        $complaintId = $complaintId ?? null;
        $complaintEvents = $complaintEvents ?? [];
        $complaintAttachments = $complaintAttachments ?? [];
        $complaintContactLabel = $complaintContactLabel ?? '';
        $complaintVoucherLabel = $complaintVoucherLabel ?? '';

        View::render('layout/app', compact(
            'title',
            'user',
            'navMode',
            'departments',
            'contentTemplate',
            'area',
            'dept',
            'menuItems',
            'settingsItem',
            'dienstleistungenSection',
            'hrSection',
            'lohnSection',
            'warenwirtschaftSection',
            'buchhaltungSection',
            'websiteSection',
            'kommunikationSection',
            'kdvSection',
            'belegeFocus',
            'belegeFormFocus',
            'currentPage',
            'settingsNav',
            'settingsSelection',
            'flash',
            'dbConfig',
            'dbConnected',
            'backupInfo',
            'mailConfig',
            'mailReady',
            'mailRecent',
            'mailAddressConfig',
            'postboxes',
            'postboxMemberOptions',
            'kasConfigured',
            'postMailboxes',
            'postInbox',
            'postMailboxFilter',
            'postUnreadCount',
            'postMessage',
            'postCompose',
            'postComposeForm',
            'postListMode',
            'postMailboxCards',
            'postHasMore',
            'postPageNum',
            'postPageCount',
            'postPageLimit',
            'postTotalCount',
            'postSendableMailboxes',
            'postFolder',
            'postFolders',
            'postFolderLabel',
            'postIsSentFolder',
            'postImapLive',
            'postImapAsync',
            'postSearchQuery',
            'postSearchContactId',
            'postSearchMode',
            'postLinkContext',
            'postUiConfig',
            'ctiConfig',
            'callLog',
            'telefonDial',
            'voucherLinkedMails',
            'smtpTestReport',
            'appearanceConfig',
            'crmThemeConfig',
            'departmentsData',
            'departmentEmployees',
            'departmentStaffContacts',
            'lagerStrukturTab',
            'stockPurchaseForm',
            'amazonBusinessForm',
            'stockLocations',
            'stockHalls',
            'stockShelves',
            'stockLocationOptions',
            'stockPlaces',
            'calendarTeamTab',
            'calendarAreas',
            'calendarEmployees',
            'calendarAbsences',
            'calendarLinkUsers',
            'calendarDepartmentOptions',
            'calendarLinkContacts',
            'calendarDepartmentSuggestions',
            'calendarWorkingHours',
            'calendarAppearanceConfig',
            'calendarEmbedConfig',
            'calendarArticles',
            'catalogFilter',
            'catalogView',
            'purchaseListOpen',
            'purchaseListOrdered',
            'purchaseListIgnored',
            'purchaseOrderArticleOptions',
            'openOrderUrl',
            'supplierContactOptions',
            'lagerView',
            'academyView',
            'academyAreas',
            'academyAssignments',
            'academyCatalog',
            'academyPendingHr',
            'academyCourse',
            'academyModule',
            'academyAssignment',
            'academySummary',
            'academyRulesAccepted',
            'academyTierPlan',
            'canManageAcademy',
            'canAcademyHr',
            'academyAdminCourse',
            'academyAllCourses',
            'academyUserOptions',
            'academyAdminTab',
            'academyDepartments',
            'academyCoursesByDepartment',
            'academyAdminDepartmentId',
            'academyDepartmentVideos',
            'academyAllVideos',
            'academyCourseModuleIds',
            'academyAdminVideo',
            'academyLibraryVideos',
            'academyCourseDepartmentIds',
            'academyModuleDepartmentIds',
            'stockItems',
            'stockMovements',
            'stockInventories',
            'stockOutboundVouchers',
            'activeInventory',
            'activeInventoryLines',
            'companyConfig',
            'companyExtended',
            'taxAdvisorConfig',
            'taxAdvisorCompanyOptions',
            'elsterConfig',
'legalProductsConfig',
            'ldapConfig',
            'accountingPaymentSettings',
            'documentPresentationSettings',
            'letterheadSettings',
            'letterheadCompany',
            'letterheadMissingMandatory',
            'letterheadCompliance',
            'timeTrackingSettings',
            'payrollEmployerForm',
            'chartOfAccountsConfig',
            'chartAccountCount',
            'chartCatalogCount',
            'chartHintCount',
            'voucherList',
            'voucherBoard',
            'voucherPeriod',
            'voucherSearch',
            'voucherPage',
            'voucherYear',
            'voucherTypeFilter',
            'voucherDocumentKindFilter',
            'voucherDocumentStatusFilter',
            'voucherDraftCount',
            'voucherImportPending',
            'voucherYears',
            'voucherFileCounts',
            'voucherId',
            'transfersPrepared',
            'transfersExecuted',
            'openTransferId',
            'ledgerYear',
            'ledgerYears',
            'ledgerSearch',
            'ledgerShowEmpty',
            'ledgerAccount',
            'ledgerYearStatus',
            'ledgerOverview',
            'ledgerStatement',
            'ledgerPostings',
            'voucherChain',
            'followUpKinds',
            'canReanimateOffer',
            'chainSummary',
            'voucherMailConfigured',
            'voucherMailCanSend',
            'voucherMailTo',
            'voucherMailSubject',
            'voucherMailIntro',
            'voucherDunningCanSend',
            'voucherDunningNextLevel',
            'voucherDunningNextLabel',
            'voucherDunningFee',
            'oposDirection',
            'oposSearch',
            'oposData',
            'datevExportYear',
            'datevExportSettings',
            'datevExportYears',
            'cashYear',
            'cashYears',
            'cashEntries',
            'cashTotals',
            'manualYear',
            'manualYears',
            'manualBatches',
            'reportYear',
            'reportYears',
            'reportType',
            'balanceSheet',
            'profitLoss',
            'bankTransactionsOpen',
            'bankTransactionsGhosts',
            'bankTransactionsMatched',
            'jaYear',
            'jaPreview',
            'fiscalYears',
            'jaYearStatus',
            'acqInput',
            'acqResult',
            'acqCompany',
            'acqAreas',
            'acqPresets',
            'isAdmin',
            'numberRangeType',
            'numberRangeDoc',
            'numberRangeTypes',
            'numberRangeHistory',
            'calendarEmailTemplates',
            'notificationTemplateData',
            'emailLayout',
            'calendarNotificationDelivery',
            'canEdit',
            'sidebarItems',
            'crmUsers',
            'contactList',
            'contactSearch',
            'contactPage',
            'bookingList',
            'bookingSearch',
            'bookingPage',
            'contactId',
            'contact',
            'form',
            'formError',
            'hrDepartments',
            'hrTeams',
            'canManageTeams',
            'teamId',
            'employeeContactOptions',
            'canEditTeam',
            'readOnly',
            'departmentOptions',
            'contactTeams',
            'bankAccounts',
            'employeeData',
            'employeeFiles',
            'showEmployeeFields',
            'payrollWageAssignments',
            'payrollWageTypeOptions',
            'staffAccessStatus',
            'staffAccessHasMailbox',
            'allowedContactRoles',
            'canDeleteContact',
            'companyEmployees',
            'employerForm',
            'employerLink',
            'companyContactOptions',
            'personContactOptions',
            'kontakteReturnTo',
            'kontakteSupplierNumberPreview',
            'isDraftVoucher',
            'websitePageList',
            'websiteMaintenance',
            'supportGrant',
            'supportTokenOnce',
            'kdvSupportSessions',
            'customer',
            'customers',
            'kdvOrgOptions',
            'kdvFirmOptions',
            'predecessor',
            'umfirmForm',
            'umfirmChecklist',
            'rumpfOrg',
            'rumpfFirms',
            'rumpfPairs',
            'rumpfOrgId',
            'result',
            'provisionGateError',
            'websitePageId',
            'websiteFormList',
            'websiteFormId',
            'websiteForm',
            'websiteFormSubmissions',
            'websiteFormSubmission',
            'websiteFormOptions',
            'websiteMenuForm',
            'websiteMenuSuggestions',
            'websiteChromeForm',
            'websiteDesignForm',
            'websiteStatsSummary',
            'websiteStatsByDay',
            'websiteStatsTopPaths',
            'websiteStatsTopReferrers',
            'websiteAnalyticsLinks',
            'websiteStatsDays',
            'bookingId',
            'booking',
            'bookingArticleOptions',
            'bookingEmployeeOptions',
            'mediaList',
            'mediaItem',
            'mediaIsNew',
            'timeClockSummary',
            'timeClockStatus',
            'timeClockContactId',
            'timeClockEmployeeLabel',
            'timeClockCanTeam',
            'timeClockTeam',
            'timeClockHasKioskPin',
            'kioskPendingResets',
            'kioskStaffOptions',
            'overtimeReminders',
            'timeMonthReport',
            'timeMonthYearMonth',
            'timeMonthContactId',
            'timeMonthCanTeam',
            'timeMonthStaffOptions',
            'timeKontoContactId',
            'timeKontoContactLabel',
            'timeKontoBalanceMinutes',
            'timeKontoLots',
            'timeKontoCorrections',
            'timeKontoReductions',
            'timeKontoStaffOptions',
            'timeShiftTemplates',
            'timeShiftTemplateEdit',
            'timeShiftWeekMonday',
            'timeShiftWeekDates',
            'timeShiftStaff',
            'timeShiftActiveTemplates',
            'payrollWageTypes',
            'payrollWageTypeEdit',
            'payrollWageCanEdit',
            'payrollRateSets',
            'payrollRateSetView',
            'payrollRateParams',
            'payrollRateCanEdit',
            'payrollRunMonth',
            'payrollRuns',
            'payrollRun',
            'payrollRunEmployees',
            'payrollRunEvents',
            'payrollRunLinesByContact',
            'payrollStaffOptions',
            'payrollRunCanEdit',
            'payrollRunCanApprove',
            'payrollRunCanPostFibu',
            'payrollFibuLive',
            'payrollFibuMeta',
            'payrollFibuPreview',
            'payrollFibuPreviewError',
            'payrollFibuShow',
            'payrollFibuRoleLabels',
            'payrollFibuChecklist',
            'lohnkontoYear',
            'lohnkontoContactId',
            'lohnkontoContactLabel',
            'lohnkontoIncludeDrafts',
            'lohnkontoRows',
            'lohnkontoTotals',
            'lohnkontoStaffOptions',
            'payrollCrmDocuments',
            'timeShiftAssignmentMap',
            'timeVacContactId',
            'timeVacContactLabel',
            'timeVacYear',
            'timeVacBalance',
            'timeVacOwnList',
            'timeVacPending',
            'timeVacStaffOptions',
            'timeVacEntContactId',
            'timeVacEntBalance',
            'timeVacCanTeam',
            'timeVacCanApprove',
            'timeAbsContactId',
            'timeAbsContactLabel',
            'timeAbsOwnList',
            'timeAbsPending',
            'timeAbsStaffOptions',
            'timeAbsCanTeam',
            'timeAbsCanApprove',
            'timeAbsYearMonth',
            'timeAbsCalendar',
            'timeAbsAttachments',
            'timeProvisionYear',
            'timeProvisionPreview',
            'timeProvisionConfig',
            'timeProvisionDraft',
            'timeProvisionExistingBatchId',
            'timeProvisionCanBook',
            'timePayrollYearMonth',
            'timePayrollDataset',
            'timePayrollExports',
            'timePayrollDatevSettings',
            'timePayrollDatevConfigured',
            'timePayrollAutoDownload',
            'timeHoursImportErrors',
            'contactImportErrors',
            'recipeList',
            'recipeForm',
            'recipeId',
            'recipeArticleOptions',
            'recipeWorkCenterOptions',
            'recipeCalc',
            'recipeSnapshots',
            'recipeActuals',
            'recipeAuxiliaryMaterials',
            'workCenterList',
            'machineCapacityProfiles',
            'machineMaintenancePlans',
            'machineMaintenanceLogs',
            'machineBookingWindows',
            'machineRecipeOptions',
            'machineBookingFilterWorkCenterId',
            'machineBookingFilterFrom',
            'machineBookingFilterTo',
            'machineBookingEdit',
            'machineBookingCalendar',
            'machineBookingCalMode',
            'machineBookingCalYm',
            'machineBookingCalWeek',
            'workCenterForm',
            'workCenterId',
            'recipeCostRates',
            'recipeCostRatesForm',
            'formError',
            'writerDocumentId',
            'writerForm',
            'writerVersions',
            'writerExportFormats',
            'writerLinkContext',
            'writerCanLinkVoucher',
            'writerDocumentList',
            'writerStatusFilter',
            'writerContactFilter',
            'writerFilterContact',
            'giftVoucherList',
            'giftVoucherForm',
            'giftVoucherId',
            'giftFormMode',
            'giftFlyerTemplates',
            'giftFlyerTemplate',
            'flyerTemplateList',
            'flyerTemplateForm',
            'flyerTemplateId',
            'flyerTemplateMode',
            'flyerGift',
            'flyerPrint',
            'companyName',
            'companyLogoUrl',
            'complaintList',
            'complaintForm',
            'complaintId',
            'complaintEvents',
            'complaintAttachments',
            'complaintContactLabel',
            'complaintVoucherLabel',
        ));
        break;

    default:
        // Public website pages: try to match slug
        $slug = ltrim($path, '/');

        if (
            !Database::isConfigured()
            && !AuthService::check()
            && strcasecmp($slug, 'login') !== 0
            && !str_starts_with($slug, 'assets/')
        ) {
            WebsiteMaintenanceSettings::renderPlaceholderMaintenance();
        }

        if (
            Database::isConfigured()
            && WebsiteMaintenanceSettings::isActive()
            && !AuthService::check()
            && $slug !== ''
            && strcasecmp($slug, 'login') !== 0
            && !str_starts_with($slug, 'api/')
            && !str_starts_with($slug, 'assets/')
            && !str_starts_with($slug, 'app/media')
            && $slug !== 'support-zugang'
        ) {
            WebsiteMaintenanceSettings::renderAndExit();
        }

        $publicPage = ($slug !== '' && Database::isConfigured()) ? WebsitePageRepository::findBySlug($slug) : null;

        // Homepage: '/' for non-authenticated users → show startseite if published
        if ($publicPage === null && $path === '/' && !AuthService::check() && Database::isConfigured()) {
            $publicPage = WebsitePageRepository::findHomepage();
        }

        if ($publicPage !== null) {
            $chrome = WebsiteSettings::chrome();
            $menu = WebsiteSettings::publicMenu();
            $design = WebsiteSettings::design();
            View::render('website-public', [
                'page' => LegalPagePublicHelper::enrichPage($publicPage, false),
                'chrome' => $chrome,
                'menu' => $menu,
                'design' => $design,
            ]);
        } else {
            http_response_code(404);
            if (AuthService::check()) {
                header('Location: /app', true, 302);
            } else {
                View::render('offline');
            }
        }
        break;
}

