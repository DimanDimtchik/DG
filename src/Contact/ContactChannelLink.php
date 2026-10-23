<?php

declare(strict_types=1);

/**
 * Anklickbare E-Mail-/Telefon-Links.
 * E-Mail: CRM-Compose wenn Post-Modul erlaubt, sonst mailto:; Mailto bleibt als Fallback.
 */
final class ContactChannelLink
{
    /**
     * CRM-Compose bevorzugen (eingeloggter Nutzer mit Post-Recht).
     */
    public static function preferCrmMail(): bool
    {
        if (!class_exists('AuthService') || !class_exists('MenuRegistry') || !class_exists('RoleResolver')) {
            return false;
        }
        $user = AuthService::user();
        if ($user === null || RoleResolver::isCustomer($user)) {
            return false;
        }

        return MenuRegistry::canAccess($user, 'post');
    }

    /**
     * CRM-Telefonanlage / Click-to-Call aktiv? (noch nicht)
     */
    public static function preferCrmPhone(): bool
    {
        return false;
    }

    public static function composeUrl(string $email = '', ?int $mailboxId = null): string
    {
        $params = ['page' => 'post', 'compose' => '1'];
        $email = EmailNormalizer::normalize($email);
        if ($email !== '') {
            $params['to'] = $email;
        }
        if ($mailboxId !== null && $mailboxId > 0) {
            $params['mailbox'] = (string) $mailboxId;
        }

        return '/app?' . http_build_query($params);
    }

    public static function emailHref(string $email): string
    {
        $email = EmailNormalizer::normalize($email);
        if ($email === '' || !EmailNormalizer::isValidSyntax($email)) {
            return '';
        }

        if (self::preferCrmMail()) {
            return self::composeUrl($email);
        }

        return 'mailto:' . $email;
    }

    public static function phoneHref(string $phone, ?string $defaultCountry = null): string
    {
        $e164 = PhoneNormalizer::toE164($phone, $defaultCountry);
        if ($e164 === null) {
            $clean = PhoneNormalizer::normalize($phone, $defaultCountry);
            if ($clean === '') {
                return '';
            }
            $digits = preg_replace('/\D/', '', $clean) ?? '';
            if ($digits === '') {
                return '';
            }
            $e164 = str_starts_with($clean, '+') ? '+' . $digits : $digits;
        }

        if (self::preferCrmPhone()) {
            // Phase Telefon: CRM-Dialer
        }

        return 'tel:' . $e164;
    }

    /**
     * HTML-Anker oder Platzhalter. Bereits escaped.
     * Bei CRM-Compose zusätzlich kleiner mailto:-Fallback.
     */
    public static function emailHtml(string $email, string $empty = '—'): string
    {
        $normalized = EmailNormalizer::normalize($email);
        if ($normalized === '') {
            return View::escape($empty);
        }
        $href = self::emailHref($normalized);
        $label = View::escape($normalized);
        if ($href === '') {
            return $label;
        }

        $html = '<a class="dg-channel-link dg-channel-link--email" href="'
            . View::escape($href)
            . '">' . $label . '</a>';

        if (self::preferCrmMail()) {
            $html .= ' <a class="dg-channel-link dg-channel-link--mailto-fallback" href="mailto:'
                . View::escape($normalized)
                . '" title="In Mailprogramm öffnen">↗</a>';
        }

        return $html;
    }

    /**
     * HTML-Anker (Anzeige formatiert) oder Platzhalter. Bereits escaped.
     */
    public static function phoneHtml(string $phone, string $empty = '—', ?string $defaultCountry = null): string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return View::escape($empty);
        }
        $display = PhoneNormalizer::formatForDisplay($raw, $defaultCountry);
        $href = self::phoneHref($raw, $defaultCountry);
        $label = View::escape($display !== '' ? $display : $raw);
        if ($href === '') {
            return $label;
        }

        return '<a class="dg-channel-link dg-channel-link--phone" href="'
            . View::escape($href)
            . '">' . $label . '</a>';
    }
}
