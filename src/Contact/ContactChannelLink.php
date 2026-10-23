<?php

declare(strict_types=1);

/**
 * Anklickbare E-Mail-/Telefon-Links.
 * Heute: mailto: / tel: (Outlook, Softphone am PC).
 * Später: CRM-Compose / Telefonanlage — Hook-Punkte bewusst zentral.
 */
final class ContactChannelLink
{
    /**
     * CRM-Posteingang/Compose aktiv? (Phase 2 — vorerst immer false)
     */
    public static function preferCrmMail(): bool
    {
        return false;
    }

    /**
     * CRM-Telefonanlage / Click-to-Call aktiv? (Phase 3 — vorerst immer false)
     */
    public static function preferCrmPhone(): bool
    {
        return false;
    }

    public static function emailHref(string $email): string
    {
        $email = EmailNormalizer::normalize($email);
        if ($email === '' || !EmailNormalizer::isValidSyntax($email)) {
            return '';
        }

        if (self::preferCrmMail()) {
            // Phase 2: return '/app/…compose?to=' . rawurlencode($email);
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
            // Phase 3: return '/app/…dial?n=' . rawurlencode($e164);
        }

        return 'tel:' . $e164;
    }

    /**
     * HTML-Anker oder Platzhalter. Bereits escaped.
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

        return '<a class="dg-channel-link dg-channel-link--email" href="'
            . View::escape($href)
            . '">' . $label . '</a>';
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
