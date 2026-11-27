<?php

declare(strict_types=1);

/**
 * KH2 — Sichtbarkeit nach Rolle: Mitarbeiter vs. Admin.
 *
 * Mitarbeiter: Antwort, Links, verständliche Quellen (ohne interne Refs/Roh-Fakten).
 * Admin: zusätzlich rag_facts, rank_meta, Quellen-Refs, Rechen-Rohdaten.
 *
 * Protokoll speichert weiterhin die volle Antwort (vor Filter) — nur die API-Ausgabe filtert.
 */
final class KichelRoleView
{
    /** Felder, die nur Admins in der Hilfe-API sehen. */
    public const ADMIN_ONLY_KEYS = [
        'rag_facts',
        'rank_meta',
    ];

    /**
     * @param array<string, mixed> $response volle Assistant-Antwort
     * @return array<string, mixed>
     */
    public static function forUser(User $user, array $response): array
    {
        if (RoleResolver::isAdmin($user)) {
            return $response;
        }

        return self::forStaff($response);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function forStaff(array $response): array
    {
        foreach (self::ADMIN_ONLY_KEYS as $key) {
            unset($response[$key]);
        }

        if (isset($response['sources']) && is_array($response['sources'])) {
            $response['sources'] = self::sourcesForStaff($response['sources']);
        }

        if (isset($response['calculation']) && is_array($response['calculation'])) {
            $calc = $response['calculation'];
            $response['calculation'] = [
                'kind' => (string) ($calc['kind'] ?? ''),
                'computed_by' => (string) ($calc['computed_by'] ?? 'php'),
            ];
        }

        return $response;
    }

    /**
     * @param list<mixed> $sources
     * @return list<array{label: string, href: string|null}>
     */
    public static function sourcesForStaff(array $sources): array
    {
        $out = [];
        $seen = [];
        foreach ($sources as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['ref'] ?? ''));
            }
            if ($label === '') {
                continue;
            }
            $href = isset($row['href']) && is_string($row['href']) && $row['href'] !== ''
                ? $row['href']
                : null;
            $key = mb_strtolower($label . '|' . ($href ?? ''), 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'label' => $label,
                'href' => $href,
            ];
        }

        return $out;
    }
}
