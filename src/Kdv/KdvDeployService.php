<?php
declare(strict_types=1);

/**
 * Automated provisioning of new CRM customer instances.
 *
 * Steps performed:
 * 1. Create domain via KAS-API (add_domain)
 * 2. Create database via KAS-API (add_database)
 * 3. Create initial mailbox via KAS-API (add_mailaccount)
 * 4. Deploy CRM files via SCP
 * 5. Write database.local.php and app.local.php on remote
 * 6. Generate install URL and send invitation email
 */
final class KdvDeployService
{
    private const KAS_SOAP_AUTH = 'https://kasapi.kasserver.com/soap/wsdl/KasAuth.wsdl';
    private const KAS_SOAP_API  = 'https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl';

    private const SSH_KEY_PATH  = '~/.ssh/id_ed25519_ganzom';
    private const CRM_SOURCE    = '/src/crm-release';

    /**
     * Full provisioning pipeline for a new customer.
     *
     * @param array{
     *   kas_login: string, kas_pass: string,
     *   domain: string, company_name: string,
     *   contact_email: string, contact_name: string,
     *   ssh_host?: string
     * } $params
     * @return array{
     *   success: bool,
     *   steps: list<array{step: string, ok: bool, detail: string}>,
     *   install_url?: string,
     *   mailbox_email?: string,
     *   mailbox_password?: string
     * }
     */
    public static function provision(array $params): array
    {
        $steps = [];
        $kasLogin = $params['kas_login'];
        $kasPass  = $params['kas_pass'];
        $domain   = $params['domain'];
        $sshHost  = $params['ssh_host'] ?? 'ssh-' . $kasLogin . '@' . $domain;
        $customerId = (int) ($params['customer_id'] ?? 0);
        $mailPass = '';
        $mailboxEmail = 'info@' . $domain;

        // 1. Authenticate with KAS
        try {
            $token = self::kasAuth($kasLogin, $kasPass);
            $steps[] = ['step' => 'KAS-Authentifizierung', 'ok' => true, 'detail' => 'Token erhalten'];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'KAS-Authentifizierung', 'ok' => false, 'detail' => $e->getMessage()];
            return ['success' => false, 'steps' => $steps];
        }

        // 2. Create domain
        try {
            self::kasCall($kasLogin, $token, 'add_domain', [
                'domain_name' => self::domainName($domain),
                'domain_tld'  => self::domainTld($domain),
                'domain_path' => '/',
                'ssl_proxy'   => 'Y',
                'redirect_status' => '0',
            ]);
            $steps[] = ['step' => 'Domain anlegen', 'ok' => true, 'detail' => $domain];
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'bereits')) {
                $steps[] = ['step' => 'Domain anlegen', 'ok' => true, 'detail' => 'Domain existiert bereits'];
            } else {
                $steps[] = ['step' => 'Domain anlegen', 'ok' => false, 'detail' => $e->getMessage()];
                return ['success' => false, 'steps' => $steps];
            }
        }

        // 3. Create database
        $dbName = '';
        $dbPass = '';
        try {
            $dbPass = self::generatePassword();
            $result = self::kasCall($kasLogin, $token, 'add_database', [
                'database_password' => $dbPass,
            ]);
            $dbName = is_string($result) ? $result : ($kasLogin . '_crm');
            $steps[] = ['step' => 'Datenbank erstellen', 'ok' => true, 'detail' => "DB: $dbName"];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'Datenbank erstellen', 'ok' => false, 'detail' => $e->getMessage()];
            return ['success' => false, 'steps' => $steps];
        }

        // 4. Create mailbox info@domain
        try {
            $mailPass = self::generatePassword(24);
            self::kasCall($kasLogin, $token, 'add_mailaccount', [
                'mail_password'    => $mailPass,
                'mail_local_part'  => 'info',
                'mail_domain_part' => $domain,
            ]);
            $steps[] = ['step' => 'E-Mail-Postfach', 'ok' => true, 'detail' => $mailboxEmail . ' (Passwort erzeugt)'];
        } catch (Throwable $e) {
            // Non-fatal: mailbox might already exist — then we cannot know the password
            $steps[] = ['step' => 'E-Mail-Postfach', 'ok' => true, 'detail' => 'Übersprungen: ' . $e->getMessage()];
            $mailPass = '';
        }

        // 5. Deploy CRM files via SCP
        try {
            self::deployCrmFiles($kasLogin, $domain, $sshHost);
            $steps[] = ['step' => 'CRM-Dateien deployen', 'ok' => true, 'detail' => 'Dateien übertragen'];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'CRM-Dateien deployen', 'ok' => false, 'detail' => $e->getMessage()];
            return ['success' => false, 'steps' => $steps];
        }

        // 6. Write config files on remote
        try {
            self::writeRemoteConfig($kasLogin, $domain, $sshHost, $dbName, $dbPass, $params);
            $steps[] = ['step' => 'Konfiguration schreiben', 'ok' => true, 'detail' => 'database.local.php + app.local.php + Install-Vorausfüllung'];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'Konfiguration schreiben', 'ok' => false, 'detail' => $e->getMessage()];
            return ['success' => false, 'steps' => $steps];
        }

        // 7. Update customer record (+ mailbox credentials if newly created)
        $installUrl = 'https://' . $domain . '/install.php';
        try {
            if ($customerId < 1) {
                $customers = KdvCustomerRepository::list();
                foreach ($customers as $c) {
                    if (($c['domain'] ?? '') === $domain) {
                        $customerId = (int) $c['id'];
                        break;
                    }
                }
            }
            if ($customerId > 0) {
                $existing = KdvCustomerRepository::findById($customerId) ?? [];
                KdvCustomerRepository::save([
                    'status'      => 'installiert',
                    'db_name'     => $dbName,
                    'kas_login'   => $kasLogin,
                    'crm_version' => App::version(),
                ] + $existing, $customerId);
                if ($mailPass !== '') {
                    KdvCustomerRepository::setMailboxCredentials($customerId, $mailboxEmail, $mailPass);
                }
            }
            $steps[] = ['step' => 'Status aktualisieren', 'ok' => true, 'detail' => 'Status → installiert'];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'Status aktualisieren', 'ok' => false, 'detail' => $e->getMessage()];
        }

        // 8. Send install link + mailbox access to customer
        try {
            self::sendInstallEmail(
                $params['contact_email'],
                $params['contact_name'],
                $params['company_name'],
                $installUrl,
                $mailPass !== '' ? $mailboxEmail : '',
                $mailPass
            );
            $detail = 'Gesendet an ' . $params['contact_email'];
            if ($mailPass !== '') {
                $detail .= ' (inkl. Postfach-Passwort)';
            }
            $steps[] = ['step' => 'Installations-E-Mail', 'ok' => true, 'detail' => $detail];
        } catch (Throwable $e) {
            $steps[] = ['step' => 'Installations-E-Mail', 'ok' => false, 'detail' => $e->getMessage()];
        }

        $out = ['success' => true, 'steps' => $steps, 'install_url' => $installUrl];
        if ($mailPass !== '') {
            $out['mailbox_email'] = $mailboxEmail;
            $out['mailbox_password'] = $mailPass;
        }
        return $out;
    }

    // ── KAS-API ─────────────────────────────────────────────────────

    /** @throws SoapFault Bei KAS-Auth-Fehler. */
    private static function kasAuth(string $login, string $password): string
    {
        $client = new SoapClient(self::KAS_SOAP_AUTH);
        return $client->KasAuth(json_encode([
            'kas_login'     => $login,
            'kas_auth_type' => 'plain',
            'kas_auth_data' => $password,
        ]));
    }

    /**
     * @param array<string, mixed> $params
     * @throws SoapFault Bei KAS-API-Fehler.
     * @return mixed ReturnInfo aus der KAS-Antwort.
     */
    private static function kasCall(string $login, string $token, string $action, array $params = [])
    {
        $client = new SoapClient(self::KAS_SOAP_API);
        $response = $client->KasApi(json_encode([
            'kas_login'        => $login,
            'kas_auth_type'    => 'session',
            'kas_auth_data'    => $token,
            'kas_action'       => $action,
            'KasRequestParams' => $params,
        ]));

        if (is_array($response) && isset($response['Response']['ReturnInfo'])) {
            return $response['Response']['ReturnInfo'];
        }
        return $response;
    }

    // ── Deploy ──────────────────────────────────────────────────────

    /**
     * Kopiert CRM-Verzeichnisse per SCP auf den Kunden-Webspace.
     *
     * @throws RuntimeException Bei SSH/SCP-Fehler.
     */
    private static function deployCrmFiles(string $kasLogin, string $domain, string $sshHost): void
    {
        $sshKey = self::resolveKeyPath();
        $remotePath = "~/www/htdocs/$kasLogin/$domain";

        $dirs = ['src', 'views', 'assets', 'database', 'bin', 'config', 'storage'];
        $files = ['index.php', 'bootstrap.php', '.htaccess', 'install.php'];

        // Create remote directory structure
        $mkdirs = implode(' ', array_map(fn($d) => "$remotePath/$d", $dirs));
        self::execSsh($sshKey, $sshHost, "mkdir -p $mkdirs $remotePath/storage/{logs,media,contacts,mail/sent,mail/inbox,vouchers} $remotePath/tmp-upload");

        // SCP directories
        $dgRoot = defined('DG_ROOT') ? DG_ROOT : dirname(__DIR__, 2);
        foreach ($dirs as $dir) {
            $localDir = $dgRoot . '/' . $dir;
            if (is_dir($localDir)) {
                self::execCmd("scp -i " . escapeshellarg($sshKey) . " -r " . escapeshellarg($localDir) . " " . escapeshellarg("$sshHost:$remotePath/"));
            }
        }

        // SCP files
        foreach ($files as $file) {
            $localFile = $dgRoot . '/' . $file;
            if (file_exists($localFile)) {
                self::execCmd("scp -i " . escapeshellarg($sshKey) . " " . escapeshellarg($localFile) . " " . escapeshellarg("$sshHost:$remotePath/$file"));
            }
        }

        // .htaccess for storage
        self::execSsh($sshKey, $sshHost, "echo 'Deny from all' > $remotePath/storage/.htaccess");
    }

    /** Schreibt database.local.php und app.local.php auf dem Remote-Server. */
    private static function writeRemoteConfig(string $kasLogin, string $domain, string $sshHost, string $dbName, string $dbPass, array $params = []): void
    {
        $sshKey = self::resolveKeyPath();
        $remotePath = "~/www/htdocs/$kasLogin/$domain";

        $dbConfig = sprintf(
            "<?php\nreturn [\n    'host' => 'localhost',\n    'name' => %s,\n    'user' => %s,\n    'pass' => %s,\n];\n",
            var_export($dbName, true),
            var_export($dbName, true),
            var_export($dbPass, true)
        );

        $appConfig = sprintf(
            "<?php\nreturn [\n    'home_url' => %s,\n    'public_url' => %s,\n];\n",
            var_export('https://' . $domain, true),
            var_export('https://' . $domain, true)
        );

        self::execSsh($sshKey, $sshHost,
            "printf " . escapeshellarg($dbConfig) . " > $remotePath/config/database.local.php"
        );
        self::execSsh($sshKey, $sshHost,
            "printf " . escapeshellarg($appConfig) . " > $remotePath/config/app.local.php"
        );

        $prefill = [
            'company_name' => trim((string) ($params['company_name'] ?? '')),
            'contact_email' => trim((string) ($params['contact_email'] ?? '')),
            'contact_phone' => trim((string) ($params['contact_phone'] ?? '')),
            'business_profile' => trim((string) ($params['business_profile'] ?? '')),
            'business_kind' => is_array($params['business_kind'] ?? null) ? $params['business_kind'] : [],
        ];
        if ($prefill['company_name'] !== '' || $prefill['business_profile'] !== '' || $prefill['business_kind'] !== []) {
            $prefillJson = json_encode($prefill, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            self::execSsh($sshKey, $sshHost,
                "mkdir -p $remotePath/storage && printf " . escapeshellarg($prefillJson) . " > $remotePath/storage/install-prefill.json"
            );
        }
    }

    // ── E-Mail ──────────────────────────────────────────────────────

    /** Sendet Installationslink und optional Postfach-Zugangsdaten. */
    private static function sendInstallEmail(
        string $email,
        string $name,
        string $company,
        string $installUrl,
        string $mailboxEmail = '',
        string $mailboxPassword = ''
    ): void {
        $subject = "CRM-Installation für $company";
        $html = '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:0 auto;padding:2rem">'
            . '<h2 style="color:#1e293b">Ihr CRM ist bereit</h2>'
            . '<p>Hallo ' . htmlspecialchars($name !== '' ? $name : 'Kunde') . ',</p>'
            . '<p>Ihr CRM-System für <strong>' . htmlspecialchars($company) . '</strong> wurde auf dem Server eingerichtet. '
            . 'Bitte schließen Sie die Installation ab:</p>'
            . '<p style="margin:24px 0"><a href="' . htmlspecialchars($installUrl) . '" '
            . 'style="display:inline-block;padding:12px 24px;background:#6e6258;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">'
            . 'Installation starten</a></p>'
            . '<p style="font-size:13px;color:#64748b">Sie werden durch einen Assistenten geführt. '
            . 'Für Ihr CRM-Benutzerkonto wählen Sie später selbst ein Passwort (Link per E-Mail).</p>';

        if ($mailboxEmail !== '' && $mailboxPassword !== '') {
            $html .= '<hr style="border:none;border-top:1px solid #e2e8f0;margin:28px 0">'
                . '<h3 style="color:#1e293b;font-size:1.05rem">Ihr E-Mail-Postfach</h3>'
                . '<p>Wir haben ein Postfach für Sie angelegt. Bitte bewahren Sie diese Daten sicher auf '
                . 'und ändern Sie das Passwort bei Gelegenheit im Webmail.</p>'
                . '<table style="border-collapse:collapse;width:100%;font-size:14px">'
                . '<tr><td style="padding:6px 8px;border:1px solid #e2e8f0;background:#f8fafc">E-Mail</td>'
                . '<td style="padding:6px 8px;border:1px solid #e2e8f0"><strong>' . htmlspecialchars($mailboxEmail) . '</strong></td></tr>'
                . '<tr><td style="padding:6px 8px;border:1px solid #e2e8f0;background:#f8fafc">Passwort</td>'
                . '<td style="padding:6px 8px;border:1px solid #e2e8f0"><code style="font-size:13px">' . htmlspecialchars($mailboxPassword) . '</code></td></tr>'
                . '<tr><td style="padding:6px 8px;border:1px solid #e2e8f0;background:#f8fafc">Webmail</td>'
                . '<td style="padding:6px 8px;border:1px solid #e2e8f0"><a href="https://webmail.kasserver.com">https://webmail.kasserver.com</a></td></tr>'
                . '<tr><td style="padding:6px 8px;border:1px solid #e2e8f0;background:#f8fafc">IMAP / SMTP</td>'
                . '<td style="padding:6px 8px;border:1px solid #e2e8f0">w0217246.kasserver.com · IMAP 993 SSL · SMTP 465 SSL</td></tr>'
                . '</table>'
                . '<p style="font-size:12px;color:#64748b;margin-top:12px">Benutzername ist immer die vollständige E-Mail-Adresse.</p>';
        }

        $attachments = self::installGuideAttachments();
        if ($attachments !== []) {
            $html .= '<p style="font-size:13px;color:#64748b;margin-top:24px">Als Anhang finden Sie die '
                . '<strong>Installationsanleitung</strong> (PDF) – Schritt für Schritt, auch zum Ausdrucken.</p>';
        }

        $html .= '</div>';

        if (class_exists('MailService') && class_exists('MailSettings') && MailSettings::isConfigured()) {
            MailService::send(new MailMessage(
                subject: $subject,
                htmlBody: $html,
                to: [$email],
                attachments: $attachments,
            ));
        } else {
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'dg.ganz-om.de') . "\r\n";
            @mail($email, $subject, $html, $headers);
        }
    }

    /**
     * Kunden-PDF für Install-Mail (Release: assets/docs, Dev: docs/).
     *
     * @return list<array{filename: string, content: string, mime?: string}>
     */
    private static function installGuideAttachments(): array
    {
        if (!defined('DG_ROOT') || !class_exists('MailMessage')) {
            return [];
        }
        $candidates = [
            DG_ROOT . '/assets/docs/CRM-Installationsanleitung-einfach.pdf',
            DG_ROOT . '/docs/CRM-Installationsanleitung-einfach.pdf',
        ];
        foreach ($candidates as $path) {
            $list = MailMessage::attachmentFromFile(
                $path,
                'CRM-Installationsanleitung.pdf',
                'application/pdf'
            );
            if ($list !== []) {
                return $list;
            }
        }

        return [];
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** Hostname ohne TLD (für KAS add_domain). */
    private static function domainName(string $domain): string
    {
        $parts = explode('.', $domain);
        return count($parts) > 2 ? implode('.', array_slice($parts, 0, -1)) : $parts[0];
    }

    /** Top-Level-Domain (letztes Label). */
    private static function domainTld(string $domain): string
    {
        $parts = explode('.', $domain);
        return end($parts);
    }

    /** Kryptographisch sicheres Passwort für DB/Mail. */
    private static function generatePassword(int $length = 20): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /** Absoluter Pfad zum Deploy-SSH-Key. */
    private static function resolveKeyPath(): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        return $home . '/.ssh/id_ed25519_ganzom';
    }

    /**
     * @throws RuntimeException Bei Exit-Code != 0.
     */
    private static function execSsh(string $key, string $host, string $command): string
    {
        $cmd = sprintf('ssh -o BatchMode=yes -o ConnectTimeout=10 -i %s %s %s 2>&1',
            escapeshellarg($key), escapeshellarg($host), escapeshellarg($command));
        return self::execCmd($cmd);
    }

    /**
     * @throws RuntimeException Bei Exit-Code != 0.
     */
    private static function execCmd(string $cmd): string
    {
        exec($cmd, $output, $code);
        $out = implode("\n", $output);
        if ($code !== 0) {
            throw new RuntimeException("Befehl fehlgeschlagen (Code $code): $out");
        }
        return $out;
    }
}
