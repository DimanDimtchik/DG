<?php
declare(strict_types=1);

/** Bankumsätze aus CAMT.053 (SEPA Kontoauszug XML). */
final class Camt053Importer
{
    /**
     * @return array{batch: string, imported: int, skipped: int}
     */
    public static function import(string $xmlContent): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $xmlContent = trim($xmlContent);
        if ($xmlContent === '') {
            throw new InvalidArgumentException('Leere CAMT-Datei.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new InvalidArgumentException('Ungültiges CAMT.053 XML.');
        }

        $xml->registerXPathNamespace('camt', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
        $entries = $xml->xpath('//camt:Ntry') ?: $xml->xpath('//Ntry') ?: [];
        $batch = bin2hex(random_bytes(16));
        $imported = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            $amountNode = $entry->Amt ?? $entry->children()->Amt ?? null;
            if ($amountNode === null) {
                $skipped++;
                continue;
            }
            $amount = round((float) ($amountNode->__toString() ?? '0'), 2);
            $creditDebit = strtoupper((string) ($entry->CdtDbtInd ?? 'CRDT'));
            if ($creditDebit === 'DBIT') {
                $amount = -abs($amount);
            } else {
                $amount = abs($amount);
            }

            $bookingDate = self::parseDate($entry->BookgDt ?? $entry->ValDt ?? null);
            $valueDate = self::parseDate($entry->ValDt ?? null);
            $reference = trim((string) ($entry->NtryDtls->TxDtls->RmtInf->Ustrd ?? ''));
            if ($reference === '') {
                $reference = trim((string) ($entry->AddtlNtryInf ?? ''));
            }
            $endToEnd = trim((string) ($entry->NtryDtls->TxDtls->Refs->EndToEndId ?? ''));
            $counterparty = trim((string) ($entry->NtryDtls->TxDtls->RltdPties->Cdtr->Nm ?? $entry->NtryDtls->TxDtls->RltdPties->Dbtr->Nm ?? ''));
            $iban = strtoupper(str_replace(' ', '', (string) ($entry->NtryDtls->TxDtls->RltdPties->CdtrAcct->Id->IBAN ?? $entry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->IBAN ?? '')));

            if ($amount == 0.0 || $bookingDate === '') {
                $skipped++;
                continue;
            }

            BankTransactionRepository::insert([
                'import_batch' => $batch,
                'transaction_date' => $bookingDate,
                'value_date' => $valueDate !== '' ? $valueDate : null,
                'amount' => $amount,
                'counterparty_name' => $counterparty,
                'counterparty_iban' => $iban,
                'reference_text' => mb_substr($reference, 0, 500),
                'end_to_end_id' => mb_substr($endToEnd, 0, 64),
            ]);
            $imported++;
        }

        if ($imported > 0) {
            BankReconciliationService::autoMatchBatch($batch);
        }

        return ['batch' => $batch, 'imported' => $imported, 'skipped' => $skipped];
    }

    private static function parseDate(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        $dt = (string) ($node->Dt ?? $node->__toString() ?? '');

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $dt) === 1 ? substr($dt, 0, 10) : '';
    }
}
