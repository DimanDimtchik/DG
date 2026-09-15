<?php
declare(strict_types=1);

/**
 * Kichel Phase 2 — Ollama nur für Formulierung, nie für Beträge.
 */
final class KichelOllamaClient
{
    /**
     * Formuliert eine Antwort aus vorgegebenen Fakten. Zahlen dürfen nicht geändert werden.
     *
     * @param array<string, mixed> $facts
     */
    public static function phrase(string $userQuery, string $baseAnswer, array $facts): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $cfg = self::config();
        $url = rtrim((string) ($cfg['ollama_url'] ?? 'http://127.0.0.1:11434'), '/') . '/api/generate';
        $model = (string) ($cfg['ollama_model'] ?? 'llama3.1:8b');

        $factsJson = json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $prompt = <<<PROMPT
Du bist Kichel, der CRM-Helfer. Formuliere eine kurze, freundliche Antwort auf Deutsch.

STRENG VERBOTEN:
- Beträge, Prozente oder Summen selbst berechnen oder ändern
- Fakten aus dem JSON ignorieren oder widersprechen

Nutzerfrage: {$userQuery}

Fakten (verbindlich, unverändert übernehmen):
{$factsJson}

Basisantwort aus dem CRM (Zahlen daraus sind maßgeblich):
{$baseAnswer}

Antworte in 2–4 Sätzen. Alle Geldbeträge exakt wie in den Fakten.
PROMPT;

        $payload = json_encode([
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
        ], JSON_THROW_ON_ERROR);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => (int) ($cfg['timeout_seconds'] ?? 60),
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $text = trim((string) ($data['response'] ?? ''));
        if ($text === '') {
            return null;
        }

        if (!self::numbersConsistent($baseAnswer, $facts, $text)) {
            return null;
        }

        return $text;
    }

    public static function isEnabled(): bool
    {
        $cfg = self::config();

        return (bool) ($cfg['ollama_enabled'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $file = DG_ROOT . '/config/kichel.local.php';
        if (!is_readable($file)) {
            return [];
        }

        $cfg = require $file;

        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @param array<string, mixed> $facts
     */
    private static function numbersConsistent(string $baseAnswer, array $facts, string $ollamaText): bool
    {
        $haystack = $baseAnswer . ' ' . $ollamaText;
        foreach ($facts as $key => $value) {
            if (!is_int($value) && !is_float($value)) {
                continue;
            }
            if ($key === 'payment_days' && (int) $value === 0) {
                continue;
            }
            $formatted = number_format((float) $value, 2, ',', '.');
            if (!str_contains($haystack, $formatted) && !str_contains($ollamaText, (string) $value)) {
                // Ollama muss mindestens einen Schlüsselbetrag in korrekter Schreibweise enthalten
                if (str_contains((string) $key, 'amount') || str_contains((string) $key, 'percent')) {
                    return false;
                }
            }
        }

        return true;
    }
}
