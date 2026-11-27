<?php
declare(strict_types=1);

/**
 * Kichel Phase 2 / KR2 — Ollama nur für Formulierung, nie für Beträge oder neue Normen.
 */
final class KichelOllamaClient
{
    /**
     * Formuliert eine Antwort aus vorgegebenen RAG-Fakten (oder Legacy-Assoc bei Money).
     * Bei ausgeschaltetem Ollama / Fehler: null — Caller nutzt Fallback (compose / basis).
     *
     * @param list<array<string, mixed>>|array<string, mixed> $facts
     */
    public static function phrase(string $userQuery, string $baseAnswer, array $facts): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $cfg = self::config();
        $url = rtrim((string) ($cfg['ollama_url'] ?? 'http://127.0.0.1:11434'), '/') . '/api/generate';
        $model = (string) ($cfg['ollama_model'] ?? 'llama3.1:8b');

        $promptFacts = self::factsForPrompt($facts);
        $factsJson = json_encode($promptFacts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $prompt = <<<PROMPT
Du bist Kichel, der CRM-Helfer. Formuliere eine kurze, freundliche Antwort auf Deutsch.

VERBINDLICH — nur diese Fakten:
- Nutze ausschließlich die JSON-Fakten und die Basisantwort.
- Zahlen, Beträge, Prozente und Summen unverändert übernehmen (Schreibweise wie in den Fakten/Basisantwort).
- Keine neuen Gesetze, Paragraphen (§), Urteile oder Rechtsfolgen erfinden.
- Wenn die Fakten die Frage nicht decken: klar sagen, dass dazu keine gesicherten Angaben vorliegen — nicht raten.

STRENG VERBOTEN:
- Beträge, Prozente oder Summen selbst berechnen oder ändern
- Fakten widersprechen oder weglassen, die die Antwort tragen
- Wissen „aus dem Gedächtnis“ zu Steuern/Recht hinzufügen
- Buchungs- oder Kontierungsvorschläge („einfach auf Konto … buchen“, „als Aufwand verbuchen“), die nicht wörtlich in den Fakten stehen
- Aufforderungen, ohne Beleg oder außerhalb der CRM-Belegkette zu buchen (GoBD)

ERLAUBT:
- Auf CRM-Menüs verweisen (z. B. Buchhaltung → Belege) und beschreiben, was das CRM dort tut
- Zahlen und Pfade aus den Fakten unverändert wiedergeben

Nutzerfrage: {$userQuery}

Fakten (verbindlich):
{$factsJson}

Basisantwort aus dem CRM (Zahlen daraus sind maßgeblich):
{$baseAnswer}

Antworte in 2–4 Sätzen. Am Ende kurz die Quelle nennen, wenn in den Fakten source_label gesetzt ist.
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

        // KH3 — GoBD: keine erfundenen Buchungsratschläge aus dem Modell
        if (KichelGobdTone::isUnsafe($text)) {
            return null;
        }

        return $text;
    }

    /**
     * phrase wenn Ollama ok, sonst bausteinartiger Fallback aus Fakten (KR2c).
     *
     * @param list<array<string, mixed>> $facts RAG-Faktenliste
     */
    public static function compose(string $userQuery, array $facts, ?string $baseAnswer = null): string
    {
        $facts = KichelRagFact::normalizeList($facts);
        $fallback = KichelRagFact::fallbackAnswer($facts, $baseAnswer);
        $phrased = self::phrase($userQuery, $fallback, $facts);
        $answer = $phrased ?? $fallback;

        return KichelGobdTone::filterAnswer($answer);
    }

    public static function isEnabled(): bool
    {
        $cfg = self::config();

        return (bool) ($cfg['ollama_enabled'] ?? false);
    }

    public static function configuredModel(): string
    {
        $cfg = self::config();

        return (string) ($cfg['ollama_model'] ?? 'llama3.1:8b');
    }

    /**
     * BO3c — freie Prompt-Antwort (z. B. JSON-Extrakt). null wenn aus/Fehler.
     * Kein Zahlen-Abgleich wie bei phrase(); Caller validiert.
     */
    public static function generate(string $prompt): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $cfg = self::config();
        $url = rtrim((string) ($cfg['ollama_url'] ?? 'http://127.0.0.1:11434'), '/') . '/api/generate';
        $model = self::configuredModel();
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

        return $text !== '' ? $text : null;
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
     * @param list<array<string, mixed>>|array<string, mixed> $facts
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    private static function factsForPrompt(array $facts): array
    {
        if ($facts === []) {
            return [];
        }
        if (!array_is_list($facts)) {
            return $facts;
        }

        $out = [];
        foreach (KichelRagFact::normalizeList($facts) as $fact) {
            $row = [
                'type' => $fact['type'],
                'title' => $fact['title'],
                'body' => $fact['body'],
                'source_label' => $fact['source_label'],
                'source_ref' => $fact['source_ref'],
                'confidence' => $fact['confidence'],
            ];
            if ($fact['href'] !== null) {
                $row['href'] = $fact['href'];
            }
            if ($fact['numbers'] !== []) {
                $row['numbers'] = $fact['numbers'];
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>>|array<string, mixed> $facts
     */
    private static function numbersConsistent(string $baseAnswer, array $facts, string $ollamaText): bool
    {
        $numbers = KichelRagFact::collectNumbers($facts);
        if ($numbers === []) {
            return true;
        }

        $haystack = $baseAnswer . ' ' . $ollamaText;
        foreach ($numbers as $key => $value) {
            if ($key === 'payment_days' && (int) $value === 0) {
                continue;
            }
            $formatted = number_format((float) $value, 2, ',', '.');
            if (!str_contains($haystack, $formatted) && !str_contains($ollamaText, (string) $value)) {
                if (str_contains((string) $key, 'amount') || str_contains((string) $key, 'percent')) {
                    return false;
                }
            }
        }

        return true;
    }
}
