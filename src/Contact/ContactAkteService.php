<?php
declare(strict_types=1);

/**
 * Kontakt-Akte: chronologische Mails und Dokumente (Beleg / Verfasser / HR) für einen Kontakt.
 */
final class ContactAkteService
{
    public const LIMIT = 50;

    /**
     * @return array{
     *   mails: list<array<string, mixed>>,
     *   documents: list<array<string, mixed>>,
     *   can_view_mails: bool,
     *   can_view_vouchers: bool,
     *   can_view_writer: bool,
     *   can_view_hr: bool
     * }
     */
    public static function build(User $viewer, Contact $contact): array
    {
        $contactId = $contact->id;
        $canMails = MenuRegistry::canAccess($viewer, 'post');
        $canVouchers = MenuRegistry::canAccess($viewer, 'buchhaltung-belege')
            || MenuRegistry::canAccess($viewer, 'buchhaltung-beleg-form');
        $canWriter = MenuRegistry::canAccess($viewer, 'dokumentenverfasser')
            || MenuRegistry::canAccess($viewer, 'dokumentenverfasser-form');
        $canHr = ContactAccessResolver::canViewEmployeeHrData($viewer, $contact);

        $mails = [];
        if ($canMails && Database::isConfigured()) {
            $boxes = MailboxRepository::accessibleForUser($viewer);
            $mailboxIds = array_values(array_filter(array_map(
                static fn (array $b): int => (int) ($b['id'] ?? 0),
                $boxes
            )));
            $mails = MailLogRepository::listForContact($contactId, $mailboxIds, self::LIMIT);
        }

        $documents = [];
        if ($canVouchers && Database::isConfigured()) {
            $list = VoucherRepository::list([
                'contact_id' => $contactId,
                'page' => 1,
                'per_page' => self::LIMIT,
            ]);
            foreach ($list['items'] as $row) {
                $documents[] = [
                    'kind' => 'beleg',
                    'kind_label' => 'Beleg',
                    'date' => (string) ($row['voucher_date'] ?? $row['created_at'] ?? ''),
                    'title' => trim(
                        (string) ($row['type_label'] ?? 'Beleg') . ' '
                        . (trim((string) ($row['invoice_number'] ?? '')) !== ''
                            ? (string) $row['invoice_number']
                            : '#' . (int) ($row['id'] ?? 0))
                    ),
                    'meta' => trim((string) ($row['supplier_name'] ?? '')),
                    'url' => '/app?page=buchhaltung-beleg-form&action=edit&id=' . (int) ($row['id'] ?? 0),
                    'url_label' => 'Öffnen',
                ];
            }
        }

        if ($canWriter && Database::isConfigured() && class_exists('WriterDocumentRepository')) {
            foreach (WriterDocumentRepository::listRecent(self::LIMIT, 0, null, $contactId) as $row) {
                $documents[] = [
                    'kind' => 'writer',
                    'kind_label' => 'Dokument',
                    'date' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
                    'title' => trim((string) ($row['title'] ?? '')) !== ''
                        ? (string) $row['title']
                        : (trim((string) ($row['subject'] ?? '')) !== ''
                            ? (string) $row['subject']
                            : 'Dokument #' . (int) ($row['id'] ?? 0)),
                    'meta' => (string) ($row['status'] ?? ''),
                    'url' => '/app?page=dokumentenverfasser-form&action=edit&id=' . (int) ($row['id'] ?? 0),
                    'url_label' => 'Öffnen',
                ];
            }
        }

        if ($canHr) {
            foreach (EmployeeDocuments::listUploaded($contact->employeeFiles) as $file) {
                $fileIndex = $file['fileIndex'] ?? null;
                $documents[] = [
                    'kind' => 'hr',
                    'kind_label' => 'HR',
                    'date' => (string) ($file['uploaded_at'] ?? ''),
                    'title' => (string) ($file['label'] ?? 'Datei'),
                    'meta' => (string) ($file['name'] ?? ''),
                    'url' => EmployeeDocuments::viewUrl($contactId, (string) $file['type'], $fileIndex),
                    'url_label' => 'Anzeigen',
                    'download_url' => EmployeeDocuments::downloadUrl($contactId, (string) $file['type'], $fileIndex),
                ];
            }
        }

        usort($documents, static function (array $a, array $b): int {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });
        if (count($documents) > self::LIMIT) {
            $documents = array_slice($documents, 0, self::LIMIT);
        }

        return [
            'mails' => $mails,
            'documents' => $documents,
            'can_view_mails' => $canMails,
            'can_view_vouchers' => $canVouchers,
            'can_view_writer' => $canWriter,
            'can_view_hr' => $canHr,
        ];
    }
}
