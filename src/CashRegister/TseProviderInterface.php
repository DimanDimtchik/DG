<?php
declare(strict_types=1);

/**
 * TSE-Provider-Vertrag (K3a) — Arrays laut Plan K2a, keine DTOs.
 *
 * @phpstan-type TseTxInput array{
 *   receipt_type?: string,
 *   amount_gross?: float|int|string,
 *   vat_amounts?: list<array{vat_rate_code?: string, vat_rate?: string, amount?: float|int|string}>,
 *   client_serial_number?: string,
 *   process_type?: string,
 *   process_data?: string
 * }
 * @phpstan-type TseTxResult array{
 *   tse_tx_id: string,
 *   state: string,
 *   tx_number: int|null,
 *   revision: int|null,
 *   signature_value: string|null,
 *   signature_algorithm: string|null,
 *   signature_counter: string|null,
 *   qr_code_data: string|null,
 *   time_start: int|null,
 *   time_end: int|null,
 *   tss_serial_number: string,
 *   client_serial_number: string,
 *   raw: array<string, mixed>|null
 * }
 * @phpstan-type TseStatusResult array{
 *   ok: bool,
 *   state: string,
 *   tss_id: string,
 *   client_id: string,
 *   tss_serial_number: string,
 *   certificate_valid_until: string|null,
 *   message: string
 * }
 * @phpstan-type TseExportRequest array{
 *   date_from?: string|null,
 *   date_to?: string|null,
 *   format?: string
 * }
 * @phpstan-type TseExportResult array{
 *   ok: bool,
 *   file_bytes: string|null,
 *   download_ref: string|null,
 *   checksum: string,
 *   message: string
 * }
 */
interface TseProviderInterface
{
    /** Provider-Kurzname: mock | fiskaly | … */
    public function getName(): string;

    /**
     * Darf die App bei TSE-Ausfall Bons speichern und später nachsignieren? (K14c)
     * false = keine Nachsignatur-Queue; Ausfall nur Flag/Warnung/Abbruch (K14a/b).
     * true nur laut Anbieter — Implementierung Nachzug: K14d.
     */
    public function supportsDeferredSignature(): bool;

    /**
     * TSS/Client erreichbar? Kein Bon.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array;

    /**
     * Bon starten (ACTIVE). $txId = Client-UUID.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function startTransaction(string $txId, array $input = []): array;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateTransaction(string $txId, int $revision, array $input = []): array;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function finishTransaction(string $txId, int $revision, array $input = []): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelTransaction(string $txId, int $revision): array;

    /**
     * Export/TAR — bis K18 ggf. Stub.
     *
     * @param array<string, mixed> $request
     * @return array{ok: bool, file_bytes: string|null, download_ref: string|null, checksum: string, message: string}
     */
    public function exportData(array $request = []): array;
}
