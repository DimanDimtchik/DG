<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

$plans = ShopPlans::all();
$appName = (string) ShopApp::config('name');
$marketingUrl = (string) ShopApp::config('marketing_url');
$contactEmail = (string) ShopApp::config('contact_email');
$legal = require SHOP_ROOT . '/config/legal.php';

if (str_starts_with($path, '/admin')) {
    if ($path === '/admin/logout') {
        ShopAdminAuth::logout();
        header('Location: /admin/login', true, 302);
        exit;
    }

    if ($path === '/admin/login') {
        $error = null;
        $returnTo = trim((string) ($_GET['return'] ?? $_POST['return'] ?? '/admin/wartung'));
        if ($returnTo === '' || !str_starts_with($returnTo, '/admin')) {
            $returnTo = '/admin/wartung';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            if (ShopAdminAuth::attempt($password)) {
                header('Location: ' . $returnTo, true, 302);
                exit;
            }
            $error = 'Anmeldung fehlgeschlagen.';
        }
        ShopView::render('admin-login', [
            'title' => 'Shop-Verwaltung — Anmelden',
            'error' => $error,
            'returnTo' => $returnTo,
            'adminConfigured' => ShopAdminAuth::isConfigured(),
        ], true);
        exit;
    }

    if ($path === '/admin/wartung') {
        ShopAdminAuth::requireLogin();
        $flashOk = $_SESSION['shop_admin_flash_ok'] ?? null;
        $flashErr = $_SESSION['shop_admin_flash_err'] ?? null;
        unset($_SESSION['shop_admin_flash_ok'], $_SESSION['shop_admin_flash_err']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                ShopMaintenance::save($_POST);
                $_SESSION['shop_admin_flash_ok'] = 'Wartungsmodus gespeichert.';
            } catch (Throwable $e) {
                $_SESSION['shop_admin_flash_err'] = $e->getMessage();
            }
            header('Location: /admin/wartung', true, 302);
            exit;
        }

        ShopView::render('admin-maintenance', [
            'title' => 'Shop — Wartungsmodus',
            'maintEnabled' => ShopMaintenance::isActive(),
            'maintenance' => ShopMaintenance::config(),
            'flashOk' => $flashOk,
            'flashErr' => $flashErr,
            'adminConfigured' => ShopAdminAuth::isConfigured(),
        ], true);
        exit;
    }

    http_response_code(404);
    echo 'Seite nicht gefunden.';
    exit;
}

switch ($path) {
    case '/':
        ShopView::render('home', [
            'title' => $appName . ' – CRM kaufen',
            'plans' => $plans,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/preise':
        ShopView::render('pricing', [
            'title' => 'Preise – ' . $appName,
            'plans' => $plans,
            'billing' => ($_GET['billing'] ?? 'monatlich') === 'jaehrlich' ? 'jaehrlich' : 'monatlich',
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/impressum':
        ShopView::render('impressum', [
            'title' => 'Impressum – ' . $appName,
            'legal' => $legal,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/datenschutz':
        ShopView::render('datenschutz', [
            'title' => 'Datenschutz – ' . $appName,
            'legal' => $legal,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/agb':
        ShopView::render('agb', [
            'title' => 'AGB – ' . $appName,
            'legal' => $legal,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/widerruf':
        ShopView::render('widerruf', [
            'title' => 'Widerruf – ' . $appName,
            'legal' => $legal,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/checkout':
        $planId = trim((string) ($_GET['plan'] ?? $_POST['plan'] ?? 'starter'));
        $billing = trim((string) ($_GET['billing'] ?? $_POST['billing_cycle'] ?? 'monatlich'));
        if ($billing !== ShopCheckout::BILLING_YEARLY) {
            $billing = ShopCheckout::BILLING_MONTHLY;
        }
        $plan = ShopPlans::get($planId);
        if ($plan === null) {
            header('Location: /preise', true, 302);
            exit;
        }

        $errors = [];
        $form = [
            'plan' => $planId,
            'billing_cycle' => $billing,
            'company_name' => '',
            'domain' => '',
            'domain_raw' => '',
            'contact_name' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'business_profile' => '',
            'privacy' => '',
            'agb' => '',
        ];
        $preview = null;
        $domainCheck = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = ShopCheckout::validate($_POST);
            $form = array_merge($form, $result['data']);
            $errors = $result['errors'];
            if ($form['domain'] !== '') {
                $domainCheck = ShopDomainCheck::check($form['domain_raw'] !== '' ? $form['domain_raw'] : $form['domain']);
                if (!empty($domainCheck['blocks'])) {
                    $errors[] = (string) $domainCheck['message'];
                }
            }
            if ($result['ok'] && $errors === []) {
                $chosen = ShopPlans::get($form['plan']);
                $isYearly = $form['billing_cycle'] === ShopCheckout::BILLING_YEARLY;
                $preview = [
                    'plan' => $chosen,
                    'billing_cycle' => $form['billing_cycle'],
                    'net' => $isYearly ? (float) $chosen['yearly_net'] : (float) $chosen['monthly_net'],
                    'gross' => $isYearly ? (float) $chosen['yearly_gross'] : (float) $chosen['monthly_gross'],
                    'note' => 'Zahlung mit Stripe folgt in Phase 2. Ihre Angaben wurden geprüft und sind bereit für die Einrichtung.',
                    'kdv_payload' => [
                        'company_name' => $form['company_name'],
                        'domain' => $form['domain'],
                        'contact_name' => $form['contact_name'],
                        'contact_email' => $form['contact_email'],
                        'contact_phone' => $form['contact_phone'],
                        'tariff' => (string) $chosen['kdv_tariff'],
                        'billing_cycle' => $form['billing_cycle'],
                        'monthly_price' => (float) $chosen['monthly_net'],
                        'business_profile' => $form['business_profile'],
                        'business_kind' => $form['business_profile'] !== ''
                            ? ShopCheckout::businessKindsForProfile($form['business_profile'])
                            : [],
                        'website_intent' => $form['domain'] !== '' && ShopCheckout::isWebsiteIntent($form['domain']),
                    ],
                ];
                $_SESSION['shop_checkout_draft'] = $preview;
            } else {
                $preview = null;
                unset($_SESSION['shop_checkout_draft']);
            }
        }

        $stripeReady = ShopStripe::isConfigured();
        if ($preview !== null && $stripeReady) {
            $preview['note'] = 'Angaben geprüft. Klicken Sie auf „Zur sicheren Zahlung“, um mit Stripe zu bezahlen.';
        }

        ShopView::render('checkout', [
            'title' => 'Bestellen – ' . $appName,
            'plan' => $plan,
            'plans' => $plans,
            'form' => $form,
            'errors' => $errors,
            'preview' => $preview,
            'domainCheck' => $domainCheck,
            'stripeReady' => $stripeReady,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/checkout/pay':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /checkout', true, 302);
            exit;
        }
        $draft = $_SESSION['shop_checkout_draft'] ?? null;
        if (!is_array($draft)) {
            header('Location: /preise', true, 302);
            exit;
        }
        $session = ShopStripe::createCheckoutSession($draft);
        if (!$session['ok'] || empty($session['url'])) {
            $_SESSION['shop_checkout_pay_error'] = $session['error'] ?? 'Zahlung konnte nicht gestartet werden.';
            header('Location: /checkout?plan=' . rawurlencode((string) ($draft['plan']['id'] ?? 'starter')), true, 302);
            exit;
        }
        header('Location: ' . $session['url'], true, 303);
        exit;

    case '/checkout/success':
        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        $ok = false;
        $message = 'Wir prüfen Ihre Zahlung …';
        if ($sessionId !== '') {
            $sess = ShopStripe::retrieveSession($sessionId);
            if ($sess['ok'] && (($sess['data']['payment_status'] ?? '') === 'paid' || ($sess['data']['status'] ?? '') === 'complete')) {
                $ok = true;
                $message = 'Zahlung bestätigt. Die Einrichtung Ihres CRM startet automatisch; Sie erhalten eine E-Mail.';
                // Best-effort provision if webhook delayed
                $prov = ShopStripe::provisionFromSession($sess['data'] ?? []);
                if (!$prov['ok']) {
                    $message .= ' (Hinweis: Einrichtung folgt in Kürze – Support: ' . $contactEmail . ')';
                }
                unset($_SESSION['shop_checkout_draft']);
            } else {
                $message = $sess['error'] ?? 'Zahlung noch nicht bestätigt. Bitte Seite in einer Minute neu laden.';
            }
        }
        ShopView::render('checkout-success', [
            'title' => ($ok ? 'Erfolg' : 'Zahlung') . ' – ' . $appName,
            'ok' => $ok,
            'message' => $message,
            'sessionId' => $sessionId,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/checkout/cancel':
        ShopView::render('checkout-cancel', [
            'title' => 'Abgebrochen – ' . $appName,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/webhook/stripe':
        ShopStripe::handleWebhook();
        exit;

    case '/konto':
    case '/konto/login':
        if ($path === '/konto' && ShopAccountSession::token()) {
            $token = ShopAccountSession::token();
            $me = ShopAccountApi::me((string) $token);
            if (!$me['ok']) {
                ShopAccountSession::clear();
                header('Location: /konto/login', true, 302);
                exit;
            }
            ShopAccountSession::setAccount($me['account'] ?? []);
            ShopView::render('account', [
                'title' => 'Mein Konto – ' . $appName,
                'account' => $me['account'] ?? [],
                'flashOk' => $_SESSION['shop_flash_ok'] ?? null,
                'flashErr' => $_SESSION['shop_flash_err'] ?? null,
                'marketingUrl' => $marketingUrl,
                'contactEmail' => $contactEmail,
            ]);
            unset($_SESSION['shop_flash_ok'], $_SESSION['shop_flash_err']);
            break;
        }

        if ($path === '/konto') {
            header('Location: /konto/login', true, 302);
            exit;
        }

        if (ShopAccountSession::token()) {
            header('Location: /konto', true, 302);
            exit;
        }

        $errors = [];
        $email = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $login = ShopAccountApi::login($email, $password);
            if ($login['ok'] && ($login['token'] ?? '') !== '') {
                ShopAccountSession::setToken((string) $login['token']);
                ShopAccountSession::setAccount($login['account'] ?? []);
                header('Location: /konto', true, 302);
                exit;
            }
            $errors[] = $login['error'] ?? 'Anmeldung fehlgeschlagen.';
        }
        ShopView::render('account-login', [
            'title' => 'Konto – ' . $appName,
            'errors' => $errors,
            'email' => $email,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/konto/logout':
        $token = ShopAccountSession::token();
        if ($token) {
            ShopAccountApi::logout($token);
        }
        ShopAccountSession::clear();
        header('Location: /konto/login', true, 302);
        exit;

    case '/konto/entsperren':
        $token = ShopAccountSession::requireLogin();
        $me = ShopAccountApi::me($token);
        if (!$me['ok']) {
            ShopAccountSession::clear();
            header('Location: /konto/login', true, 302);
            exit;
        }
        $account = $me['account'] ?? [];
        $errors = [];
        $resultMessage = null;
        $autoRejected = !empty($account['unlock_auto_rejected']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$autoRejected) {
            $message = trim((string) ($_POST['message'] ?? ''));
            $res = ShopAccountApi::unlockRequest($token, $message);
            if (!$res['ok']) {
                $errors[] = $res['error'] ?? 'Anfrage fehlgeschlagen.';
            } else {
                $resultMessage = (string) ($res['message'] ?? '');
                $autoRejected = empty($res['accepted']);
                if (!empty($res['accepted'])) {
                    $_SESSION['shop_flash_ok'] = $resultMessage;
                    header('Location: /konto', true, 302);
                    exit;
                }
            }
        }

        ShopView::render('account-unlock', [
            'title' => 'Entsperrung – ' . $appName,
            'account' => $account,
            'errors' => $errors,
            'resultMessage' => $resultMessage,
            'autoRejected' => $autoRejected,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/konto/passwort-vergessen':
        if (ShopAccountSession::token()) {
            header('Location: /konto', true, 302);
            exit;
        }
        $errors = [];
        $email = '';
        $success = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $res = ShopAccountApi::requestPasswordReset($email);
            if ($res['ok']) {
                $success = (string) ($res['message'] ?? '');
            } else {
                $errors[] = $res['error'] ?? 'Anfrage fehlgeschlagen.';
            }
        }
        ShopView::render('account-forgot', [
            'title' => 'Passwort vergessen – ' . $appName,
            'errors' => $errors,
            'email' => $email,
            'success' => $success,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    case '/konto/passwort-neu':
        if (ShopAccountSession::token()) {
            header('Location: /konto', true, 302);
            exit;
        }
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        $errors = [];
        $success = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = trim((string) ($_POST['token'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');
            $res = ShopAccountApi::confirmPasswordReset($token, $password, $confirm);
            if ($res['ok']) {
                $success = (string) ($res['message'] ?? 'Passwort geändert.');
            } else {
                $errors[] = $res['error'] ?? 'Passwort konnte nicht gesetzt werden.';
            }
        }
        ShopView::render('account-reset', [
            'title' => 'Neues Passwort – ' . $appName,
            'errors' => $errors,
            'token' => $token,
            'success' => $success,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
        ]);
        break;

    default:
        http_response_code(404);
        ShopView::render('home', [
            'title' => 'Nicht gefunden – ' . $appName,
            'plans' => $plans,
            'marketingUrl' => $marketingUrl,
            'contactEmail' => $contactEmail,
            'notFound' => true,
        ]);
        break;
}
