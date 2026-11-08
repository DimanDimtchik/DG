<?php
declare(strict_types=1);

/**
 * ES7b/ES7c: Adapter externes Signatur-Bild → media + appendNew (source=import).
 * Kein Vendor-Hardcode — arbeitet mit SignatureImportProvider oder Roh-Payload.
 * ES7c: Re-Import löscht keine Historie — Zeilenzahl muss steigen.
 *
 * @see docs/SIGNATURE-IMPORT-PROVIDER.md
 */
final class SignatureImportService
{
    /**
     * Provider abfragen und bei Treffer importieren.
     *
     * @return array{id: int, media_id: string, source_ref: string, provider: string, history_count: int}|null
     *         null = Provider lieferte nichts Neues
     */
    public static function importFromProvider(
        SignatureImportProvider $provider,
        int $userId,
        int $contactId,
        ?int $createdByUserId = null
    ): ?array {
        if (!$provider->isConfigured()) {
            throw new RuntimeException(
                'Import-Provider ist nicht konfiguriert (' . $provider->name() . ').'
            );
        }

        $payload = $provider->fetchLatestForUser($userId, $contactId);
        if ($payload === null) {
            return null;
        }

        $result = self::importPayload($userId, $contactId, $payload, $createdByUserId);
        $result['provider'] = $provider->name();

        return $result;
    }

    /**
     * Roh-Payload (wie SignatureImportProvider::fetchLatestForUser) speichern.
     * ES7c: Historie wächst um genau eine Zeile — kein DELETE alter Signaturen.
     *
     * @param array{
     *   external_ref?: string,
     *   mime?: string,
     *   content?: string,
     *   captured_at?: string|null,
     *   display_hint?: string|null
     * } $payload
     * @return array{id: int, media_id: string, source_ref: string, history_count: int}
     */
    public static function importPayload(
        int $userId,
        int $contactId,
        array $payload,
        ?int $createdByUserId = null
    ): array {
        $ref = trim((string) ($payload['external_ref'] ?? ''));
        $mime = trim((string) ($payload['mime'] ?? ''));
        $content = (string) ($payload['content'] ?? '');
        $captured = isset($payload['captured_at']) ? trim((string) $payload['captured_at']) : '';

        if ($ref === '') {
            throw new InvalidArgumentException('external_ref fehlt.');
        }
        if ($mime === '') {
            throw new InvalidArgumentException('mime fehlt.');
        }
        if ($content === '') {
            throw new InvalidArgumentException('content fehlt.');
        }

        $countBefore = UserSignatureStore::countForUser($userId);

        $id = UserSignatureStore::appendFromImport(
            $userId,
            $contactId,
            $content,
            $mime,
            $ref,
            $createdByUserId,
            $captured !== '' ? $captured : null
        );

        $countAfter = UserSignatureStore::countForUser($userId);
        // ES7c: Re-Import / Import darf keine Zeile entfernen
        if ($countAfter !== $countBefore + 1) {
            throw new RuntimeException(
                'Import-Historie inkonsistent (erwartet +1 Zeile, vorher '
                . $countBefore . ', nachher ' . $countAfter . ').'
            );
        }

        $active = UserSignatureStore::activeForUser($userId);
        $mediaId = is_array($active) ? (string) ($active['media_id'] ?? '') : '';

        return [
            'id' => $id,
            'media_id' => $mediaId,
            'source_ref' => $ref,
            'history_count' => $countAfter,
        ];
    }
}
