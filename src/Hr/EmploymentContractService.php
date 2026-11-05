<?php
declare(strict_types=1);

/**
 * Orchestriert Prüfung, PDF-Erzeugung und Ablage als Mitarbeiterdokument „Arbeitsvertrag“.
 */
final class EmploymentContractService
{
    /**
     * @param array<string, mixed> $options
     *   contract_type?, force?, annex_training?, annex_pension?, annex_ifsg?, annex_video?
     * @return array{
     *   ok: bool,
     *   message: string,
     *   missing?: list<array{key: string, label: string}>,
     *   preview?: array{contract_type: string, is_minor: bool, type_label: string},
     *   file?: array<string, string>,
     *   download_url?: string
     * }
     */
    public static function generate(int $contactId, array $options = []): array
    {
        if ($contactId <= 0) {
            return ['ok' => false, 'message' => 'Ungültige Kontakt-ID.'];
        }
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return ['ok' => false, 'message' => 'Kontakt nicht gefunden.'];
        }
        if (!CrmRole::hasEmployeeProfile($contact->contactRole)) {
            return ['ok' => false, 'message' => 'Arbeitsverträge nur für Mitarbeiterkontakte.'];
        }

        $ctx = EmploymentContractContext::build($contact, $options);
        $force = !empty($options['force']);
        if ($ctx['missing'] !== [] && !$force) {
            return [
                'ok' => false,
                'message' => 'Pflichtangaben fehlen (Nachweisgesetz). Bitte ergänzen oder mit „Trotzdem erzeugen“ fortfahren.',
                'missing' => $ctx['missing'],
                'preview' => [
                    'contract_type' => $ctx['contract_type'],
                    'is_minor' => $ctx['is_minor'],
                    'type_label' => EmploymentContractContext::typeLabels()[$ctx['contract_type']] ?? $ctx['contract_type'],
                ],
            ];
        }

        $opts = [
            'contract_type' => $ctx['contract_type'],
            'is_minor' => $ctx['is_minor'],
            'annex_training' => self::flag($options, 'annex_training', true),
            'annex_pension' => self::flag(
                $options,
                'annex_pension',
                $ctx['contract_type'] === EmploymentContractContext::TYPE_MINIJOB
            ),
            'annex_ifsg' => self::flag(
                $options,
                'annex_ifsg',
                ($ctx['placeholders']['food_hygiene_duty'] ?? '') === '1'
            ),
            'annex_video' => self::flag(
                $options,
                'annex_video',
                ($ctx['placeholders']['training_video_url'] ?? '') !== ''
            ),
        ];

        $pdf = EmploymentContractPdfService::renderPdf($ctx['placeholders'], $opts);
        $slug = $ctx['contract_type'];
        if ($ctx['is_minor']) {
            $slug .= '-minderjaehrig';
        }
        $originalName = 'Arbeitsvertrag-' . $slug . '-' . date('Y-m-d') . '.pdf';

        $files = ContactFileStorage::normalizeFiles($contact->employeeFiles);
        $previous = is_array($files['employment_contract'] ?? null) ? $files['employment_contract'] : [];
        $hadPrevious = !empty($previous['path']);
        $stored = ContactFileStorage::storeBinaryDocument(
            $contactId,
            'employment_contract',
            $pdf,
            $originalName,
            'application/pdf',
            $previous
        );
        $files['employment_contract'] = $stored;
        ContactRepository::updateEmployeeFiles($contactId, $files);

        $viewUrl = EmployeeDocuments::viewUrl($contactId, 'employment_contract');
        $download = EmployeeDocuments::downloadUrl($contactId, 'employment_contract');
        $message = 'Arbeitsvertrag als PDF angelegt — unter Mitarbeiterdaten → Hochgeladene Dokumente bzw. Dokumente „Arbeitsvertrag“.';
        if ($hadPrevious) {
            $message .= ' Ein vorheriger Upload wurde ersetzt.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'file' => $stored,
            'view_url' => $viewUrl,
            'download_url' => $download,
            'preview' => [
                'contract_type' => $ctx['contract_type'],
                'is_minor' => $ctx['is_minor'],
                'type_label' => EmploymentContractContext::typeLabels()[$ctx['contract_type']] ?? $ctx['contract_type'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function flag(array $options, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }
        $v = $options[$key];
        if (is_bool($v)) {
            return $v;
        }

        return !empty($v) && (string) $v !== '0';
    }
}
