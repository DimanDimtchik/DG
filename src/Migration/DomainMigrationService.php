<?php
declare(strict_types=1);

/**
 * Handles domain and email migration for new customers.
 *
 * Automated steps (via KAS-API):
 * - Create domain on All-Inkl
 * - Create mailboxes on All-Inkl
 * - Set DNS records
 * - Migrate emails via IMAP-to-IMAP
 *
 * Manual steps (instructions provided):
 * - Request Auth-Code from old provider (domain transfer)
 * - Update DNS nameservers at old provider (if keeping domain there)
 */
final class DomainMigrationService
{
    /**
     * Prüft, ob die Domain per DNS (A/AAAA) erreichbar aufgelöst wird.
     */
    public static function domainExists(string $domain): bool
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') return false;

        $records = @dns_get_record($domain, DNS_A | DNS_AAAA);
        return is_array($records) && count($records) > 0;
    }

    /**
     * Check if a domain has MX records (active email).
     */
    public static function hasMxRecords(string $domain): bool
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') return false;

        $records = @dns_get_record($domain, DNS_MX);
        return is_array($records) && count($records) > 0;
    }

    /**
     * Detect the current hosting provider from DNS/MX.
     */
    public static function detectProvider(string $domain): string
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') return 'unbekannt';

        $records = @dns_get_record($domain, DNS_NS);
        $ns = '';
        if (is_array($records) && !empty($records[0]['target'])) {
            $ns = strtolower((string) $records[0]['target']);
        }

        if (str_contains($ns, 'kasserver') || str_contains($ns, 'all-inkl')) return 'All-Inkl';
        if (str_contains($ns, 'strato')) return 'Strato';
        if (str_contains($ns, 'ionos') || str_contains($ns, '1und1') || str_contains($ns, 'ui-dns')) return 'IONOS (1&1)';
        if (str_contains($ns, 'hetzner')) return 'Hetzner';
        if (str_contains($ns, 'hosteurope')) return 'Host Europe';
        if (str_contains($ns, 'domainfactory') || str_contains($ns, 'df.eu')) return 'DomainFactory';
        if (str_contains($ns, 'cloudflare')) return 'Cloudflare';
        if (str_contains($ns, 'godaddy') || str_contains($ns, 'domaincontrol')) return 'GoDaddy';
        if (str_contains($ns, 'ovh')) return 'OVH';
        if (str_contains($ns, 'mittwald')) return 'Mittwald';
        if (str_contains($ns, 'netcup')) return 'Netcup';
        if (str_contains($ns, 'google')) return 'Google Domains';
        if (str_contains($ns, 'awsdns')) return 'AWS Route53';

        return $ns !== '' ? 'Unbekannt (' . $ns . ')' : 'unbekannt';
    }

    /**
     * Get migration instructions based on the scenario.
     *
     * @return list<array{step: int, title: string, description: string, automated: bool}>
     */
    public static function domainMigrationSteps(string $currentProvider, bool $transferDomain): array
    {
        $steps = [];
        $n = 1;

        if ($transferDomain) {
            $steps[] = [
                'step' => $n++,
                'title' => 'Auth-Code beim aktuellen Provider anfordern',
                'description' => self::authCodeInstructions($currentProvider),
                'automated' => false,
            ];
            $steps[] = [
                'step' => $n++,
                'title' => 'Domain bei All-Inkl bestellen',
                'description' => 'Wir bestellen die Domain mit dem Auth-Code über die KAS-API bei All-Inkl. '
                    . 'Der Transfer dauert bei .de-Domains ca. 1–3 Tage, bei .com/.net ca. 5–7 Tage.',
                'automated' => true,
            ];
        } else {
            $steps[] = [
                'step' => $n++,
                'title' => 'DNS-Nameserver beim aktuellen Provider ändern',
                'description' => 'Ändern Sie die Nameserver Ihrer Domain auf die All-Inkl Nameserver: '
                    . 'ns5.kasserver.com, ns6.kasserver.com, ns7.kasserver.com. '
                    . 'Die Änderung wird innerhalb von 24–48 Stunden wirksam.',
                'automated' => false,
            ];
        }

        $steps[] = [
            'step' => $n++,
            'title' => 'Website-Daten werden automatisch eingerichtet',
            'description' => 'Das CRM und alle notwendigen Dateien werden auf dem neuen Webspace installiert.',
            'automated' => true,
        ];

        $steps[] = [
            'step' => $n++,
            'title' => 'SSL-Zertifikat einrichten',
            'description' => 'Ein kostenloses Let\'s-Encrypt-Zertifikat wird automatisch beantragt.',
            'automated' => true,
        ];

        return $steps;
    }

    /**
     * @return list<array{step: int, title: string, description: string, automated: bool}>
     */
    public static function emailMigrationSteps(bool $hasExistingMail): array
    {
        if (!$hasExistingMail) {
            return [[
                'step' => 1,
                'title' => 'Neue E-Mail-Adressen einrichten',
                'description' => 'Wir erstellen die gewünschten E-Mail-Adressen automatisch über die KAS-API.',
                'automated' => true,
            ]];
        }

        return [
            [
                'step' => 1,
                'title' => 'Neue Postfächer bei All-Inkl anlegen',
                'description' => 'Wir erstellen die Postfächer automatisch über die KAS-API, damit während des Umzugs keine E-Mails verloren gehen.',
                'automated' => true,
            ],
            [
                'step' => 2,
                'title' => 'E-Mails per IMAP übertragen',
                'description' => 'Bestehende E-Mails werden automatisch per IMAP vom alten Server auf den neuen kopiert. '
                    . 'Dafür benötigen wir die IMAP-Zugangsdaten des alten Providers (Server, Benutzername, Passwort).',
                'automated' => true,
            ],
            [
                'step' => 3,
                'title' => 'E-Mail-Programme umstellen',
                'description' => 'Nach dem Umzug müssen die IMAP/SMTP-Einstellungen in Outlook, Thunderbird etc. '
                    . 'auf die All-Inkl-Server geändert werden: IMAP: [login].kasserver.com:993 (SSL), '
                    . 'SMTP: [login].kasserver.com:465 (SSL).',
                'automated' => false,
            ],
        ];
    }

    /**
     * Migrate emails from old IMAP server to new IMAP server.
     *
     * @return array{success: bool, copied: int, errors: list<string>}
     */
    public static function migrateImapMailbox(
        string $oldHost, int $oldPort, string $oldUser, string $oldPass,
        string $newHost, int $newPort, string $newUser, string $newPass,
        bool $oldSsl = true, bool $newSsl = true
    ): array {
        $errors = [];
        $copied = 0;

        $oldFlags = $oldSsl ? '/imap/ssl/novalidate-cert' : '/imap/notls';
        $newFlags = $newSsl ? '/imap/ssl/novalidate-cert' : '/imap/notls';

        $oldConn = @imap_open('{' . $oldHost . ':' . $oldPort . $oldFlags . '}', $oldUser, $oldPass);
        if ($oldConn === false) {
            return ['success' => false, 'copied' => 0, 'errors' => ['Verbindung zum alten Server fehlgeschlagen: ' . imap_last_error()]];
        }

        $newConn = @imap_open('{' . $newHost . ':' . $newPort . $newFlags . '}', $newUser, $newPass);
        if ($newConn === false) {
            imap_close($oldConn);
            return ['success' => false, 'copied' => 0, 'errors' => ['Verbindung zum neuen Server fehlgeschlagen: ' . imap_last_error()]];
        }

        $folders = imap_list($oldConn, '{' . $oldHost . ':' . $oldPort . $oldFlags . '}', '*') ?: [];

        foreach ($folders as $folder) {
            $folderName = str_replace('{' . $oldHost . ':' . $oldPort . $oldFlags . '}', '', $folder);

            @imap_createmailbox($newConn, '{' . $newHost . ':' . $newPort . $newFlags . '}' . $folderName);

            $oldMbox = @imap_open($folder, $oldUser, $oldPass);
            if ($oldMbox === false) {
                $errors[] = "Ordner '$folderName' konnte nicht geöffnet werden.";
                continue;
            }

            $msgCount = imap_num_msg($oldMbox);
            for ($i = 1; $i <= $msgCount; $i++) {
                $header = imap_fetchheader($oldMbox, $i);
                $body = imap_body($oldMbox, $i);
                $flags = '';

                $overview = imap_fetch_overview($oldMbox, (string) $i, 0);
                if (!empty($overview[0])) {
                    $ov = $overview[0];
                    if (!empty($ov->seen)) $flags .= '\\Seen ';
                    if (!empty($ov->flagged)) $flags .= '\\Flagged ';
                    if (!empty($ov->answered)) $flags .= '\\Answered ';
                    if (!empty($ov->draft)) $flags .= '\\Draft ';
                }

                $result = @imap_append(
                    $newConn,
                    '{' . $newHost . ':' . $newPort . $newFlags . '}' . $folderName,
                    $header . "\r\n" . $body,
                    trim($flags)
                );

                if ($result) {
                    $copied++;
                } else {
                    $errors[] = "Nachricht $i in '$folderName' konnte nicht kopiert werden.";
                }
            }

            imap_close($oldMbox);
        }

        imap_close($oldConn);
        imap_close($newConn);

        return ['success' => empty($errors), 'copied' => $copied, 'errors' => $errors];
    }

    /**
     * Create a mailbox via KAS-API.
     *
     * @param string $localPart Teil vor @ (z. B. info).
     * @param string $domain Domain-Teil der Adresse.
     */
    public static function createMailbox(string $kasLogin, string $kasPass, string $localPart, string $domain, string $password): bool
    {
        $params = json_encode([
            'kas_login'        => $kasLogin,
            'kas_auth_type'    => 'plain',
            'kas_auth_data'    => $kasPass,
            'kas_action'       => 'add_mailaccount',
            'KasRequestParams' => [
                'mail_password'    => $password,
                'mail_local_part'  => $localPart,
                'mail_domain_part' => $domain,
            ],
        ]);

        $ctx = stream_context_create([
            'http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $params, 'timeout' => 30],
        ]);

        $response = @file_get_contents('https://kasapi.kasserver.com/json-api', false, $ctx);
        if ($response === false) return false;

        $data = json_decode($response, true);
        return is_array($data) && !empty($data['Response']['ReturnInfo']);
    }

    // ── Private helpers ─────────────────────────────────────────────

    /** Normalisiert Domain-Strings (lowercase, ohne Schema/Slash). */
    private static function normalizeDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }

    /** Provider-spezifische Hinweise zum Auth-Code für Domain-Transfer. */
    private static function authCodeInstructions(string $provider): string
    {
        $generic = 'Fordern Sie den Auth-Code (auch EPP-Code oder Autorisierungscode genannt) bei Ihrem aktuellen Provider an. '
            . 'Dies geht meist im Kundenportal unter Domain-Verwaltung oder per E-Mail an den Support.';

        return match ($provider) {
            'Strato'        => 'Bei Strato: Loggen Sie sich in Ihr Strato-Kundenportal ein → Domains → Domain verwalten → Auth-Code anfordern. Der Code wird per E-Mail zugestellt.',
            'IONOS (1&1)'   => 'Bei IONOS: Loggen Sie sich ein → Domains & SSL → Domain auswählen → Transfersperre aufheben → Auth-Code anfordern.',
            'Hetzner'       => 'Bei Hetzner: Robot-Verwaltung → Domains → Auth-Code anfordern.',
            'Host Europe'   => 'Bei Host Europe: KIS → Domainservices → Auth-Code anfordern.',
            'DomainFactory' => 'Bei DomainFactory: Kundenmenü → Domains → Transfercode/Auth-Code anfordern.',
            'GoDaddy'       => 'Bei GoDaddy: My Products → Domains → Domain Settings → Transfer domain away → Get authorization code.',
            'Mittwald'      => 'Bei Mittwald: mStudio → Domains → Auth-Code anfordern.',
            'Netcup'        => 'Bei Netcup: CCP → Domains → Auth-Code anfordern.',
            'All-Inkl'      => 'Die Domain ist bereits bei All-Inkl. Ein Transfer ist nicht notwendig – wir richten nur den Webspace ein.',
            default         => $generic,
        };
    }
}
