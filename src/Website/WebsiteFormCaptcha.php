<?php
declare(strict_types=1);

/**
 * Session-based math captcha for public website forms (no third-party keys).
 */
final class WebsiteFormCaptcha
{
    private const SESSION_KEY = 'dg_form_captcha';

    /**
     * Create a new challenge and store the expected answer in the session.
     *
     * @return array{token: string, prompt: string, a: int, b: int}
     */
    public static function issue(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $a = random_int(2, 9);
        $b = random_int(1, 8);
        $token = bin2hex(random_bytes(8));
        $_SESSION[self::SESSION_KEY] = [
            'token' => $token,
            'answer' => $a + $b,
            'expires' => time() + 1800,
        ];

        return [
            'token' => $token,
            'prompt' => $a . ' + ' . $b,
            'a' => $a,
            'b' => $b,
        ];
    }

    /**
     * Verify posted captcha token + answer; consumes the challenge on success.
     */
    public static function verify(string $token, string $answerRaw): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($stored)) {
            return false;
        }
        if ((string) ($stored['token'] ?? '') === '' || !hash_equals((string) $stored['token'], $token)) {
            return false;
        }
        if ((int) ($stored['expires'] ?? 0) < time()) {
            return false;
        }

        $answer = (int) preg_replace('/\D+/', '', $answerRaw);
        return $answer === (int) ($stored['answer'] ?? -1);
    }

    /**
     * Public form field HTML.
     */
    public static function renderFieldHtml(): string
    {
        $challenge = self::issue();
        return '<div class="ws-form__cell ws-form__cell--12 ws-form__captcha">'
            . '<label class="ws-form__label"><span>Sicherheitsfrage: Was ist '
            . View::escape($challenge['prompt']) . '? <span class="ws-form__req">*</span></span>'
            . '<input type="hidden" name="captcha_token" value="' . View::escape($challenge['token']) . '">'
            . '<input type="text" name="captcha_answer" inputmode="numeric" autocomplete="off" required '
            . 'placeholder="Ergebnis" aria-label="Sicherheitsfrage">'
            . '</label>'
            . '<small class="ws-form__help">Zum Schutz vor Spam.</small>'
            . '</div>';
    }
}
