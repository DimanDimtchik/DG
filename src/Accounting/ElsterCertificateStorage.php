<?php
declare(strict_types=1);

/**
 * Ablage ELSTER-Softwarezertifikat (.pfx) + Passwort (verschlüsselt) + Ablaufdatum.
 * Vorbereitung für ERiC — kein Auto-Login bei Mein ELSTER.
 */
final class ElsterCertificateStorage
{
    public const LOGIN_URL = 'https://www.elster.de/eportal/login';

    /** Erinnerungstage vor Ablauf (an ELSTER-Hinweiszeiten angelehnt). */
    public const REMINDER_DAYS = [90, 40, 30, 2];

    public static function storageDir(): string
    {
        return DG_ROOT . '/storage/elster';
    }

    public static function certificatePath(): string
    {
        return self::storageDir() . '/software-certificate.pfx';
    }

    /**
     * @return array<string, mixed>
     */
    public static function metaDefaults(): array
    {
        return [
            'certificate_uploaded' => false,
            'certificate_label' => '',
            'certificate_expires_at' => '',
            'certificate_password_enc' => '',
            'certificate_password_set' => false,
            'certificate_subject' => '',
            'reminder_enabled' => true,
            'reminder_sent' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function meta(): array
    {
        $stored = Database::isConfigured()
            ? SettingsStore::get(ElsterSettings::STORE_KEY, ElsterSettings::defaults())
            : ElsterSettings::defaults();
        $meta = array_merge(self::metaDefaults(), array_intersect_key($stored, self::metaDefaults()));
        $meta['certificate_uploaded'] = !empty($meta['certificate_uploaded']) && is_file(self::certificatePath());
        $meta['certificate_password_set'] = trim((string) ($meta['certificate_password_enc'] ?? '')) !== '';
        $meta['has_file'] = is_file(self::certificatePath());
        $meta['login_url'] = self::LOGIN_URL;
        $meta['days_until_expiry'] = self::daysUntilExpiry((string) ($meta['certificate_expires_at'] ?? ''));
        $meta['expiry_status'] = self::expiryStatus((string) ($meta['certificate_expires_at'] ?? ''));

        return $meta;
    }

    public static function daysUntilExpiry(string $ymd): ?int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return null;
        }
        $exp = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        $today = new DateTimeImmutable('today');
        if ($exp === false) {
            return null;
        }

        return (int) $today->diff($exp)->format('%r%a');
    }

    public static function expiryStatus(string $ymd): string
    {
        $days = self::daysUntilExpiry($ymd);
        if ($days === null) {
            return 'unknown';
        }
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 2) {
            return 'critical';
        }
        if ($days <= 40) {
            return 'warning';
        }
        if ($days <= 90) {
            return 'soon';
        }

        return 'ok';
    }

    /**
     * Speichert Upload + Passwort; liest Ablauf aus dem Zertifikat wenn möglich.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file $_FILES['elster_certificate'] o. ä.
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(array $input, ?array $file = null): void
    {
        if (!Database::isConfigured()) {
            throw new InvalidArgumentException('Datenbank nicht verbunden.');
        }

        $current = SettingsStore::get(ElsterSettings::STORE_KEY, ElsterSettings::defaults());
        $password = (string) ($input['elster_certificate_password'] ?? '');
        $passwordConfirm = (string) ($input['elster_certificate_password_confirm'] ?? '');
        $manualExpiry = trim((string) ($input['elster_certificate_expires_at'] ?? ''));
        $reminderEnabled = !empty($input['elster_certificate_reminder_enabled']);
        $clearPassword = !empty($input['elster_certificate_clear_password']);
        $removeFile = !empty($input['elster_certificate_remove']);

        if ($removeFile) {
            self::deleteFile();
            $current['certificate_uploaded'] = false;
            $current['certificate_label'] = '';
            $current['certificate_subject'] = '';
            $current['certificate_expires_at'] = '';
            $current['certificate_password_enc'] = '';
            $current['reminder_sent'] = [];
        }

        $hasUpload = is_array($file)
            && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Zertifikat-Upload fehlgeschlagen.');
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            $name = (string) ($file['name'] ?? 'certificate.pfx');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new InvalidArgumentException('Ungültiger Zertifikat-Upload.');
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pfx', 'p12'], true)) {
                throw new InvalidArgumentException('Bitte eine ELSTER-Zertifikatsdatei (.pfx / .p12) wählen.');
            }
            if ($password === '') {
                throw new InvalidArgumentException('Zum Speichern des Zertifikats ist das Passwort erforderlich (Prüfung der Datei).');
            }
            ElsterCertificatePassword::assertValid($password);
            if ($passwordConfirm !== '' && $password !== $passwordConfirm) {
                throw new InvalidArgumentException('Passwort und Wiederholung stimmen nicht überein.');
            }

            $bin = file_get_contents($tmp);
            if ($bin === false || $bin === '') {
                throw new InvalidArgumentException('Zertifikatdatei konnte nicht gelesen werden.');
            }

            $parsed = self::parsePkcs12($bin, $password);
            self::ensureDir();
            if (file_put_contents(self::certificatePath(), $bin) === false) {
                throw new InvalidArgumentException('Zertifikat konnte nicht gespeichert werden.');
            }
            @chmod(self::certificatePath(), 0600);

            $current['certificate_uploaded'] = true;
            $current['certificate_label'] = $name;
            $current['certificate_subject'] = (string) ($parsed['subject'] ?? '');
            $current['certificate_password_enc'] = self::encryptPassword($password);
            $expiry = (string) ($parsed['expires_at'] ?? '');
            if ($manualExpiry !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $manualExpiry)) {
                $expiry = $manualExpiry;
            }
            if ($expiry !== '') {
                $current['certificate_expires_at'] = $expiry;
            }
            $current['reminder_sent'] = [];
        } elseif ($password !== '') {
            ElsterCertificatePassword::assertValid($password);
            if ($passwordConfirm !== '' && $password !== $passwordConfirm) {
                throw new InvalidArgumentException('Passwort und Wiederholung stimmen nicht überein.');
            }
            if (is_file(self::certificatePath())) {
                $bin = file_get_contents(self::certificatePath());
                if ($bin !== false && $bin !== '') {
                    $parsed = self::parsePkcs12($bin, $password);
                    if (($current['certificate_expires_at'] ?? '') === '' && !empty($parsed['expires_at'])) {
                        $current['certificate_expires_at'] = $parsed['expires_at'];
                    }
                    if (($current['certificate_subject'] ?? '') === '' && !empty($parsed['subject'])) {
                        $current['certificate_subject'] = $parsed['subject'];
                    }
                    $current['certificate_uploaded'] = true;
                }
            }
            $current['certificate_password_enc'] = self::encryptPassword($password);
        } elseif ($clearPassword) {
            $current['certificate_password_enc'] = '';
        }

        if (!$hasUpload && !$removeFile && $manualExpiry !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $manualExpiry)) {
                throw new InvalidArgumentException('Ablaufdatum bitte als JJJJ-MM-TT angeben.');
            }
            $oldExpiry = (string) ($current['certificate_expires_at'] ?? '');
            $current['certificate_expires_at'] = $manualExpiry;
            if ($oldExpiry !== $manualExpiry) {
                $current['reminder_sent'] = [];
            }
        }

        $current['reminder_enabled'] = $reminderEnabled;
        $current['certificate_password_set'] = trim((string) ($current['certificate_password_enc'] ?? '')) !== '';

        SettingsStore::set(ElsterSettings::STORE_KEY, array_merge(
            ElsterSettings::defaults(),
            $current,
            [
                'mode' => ElsterSettings::sanitizeMode((string) ($current['mode'] ?? ElsterSettings::MODE_CSV)),
                'eric_test_mode' => !empty($current['eric_test_mode']),
                'eric_worker_url' => trim((string) ($current['eric_worker_url'] ?? '')),
                'manufacturer_id' => trim((string) ($current['manufacturer_id'] ?? '')),
            ]
        ));
    }

    /**
     * @return array{expires_at?: string, subject?: string}
     * @throws InvalidArgumentException
     */
    public static function parsePkcs12(string $binary, string $password): array
    {
        if (!function_exists('openssl_pkcs12_read')) {
            throw new InvalidArgumentException('OpenSSL PKCS#12 ist auf diesem Server nicht verfügbar.');
        }
        $certs = [];
        if (!@openssl_pkcs12_read($binary, $certs, $password)) {
            throw new InvalidArgumentException(
                'Zertifikat konnte mit diesem Passwort nicht geöffnet werden. Bitte Passwort und Datei prüfen.'
            );
        }
        $certPem = (string) ($certs['cert'] ?? '');
        if ($certPem === '') {
            return [];
        }
        $parsed = @openssl_x509_parse($certPem);
        if (!is_array($parsed)) {
            return [];
        }
        $out = [];
        $validTo = $parsed['validTo_time_t'] ?? null;
        if (is_int($validTo) && $validTo > 0) {
            $out['expires_at'] = gmdate('Y-m-d', $validTo);
        }
        $cn = '';
        if (isset($parsed['subject']['CN'])) {
            $cn = (string) $parsed['subject']['CN'];
        } elseif (is_array($parsed['subject'] ?? null)) {
            $cn = implode(', ', array_map('strval', $parsed['subject']));
        }
        if ($cn !== '') {
            $out['subject'] = $cn;
        }

        return $out;
    }

    public static function decryptPassword(): ?string
    {
        $meta = self::meta();
        $enc = trim((string) ($meta['certificate_password_enc'] ?? ''));
        if ($enc === '') {
            return null;
        }
        $plain = self::decrypt($enc);

        return $plain !== '' ? $plain : null;
    }

    private static function deleteFile(): void
    {
        $path = self::certificatePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function ensureDir(): void
    {
        $dir = self::storageDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Ablageverzeichnis für ELSTER-Zertifikat fehlt.');
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }

    private static function encryptPassword(string $password): string
    {
        $key = self::cryptoKey();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($password, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new InvalidArgumentException('Passwort konnte nicht verschlüsselt werden.');
        }

        return 'v1:' . base64_encode($iv . $cipher);
    }

    private static function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, 'v1:')) {
            return '';
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::cryptoKey(), OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? $plain : '';
    }

    private static function cryptoKey(): string
    {
        $seed = DG_ROOT;
        $local = ElsterSettings::localConfig();
        if (!empty($local['certificate_secret']) && is_string($local['certificate_secret'])) {
            $seed .= '|' . $local['certificate_secret'];
        }

        return hash('sha256', $seed . '|elster-certificate-password', true);
    }
}
