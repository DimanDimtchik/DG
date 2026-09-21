<?php
declare(strict_types=1);

/**
 * Multi-Firma MF6b: Einbahn-Contact-Export als JSON (Schema MF6a).
 */
final class ContactExportService
{
    public const FORMAT = 'dg_contact_export';
    public const VERSION = 1;
    private const MAX_CONTACTS = 5000;

    /** @var list<string> */
    private const FIELD_KEYS = [
        'salutation',
        'first_name',
        'last_name',
        'display_name',
        'company_name',
        'email',
        'email_2',
        'phone_1',
        'phone_2',
        'customer_number',
        'supplier_number',
        'tax_number',
        'vat_id',
        'contact_note',
        'website',
        'contact_role',
        'address1_extra',
        'address1_street',
        'address1_postal',
        'address1_city',
        'address1_country',
        'address2_extra',
        'address2_street',
        'address2_postal',
        'address2_city',
        'address2_country',
    ];

    /**
     * Ob der Export-Button angezeigt werden darf (MF6a Voraussetzungen).
     */
    public static function isExportAllowed(?User $user): bool
    {
        if ($user === null || !MenuRegistry::canAccess($user, 'kontakte')) {
            return false;
        }
        if (!Database::isConfigured()) {
            return false;
        }
        $meta = self::sourceMeta();
        if ($meta['share_contacts']) {
            return true;
        }
        // Kundeninstanz ohne KDV-Org: Admin-Export erlaubt
        if ($meta['org_id'] === null) {
            return RoleResolver::isAdmin($user);
        }

        // Org vorhanden, Flag aus → kein Export
        return false;
    }

    /**
     * @return array{
     *   domain: string,
     *   firm_label: string,
     *   org_id: int|null,
     *   share_contacts: bool
     * }
     */
    public static function sourceMeta(): array
    {
        $domain = FirmSwitcherService::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $firmLabel = trim(CompanySettings::displayName());
        if ($firmLabel === '') {
            $firmLabel = trim((string) App::config('crm_name'));
        }
        if ($firmLabel === '') {
            $firmLabel = $domain !== '' ? $domain : 'CRM';
        }

        $orgId = null;
        $share = false;
        if (
            $domain !== ''
            && class_exists('KdvCustomerRepository')
            && class_exists('KdvOrgRepository')
            && KdvOrgRepository::tableReady()
        ) {
            $slot = KdvCustomerRepository::findByDomain($domain);
            if ($slot !== null) {
                $oid = (int) ($slot['org_id'] ?? 0);
                if ($oid > 0) {
                    $orgId = $oid;
                    $org = KdvOrgRepository::findById($oid);
                    if ($org !== null && KdvOrgRepository::shareContactsColumnReady()) {
                        $share = !empty($org['share_contacts']);
                    }
                    $slotLabel = trim((string) ($slot['company_name'] ?? ''));
                    if ($slotLabel !== '') {
                        $firmLabel = $slotLabel;
                    }
                }
            }
        }

        return [
            'domain' => $domain,
            'firm_label' => $firmLabel,
            'org_id' => $orgId,
            'share_contacts' => $share,
        ];
    }

    /**
     * Baut Export-Paket (MF6a-Schema).
     *
     * @return array{payload: array<string, mixed>, count: int, filename: string, json: string}
     */
    public static function buildPackage(string $search, ?User $viewer): array
    {
        $meta = self::sourceMeta();
        if ($meta['domain'] === '') {
            throw new RuntimeException('Quell-Domain unbekannt — Export nicht möglich.');
        }

        $contacts = ContactRepository::listForExport($search, $viewer, self::MAX_CONTACTS);
        $exported = [];
        foreach ($contacts as $contact) {
            $exported[] = self::mapContact($contact, $meta['firm_label'], $meta['domain']);
        }

        $payload = [
            'format' => self::FORMAT,
            'v' => self::VERSION,
            'exported_at' => date('c'),
            'source' => [
                'domain' => $meta['domain'],
                'firm_label' => $meta['firm_label'],
                'org_id' => $meta['org_id'],
                'share_contacts' => $meta['share_contacts'],
            ],
            'contacts' => $exported,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('JSON-Export fehlgeschlagen.');
        }

        $safeDomain = preg_replace('/[^a-z0-9.-]+/i', '-', $meta['domain']) ?: 'crm';
        $filename = 'kontakte-' . $safeDomain . '-' . date('Ymd-His') . '.json';

        return [
            'payload' => $payload,
            'count' => count($exported),
            'filename' => $filename,
            'json' => $json . "\n",
        ];
    }

    /**
     * Sendet Download-Headers und JSON-Body (beendet Request).
     */
    public static function sendDownload(string $search, ?User $viewer): never
    {
        $pkg = self::buildPackage($search, $viewer);
        Flash::set('success', 'Export erstellt (' . $pkg['count'] . ' Kontakte).');

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $pkg['filename']) . '"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $pkg['json'];
        exit;
    }

    /**
     * @return array{source_id: int, match: array{email: string, customer_number: string, supplier_number: string}, fields: array<string, string>, origin_hint: string}
     */
    private static function mapContact(Contact $c, string $firmLabel, string $domain): array
    {
        $form = ContactRepository::toForm($c);
        $fields = [];
        foreach (self::FIELD_KEYS as $key) {
            $fields[$key] = trim((string) ($form[$key] ?? ''));
        }
        if ($fields['address1_country'] === '') {
            $fields['address1_country'] = 'DE';
        }
        if ($fields['address2_country'] === '') {
            $fields['address2_country'] = 'DE';
        }

        $email = strtolower($fields['email']);
        $hint = 'Übernommen aus ' . $firmLabel . ' (' . $domain . ')';
        if (function_exists('mb_substr')) {
            $hint = mb_substr($hint, 0, 191);
        } else {
            $hint = substr($hint, 0, 191);
        }

        return [
            'source_id' => $c->id,
            'match' => [
                'email' => $email,
                'customer_number' => $fields['customer_number'],
                'supplier_number' => $fields['supplier_number'],
            ],
            'fields' => $fields,
            'origin_hint' => $hint,
        ];
    }
}
