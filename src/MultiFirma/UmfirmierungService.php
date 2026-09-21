<?php
declare(strict_types=1);

/**
 * Multi-Firma MF3: Umfirmierungs-Assistent (KDV-Register, Variante A).
 * Legt Nachfolger-Slot an, archiviert Vorgänger — keine Instanz-Provision, keine Buchungsübernahme.
 */
final class UmfirmierungService
{
    public const GEWINNERMITTLUNG = [
        '' => '— noch nicht gesetzt —',
        'euer' => 'Einnahmen-Überschuss-Rechnung (§ 4 Abs. 3 EStG)',
        'bilanz' => 'Betriebsvermögensvergleich / Bilanz (§ 4 Abs. 1 EStG)',
    ];

    /**
     * Prüfbarkeits-Checkliste (Hinweis, nicht buchungswirksam).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function checklist(): array
    {
        return [
            ['key' => 'abschluss_alt', 'label' => 'Abschluss Vorgänger (Rumpf-WJ) vorbereiten'],
            ['key' => 'eroeffnung_neu', 'label' => 'Eröffnungsbilanz / Anfangswerte Nachfolger'],
            ['key' => 'uebergangsgewinn', 'label' => 'Übergangsgewinn/-verlust prüfen (Hinweis an Steuerberater)'],
            ['key' => 'bank', 'label' => 'Bankkonten / Lastschriften umstellen'],
            ['key' => 'vertraege', 'label' => 'Verträge, Versicherungen, Mandate'],
            ['key' => 'ust', 'label' => 'USt / Steuernummer / ELSTER-Zertifikat Nachfolger'],
            ['key' => 'impressum', 'label' => 'Impressum / Website / Briefköpfe'],
            ['key' => 'datev', 'label' => 'DATEV-Mandant / Beraternummer Nachfolger'],
            ['key' => 'provision', 'label' => 'CRM-Instanz Nachfolger manuell provisionieren (Domain/DB)'],
        ];
    }

    public static function normalizeGewinn(string $value): string
    {
        $value = strtolower(trim($value));

        return isset(self::GEWINNERMITTLUNG[$value]) ? $value : '';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{predecessor_id: int, successor_id: int, checklist: list<array{key: string, label: string}>}
     */
    public static function start(int $predecessorId, array $input): array
    {
        if ($predecessorId < 1 || !Database::isConfigured()) {
            throw new InvalidArgumentException('Vorgänger-Firma ungültig.');
        }
        if (!KdvCustomerRepository::multiFirmaColumnsReady()) {
            throw new RuntimeException('Multi-Firma-Spalten fehlen — Migration 082 ausführen.');
        }
        MigrationRunner::runPending();

        $pred = KdvCustomerRepository::findById($predecessorId);
        if ($pred === null) {
            throw new InvalidArgumentException('Vorgänger nicht gefunden.');
        }
        if ((string) ($pred['firm_slot_status'] ?? 'active') !== 'active') {
            throw new InvalidArgumentException('Nur aktive Slots können umfirmiert werden.');
        }

        $stichtag = trim((string) ($input['stichtag'] ?? ''));
        if ($stichtag === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $stichtag)) {
            throw new InvalidArgumentException('Stichtag (YYYY-MM-DD) ist erforderlich.');
        }
        $newName = trim((string) ($input['company_name'] ?? ''));
        $newDomain = trim((string) ($input['domain'] ?? ''));
        if ($newName === '') {
            throw new InvalidArgumentException('Name der Nachfolger-Firma ist erforderlich.');
        }
        if ($newDomain === '') {
            throw new InvalidArgumentException('Domain der Nachfolger-Firma ist erforderlich.');
        }
        if (strcasecmp($newDomain, (string) ($pred['domain'] ?? '')) === 0) {
            throw new InvalidArgumentException('Nachfolger braucht eine andere Domain als der Vorgänger.');
        }

        $orgId = (int) ($pred['org_id'] ?? 0);
        if ($orgId < 1) {
            $orgId = KdvOrgRepository::ensureByName(
                trim((string) ($pred['company_name'] ?? 'Organisation')),
                trim((string) ($pred['contact_email'] ?? '')) ?: null
            );
            KdvCustomerRepository::save([
                'company_name' => (string) ($pred['company_name'] ?? ''),
                'domain' => (string) ($pred['domain'] ?? ''),
                'org_id' => $orgId,
                'firm_relation' => (string) ($pred['firm_relation'] ?? 'standalone'),
                'firm_slot_status' => 'active',
                'tariff' => (string) ($pred['tariff'] ?? 'basic'),
                'monthly_price' => (float) ($pred['monthly_price'] ?? 0),
                'billing_cycle' => (string) ($pred['billing_cycle'] ?? 'monatlich'),
                'status' => (string) ($pred['status'] ?? 'aktiv'),
                'contact_name' => (string) ($pred['contact_name'] ?? ''),
                'contact_email' => (string) ($pred['contact_email'] ?? ''),
                'contact_phone' => (string) ($pred['contact_phone'] ?? ''),
                'gewinnermittlung' => (string) ($pred['gewinnermittlung'] ?? ''),
                'company_type' => (string) ($pred['company_type'] ?? ''),
                'tax_number_note' => (string) ($pred['tax_number_note'] ?? ''),
            ], $predecessorId);
        }

        $gewinn = self::normalizeGewinn((string) ($input['gewinnermittlung'] ?? 'bilanz'));
        $companyType = mb_substr(trim((string) ($input['company_type'] ?? '')), 0, 80);
        $taxNote = mb_substr(trim((string) ($input['tax_number_note'] ?? '')), 0, 191);
        $tariff = (string) ($input['tariff'] ?? ($pred['tariff'] ?? 'basic'));
        if (!isset(KdvCustomerRepository::TARIFFS[$tariff])) {
            $tariff = 'basic';
        }

        $checklist = self::checklist();
        $checkNote = "Umfirmierung Stichtag {$stichtag} (Vorgänger #{$predecessorId}). Checkliste:\n";
        foreach ($checklist as $item) {
            $checkNote .= '- [ ] ' . $item['label'] . "\n";
        }

        $successorId = KdvCustomerRepository::save([
            'company_name' => $newName,
            'domain' => $newDomain,
            'contact_name' => (string) ($input['contact_name'] ?? $pred['contact_name'] ?? ''),
            'contact_email' => (string) ($input['contact_email'] ?? $pred['contact_email'] ?? ''),
            'contact_phone' => (string) ($input['contact_phone'] ?? $pred['contact_phone'] ?? ''),
            'org_id' => $orgId,
            'firm_relation' => 'nachfolger',
            'related_customer_id' => $predecessorId,
            'firm_slot_status' => 'active',
            'effective_from' => $stichtag,
            'gewinnermittlung' => $gewinn,
            'company_type' => $companyType,
            'tax_number_note' => $taxNote,
            'tariff' => $tariff,
            'billing_cycle' => (string) ($pred['billing_cycle'] ?? 'monatlich'),
            'status' => 'neu',
            'contract_start' => $stichtag,
            'mf_apply_price' => '1',
            'notes' => $checkNote,
            'db_name' => trim((string) ($input['db_name'] ?? '')) ?: null,
        ], null);

        $arch = MultiFirmaPricingService::archiveSlotDefaults($stichtag);
        KdvCustomerRepository::save([
            'company_name' => (string) ($pred['company_name'] ?? ''),
            'domain' => (string) ($pred['domain'] ?? ''),
            'db_name' => (string) ($pred['db_name'] ?? ''),
            'contact_name' => (string) ($pred['contact_name'] ?? ''),
            'contact_email' => (string) ($pred['contact_email'] ?? ''),
            'contact_phone' => (string) ($pred['contact_phone'] ?? ''),
            'kas_login' => (string) ($pred['kas_login'] ?? ''),
            'status' => (string) ($pred['status'] ?? 'aktiv'),
            'tariff' => (string) ($pred['tariff'] ?? 'basic'),
            'monthly_price' => 0,
            'billing_cycle' => (string) ($pred['billing_cycle'] ?? 'monatlich'),
            'contract_start' => (string) ($pred['contract_start'] ?? ''),
            'contract_end' => (string) ($pred['contract_end'] ?? ''),
            'notes' => trim((string) ($pred['notes'] ?? '') . "\n\nUmfirmierung → Nachfolger #{$successorId}, Stichtag {$stichtag}."),
            'org_id' => $orgId,
            'firm_relation' => 'vorgaenger',
            'related_customer_id' => $successorId,
            'firm_slot_status' => $arch['firm_slot_status'],
            'effective_from' => (string) ($pred['effective_from'] ?? ''),
            'effective_to' => $arch['effective_to'],
            'gewinnermittlung' => (string) ($pred['gewinnermittlung'] ?? ''),
            'company_type' => (string) ($pred['company_type'] ?? ''),
            'tax_number_note' => (string) ($pred['tax_number_note'] ?? ''),
            'mf_make_archive_slot' => '0',
        ], $predecessorId);

        return [
            'predecessor_id' => $predecessorId,
            'successor_id' => $successorId,
            'checklist' => $checklist,
        ];
    }
}
