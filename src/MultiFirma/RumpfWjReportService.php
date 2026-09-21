<?php
declare(strict_types=1);

/**
 * Multi-Firma MF4: Bericht Rumpfwirtschaftsjahr / Umfirmierung (KDV-Org-Sicht).
 */
final class RumpfWjReportService
{
    /**
     * @return array{
     *   org: array<string, mixed>|null,
     *   firms: list<array<string, mixed>>,
     *   pairs: list<array{vorgaenger: array<string, mixed>, nachfolger: array<string, mixed>, stichtag: string}>
     * }
     */
    public static function forOrg(int $orgId): array
    {
        if ($orgId < 1 || !KdvOrgRepository::tableReady()) {
            return ['org' => null, 'firms' => [], 'pairs' => []];
        }
        $org = KdvOrgRepository::findById($orgId);
        $firms = KdvCustomerRepository::listByOrgId($orgId);
        $byId = [];
        foreach ($firms as $f) {
            $byId[(int) ($f['id'] ?? 0)] = $f;
        }
        $pairs = [];
        foreach ($firms as $f) {
            if ((string) ($f['firm_relation'] ?? '') !== 'nachfolger') {
                continue;
            }
            $predId = (int) ($f['related_customer_id'] ?? 0);
            $pred = $byId[$predId] ?? null;
            if ($pred === null) {
                continue;
            }
            $stichtag = (string) ($f['effective_from'] ?? '');
            if ($stichtag === '') {
                $stichtag = (string) ($pred['effective_to'] ?? '');
            }
            $pairs[] = [
                'vorgaenger' => $pred,
                'nachfolger' => $f,
                'stichtag' => $stichtag,
            ];
        }

        return ['org' => $org, 'firms' => $firms, 'pairs' => $pairs];
    }

    /**
     * Label für Slot-Zeitraum (Rumpf-WJ-Hinweis).
     *
     * @param array<string, mixed> $firm
     */
    public static function periodLabel(array $firm): string
    {
        $from = (string) ($firm['effective_from'] ?? '');
        $to = (string) ($firm['effective_to'] ?? '');
        $slot = (string) ($firm['firm_slot_status'] ?? 'active');
        if ($from === '' && $to === '') {
            return $slot === 'archive_readonly' ? 'Archiv (ohne Stichtag)' : 'ohne Stichtagsangabe';
        }
        $fromLabel = $from !== '' ? date('d.m.Y', strtotime($from)) : '…';
        $toLabel = $to !== '' ? date('d.m.Y', strtotime($to)) : 'offen';

        return $fromLabel . ' – ' . $toLabel;
    }
}
