<?php
declare(strict_types=1);

/**
 * Orchestrierung Personalvorgänge: Akte, PDF, Zugang, optional Austritt.
 */
final class HrPersonnelEventService
{
    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, event_id?: int}
     */
    public static function addNote(int $contactId, array $input, ?int $userId, array $uploadFile = []): array
    {
        $contact = self::requireEmployee($contactId);
        if (is_array($contact)) {
            return $contact;
        }

        $eventDate = self::normalizeDate((string) ($input['event_date'] ?? date('Y-m-d'))) ?? date('Y-m-d');
        $title = trim((string) ($input['title'] ?? 'Aktennotiz'));
        if ($title === '') {
            $title = 'Aktennotiz';
        }
        $summary = trim((string) ($input['summary'] ?? ''));

        $fileDocType = '';
        $fileIndex = null;
        $filePath = '';

        if ($uploadFile !== [] && ($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $files = ContactFileStorage::normalizeFiles($contact->employeeFiles);
            $docType = trim((string) ($input['file_doc_type'] ?? 'written_warnings'));
            if (!isset(EmployeeData::multiDocumentTypes()[$docType])) {
                $docType = 'written_warnings';
            }
            $tmp = (string) ($uploadFile['tmp_name'] ?? '');
            $binary = is_file($tmp) ? (string) file_get_contents($tmp) : '';
            if ($binary === '') {
                return ['ok' => false, 'message' => 'Upload konnte nicht gelesen werden.'];
            }
            $orig = (string) ($uploadFile['name'] ?? 'scan.pdf');
            $mime = (string) ($uploadFile['type'] ?? 'application/pdf');
            $existingList = is_array($files[$docType] ?? null) && !isset($files[$docType]['path'])
                ? $files[$docType]
                : [];
            $appended = ContactFileStorage::appendBinaryDocument(
                $contactId,
                $docType,
                $binary,
                $orig,
                $mime !== '' ? $mime : 'application/pdf',
                $existingList
            );
            $files[$docType] = $appended['list'];
            ContactRepository::updateEmployeeFiles($contactId, $files);
            $fileDocType = $docType;
            $fileIndex = $appended['index'];
            $filePath = $appended['entry']['path'];
        }

        $id = HrPersonnelEventRepository::insert([
            'contact_id' => $contactId,
            'event_type' => HrPersonnelEventRepository::TYPE_NOTE,
            'event_date' => $eventDate,
            'title' => $title,
            'summary' => $summary,
            'status' => HrPersonnelEventRepository::STATUS_ISSUED,
            'file_doc_type' => $fileDocType,
            'file_index' => $fileIndex,
            'file_path' => $filePath,
            'created_by' => $userId,
        ]);

        return ['ok' => true, 'message' => 'Personalvorgang gespeichert.', 'event_id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, event_id?: int, download_url?: string}
     */
    public static function generateWarning(int $contactId, array $input, ?int $userId): array
    {
        $contact = self::requireEmployee($contactId);
        if (is_array($contact)) {
            return $contact;
        }

        $facts = trim((string) ($input['facts_text'] ?? ''));
        if ($facts === '') {
            return ['ok' => false, 'message' => 'Sachverhalt ist Pflicht.'];
        }

        $ctx = EmploymentContractContext::build($contact, []);
        $eventDate = self::normalizeDate((string) ($input['event_date'] ?? date('Y-m-d'))) ?? date('Y-m-d');
        $payload = [
            'event_type' => HrPersonnelEventRepository::TYPE_WARNING,
            'event_date_de' => EmploymentContractContext::formatDateDe($eventDate),
            'facts_text' => $facts,
            'expectation_text' => trim((string) ($input['expectation_text'] ?? '')),
            'threat_text' => trim((string) ($input['threat_text'] ?? '')),
        ];
        $pdf = HrPersonnelEventPdfService::render($ctx['placeholders'], $payload);
        $stored = self::storePdf($contact, 'written_warnings', $pdf, 'Abmahnung-' . $eventDate . '.pdf');

        $id = HrPersonnelEventRepository::insert([
            'contact_id' => $contactId,
            'event_type' => HrPersonnelEventRepository::TYPE_WARNING,
            'event_date' => $eventDate,
            'title' => trim((string) ($input['title'] ?? 'Abmahnung')) ?: 'Abmahnung',
            'summary' => mb_substr($facts, 0, 500),
            'facts_text' => $facts,
            'expectation_text' => $payload['expectation_text'],
            'threat_text' => $payload['threat_text'],
            'status' => HrPersonnelEventRepository::STATUS_ISSUED,
            'file_doc_type' => 'written_warnings',
            'file_index' => $stored['index'],
            'file_path' => $stored['entry']['path'],
            'created_by' => $userId,
        ]);

        return [
            'ok' => true,
            'message' => 'Abmahnung erzeugt und in der Akte gespeichert.',
            'event_id' => $id,
            'download_url' => EmployeeDocuments::downloadUrl($contactId, 'written_warnings', $stored['index']),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, event_id?: int, download_url?: string}
     */
    public static function generateTermination(int $contactId, array $input, ?int $userId): array
    {
        $contact = self::requireEmployee($contactId);
        if (is_array($contact)) {
            return $contact;
        }

        $kind = trim((string) ($input['event_type'] ?? HrPersonnelEventRepository::TYPE_TERM_ORDINARY));
        $allowed = [
            HrPersonnelEventRepository::TYPE_TERM_ORDINARY,
            HrPersonnelEventRepository::TYPE_TERM_SUMMARY,
            HrPersonnelEventRepository::TYPE_TERM_AGREEMENT,
        ];
        if (!in_array($kind, $allowed, true)) {
            $kind = HrPersonnelEventRepository::TYPE_TERM_ORDINARY;
        }

        $endDate = self::normalizeDate((string) ($input['end_date'] ?? ''));
        if ($endDate === null) {
            return ['ok' => false, 'message' => 'Beendigungsdatum ist Pflicht.'];
        }
        if ($kind === HrPersonnelEventRepository::TYPE_TERM_SUMMARY && trim((string) ($input['facts_text'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Bei fristloser Kündigung ist eine kurze Begründung Pflicht.'];
        }

        $ctx = EmploymentContractContext::build($contact, []);
        $eventDate = self::normalizeDate((string) ($input['event_date'] ?? date('Y-m-d'))) ?? date('Y-m-d');
        $payload = [
            'event_type' => $kind,
            'event_date_de' => EmploymentContractContext::formatDateDe($eventDate),
            'end_date_de' => EmploymentContractContext::formatDateDe($endDate),
            'facts_text' => trim((string) ($input['facts_text'] ?? '')),
        ];
        $pdf = HrPersonnelEventPdfService::render($ctx['placeholders'], $payload);
        $labels = HrPersonnelEventRepository::typeLabels();
        $slug = match ($kind) {
            HrPersonnelEventRepository::TYPE_TERM_SUMMARY => 'fristlos',
            HrPersonnelEventRepository::TYPE_TERM_AGREEMENT => 'aufhebung',
            default => 'ordentlich',
        };
        $stored = self::storePdf($contact, 'termination_letters', $pdf, 'Kuendigung-' . $slug . '-' . $eventDate . '.pdf');

        $title = trim((string) ($input['title'] ?? '')) ?: ($labels[$kind] ?? 'Kündigung');
        $id = HrPersonnelEventRepository::insert([
            'contact_id' => $contactId,
            'event_type' => $kind,
            'event_date' => $eventDate,
            'title' => $title,
            'summary' => 'Ende: ' . EmploymentContractContext::formatDateDe($endDate),
            'facts_text' => $payload['facts_text'],
            'status' => HrPersonnelEventRepository::STATUS_ISSUED,
            'file_doc_type' => 'termination_letters',
            'file_index' => $stored['index'],
            'file_path' => $stored['entry']['path'],
            'created_by' => $userId,
        ]);

        $msg = 'Schreiben erzeugt und in der Akte gespeichert.';
        if (!empty($input['set_exit_date'])) {
            $ed = $contact->employeeData;
            $ed['exit_date'] = $endDate;
            ContactRepository::updateEmployeeData($contactId, $ed);
            $msg .= ' Austrittsdatum gesetzt. Bitte Lohn/SV-Abmeldung prüfen.';
        }

        return [
            'ok' => true,
            'message' => $msg,
            'event_id' => $id,
            'download_url' => EmployeeDocuments::downloadUrl($contactId, 'termination_letters', $stored['index']),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string}
     */
    public static function updateDelivery(int $contactId, int $eventId, array $input): array
    {
        $contact = self::requireEmployee($contactId);
        if (is_array($contact)) {
            return $contact;
        }
        $event = HrPersonnelEventRepository::find($eventId);
        if ($event === null || (int) $event['contact_id'] !== $contactId) {
            return ['ok' => false, 'message' => 'Vorgang nicht gefunden.'];
        }

        $channel = trim((string) ($input['delivery_channel'] ?? ''));
        if ($channel !== '' && !isset(HrPersonnelEventRepository::channelLabels()[$channel])) {
            $channel = HrPersonnelEventRepository::CHANNEL_OTHER;
        }
        $deliveryDate = self::normalizeDate((string) ($input['delivery_date'] ?? ''));
        $note = trim((string) ($input['delivery_note'] ?? ''));
        $status = $deliveryDate !== null
            ? HrPersonnelEventRepository::STATUS_DELIVERED
            : (string) ($event['status'] ?? HrPersonnelEventRepository::STATUS_ISSUED);

        HrPersonnelEventRepository::update($eventId, [
            'delivery_date' => $deliveryDate ?? '',
            'delivery_channel' => $channel,
            'delivery_note' => $note,
            'status' => $status,
        ]);

        return ['ok' => true, 'message' => 'Zugang aktualisiert.'];
    }

    /**
     * @return Contact|array{ok: false, message: string}
     */
    private static function requireEmployee(int $contactId): Contact|array
    {
        if ($contactId <= 0) {
            return ['ok' => false, 'message' => 'Ungültige Kontakt-ID.'];
        }
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return ['ok' => false, 'message' => 'Kontakt nicht gefunden.'];
        }
        if (!CrmRole::hasEmployeeProfile($contact->contactRole)) {
            return ['ok' => false, 'message' => 'Nur für Mitarbeiterkontakte.'];
        }

        return $contact;
    }

    /**
     * @return array{entry: array<string, string>, index: int, list: list<array<string, string>>}
     */
    private static function storePdf(Contact $contact, string $docType, string $pdf, string $name): array
    {
        $files = ContactFileStorage::normalizeFiles($contact->employeeFiles);
        $existingList = is_array($files[$docType] ?? null) && !isset($files[$docType]['path'])
            ? $files[$docType]
            : [];
        $appended = ContactFileStorage::appendBinaryDocument(
            $contact->id,
            $docType,
            $pdf,
            $name,
            'application/pdf',
            $existingList
        );
        $files[$docType] = $appended['list'];
        ContactRepository::updateEmployeeFiles($contact->id, $files);

        return $appended;
    }

    private static function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $dt = date_create($raw);

        return $dt instanceof DateTimeInterface ? $dt->format('Y-m-d') : null;
    }

}
