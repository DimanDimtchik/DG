<?php
declare(strict_types=1);

/**
 * ES7a: Platzhalter-Interface für externen Unterschrifts-Import.
 *
 * Kein konkreter Adapter hier (ES7b). Speichern immer über
 * UserSignatureStore::appendNew mit source=import + source_ref.
 *
 * @see docs/SIGNATURE-IMPORT-PROVIDER.md
 */
interface SignatureImportProvider
{
    /**
     * Branchenneutraler Kurzname (Logs/Einstellungen) — kein Tenant-Hardcode.
     */
    public function name(): string;

    /**
     * Ob Endpoint/Credentials konfiguriert sind (ohne Secrets zu exponieren).
     */
    public function isConfigured(): bool;

    /**
     * Neueste Signatur für den CRM-User vom externen Dienst laden.
     *
     * @return array{
     *   external_ref: string,
     *   mime: string,
     *   content: string,
     *   captured_at?: string|null,
     *   display_hint?: string|null
     * }|null null = nichts Neues / kein Treffer
     */
    public function fetchLatestForUser(int $userId, int $contactId): ?array;
}
