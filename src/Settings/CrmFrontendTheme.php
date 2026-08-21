<?php
declare(strict_types=1);

/**
 * Crm Frontend Theme.
 */
final class CrmFrontendTheme
{
    /**
     * Liefert CSS-Custom-Properties.
     * @return array<string, string>
     */
    public static function cssVariables(): array
    {
        $colors = CrmThemeSettings::colors();

        return [
            '--dg-menu-bg' => $colors['menu_bg'],
            '--dg-menu-bg-hover' => $colors['menu_bg_hover'],
            '--dg-menu-bg-active' => $colors['menu_bg_active'],
            '--dg-menu-text' => $colors['menu_text'],
            '--dg-menu-text-muted' => $colors['menu_text_muted'],
            '--dg-menu-border' => $colors['menu_border'],
            '--dg-brand' => $colors['brand'],
            '--dg-brand-dark' => $colors['brand_dark'],
            '--dg-body-bg' => $colors['body_bg'],
            '--dg-surface' => $colors['surface'],
            '--dg-text' => $colors['text'],
            '--dg-text-secondary' => $colors['text_secondary'],
            '--dg-text-muted' => $colors['text_muted'],
            '--dg-border' => $colors['border'],
            '--dg-border-strong' => $colors['border_strong'],
            '--dg-primary' => $colors['primary'],
            '--dg-primary-hover' => $colors['primary_hover'],
            '--dg-focus-ring' => $colors['focus_ring'],
        ];
    }

    /**
     * Methode root declarations.
     * @return string
     */
    public static function rootDeclarations(): string
    {
        $parts = [];
        foreach (self::cssVariables() as $name => $value) {
            $parts[] = $name . ':' . $value;
        }

        return implode(';', $parts);
    }

    /**
     * Methode wrapper style attribute.
     * @return string
     */
    public static function wrapperStyleAttribute(): string
    {
        return ' style="' . htmlspecialchars(self::rootDeclarations(), ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Methode inline css.
     * @return string
     */
    public static function inlineCss(): string
    {
        return ':root{' . self::rootDeclarations() . '}';
    }
}
