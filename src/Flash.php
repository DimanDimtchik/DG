<?php
declare(strict_types=1);

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['dg_flash'] = ['type' => $type, 'message' => $message];
    }

    /** @return array{type: string, message: string}|null */
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
