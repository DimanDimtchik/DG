<?php
declare(strict_types=1);

/**
 * Globale SMTP-/Absender-Einstellungen für den E-Mail-Versand.
 */
final class MailSettings
{
  public const STORE_KEY = 'mail';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
    public static function defaults(): array
  {
    return [
      'enabled' => false,
      'smtp_host' => '',
      'smtp_port' => 587,
      'smtp_encryption' => 'tls',
      'smtp_username' => '',
      'smtp_password' => '',
    ];
  }

    /**
     * Liefert die aktuelle Konfiguration.
     * @return array<string, mixed>
     */
    public static function config(): array
  {
    $cfg = SettingsStore::get(self::STORE_KEY, self::defaults());
    $cfg['smtp_port'] = max(1, min(65535, (int) ($cfg['smtp_port'] ?? 587)));
    $encryption = (string) ($cfg['smtp_encryption'] ?? 'tls');
    if (!in_array($encryption, ['', 'tls', 'ssl'], true)) {
      $cfg['smtp_encryption'] = 'tls';
    }

    return $cfg;
  }

    /**
     * Methode sender.
     * @return array<string, mixed>
     */
    public static function sender(): array
  {
    $name = CompanySettings::displayName();
    $email = CompanySettings::mailEmail();
    $replyTo = $email;

    return [
      'name' => $name,
      'email' => $email,
      'reply_to' => $replyTo,
    ];
  }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
  {
    $cfg = self::config();
    $sender = self::sender();

    return [
      'enabled' => !empty($cfg['enabled']),
      'smtp_host' => (string) ($cfg['smtp_host'] ?? ''),
      'smtp_port' => (int) ($cfg['smtp_port'] ?? 587),
      'smtp_encryption' => (string) ($cfg['smtp_encryption'] ?? 'tls'),
      'smtp_username' => trim((string) ($cfg['smtp_username'] ?? '')),
      'smtp_password' => (string) ($cfg['smtp_password'] ?? ''),
      'sender_name' => $sender['name'],
      'sender_email' => $sender['email'],
      'sender_reply_to' => $sender['reply_to'],
      'company_configured' => CompanySettings::isConfiguredForMail(),
    ];
  }

    /**
     * Prüft, ob die Konfiguration vollständig ist.
     * @return bool
     */
  public static function isConfigured(): bool
  {
    $cfg = self::config();

    return !empty($cfg['enabled'])
      && ($cfg['smtp_host'] ?? '') !== ''
      && trim((string) ($cfg['smtp_username'] ?? '')) !== ''
      && CompanySettings::isConfiguredForMail();
  }

    /**
     * Methode save.
     * @param array $input
     * @return void
     * @throws InvalidArgumentException
     */
    public static function save(array $input): void
  {
    if (!CompanySettings::isConfiguredForMail()) {
      throw new InvalidArgumentException(
                'Bitte zuerst Firmenname und E-Mail unter Einstellungen → Firmendaten eintragen.'
      );
    }

    $data = self::normalizeInput($input);

    if ($data['smtp_host'] === '') {
      throw new InvalidArgumentException('SMTP-Host ist erforderlich.');
    }
    if ($data['smtp_username'] === '') {
      throw new InvalidArgumentException('SMTP-Benutzer ist erforderlich.');
    }

    SettingsStore::set(self::STORE_KEY, $data);
  }

    /**
     * Führt aus: normalize input.
     * @param array $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input): array
  {
    $current = self::config();

    $username = trim((string) ($input['smtp_username'] ?? ''));
    if ($username === '') {
      $username = trim((string) ($current['smtp_username'] ?? ''));
    }

    $password = trim((string) ($input['smtp_password'] ?? ''));
    if ($password === '' && ($current['smtp_password'] ?? '') !== '') {
      $password = (string) $current['smtp_password'];
    }

    $encryption = trim((string) ($input['smtp_encryption'] ?? 'tls'));
    if (!in_array($encryption, ['', 'tls', 'ssl'], true)) {
      $encryption = 'tls';
    }

    return [
      'enabled' => !empty($input['enabled']),
      'smtp_host' => trim((string) ($input['smtp_host'] ?? '')),
      'smtp_port' => max(1, min(65535, (int) ($input['smtp_port'] ?? 587))),
      'smtp_encryption' => $encryption,
      'smtp_username' => $username,
      'smtp_password' => $password,
    ];
  }

    /**
     * Speichert summary.
     * @param array $input
     * @return array<string, mixed>
     */
    public static function saveSummary(array $input): array
  {
    $data = self::normalizeInput($input);
    $passwordEntered = trim((string) ($input['smtp_password'] ?? '')) !== '';

    return [
      'host' => (string) $data['smtp_host'],
      'username' => (string) $data['smtp_username'],
      'port' => (int) $data['smtp_port'],
      'password_saved' => $passwordEntered || ($data['smtp_password'] ?? '') !== '',
    ];
  }

    /**
     * Methode test connection report.
     * @param array $input Eingabedaten
     * @return array{ok: bool, summary: string, host: string, port: int, encryption: string, username: string, steps: list<array{label: string, ok: bool, detail: string}>}
     */
    public static function testConnectionReport(array $input): array
  {
    $data = self::normalizeInput($input);

    $client = new SmtpClient(
      (string) $data['smtp_host'],
      (int) $data['smtp_port'],
      (string) $data['smtp_encryption'],
      (string) $data['smtp_username'],
      (string) $data['smtp_password']
    );

    $report = $client->diagnose();
    $sender = self::sender();
    $report['sender_email'] = $sender['email'];
    $report['sender_name'] = $sender['name'];

    return $report;
  }

    /**
     * Methode test connection.
     * @param array $input
     * @return string
     * @throws RuntimeException
     */
    public static function testConnection(array $input): string
  {
    $report = self::testConnectionReport($input);
    if (!$report['ok']) {
      throw new RuntimeException($report['summary']);
    }

    return $report['summary'];
  }
}
