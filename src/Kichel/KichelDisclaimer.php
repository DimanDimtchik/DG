<?php

declare(strict_types=1);

/**
 * KH0 — einheitlicher Nutzungs-/Hinweistext für Kichel (Widget + Super-Kichel).
 * Kurz, verständlich; keine Plan-Codes in der Endnutzer-Formulierung.
 */
final class KichelDisclaimer
{
    public const TEXT = 'Unterstützung im CRM — keine Rechtsberatung. Bitte Quellen prüfen.';

    public static function text(): string
    {
        return self::TEXT;
    }
}
