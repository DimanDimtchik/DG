<?php
declare(strict_types=1);

/** HTML-Layout für Kalender-E-Mails (CRM-Port des Terminkalender-Designs). */
final class CalendarEmailLayout
{
    /**
     * @param array<string, string> $details Label => value
     * @param array{
     *   title?: string,
     *   intro?: string,
     *   details?: array<string, string>,
     *   footer_note?: string,
     *   extra_html?: string,
     *   context?: array<string, string>
     * } $args
     */
    public static function renderBookingEmail(array $args): string
    {
        $context = is_array($args['context'] ?? null) ? $args['context'] : CalendarEmailTokens::demoContext();
        $emailTheme = EmailLayoutSettings::emailTheme();
        $footerTheme = EmailLayoutSettings::resolvedFooter($context);
        $title = trim((string) ($args['title'] ?? ''));
        $intro = trim((string) ($args['intro'] ?? ''));
        $details = is_array($args['details'] ?? null) ? $args['details'] : [];
        $footerNote = trim((string) ($args['footer_note'] ?? ''));
        $extraHtml = (string) ($args['extra_html'] ?? '');

        $introBlock = $intro !== ''
            ? '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:' . self::esc($emailTheme['text']) . ';">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';

        $footerBlock = $footerNote !== ''
            ? '<p style="margin:24px 0 0;font-size:13px;line-height:1.5;color:' . self::esc($emailTheme['text_muted']) . ';">' . htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';

        $content = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;margin:0 auto;">'
            . '<tr><td style="padding:28px 24px 8px;font-family:Arial,Helvetica,sans-serif;background-color:' . self::esc($emailTheme['surface']) . ';">'
            . '<h1 style="margin:0;font-size:22px;font-weight:600;color:' . self::esc($emailTheme['text']) . ';">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 24px 24px;font-family:Arial,Helvetica,sans-serif;background-color:' . self::esc($emailTheme['surface']) . ';">'
            . $introBlock
            . self::renderDetailsTable($details, $emailTheme)
            . $extraHtml
            . $footerBlock
            . self::renderClosingSignature($context)
            . '</td></tr>'
            . '<tr><td style="' . self::siteFooterBarStyle($footerTheme) . '">'
            . self::renderSiteFooter($context)
            . '</td></tr>'
            . '</table>';

        return self::wrapDocument($title, $content, $context);
    }

    /** @param array<string, string> $details */
    public static function previewDetails(): array
    {
        $ctx = CalendarEmailTokens::demoContext();

        return [
            'Leistung' => $ctx['leistung'],
            'Termin' => $ctx['termin_datum'] . ', ' . $ctx['termin_zeit'],
            'Kunde' => $ctx['kunde_name'],
            'E-Mail' => $ctx['kunde_email'],
            'Telefon' => $ctx['kunde_telefon'],
            'Mitarbeiter' => $ctx['mitarbeiter'],
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     * @param array<string, string>|null $context
     */
    public static function settingsHeaderPreview(?array $cfg = null, ?array $context = null): string
    {
        $context = $context ?? CalendarEmailTokens::demoContext();
        $header = EmailLayoutSettings::resolvedHeader($context, $cfg);
        $emailTheme = EmailLayoutSettings::emailTheme();
        $inner = self::renderBrandHeaderResolved($header);

        return '<div class="dg-email-layout-preview__card">'
            . self::renderHeaderBarBlock($header, $inner)
            . '<div style="padding:16px 24px;background-color:' . self::esc($emailTheme['surface']) . ';font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:' . self::esc($emailTheme['text_muted']) . ';">E-Mail-Inhalt (Vorlage)</div>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $cfg
     * @param array<string, string>|null $context
     */
    public static function settingsFooterPreview(?array $cfg = null, ?array $context = null): string
    {
        $context = $context ?? CalendarEmailTokens::demoContext();
        $footer = EmailLayoutSettings::resolvedFooter($context, $cfg);
        $closing = self::renderClosingSignatureResolved($footer);
        $closing = $closing !== ''
            ? str_replace('margin:24px 0 0', 'margin:0', $closing)
            : '';
        $site = self::renderSiteFooterResolved($footer);

        return '<div class="dg-email-layout-preview__card">'
            . '<div style="padding:20px 24px;background-color:' . self::esc((string) $footer['surface_color']) . ';font-family:Arial,Helvetica,sans-serif;">' . $closing . '</div>'
            . '<div style="' . self::siteFooterBarStyle($footer) . '">' . $site . '</div>'
            . '</div>';
    }

    private static function renderClosingSignature(array $context): string
    {
        return self::renderClosingSignatureResolved(EmailLayoutSettings::resolvedFooter($context));
    }

    /** @param array<string, mixed> $footer */
    public static function renderClosingSignatureBlock(array $footer): string
    {
        return self::renderClosingSignatureResolved($footer);
    }

    /** @param array<string, mixed> $footer */
    public static function closingPlainText(array $footer): string
    {
        $lines = array_filter([
            trim((string) ($footer['thanks_line'] ?? '')),
            trim((string) ($footer['salutation'] ?? '')),
            trim((string) ($footer['signature'] ?? '')),
        ], static fn(string $line): bool => $line !== '');

        return implode("\n", $lines);
    }

    /**
     * Vollständiges HTML für Post-Versand (Kopfzeile, Inhalt, Grußblock, Site-Fuß).
     *
     * @param array<string, mixed> $footer Bereits aufgelöste Fußzeile inkl. personalisierter Signatur
     * @param array<string, string> $context
     */
    public static function renderPostMessage(string $innerHtml, array $footer, array $context = []): string
    {
        $emailTheme = EmailLayoutSettings::emailTheme();
        $bodyStyle = 'margin:0;font-size:' . (int) AppearanceSettings::emailFontSizePx() . 'px;line-height:1.6;color:'
            . self::esc($emailTheme['text']) . ';font-family:Arial,Helvetica,sans-serif;';

        $opening = trim((string) ($footer['opening_greeting'] ?? ''));
        $openingBlock = $opening !== ''
            ? '<p style="margin:0 0 16px;' . $bodyStyle . '">' . htmlspecialchars($opening, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';

        $content = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;margin:0 auto;">'
            . '<tr><td style="padding:24px;font-family:Arial,Helvetica,sans-serif;background-color:' . self::esc($emailTheme['surface']) . ';">'
            . '<div style="' . $bodyStyle . '">' . $openingBlock . $innerHtml . '</div>'
            . self::renderClosingSignatureResolved($footer)
            . '</td></tr>'
            . '<tr><td style="' . self::siteFooterBarStyle($footer) . '">'
            . self::renderSiteFooterResolved($footer)
            . '</td></tr>'
            . '</table>';

        return self::wrapDocument('', $content, $context);
    }

    /** @param array<string, mixed> $footer */
    private static function renderClosingSignatureResolved(array $footer): string
    {
        $parts = array_filter([
            (string) ($footer['thanks_line'] ?? ''),
            (string) ($footer['salutation'] ?? ''),
            (string) ($footer['signature'] ?? ''),
        ], static fn(string $line): bool => trim($line) !== '');

        if ($parts === []) {
            return '';
        }

        $textColor = self::esc((string) ($footer['signature_text_color'] ?? EmailLayoutSettings::emailTheme()['text']));
        $html = '';
        foreach ($parts as $line) {
            $html .= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '<br>';
        }

        return '<p style="margin:24px 0 0;font-size:15px;line-height:1.75;color:' . $textColor . ';">' . rtrim($html, '<br>') . '</p>';
    }

    /** @param array<string, string> $context */
    private static function renderSiteFooter(array $context): string
    {
        return self::renderSiteFooterResolved(EmailLayoutSettings::resolvedFooter($context));
    }

    /** @param array<string, mixed> $footer */
    private static function renderSiteFooterResolved(array $footer): string
    {
        $hasLegal = $footer['show_legal_links'] && $footer['legal_links'] !== [];
        $hasSocial = $footer['show_social_links'] && $footer['social_links'] !== [];
        if (
            !$footer['show_company_block']
            && $footer['extra_text'] === ''
            && trim((string) $footer['website']) === ''
            && !$hasLegal
            && !$hasSocial
        ) {
            return '';
        }

        $textColor = self::esc((string) $footer['bar_text_color']);
        $lines = [];
        if ($footer['show_company_block']) {
            if ($footer['company_name'] !== '') {
                $lines[] = '<strong style="color:' . $textColor . ';">' . htmlspecialchars((string) $footer['company_name'], ENT_QUOTES, 'UTF-8') . '</strong>';
            }
            if ($footer['street'] !== '') {
                $lines[] = htmlspecialchars((string) $footer['street'], ENT_QUOTES, 'UTF-8');
            }
            $cityLine = trim((string) $footer['postal'] . ' ' . (string) $footer['city']);
            if ($cityLine !== '') {
                $lines[] = htmlspecialchars($cityLine, ENT_QUOTES, 'UTF-8');
            }
        }

        $linkColor = self::esc((string) $footer['link_color']);
        $website = trim((string) $footer['website']);
        $websiteHtml = $website !== ''
            ? '<p style="margin:8px 0 0;"><a href="' . htmlspecialchars($website, ENT_QUOTES, 'UTF-8') . '" style="color:' . $linkColor . ';text-decoration:none;">'
                . htmlspecialchars($website, ENT_QUOTES, 'UTF-8') . '</a></p>'
            : '';

        $socialHtml = $hasSocial
            ? self::renderFooterLinkRow($footer['social_links'], $footer, 'Social Media')
            : '';

        $legalHtml = $hasLegal
            ? self::renderFooterLinkRow($footer['legal_links'], $footer, 'Rechtliches')
            : '';

        $extraHtml = $footer['extra_text'] !== ''
            ? '<p style="margin:12px 0 0;">' . nl2br(htmlspecialchars((string) $footer['extra_text'], ENT_QUOTES, 'UTF-8')) . '</p>'
            : '';

        if ($lines === [] && $websiteHtml === '' && $socialHtml === '' && $legalHtml === '' && $extraHtml === '') {
            return '';
        }

        $companyBlock = $lines !== []
            ? '<p style="margin:0;">' . implode('<br>', $lines) . '</p>'
            : '';

        return $companyBlock . $websiteHtml . $socialHtml . $legalHtml . $extraHtml;
    }

    /**
     * @param list<array{label: string, url: string}> $links
     * @param array<string, mixed> $footer
     */
    private static function renderFooterLinkRow(array $links, array $footer, string $ariaLabel): string
    {
        if ($links === []) {
            return '';
        }

        $linkColor = self::esc((string) $footer['link_color']);
        $textColor = self::esc((string) $footer['bar_text_color']);
        $sepColor = self::esc((string) $footer['bar_text_muted']);
        $parts = [];
        foreach ($links as $link) {
            $parts[] = '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '" style="color:' . $linkColor . ';text-decoration:none;">'
                . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return '<p role="navigation" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '" style="margin:12px 0 0;font-size:12px;line-height:1.6;color:' . $textColor . ';">'
            . implode(' <span style="color:' . $sepColor . ';">|</span> ', $parts)
            . '</p>';
    }

    /**
     * @param array<string, string> $details
     * @param array<string, string> $emailTheme
     */
    private static function renderDetailsTable(array $details, array $emailTheme): string
    {
        if ($details === []) {
            return '';
        }

        $rows = '';
        $odd = true;
        foreach ($details as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $bg = $odd ? $emailTheme['body_bg'] : $emailTheme['surface'];
            $odd = !$odd;
            $rows .= '<tr>'
                . '<th scope="row" width="140" align="left" valign="top" style="padding:12px 16px;background-color:' . self::esc($bg) . ';border-bottom:1px solid ' . self::esc($emailTheme['border']) . ';font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:' . self::esc($emailTheme['text_muted']) . ';">'
                . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                . '</th>'
                . '<td valign="top" style="padding:12px 16px;background-color:' . self::esc($bg) . ';border-bottom:1px solid ' . self::esc($emailTheme['border']) . ';font-family:Arial,Helvetica,sans-serif;font-size:14px;color:' . self::esc($emailTheme['text']) . ';">'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                . '</td></tr>';
        }

        if ($rows === '') {
            return '';
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid ' . self::esc($emailTheme['border']) . ';border-radius:4px;overflow:hidden;margin:0 0 24px;"><tbody>'
            . $rows
            . '</tbody></table>';
    }

    /** @param array<string, string> $context */
    private static function renderBrandHeader(array $context): string
    {
        return self::renderBrandHeaderResolved(EmailLayoutSettings::resolvedHeader($context));
    }

    /** @param array<string, mixed> $header */
    private static function renderBrandHeaderResolved(array $header): string
    {
        $textColor = self::esc((string) $header['text_color']);
        $sublineColor = self::esc((string) $header['subline_color']);
        $barBg = self::esc((string) $header['background_color']);
        $parts = [];

        if (!empty($header['show_logo']) && ($header['logo_url'] ?? '') !== '') {
            $parts[] = '<span class="dg-email-logo-plate" style="display:inline-block;background-color:' . $barBg . ';padding:6px 10px;border-radius:4px;line-height:0;">'
                . '<img class="dg-email-logo" src="' . htmlspecialchars((string) $header['logo_url'], ENT_QUOTES, 'UTF-8') . '" alt="'
                . htmlspecialchars((string) $header['logo_alt'], ENT_QUOTES, 'UTF-8')
                . '" width="220" style="display:block;max-height:42px;max-width:220px;width:auto;height:auto;border:0;outline:none;text-decoration:none;">'
                . '</span>';
        }

        if (($header['title'] ?? '') !== '') {
            $parts[] = '<span class="dg-email-header__title" style="display:block;font-size:18px;font-weight:700;color:' . $textColor . ';letter-spacing:0.02em;line-height:1.3;">'
                . htmlspecialchars((string) $header['title'], ENT_QUOTES, 'UTF-8') . '</span>';
        }

        if (($header['subline'] ?? '') !== '') {
            $parts[] = '<span class="dg-email-header__subline" style="display:block;margin-top:6px;font-size:13px;line-height:1.4;color:' . $sublineColor . ';">'
                . htmlspecialchars((string) $header['subline'], ENT_QUOTES, 'UTF-8') . '</span>';
        }

        if ($parts === []) {
            $parts[] = '<span class="dg-email-header__title" style="font-size:18px;font-weight:700;color:' . $textColor . ';letter-spacing:0.02em;line-height:1.3;">'
                . htmlspecialchars(CompanySettings::displayName() ?: 'DG CRM', ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return implode('', $parts);
    }

    /** @param array<string, mixed> $header */
    private static function renderHeaderBarBlock(array $header, string $inner): string
    {
        $bg = self::esc((string) $header['background_color']);

        return '<div class="dg-email-header" style="padding:20px 24px;background-color:' . $bg . ';font-family:Arial,Helvetica,sans-serif;">' . $inner . '</div>';
    }

    /** @param array<string, mixed> $header */
    private static function emailHeadBlock(array $header): string
    {
        $bg = self::esc((string) $header['background_color']);
        $titleColor = self::esc((string) $header['text_color']);
        $sublineColor = self::esc((string) $header['subline_color']);

        return '<meta name="color-scheme" content="light only">'
            . '<meta name="supported-color-schemes" content="light">'
            . '<style type="text/css">'
            . ':root{color-scheme:light only;supported-color-schemes:light;}'
            . '.dg-email-header{background-color:' . $bg . ' !important;background-image:linear-gradient(' . $bg . ',' . $bg . ') !important;}'
            . '.dg-email-header__title{color:' . $titleColor . ' !important;}'
            . '.dg-email-header__subline{color:' . $sublineColor . ' !important;}'
            . '.dg-email-logo-plate{background-color:' . $bg . ' !important;background-image:linear-gradient(' . $bg . ',' . $bg . ') !important;}'
            . '.dg-email-logo{filter:none !important;-webkit-filter:none !important;}'
            . '@media (prefers-color-scheme:dark){'
            . '.dg-email-header{background-color:' . $bg . ' !important;background-image:linear-gradient(' . $bg . ',' . $bg . ') !important;}'
            . '.dg-email-header__title{color:' . $titleColor . ' !important;}'
            . '.dg-email-header__subline{color:' . $sublineColor . ' !important;}'
            . '.dg-email-logo-plate{background-color:' . $bg . ' !important;background-image:linear-gradient(' . $bg . ',' . $bg . ') !important;}'
            . '.dg-email-logo{filter:none !important;-webkit-filter:none !important;}'
            . '[data-ogsc] .dg-email-header,[data-ogsb] .dg-email-header{background-color:' . $bg . ' !important;}'
            . '[data-ogsc] .dg-email-header__title,[data-ogsb] .dg-email-header__title{color:' . $titleColor . ' !important;}'
            . '[data-ogsc] .dg-email-header__subline,[data-ogsb] .dg-email-header__subline{color:' . $sublineColor . ' !important;}'
            . '[data-ogsc] .dg-email-logo-plate,[data-ogsb] .dg-email-logo-plate{background-color:' . $bg . ' !important;}'
            . '}'
            . '</style>';
    }

    /** @param array<string, string> $context */
    private static function wrapDocument(string $title, string $content, array $context): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $header = EmailLayoutSettings::resolvedHeader($context);
        $emailTheme = EmailLayoutSettings::emailTheme();
        $brandHeader = self::renderBrandHeader($context);

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . self::emailHeadBlock($header)
            . '<title>' . $titleEsc . '</title></head>'
            . '<body style="margin:0;padding:0;background-color:' . self::esc($emailTheme['body_bg']) . ';">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:' . self::esc($emailTheme['body_bg']) . ';">'
            . '<tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;background-color:' . self::esc($emailTheme['surface']) . ';border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
            . '<tr><td class="dg-email-header" bgcolor="' . self::htmlBgcolor((string) $header['background_color']) . '" style="background-color:' . self::esc((string) $header['background_color']) . ';padding:20px 24px;font-family:Arial,Helvetica,sans-serif;">' . $brandHeader . '</td></tr>'
            . '<tr><td>' . $content . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function htmlBgcolor(string $hexColor): string
    {
        return ltrim(trim($hexColor), '#');
    }

    /** @param array<string, mixed> $footer */
    private static function siteFooterBarStyle(array $footer): string
    {
        return 'padding:16px 24px;border-top:1px solid ' . self::esc((string) $footer['bar_border_color'])
            . ';background-color:' . self::esc((string) $footer['bar_background_color'])
            . ';font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:'
            . self::esc((string) $footer['bar_text_color']) . ';';
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
