<?php
declare(strict_types=1);

/**
 * Einfaches PHP-View-Rendering und HTML-Escaping.
 */
final class View
{
    /**
     * Rendert eine View-Datei unter views/ mit optionalen Template-Daten.
     *
     * @param array<string, mixed> $data Variablen, die im Template verfügbar sind.
     */
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require DG_ROOT . '/views/' . $view . '.php';
    }

    /**
     * Alias für {@see render()} – Partial-Templates.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $view, array $data = []): void
    {
        self::render($view, $data);
    }

    /**
     * Escaped einen String für sichere HTML-Ausgabe.
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
