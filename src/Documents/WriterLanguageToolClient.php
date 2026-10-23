<?php

declare(strict_types=1);

/**
 * Optionaler LanguageTool-Client (D4) — nur wenn in WriterAssistSettings aktiv.
 * Kein Default auf öffentliche Cloud.
 */
final class WriterLanguageToolClient
{
    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   matches: list<array{message: string, short: string, offset: int, length: int, replacements: list<string>}>
     * }
     */
    public static function check(string $text, ?array $cfg = null): array
    {
        $cfg = $cfg ?? WriterAssistSettings::config();
        if (!WriterAssistSettings::languageToolReady($cfg)) {
            return [
                'ok' => false,
                'message' => 'LanguageTool ist nicht freigeschaltet oder die URL fehlt (Einstellungen → Briefpapier).',
                'matches' => [],
            ];
        }

        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return ['ok' => true, 'message' => 'Kein Text.', 'matches' => []];
        }
        if (mb_strlen($text) > 20000) {
            $text = mb_substr($text, 0, 20000);
        }

        $base = rtrim((string) $cfg['languagetool_url'], '/');
        // Erlaubt …/v2/check oder Basis-URL
        $endpoint = str_ends_with(strtolower($base), '/v2/check')
            ? $base
            : $base . (str_ends_with($base, '/v2') ? '/check' : '/v2/check');

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'message' => 'cURL ist auf dem Server nicht verfügbar.',
                'matches' => [],
            ];
        }

        $post = http_build_query([
            'text' => $text,
            'language' => (string) ($cfg['languagetool_language'] ?? 'de-DE'),
            'enabledOnly' => 'false',
        ]);

        $ch = curl_init($endpoint);
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ];
        $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if (is_string($ca) && $ca !== '') {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($raw)) {
            return [
                'ok' => false,
                'message' => 'LanguageTool nicht erreichbar: ' . ($error !== '' ? $error : 'Netzwerkfehler'),
                'matches' => [],
            ];
        }
        if ($status < 200 || $status >= 300) {
            return [
                'ok' => false,
                'message' => 'LanguageTool antwortete mit HTTP ' . $status . '.',
                'matches' => [],
            ];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'message' => 'Ungültige LanguageTool-Antwort.',
                'matches' => [],
            ];
        }

        $matches = [];
        foreach (is_array($data['matches'] ?? null) ? $data['matches'] : [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $replacements = [];
            foreach (is_array($m['replacements'] ?? null) ? $m['replacements'] : [] as $r) {
                if (is_array($r) && isset($r['value'])) {
                    $replacements[] = (string) $r['value'];
                }
                if (count($replacements) >= 5) {
                    break;
                }
            }
            $matches[] = [
                'message' => trim((string) ($m['message'] ?? '')),
                'short' => trim((string) ($m['shortMessage'] ?? '')),
                'offset' => (int) ($m['offset'] ?? 0),
                'length' => (int) ($m['length'] ?? 0),
                'replacements' => $replacements,
            ];
            if (count($matches) >= 40) {
                break;
            }
        }

        return [
            'ok' => true,
            'message' => $matches === []
                ? 'LanguageTool: keine Treffer.'
                : sprintf('LanguageTool: %d Hinweis(e).', count($matches)),
            'matches' => $matches,
        ];
    }
}
