<?php
declare(strict_types=1);

/**
 * Nach Druck/Versand: Ausgangsbelege nicht überschreiben — stornieren und neu anlegen.
 */
final class VoucherDocumentRevisionService
{
    /**
     * Status, nach denen der Beleginhalt nicht mehr überschrieben werden darf.
     *
     * @return list<string>
     */
    public static function immutableStatuses(): array
    {
        return [
            VoucherDocumentStatus::SENT,
            VoucherDocumentStatus::BILLED,
            VoucherDocumentStatus::ACCEPTED,
        ];
    }

    public static function isImmutable(?array $voucher): bool
    {
        if ($voucher === null) {
            return false;
        }
        $type = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? ''));
        if ($type !== 'income') {
            return false;
        }
        $kind = VoucherDocumentKind::sanitize((string) ($voucher['document_kind'] ?? ''));
        if ($kind === '') {
            return false;
        }
        $status = VoucherDocumentStatus::sanitize((string) ($voucher['document_status'] ?? ''));

        return in_array($status, self::immutableStatuses(), true);
    }

    /**
     * Nur Statuswechsel (z. B. Versendet → Abgerechnet) ohne inhaltliche Änderung.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     */
    public static function isStatusOnlyChange(array $existing, array $data): bool
    {
        $wanted = VoucherDocumentStatus::sanitize((string) ($data['document_status'] ?? ''));
        $current = VoucherDocumentStatus::sanitize((string) ($existing['document_status'] ?? ''));
        if ($wanted === '' || $wanted === $current) {
            return false;
        }
        if (!in_array($wanted, [VoucherDocumentStatus::BILLED, VoucherDocumentStatus::CANCELLED, VoucherDocumentStatus::ACCEPTED], true)) {
            return false;
        }

        $sameMoney = abs(
            VoucherRepository::parseMoney($existing['gross_amount'] ?? 0)
            - VoucherRepository::parseMoney($data['gross_amount'] ?? $existing['gross_amount'] ?? 0)
        ) < 0.02;
        $sameDate = trim((string) ($data['voucher_date'] ?? '')) === ''
            || trim((string) ($data['voucher_date'] ?? '')) === trim((string) ($existing['voucher_date'] ?? ''));
        $sameContact = (int) ($data['contact_id'] ?? 0) < 1
            || (int) ($data['contact_id'] ?? 0) === (int) ($existing['contact_id'] ?? 0);
        $postedItems = $data['items'] ?? null;
        $sameItems = !is_array($postedItems); // ohne Positions-POST = Status-Aktion

        return $sameMoney && $sameDate && $sameContact && $sameItems;
    }

    /** Druck/PDF an Kunden: Entwurf → Versendet. */
    public static function markSentOnPrint(int $voucherId): void
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return;
        }
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            return;
        }
        $type = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? ''));
        if ($type !== 'income') {
            return;
        }
        $kind = VoucherDocumentKind::sanitize((string) ($voucher['document_kind'] ?? ''));
        if ($kind === '') {
            return;
        }
        $status = VoucherDocumentStatus::sanitize((string) ($voucher['document_status'] ?? ''));
        if ($status !== '' && $status !== VoucherDocumentStatus::DRAFT) {
            return;
        }
        if (!VoucherDocumentStatus::isValidForKind(VoucherDocumentStatus::SENT, $kind)) {
            return;
        }
        VoucherRepository::updateDocumentStatus($voucherId, VoucherDocumentStatus::SENT);
    }

    /**
     * Speichern eines unveränderlichen Belegs → Storno + neuer Entwurf mit Formulardaten.
     *
     * @param array<string, mixed> $data POST-/Formular-Daten
     * @return array{new_id: int, cancelled_id: int, message: string}
     */
    public static function reviseInsteadOfOverwrite(int $existingId, array $data, ?int $userId): array
    {
        if ($existingId < 1) {
            throw new InvalidArgumentException('Beleg-ID fehlt.');
        }
        $existing = VoucherRepository::findById($existingId);
        if ($existing === null) {
            throw new InvalidArgumentException('Beleg nicht gefunden.');
        }
        if (!self::isImmutable($existing)) {
            throw new InvalidArgumentException('Beleg ist nicht gesperrt — normales Speichern nutzen.');
        }

        $newStatusWanted = VoucherDocumentStatus::sanitize((string) ($data['document_status'] ?? ''));
        // Reines Stornieren ohne Inhalt neu: nur Status setzen.
        if ($newStatusWanted === VoucherDocumentStatus::CANCELLED) {
            VoucherRepository::updateDocumentStatus($existingId, VoucherDocumentStatus::CANCELLED);

            return [
                'new_id' => $existingId,
                'cancelled_id' => $existingId,
                'message' => 'Beleg storniert.',
            ];
        }

        $oldNumber = trim((string) ($existing['invoice_number'] ?? ''));
        $oldKind = VoucherDocumentKind::sanitize((string) ($existing['document_kind'] ?? ''));
        $oldLabel = VoucherDocumentKind::label($oldKind);
        if ($oldLabel === '') {
            $oldLabel = 'Beleg';
        }

        VoucherRepository::updateDocumentStatus($existingId, VoucherDocumentStatus::CANCELLED);

        $data['id'] = '';
        $data['document_status'] = VoucherDocumentStatus::DRAFT;
        $data['invoice_number'] = '';
        $data['paid_amount'] = '';
        $data['paid_at'] = '';
        $data['payment_status'] = VoucherPaymentStatus::OPEN;
        // Kette: neuer Beleg hängt am gleichen Vorgänger (Geschwister zum Storno).
        $parentId = (int) ($existing['parent_voucher_id'] ?? 0);
        if ($parentId > 0) {
            $data['parent_voucher_id'] = (string) $parentId;
        } else {
            // Wurzel (z. B. Angebot): storniertes Dokument als Vorgänger für die Spur.
            $data['parent_voucher_id'] = (string) $existingId;
        }

        $note = trim((string) ($data['notes'] ?? ''));
        $revNote = 'Ersetzt stornierten '
            . $oldLabel
            . ($oldNumber !== '' ? ' ' . $oldNumber : ' #' . $existingId)
            . ' (Korrektur nach Druck/Versand).';
        $data['notes'] = $note !== '' ? $note . "\n" . $revNote : $revNote;

        // Keine Zahlungsübernahme beim Speichern — Zahlungen bleiben am Storno-Beleg.
        unset($data['absorb_payment_id'], $data['record_settlement'], $data['settlement_amount']);

        $newId = VoucherRepository::save($data, null, $userId);

        $msg = 'Alter Beleg storniert'
            . ($oldNumber !== '' ? ' (' . $oldNumber . ')' : '')
            . '. Korrektur als neuer Entwurf angelegt — bitte prüfen, speichern und erneut versenden.';
        if (VoucherPaymentRepository::totalPaid($existingId) > 0.01) {
            $msg .= ' Hinweis: Vorhandene Zahlungen bleiben am stornierten Beleg — bei Bedarf neu zuordnen.';
        }

        return [
            'new_id' => $newId,
            'cancelled_id' => $existingId,
            'message' => $msg,
        ];
    }
}
