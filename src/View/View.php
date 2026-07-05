<?php
declare(strict_types=1);

final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require DG_ROOT . '/views/' . $view . '.php';
    }

    public static function partial(string $view, array $data = []): void
    {
        self::render($view, $data);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
