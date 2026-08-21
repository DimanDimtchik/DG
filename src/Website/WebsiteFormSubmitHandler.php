<?php
declare(strict_types=1);

/**
 * Handles public website form POSTs: validate, store, e-mail, redirect.
 */
final class WebsiteFormSubmitHandler
{
    /**
     * Process POST /formular-senden. Never returns on success (redirects).
     */
    public static function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) {
            self::redirect('/', false, 'Ungültige Anfrage.');
        }

        $formId = (int) ($_POST['form_id'] ?? 0);
        $form = WebsiteFormRepository::findPublished($formId);
        if ($form === null) {
            self::redirect('/', false, 'Formular nicht gefunden.');
        }

        $definition = is_array($form['definition'] ?? null) ? $form['definition'] : [];
        $settings = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];
        $fields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];

        if (!empty($settings['honeypot']) && trim((string) ($_POST['website_url'] ?? '')) !== '') {
            // Bot: fake success
            self::redirect(self::referer(), true);
        }

        if (!empty($settings['captcha'])) {
            $token = (string) ($_POST['captcha_token'] ?? '');
            $answer = (string) ($_POST['captcha_answer'] ?? '');
            if (!WebsiteFormCaptcha::verify($token, $answer)) {
                self::redirect(self::referer(), false, 'Sicherheitsfrage falsch oder abgelaufen. Bitte erneut versuchen.', $formId);
            }
        }

        $payload = [];
        $errors = [];
        $replyTo = null;

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = (string) ($field['type'] ?? '');
            if (in_array($type, ['submit', 'heading', 'paragraph'], true)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $label = (string) ($field['label'] ?? $name);
            $required = !empty($field['required']);

            if ($type === 'file') {
                $file = $_FILES[$name] ?? null;
                $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($required && !$hasFile) {
                    $errors[] = $label . ' ist erforderlich.';
                }
                continue;
            }

            if ($type === 'checkbox') {
                $raw = $_POST[$name] ?? [];
                $values = is_array($raw) ? array_values(array_map('strval', $raw)) : [];
                if ($required && $values === []) {
                    $errors[] = $label . ' ist erforderlich.';
                }
                $payload[$name] = ['label' => $label, 'type' => $type, 'value' => $values];
                continue;
            }

            if ($type === 'consent') {
                $ok = !empty($_POST[$name]);
                if ($required && !$ok) {
                    $errors[] = $label . ' muss bestätigt werden.';
                }
                $payload[$name] = ['label' => $label, 'type' => $type, 'value' => $ok ? 'Ja' : 'Nein'];
                continue;
            }

            $value = trim((string) ($_POST[$name] ?? ''));
            if ($required && $value === '') {
                $errors[] = $label . ' ist erforderlich.';
            }
            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $label . ' ist keine gültige E-Mail-Adresse.';
            }
            if ($type === 'email' && $value !== '' && $replyTo === null) {
                $replyTo = $value;
            }
            $payload[$name] = ['label' => $label, 'type' => $type, 'value' => $value];
        }

        if ($errors !== []) {
            self::redirect(self::referer(), false, $errors[0], $formId);
        }

        $submissionId = 0;
        $storedFiles = [];
        $store = !empty($settings['store_submissions']);
        $sendMail = !empty($settings['send_email']);

        try {
            if ($store || self::hasFileFields($fields)) {
                $submissionId = WebsiteFormSubmissionRepository::create(
                    $formId,
                    $payload,
                    [],
                    Firewall::clientIp(),
                    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
                );
            }

            foreach ($fields as $field) {
                if (!is_array($field) || ($field['type'] ?? '') !== 'file') {
                    continue;
                }
                $name = (string) ($field['name'] ?? '');
                $file = $_FILES[$name] ?? null;
                if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($submissionId <= 0) {
                    $submissionId = WebsiteFormSubmissionRepository::create(
                        $formId,
                        $payload,
                        [],
                        Firewall::clientIp(),
                        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
                    );
                }
                $meta = WebsiteFormFileStorage::storeUpload(
                    $formId,
                    $submissionId,
                    $name,
                    $file,
                    (int) ($field['max_mb'] ?? 5)
                );
                $storedFiles[] = $meta;
                $payload[$name] = [
                    'label' => (string) ($field['label'] ?? $name),
                    'type' => 'file',
                    'value' => (string) ($meta['original_name'] ?? ''),
                ];
            }

            if ($submissionId > 0) {
                // Update payload/files after uploads
                $pdo = Database::pdo();
                $stmt = $pdo->prepare(
                    'UPDATE dg_website_form_submissions SET payload_json = :p, files_json = :f WHERE id = :id'
                );
                $stmt->execute([
                    'p' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'f' => json_encode($storedFiles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'id' => $submissionId,
                ]);
            }

            if ($sendMail) {
                self::sendNotification($form, $settings, $payload, $storedFiles, $replyTo, $submissionId);
            }
        } catch (Throwable $e) {
            if ($submissionId > 0) {
                WebsiteFormSubmissionRepository::delete($submissionId);
            }
            self::redirect(self::referer(), false, $e->getMessage() !== '' ? $e->getMessage() : 'Senden fehlgeschlagen.', $formId);
        }

        self::redirect(self::referer(), true, null, $formId);
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private static function hasFileFields(array $fields): bool
    {
        foreach ($fields as $field) {
            if (is_array($field) && ($field['type'] ?? '') === 'file') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $files
     */
    private static function sendNotification(
        array $form,
        array $settings,
        array $payload,
        array $files,
        ?string $replyTo,
        int $submissionId
    ): void {
        $to = filter_var(trim((string) ($settings['recipient_email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$to) {
            try {
                $company = CompanySettings::forForm();
                $to = filter_var(trim((string) ($company['email'] ?? '')), FILTER_VALIDATE_EMAIL);
            } catch (Throwable) {
                $to = false;
            }
        }
        if (!$to) {
            throw new RuntimeException('Kein Empfänger für das Formular konfiguriert.');
        }

        $subjectBase = trim((string) ($settings['mail_subject'] ?? 'Formularanfrage')) ?: 'Formularanfrage';
        $title = (string) ($form['title'] ?? 'Formular');
        $subject = $subjectBase . ' – ' . $title;

        $rows = '';
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $label = htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = $entry['value'] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $valueHtml = nl2br(htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $rows .= '<tr><th style="text-align:left;padding:6px 10px;border-bottom:1px solid #eee;">' . $label
                . '</th><td style="padding:6px 10px;border-bottom:1px solid #eee;">' . $valueHtml . '</td></tr>';
        }
        if ($files !== []) {
            $list = [];
            foreach ($files as $f) {
                $list[] = htmlspecialchars((string) ($f['original_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $rows .= '<tr><th style="text-align:left;padding:6px 10px;">Dateien</th><td style="padding:6px 10px;">'
                . implode(', ', $list) . ' (im CRM unter Formulare → Eingänge)</td></tr>';
        }

        $html = '<p>Neue Einsendung für <strong>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</strong>' . ($submissionId > 0 ? ' (#' . $submissionId . ')' : '') . '.</p>'
            . '<table style="border-collapse:collapse;width:100%;max-width:640px;">' . $rows . '</table>';

        if (class_exists('MailService') && MailSettings::isConfigured()) {
            MailService::send(new MailMessage(
                subject: $subject,
                htmlBody: $html,
                to: [$to],
                replyTo: $replyTo
            ));
        } else {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . 'From: noreply@' . $host . "\r\n";
            if ($replyTo) {
                $headers .= 'Reply-To: ' . $replyTo . "\r\n";
            }
            if (!@mail($to, $subject, $html, $headers)) {
                throw new RuntimeException('E-Mail konnte nicht gesendet werden.');
            }
        }
    }

    private static function referer(): string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
        if ($ref === '' || !str_starts_with($ref, '/') && !str_starts_with($ref, 'http')) {
            return '/';
        }

        return $ref;
    }

    private static function redirect(string $url, bool $ok, ?string $error = null, int $formId = 0): never
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? ((string) ($parts['path'] ?? '/')) : '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }
        unset($query['form_ok'], $query['form_err'], $query['form_id']);
        if ($ok) {
            $query['form_ok'] = '1';
            if ($formId > 0) {
                $query['form_id'] = (string) $formId;
            }
        } elseif ($error !== null) {
            $query['form_err'] = $error;
            if ($formId > 0) {
                $query['form_id'] = (string) $formId;
            }
        }
        $qs = http_build_query($query);
        $target = $path . ($qs !== '' ? '?' . $qs : '');
        if (!empty($parts['fragment'])) {
            $target .= '#' . $parts['fragment'];
        }
        header('Location: ' . $target, true, 302);
        exit;
    }
}
