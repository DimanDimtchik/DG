<?php
declare(strict_types=1);

/**
 * ELSTER-Übermittlung via ERiC — Stub bis Server-Umzug abgeschlossen.
 *
 * @see docs/ELSTER-ERIC-TODO.md
 */
final class ElsterEricClient
{
    /**
     * @return array{
     *   ready: bool,
     *   mode: string,
     *   items: list<array{id: string, label: string, ok: bool, detail: string}>
     * }
     */
    public static function readiness(): array
    {
        $settings = ElsterSettings::forForm();
        $local = ElsterSettings::localConfig();
        $items = [];

        $items[] = [
            'id' => 'server',
            'label' => 'Root-Server (kein Shared Hosting)',
            'ok' => ElsterSettings::serverSupportsEric(),
            'detail' => ElsterSettings::serverSupportsEric()
                ? 'config/elster.local.php: server_ready oder force_enable_eric gesetzt.'
                : 'Aktuell Kasserver — ERiC erst nach Umzug auf Hetzner SX65-2 o. ä.',
        ];

        $items[] = [
            'id' => 'local_config',
            'label' => 'config/elster.local.php',
            'ok' => $local !== [],
            'detail' => $local !== []
                ? 'Lokale Server-Konfiguration vorhanden.'
                : 'Datei aus config/elster.local.php.example anlegen (nur auf Server).',
        ];

        $workerUrl = trim((string) ($settings['eric_worker_url'] ?? ''));
        if ($workerUrl === '' && trim((string) ($local['worker_url'] ?? '')) !== '') {
            $workerUrl = trim((string) $local['worker_url']);
        }
        $workerOk = $workerUrl !== '' && self::pingWorker($workerUrl);
        $items[] = [
            'id' => 'worker',
            'label' => 'ERiC-Worker erreichbar',
            'ok' => $workerOk,
            'detail' => $workerUrl === ''
                ? 'Worker-URL in Einstellungen → ELSTER eintragen (nach Worker-Installation).'
                : ($workerOk ? 'Worker antwortet unter ' . $workerUrl : 'Worker nicht erreichbar: ' . $workerUrl),
        ];

        $mfg = trim((string) ($settings['manufacturer_id'] ?? ''));
        if ($mfg === '' && trim((string) ($local['manufacturer_id'] ?? '')) !== '') {
            $mfg = trim((string) $local['manufacturer_id']);
        }
        $items[] = [
            'id' => 'manufacturer',
            'label' => 'ELSTER-Hersteller-ID',
            'ok' => $mfg !== '',
            'detail' => $mfg !== ''
                ? 'Hersteller-ID hinterlegt.'
                : 'Bei ELSTER als Softwarehersteller registrieren (docs/ELSTER-ERIC-TODO.md).',
        ];

        $items[] = [
            'id' => 'certificate',
            'label' => 'ELSTER-Zertifikat',
            'ok' => !empty($settings['certificate_uploaded']),
            'detail' => !empty($settings['certificate_uploaded'])
                ? 'Zertifikat markiert als hochgeladen (Upload-UI folgt mit ERiC).'
                : 'Test-Zertifikat für Entwicklung, echtes Zertifikat nur für Produktion.',
        ];

        $ready = true;
        foreach ($items as $item) {
            if (!$item['ok']) {
                $ready = false;
            }
        }

        return [
            'ready' => $ready,
            'mode' => (string) ($settings['mode'] ?? ElsterSettings::MODE_CSV),
            'items' => $items,
        ];
    }

    public static function isReady(): bool
    {
        return self::readiness()['ready'];
    }

    /**
     * @throws RuntimeException
     */
    public static function assertReady(): void
    {
        if (!ElsterSettings::isEricMode()) {
            throw new RuntimeException(
                'ELSTER-Modus ist „CSV“. Direkte Übermittlung unter Einstellungen → ELSTER aktivieren.'
            );
        }
        if (!self::isReady()) {
            throw new RuntimeException(
                'ERiC ist noch nicht betriebsbereit. Bitte bin/elster-readiness.php ausführen '
                . 'und docs/ELSTER-ERIC-TODO.md abarbeiten.'
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    public static function validateUstva(int $year, ?int $month = null): array
    {
        self::assertReady();
        throw new RuntimeException(
            'ERiC-Validierung wird nach Server-Umzug implementiert (Phase 4 in docs/ELSTER-ERIC-TODO.md).'
        );
    }

    /**
     * @throws RuntimeException
     */
    public static function submitUstva(int $year, ?int $month = null): array
    {
        self::assertReady();
        throw new RuntimeException(
            'ERiC-Übermittlung wird nach Server-Umzug implementiert (Phase 5 in docs/ELSTER-ERIC-TODO.md).'
        );
    }

    private static function pingWorker(string $url): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $healthUrl = rtrim($url, '/') . '/health';
        $ch = curl_init($healthUrl);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_NOBODY => true,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 400;
    }
}
