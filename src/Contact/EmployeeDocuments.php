<?php
declare(strict_types=1);

/**
 * Employee Documents.
 */
final class EmployeeDocuments
{
    /**
     * viewUrl
     * @param int $contactId Kontakt-ID
     * @param string $docType
     * @param int|null $fileIndex
     * @return string
     */
    public static function viewUrl(int $contactId, string $docType, ?int $fileIndex = null): string
    {
        $url = '/app?page=kontakte&action=view&id=' . $contactId . '&doc=' . rawurlencode($docType);
        if ($fileIndex !== null) {
            $url .= '&file=' . $fileIndex;
        }

        return $url;
    }

    /**
     * downloadUrl
     * @param int $contactId Kontakt-ID
     * @param string $docType
     * @param int|null $fileIndex
     * @return string
     */
    public static function downloadUrl(int $contactId, string $docType, ?int $fileIndex = null): string
    {
        $url = '/app?page=kontakte&action=download&id=' . $contactId . '&doc=' . rawurlencode($docType);
        if ($fileIndex !== null) {
            $url .= '&file=' . $fileIndex;
        }

        return $url;
    }

    /**
     * @param array<string, array<string, string>|list<array<string, string>>> $employeeFiles
     * @return list<array{type: string, label: string, name: string, mime: string, fileIndex: int|null}>
     */
    public static function listUploaded(array $employeeFiles): array
    {
        $items = [];

        foreach (EmployeeData::documentTypes() + EmployeeData::disabilityDocumentTypes() as $type => $label) {
            $entry = $employeeFiles[$type] ?? [];
            if (!is_array($entry) || empty($entry['path'])) {
                continue;
            }
            $items[] = [
                'type' => $type,
                'label' => $label,
                'name' => (string) ($entry['original_name'] ?? 'Datei'),
                'mime' => (string) ($entry['mime'] ?? ''),
                'fileIndex' => null,
            ];
        }

        $certs = $employeeFiles['medical_certificates'] ?? [];
        if (is_array($certs) && !isset($certs['path'])) {
            foreach ($certs as $index => $entry) {
                if (!is_array($entry) || empty($entry['path'])) {
                    continue;
                }
                $items[] = [
                    'type' => 'medical_certificates',
                    'label' => 'Ärztliches Attest',
                    'name' => (string) ($entry['original_name'] ?? 'Attest'),
                    'mime' => (string) ($entry['mime'] ?? ''),
                    'fileIndex' => (int) $index,
                ];
            }
        }

        return $items;
    }

    /**
     * hasUploaded
     * @param array $employeeFiles
     * @return bool
     */
    public static function hasUploaded(array $employeeFiles): bool
    {
        return self::listUploaded($employeeFiles) !== [];
    }
}
