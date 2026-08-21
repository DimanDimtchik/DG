<?php
declare(strict_types=1);

/**
 * Niedrigstufiger SMTP-Client für den E-Mail-Versand.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $socket = null;

    /**
     * Konstruktor.
     * @param string $host
     * @param int $port
     * @param string $encryption
     * @param string $username
     * @param string $password
     * @param int $timeout
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 30,
    ) {
    }

    /**
     * Prüft SMTP-Verbindung und Anmeldung schrittweise.
     * @return array<string, mixed>
     */
    public function diagnose(): array
    {
        $steps = [];

        try {
            $remote = $this->encryption === 'ssl'
                ? 'ssl://' . $this->host . ':' . $this->port
                : 'tcp://' . $this->host . ':' . $this->port;

            $socket = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT
            );
            if ($socket === false) {
                $steps[] = [
                    'label' => 'Verbindung',
                    'ok' => false,
                    'detail' => "Verbindung zu {$this->host}:{$this->port} fehlgeschlagen: {$errstr} ({$errno})",
                ];

                return $this->report($steps, false);
            }

            stream_set_timeout($socket, $this->timeout);
            $this->socket = $socket;
            $banner = $this->expect(220);
            $steps[] = [
                'label' => 'Verbindung',
                'ok' => true,
                'detail' => trim($banner),
            ];

            $ehlo = $this->ehloWithResponse();
            $steps[] = [
                'label' => 'EHLO',
                'ok' => true,
                'detail' => trim(strtok($ehlo, "\n") ?: $ehlo),
            ];

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS');
                $tlsReply = $this->expect(220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $steps[] = [
                        'label' => 'TLS',
                        'ok' => false,
                        'detail' => 'TLS-Aufbau nach STARTTLS fehlgeschlagen.',
                    ];
                    $this->close();

                    return $this->report($steps, false);
                }
                $steps[] = [
                    'label' => 'TLS',
                    'ok' => true,
                    'detail' => trim($tlsReply),
                ];
                $ehloTls = $this->ehloWithResponse();
                $steps[] = [
                    'label' => 'EHLO (nach TLS)',
                    'ok' => true,
                    'detail' => trim(strtok($ehloTls, "\n") ?: $ehloTls),
                ];
            } elseif ($this->encryption === 'ssl') {
                $steps[] = [
                    'label' => 'SSL',
                    'ok' => true,
                    'detail' => 'Verschlüsselte Verbindung (Port ' . $this->port . ').',
                ];
            }

            if ($this->username !== '') {
                $this->command('AUTH LOGIN');
                $this->expect(334);
                $this->command(base64_encode($this->username));
                $this->expect(334);
                $this->command(base64_encode($this->password));
                $authReply = $this->expect(235);
                $steps[] = [
                    'label' => 'Anmeldung',
                    'ok' => true,
                    'detail' => 'Benutzer „' . $this->username . '“: ' . trim($authReply),
                ];
            } else {
                $steps[] = [
                    'label' => 'Anmeldung',
                    'ok' => true,
                    'detail' => 'Kein SMTP-Benutzer angegeben — nur Verbindung geprüft.',
                ];
            }

            $this->command('QUIT');
            $this->expect(221);
            $this->close();

            return $this->report($steps, true);
        } catch (Throwable $e) {
            $steps[] = [
                'label' => 'Fehler',
                'ok' => false,
                'detail' => $e->getMessage(),
            ];
            $this->close();

            return $this->report($steps, false);
        }
    }

    /**
     * Stellt die SMTP-Verbindung her und meldet sich an.
     * @return void
     * @throws RuntimeException
     */
    public function connectAndAuthenticate(): void
    {
        $this->connect();
        try {
            $this->expect(220);
            $this->ehlo();
            if ($this->encryption === 'tls') {
                $this->command('STARTTLS');
                $this->expect(220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS-Aufbau fehlgeschlagen.');
                }
                $this->ehlo();
            }
            if ($this->username !== '') {
                $this->authenticate();
            }
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    /**
     * Sendet eine MIME-Nachricht an die Empfänger.
     * @param string $from
     * @param list<string> $recipients
     * @param string $mimeData
     * @return void
     */
    public function sendMail(string $from, array $recipients, string $mimeData): void
    {
        $this->connectAndAuthenticate();
        try {
            $this->command('MAIL FROM:<' . $this->sanitizeAddress($from) . '>');
            $this->expect(250);
            foreach ($recipients as $recipient) {
                $this->command('RCPT TO:<' . $this->sanitizeAddress($recipient) . '>');
                $this->expect([250, 251]);
            }
            $this->command('DATA');
            $this->expect(354);
            $payload = preg_replace("/\r\n\./", "\r\n..", $mimeData) ?? $mimeData;
            $this->write($payload . "\r\n.\r\n");
            $this->expect(250);
            $this->command('QUIT');
            $this->expect(221);
        } finally {
            $this->close();
        }
    }

    /**
     * Methode connect.
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function connect(): void
    {
        if ($this->socket !== null) {
            return;
        }
        if ($this->host === '') {
            throw new InvalidArgumentException('SMTP-Host fehlt.');
        }

        $remote = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host . ':' . $this->port
            : 'tcp://' . $this->host . ':' . $this->port;

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            throw new RuntimeException("SMTP-Verbindung fehlgeschlagen: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    /**
     * Methode authenticate.
     * @return void
     */
    private function authenticate(): void
    {
        $this->command('AUTH LOGIN');
        $this->expect(334);
        $this->command(base64_encode($this->username));
        $this->expect(334);
        $this->command(base64_encode($this->password));
        $this->expect(235);
    }

    /**
     * Methode ehlo.
     * @return void
     */
    private function ehlo(): void
    {
        $this->ehloWithResponse();
    }

    /**
     * Methode ehlo with response.
     * @return string
     */
    private function ehloWithResponse(): string
    {
        $host = gethostname() ?: 'localhost';
        $this->command('EHLO ' . $host);

        return $this->expect(250);
    }

    /**
     * Methode report.
     * @param array $steps
     * @param bool $ok
     * @return array{ok: bool, summary: string, host: string, port: int, encryption: string, username: string, steps: list<array{label: string, ok: bool, detail: string}>}
     */
    private function report(array $steps, bool $ok): array
    {
        return [
            'ok' => $ok,
            'summary' => $ok
                ? 'SMTP-Verbindung und Anmeldung erfolgreich.'
                : 'SMTP-Test fehlgeschlagen — Details siehe unten.',
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'steps' => $steps,
        ];
    }

    /**
     * Methode command.
     * @param string $command
     * @return void
     */
    private function command(string $command): void
    {
        $this->write($command . "\r\n");
    }

    /**
     * Methode write.
     * @param string $data
     * @return void
     * @throws RuntimeException
     */
    private function write(string $data): void
    {
        if ($this->socket === null) {
            throw new RuntimeException('Keine SMTP-Verbindung.');
        }
        $written = fwrite($this->socket, $data);
        if ($written === false) {
            throw new RuntimeException('SMTP-Schreibfehler.');
        }
    }

    /**
     * Methode expect.
     * @param int|list<int> $expected
     * @return string
     * @throws RuntimeException
     */
    private function expect(int|array $expected): string
    {
        if ($this->socket === null) {
            throw new RuntimeException('Keine SMTP-Verbindung.');
        }
        $expectedCodes = is_array($expected) ? $expected : [$expected];
        $line = '';
        while (($buffer = fgets($this->socket, 515)) !== false) {
            $line .= $buffer;
            if (isset($buffer[3]) && $buffer[3] === ' ') {
                break;
            }
        }
        if ($line === '') {
            throw new RuntimeException('Leere SMTP-Antwort.');
        }
        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP-Fehler: ' . trim($line));
        }

        return $line;
    }

    /**
     * Führt aus: sanitize address.
     * @param string $email E-Mail-Adresse
     * @return string
     * @throws InvalidArgumentException
     */
    private function sanitizeAddress(string $email): string
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Ungültige E-Mail-Adresse: ' . $email);
        }

        return $email;
    }

    /**
     * Methode close.
     * @return void
     */
    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
