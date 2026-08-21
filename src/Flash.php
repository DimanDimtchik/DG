<?php
declare(strict_types=1);

/**
 * Einmalige Session-Flash-Nachrichten für Redirect-Feedback.
 */
final class Flash
{
    /**
     * @param string $type z. B. success, error, info.
     */
    public static function set(string $type, string $message): void
    {
        $_SESSION['dg_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Liest und entfernt die gespeicherte Flash-Nachricht.
     *
     * @return array{type: string, message: string}|null
     */
    public static function pull(): ?array
    {
        if (!isset($_SESSION['dg_flash'])) {
            return null;
        }
        $flash = $_SESSION['dg_flash'];
        unset($_SESSION['dg_flash']);

        return is_array($flash) ? $flash : null;
    }
}
