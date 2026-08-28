<?php

declare(strict_types=1);

final class ShopView
{
    public static function render(string $template, array $vars = [], bool $adminLayout = false): void
    {
        extract($vars, EXTR_SKIP);
        $templateFile = SHOP_ROOT . '/views/' . $template . '.php';
        if (!is_file($templateFile)) {
            http_response_code(500);
            echo 'Template fehlt: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            return;
        }
        if ($adminLayout) {
            require SHOP_ROOT . '/views/admin-layout.php';
            return;
        }
        require SHOP_ROOT . '/views/layout.php';
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = SHOP_ROOT . '/' . $path;
        $v = is_file($file) ? (string) filemtime($file) : '1';

        return ShopApp::baseUrl() . '/' . $path . '?v=' . rawurlencode($v);
    }
}
