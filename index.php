<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

switch ($path) {
    case '/':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }
        View::render('offline');
        break;

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
                    throw new InvalidArgumentException('PasswÃ¶rter stimmen nicht Ã¼berein.');
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

    case '/login':
        if (AuthService::check()) {
            $u = AuthService::user();
            header('Location: ' . ($u ? RoleResolver::homePath($u) : '/app'), true, 302);
            exit;
        }

        $error = null;
        $flash = Flash::pull();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'Bitte Benutzername und Passwort eingeben.';
            } elseif (AuthService::attempt($username, $password)) {
                $loggedIn = UserRepository::findByEmailOrUsername($username);
                header('Location: ' . ($loggedIn ? RoleResolver::homePath($loggedIn) : '/app'), true, 302);
                exit;
            } else {
                $user = UserRepository::findByEmailOrUsername($username);
                if ($user && !RoleResolver::canAccessCrm($user)) {
                    $error = 'FÃ¼r dieses Konto ist kein CRM-Zugang freigeschaltet.';
                } else {
                    $error = 'Anmeldung fehlgeschlagen. Benutzername oder Passwort ist falsch.';
                }
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

    case '/logout':
        AuthService::logout();
        header('Location: /', true, 302);
        exit;

    case '/api/finanzamt-lookup':
        FinanzamtLookupApi::handle();
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

    case '/api/calendar-staff':
        CalendarStaffApi::handle();
        exit;

    case '/api/media':
        MediaApi::handle();
        exit;

    case '/api/chart-account':
        ChartAccountApi::handle();
        exit;

    case '/api/voucher':
        VoucherApi::handle();
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

    case '/api/mail-address-preview':
        MailAddressPreviewApi::handle();
        exit;

    case '/api/public-booking':
        PublicBookingApi::handle();
        exit;

    case '/termin':
        MigrationRunner::runPending();
        if (!CalendarEmbedSettings::isOnlineBookingEnabled()) {
            View::render('public/termin-disabled', [
                'disabledReason' => 'Die Online-Terminbuchung ist derzeit deaktiviert. Bitte kontaktieren Sie uns direkt.',
            ]);
            break;
        }
        if (!Database::isConfigured()) {
            View::render('public/termin-disabled', [
                'disabledReason' => 'Die Online-Terminbuchung ist vorübergehend nicht verfügbar.',
            ]);
            break;
        }
        CalendarWorkingHoursRepository::ensureSeeded();
        CalendarStaffRepository::ensureSeeded();
        View::render('public/termin', [
            'embedConfig' => CalendarEmbedSettings::config(),
            'bookingArticles' => CalendarArticleRepository::bookingOptions(),
            'bookingEmployees' => CalendarStaffRepository::bookingEmployeeOptions(),
        ]);
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
                    Flash::set('success', 'Kontenrahmen gespeichert.');
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
            && (isset($_POST['articles_save']) || isset($_POST['articles_delete']) || isset($_POST['articles_import']))
        ) {
            $redirect = '/app?page=artikel-leistungen';
            $listKind = trim((string) ($_POST['list_kind'] ?? ''));
            if ($listKind === CalendarArticleCatalog::KIND_PRODUCT || $listKind === CalendarArticleCatalog::KIND_SERVICE) {
                $redirect .= '&kind=' . rawurlencode($listKind);
            }
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    if (isset($_POST['articles_delete'])) {
                        CalendarArticleRepository::delete((int) ($_POST['article_id'] ?? 0));
                        Flash::set('success', 'Eintrag gelÃ¶scht.');
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

        // POST: Einstellungen — Postfächer
        if (
            $page === 'einstellungen'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && RoleResolver::isAdmin($user)
            && isset($_POST['postbox_save'])
        ) {
            $redirect = SettingsRegistry::tabUrl('postfaecher');
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
            } else {
                try {
                    $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
                    $memberIds = [];
                    foreach ((array) ($_POST['mailbox_members'] ?? []) as $rawId) {
                        $memberIds[] = (int) $rawId;
                    }
                    if ($mailboxId > 0) {
                        $existing = MailboxRepository::findById($mailboxId);
                        if ($existing === null) {
                            throw new RuntimeException('Postfach nicht gefunden.');
                        }
                        $data = MailboxProvisioner::normalizePostInput($_POST, $existing);
                        $data['type'] = $existing['type'];
                        MailboxRepository::save($data, $mailboxId, ($existing['type'] ?? '') === 'shared' ? $memberIds : []);
                        Flash::set('success', 'Postfach gespeichert.');
                    } elseif (!empty($_POST['provision_kas']) && KasSettings::isConfigured()) {
                        MailboxProvisioner::createSharedFromForm($_POST, $memberIds);
                        Flash::set('success', 'Postfach angelegt (KAS).');
                    } else {
                        $data = MailboxProvisioner::normalizePostInput($_POST);
                        MailboxRepository::save($data, null, $memberIds);
                        Flash::set('success', 'Postfach angelegt.');
                    }
                } catch (Throwable $e) {
                    Flash::set('error', $e->getMessage());
                }
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        // POST: Post â€” Nachricht senden
        if (
            $page === 'post'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['post_send'])
            && MenuRegistry::canAccess($user, 'post')
        ) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                Flash::set('error', 'UngÃ¼ltiges Formular (CSRF).');
                header('Location: /app?page=post&compose=1', true, 302);
                exit;
            }
            try {
                PostMailComposer::sendFromPost($user, $_POST);
                Flash::set('success', 'Nachricht wurde gesendet.');
                header('Location: /app?page=post', true, 302);
                exit;
            } catch (Throwable $e) {
                Flash::set('error', $e->getMessage());
                $_SESSION['dg_post_compose'] = [
                    'mailbox_id' => (string) ($_POST['mailbox_id'] ?? ''),
                    'to' => (string) ($_POST['to'] ?? ''),
                    'subject' => (string) ($_POST['subject'] ?? ''),
                    'body' => (string) ($_POST['body'] ?? ''),
                    'reply_to_id' => (string) ($_POST['reply_to_id'] ?? ''),
                ];
                header('Location: /app?page=post&compose=1', true, 302);
                exit;
            }
        }

        // Mitarbeiter-Dokument: Browseransicht (inline) oder Download
        if (
            $page === 'kontakte'
            && in_array($action, ['view', 'download'], true)
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

        // POST: Kontakt lÃ¶schen
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
                Flash::set('success', $flashMessage);
                $returnTo = trim((string) ($_POST['return_to'] ?? ''));
                if ($returnTo !== '' && str_starts_with($returnTo, '/app?') && !str_contains($returnTo, '//')) {
                    $separator = str_contains($returnTo, '?') ? '&' : '?';
                    header('Location: ' . $returnTo . $separator . 'contact_id=' . $newId, true, 302);
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
                    'flash', 'dbConfig', 'dbConnected', 'canEdit', 'sidebarItems', 'contactId', 'form', 'formError', 'bankAccounts', 'employeeData', 'employeeFiles', 'showEmployeeFields', 'allowedContactRoles', 'canDeleteContact', 'companyEmployees', 'employerForm', 'companyContactOptions', 'personContactOptions'
                ));
                break;
            }
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
            try {
                $newId = VoucherRepository::save($_POST, $editId > 0 ? $editId : null, $user->id);
                Flash::set('success', 'Beleg gespeichert.');
                header('Location: /app?page=buchhaltung-beleg-form&action=edit&id=' . $newId, true, 302);
                exit;
            } catch (Throwable $e) {
                $contentTemplate = 'modules/buchhaltung-beleg-form';
                $title = $editId > 0 ? 'Beleg bearbeiten' : 'Neuer Beleg';
                $currentPage = 'buchhaltung-belege';
                $voucherId = $editId > 0 ? $editId : null;
                $form = array_merge(VoucherRepository::emptyForm(), $_POST);
                $formError = $e->getMessage();
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
                View::render('layout/app', compact(
                    'title', 'user', 'navMode', 'departments', 'contentTemplate', 'area', 'dept',
                    'menuItems', 'settingsItem', 'buchhaltungSection', 'currentPage', 'settingsNav', 'settingsSelection',
                    'flash', 'dbConfig', 'dbConnected', 'canEdit', 'sidebarItems', 'voucherId', 'form', 'formError',
                    'chartOfAccountsConfig'
                ));
                break;
            }
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
                BankTransferRepository::markExecuted($transferId);
                Flash::set('success', 'Überweisung als ausgeführt markiert.');
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

        // POST: Jahresabschluss (Jahr abschließen / Abschluss zurücknehmen)
        if (
            $page === 'buchhaltung-jahresabschluss'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && MenuRegistry::canAccess($user, 'buchhaltung-jahresabschluss')
        ) {
            $closeYear = max(2000, (int) ($_POST['year'] ?? 0));
            $redirect = '/app?page=buchhaltung-jahresabschluss&year=' . $closeYear;
            if (!Csrf::verify($_POST['_csrf'] ?? null) || !RoleResolver::isAdmin($user)) {
                Flash::set('error', 'Keine Berechtigung bzw. ungültiges Formular.');
            } elseif (isset($_POST['fiscal_year_close'])) {
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
            } elseif (isset($_POST['fiscal_year_reopen'])) {
                try {
                    FiscalYearService::reopenYear($closeYear);
                    Flash::set('success', 'Abschluss ' . $closeYear . ' zurückgenommen.');
                } catch (Throwable $e) {
                    Flash::set('error', 'Rücknahme fehlgeschlagen: ' . $e->getMessage());
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
        $mailReady = MailSettings::isConfigured();
        $mailRecent = MailLogRepository::recent();
        $mailAddressConfig = MailAddressSettings::forForm();
        $postboxes = MailboxRepository::allForAdmin();
        $postboxMemberOptions = MailboxMemberResolver::staffOptions();
        $kasConfigured = KasSettings::isConfigured();
        $smtpTestReport = SmtpTestReport::pull();
        $appearanceConfig = AppearanceSettings::forForm();
        $crmThemeConfig = CrmThemeSettings::forForm();
        $departmentsData = DepartmentRepository::allWithMembers();
        $departmentEmployees = DepartmentRepository::assignableEmployees();
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
            $rawKind = isset($_GET['kind']) ? strtolower(trim((string) $_GET['kind'])) : 'all';
            if ($rawKind === 'product' || $rawKind === 'article') {
                $catalogFilter = CalendarArticleCatalog::KIND_PRODUCT;
            } elseif ($rawKind === 'service' || $rawKind === 'leistung') {
                $catalogFilter = CalendarArticleCatalog::KIND_SERVICE;
            } else {
                $catalogFilter = 'all';
            }
            $calendarArticles = CalendarArticleRepository::all(false, $catalogFilter === 'all' ? null : $catalogFilter);
            $contentTemplate = 'modules/artikel-leistungen';
            $title = 'Artikel & Leistungen';
            $currentPage = 'artikel-leistungen';
        } elseif ($page === 'artikel-leistungen') {
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
            $voucherYear = max(2000, (int) ($_GET['year'] ?? date('Y')));
            $voucherTypeFilter = (string) ($_GET['type'] ?? '');
            if (!array_key_exists($voucherTypeFilter, VoucherRepository::voucherTypeOptions())) {
                $voucherTypeFilter = '';
            }
            $voucherList = VoucherRepository::list([
                'year' => $voucherYear,
                'type' => $voucherTypeFilter,
                'search' => $voucherSearch,
                'page' => $voucherPage,
            ]);
            $voucherYears = VoucherRepository::availableYears();
            $contentTemplate = 'modules/buchhaltung-belege';
            $title = 'Belege';
            $currentPage = 'buchhaltung-belege';
        } elseif ($page === 'buchhaltung-belege') {
            header('Location: /app', true, 302);
            exit;
        } elseif ($page === 'buchhaltung-beleg-form' && MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')) {
            $voucherId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($action === 'new') {
                if (!$canEdit) {
                    header('Location: ' . RoleResolver::homePath($user), true, 302);
                    exit;
                }
                $contentTemplate = 'modules/buchhaltung-beleg-form';
                $title = 'Neuer Beleg';
                $currentPage = 'buchhaltung-belege';
                $form = VoucherRepository::emptyForm();
                $prefillContactId = (int) ($_GET['contact_id'] ?? 0);
                if ($prefillContactId > 0) {
                    $prefillContact = ContactRepository::findById($prefillContactId);
                    if ($prefillContact !== null) {
                        $label = trim($prefillContact->companyName);
                        if ($label === '') {
                            $label = trim($prefillContact->displayName);
                        }
                        $form['contact_id'] = (string) $prefillContactId;
                        $form['contact_label'] = $label;
                        if (trim($form['supplier_name']) === '') {
                            $form['supplier_name'] = $label;
                        }
                    }
                }
                $formError = null;
            } elseif ($action === 'edit' && $voucherId > 0) {
                $voucher = VoucherRepository::findById($voucherId);
                if ($voucher === null) {
                    Flash::set('error', 'Beleg nicht gefunden.');
                    header('Location: /app?page=buchhaltung-belege', true, 302);
                    exit;
                }
                $contentTemplate = 'modules/buchhaltung-beleg-form';
                $title = $canEdit ? 'Beleg bearbeiten' : 'Beleg anzeigen';
                $currentPage = 'buchhaltung-belege';
                $form = VoucherRepository::toForm($voucher);
                $formError = null;
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
            $ledgerYear = max(2000, (int) ($_GET['year'] ?? (int) date('Y')));
            $ledgerAccount = preg_replace('/[^0-9A-Za-z]/', '', (string) ($_GET['account'] ?? '')) ?? '';
            $ledgerSearch = trim((string) ($_GET['s'] ?? ''));
            $ledgerShowEmpty = !empty($_GET['empty']);
            $ledgerYearStatus = FiscalYearService::status($ledgerYear);
            if ($ledgerAccount !== '') {
                $ledgerStatement = LedgerRepository::accountStatement($ledgerAccount, $ledgerYear);
            } else {
                $ledgerOverview = LedgerRepository::accountOverview($ledgerYear, [
                    'search' => $ledgerSearch,
                    'show_empty' => $ledgerShowEmpty,
                ]);
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
            $isAdmin = RoleResolver::isAdmin($user);
            $contentTemplate = 'modules/buchhaltung-jahresabschluss';
            $title = 'Jahresabschluss';
            $currentPage = 'buchhaltung-jahresabschluss';
        } elseif ($page === 'buchhaltung-jahresabschluss') {
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
        } elseif ($page === 'kontakte' && MenuRegistry::canAccess($user, 'kontakte')) {
            $contactId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $contactSearch = trim((string) ($_GET['s'] ?? ''));
            $contactPage = max(1, (int) ($_GET['paged'] ?? 1));
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
                $formError = null;
                $bankAccounts = ContactRepository::defaultBankAccounts();
                $employeeData = EmployeeData::empty();
                $employeeFiles = ContactFileStorage::emptyFiles();
                $showEmployeeFields = false;
                $linkFormContext = ContactCompanyLinkRepository::formContext(null, []);
                extract($linkFormContext);
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
                $bankAccounts = $contact->bankAccounts !== [] ? $contact->bankAccounts : ContactRepository::defaultBankAccounts();
                $employeeData = $contact->employeeData;
                $employeeFiles = $contact->employeeFiles;
                $showEmployeeFields = ContactAccessResolver::canViewEmployeeHrData($user, $contact);
                $canDeleteContact = ContactAccessResolver::canDeleteContact($user, $contact);
                $linkFormContext = ContactCompanyLinkRepository::formContext($contact, []);
                extract($linkFormContext);
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
                if ($canEdit && ContactAccessResolver::canEditContact($user, $contact)) {
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
            } else {
                $contactList = ContactRepository::paginate($contactSearch, $contactPage, $user);
                $contentTemplate = 'modules/kontakte';
                $title = 'Kontakte';
                $currentPage = 'kontakte';
            }
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
                $title = 'Bilder';
                $currentPage = 'bilder';
            }
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
                    || ($row['direction'] ?? '') !== 'in'
                    || ($row['status'] ?? '') !== 'received'
                    || empty($row['storage_path'])
                    || !MailboxRepository::userCanAccess($user, $mailboxId)
                ) {
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

            $contentTemplate = 'modules/post';
            $title = 'Post';
            $currentPage = 'post';
            $postMailboxes = MailboxRepository::accessibleForUser($user);
            $postSendableMailboxes = MailboxRepository::sendableForUser($user);
            $mailboxIds = array_values(array_filter(array_map(
                static fn(array $b): int => (int) ($b['id'] ?? 0),
                $postMailboxes
            )));
            $postMailboxFilter = max(0, (int) ($_GET['mailbox'] ?? 0));
            $postFolder = trim((string) ($_GET['folder'] ?? 'INBOX'));
            if ($postFolder === '') {
                $postFolder = 'INBOX';
            }
            $postCompose = !empty($_GET['compose']);
            $postFolders = MailFolderCatalog::foldersForView($postMailboxes, $postMailboxFilter);
            $postFolderLabel = MailFolderLabels::labelForPath($postFolder);
            foreach ($postFolders as $folderRow) {
                if (($folderRow['path'] ?? '') === $postFolder) {
                    $postFolderLabel = (string) ($folderRow['label'] ?? $postFolderLabel);
                    break;
                }
            }
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
                    $postComposeForm = [
                        'mailbox_id' => (string) $replyMailboxId,
                        'to' => (string) ($replyMsg['from_address'] ?? ''),
                        'subject' => PostMailComposer::replySubject((string) ($replyMsg['subject'] ?? '')),
                        'body' => '',
                        'reply_to_id' => (string) $replyId,
                    ];
                } else {
                    header('Location: /app?page=post', true, 302);
                    exit;
                }
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
                MailLogRepository::markRead($mailViewId, ($postMessage['direction'] ?? '') === 'in');
                $postMessage['body_html'] = '';
                $postMessage['body_text'] = '';
                if (!empty($postMessage['storage_path'])) {
                    try {
                        $archivePath = MailArchiveStorage::absolutePath((string) $postMessage['storage_path']);
                        $bodies = MailMimeReader::bodiesFromFile($archivePath);
                        $postMessage['body_html'] = (string) ($bodies['html'] ?? '');
                        $postMessage['body_text'] = (string) ($bodies['text'] ?? '');
                    } catch (Throwable) {
                        // Archiv optional — Vorschau bleibt Fallback
                    }
                }
                if ($postMessage['body_html'] === '' && $postMessage['body_text'] === '') {
                    $postMessage['body_text'] = MailMessage::bodyPreview((string) ($postMessage['body_preview'] ?? ''));
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
                        }
                    }
                }
            } elseif (!$postCompose) {
                $filter = $postMailboxFilter > 0 ? $postMailboxFilter : null;
                $postInbox = MailLogRepository::folderMessagesForMailboxes(
                    $mailboxIds,
                    $postFolder,
                    50,
                    $filter
                );
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
        $formError = $formError ?? null;
        $bankAccounts = $bankAccounts ?? ContactRepository::defaultBankAccounts();
        $employeeData = $employeeData ?? EmployeeData::empty();
        $employeeFiles = $employeeFiles ?? ContactFileStorage::emptyFiles();
        $showEmployeeFields = $showEmployeeFields ?? false;
        $bookingId = $bookingId ?? null;
        $booking = $booking ?? null;
        $smtpTestReport = $smtpTestReport ?? null;
        $appearanceConfig = $appearanceConfig ?? AppearanceSettings::forForm();
        $crmThemeConfig = $crmThemeConfig ?? CrmThemeSettings::forForm();
        $departmentsData = $departmentsData ?? DepartmentRepository::allWithMembers();
        $departmentEmployees = $departmentEmployees ?? DepartmentRepository::assignableEmployees();
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
        $postSendableMailboxes = $postSendableMailboxes ?? [];
        $postFolder = $postFolder ?? 'INBOX';
        $postFolders = $postFolders ?? [];
        $postFolderLabel = $postFolderLabel ?? 'Posteingang';
        $postIsSentFolder = $postIsSentFolder ?? false;
        $postImapLive = $postImapLive ?? false;
        $postImapAsync = $postImapAsync ?? false;
        $buchhaltungSection = $buchhaltungSection ?? MenuRegistry::buchhaltungSection($user);
        $chartOfAccountsConfig = $chartOfAccountsConfig ?? ChartOfAccountsSettings::forForm();
        $chartAccountCount = $chartAccountCount ?? 0;
        $chartCatalogCount = $chartCatalogCount ?? ChartAccountCatalog::catalogCount(ChartOfAccountsSettings::activeSkrType());
        $chartHintCount = $chartHintCount ?? ChartAccountRepository::countWithDetailedHints(ChartOfAccountsSettings::activeSkrType());
        $voucherSearch = $voucherSearch ?? '';
        $voucherPage = $voucherPage ?? 1;
        $voucherYear = $voucherYear ?? (int) date('Y');
        $voucherTypeFilter = $voucherTypeFilter ?? '';
        $voucherYears = $voucherYears ?? [(int) date('Y')];
        $voucherList = $voucherList ?? [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 25,
            'total_pages' => 1,
        ];
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
        $isAdmin = $isAdmin ?? RoleResolver::isAdmin($user);

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
            'buchhaltungSection',
            'currentPage',
            'settingsNav',
            'settingsSelection',
            'flash',
            'dbConfig',
            'dbConnected',
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
            'postSendableMailboxes',
            'postFolder',
            'postFolders',
            'postFolderLabel',
            'postIsSentFolder',
            'postImapLive',
            'postImapAsync',
            'smtpTestReport',
            'appearanceConfig',
            'crmThemeConfig',
            'departmentsData',
            'departmentEmployees',
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
            'companyConfig',
            'companyExtended',
            'taxAdvisorConfig',
            'taxAdvisorCompanyOptions',
            'chartOfAccountsConfig',
            'chartAccountCount',
            'chartCatalogCount',
            'chartHintCount',
            'voucherList',
            'voucherSearch',
            'voucherPage',
            'voucherYear',
            'voucherTypeFilter',
            'voucherYears',
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
            'jaYear',
            'jaPreview',
            'fiscalYears',
            'jaYearStatus',
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
            'bankAccounts',
            'employeeData',
            'employeeFiles',
            'showEmployeeFields',
            'allowedContactRoles',
            'canDeleteContact',
            'companyEmployees',
            'employerForm',
            'employerLink',
            'companyContactOptions',
            'personContactOptions',
            'bookingId',
            'booking',
            'bookingArticleOptions',
            'bookingEmployeeOptions',
            'mediaList',
            'mediaItem',
            'mediaIsNew'
        ));
        break;

    default:
        http_response_code(404);
        if (AuthService::check()) {
            header('Location: /app', true, 302);
        } else {
            View::render('offline');
        }
        break;
}

