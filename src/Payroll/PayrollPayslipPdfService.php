<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Lohn L6b: HTML → PDF für eine MA-Abrechnung (Dompdf).
 */
final class PayrollPayslipPdfService
{
    public static function isAvailable(): bool
    {
        return class_exists(Dompdf::class);
    }

    /**
     * @param array<string, mixed> $ctx siehe buildContext()
     */
    public static function renderPdf(array $ctx, bool $draft): string
    {
        if (!self::isAvailable()) {
            throw new RuntimeException(
                'PDF-Bibliothek (Dompdf) nicht geladen. Bitte vendor/ deployen (dompdf/dompdf).'
            );
        }
        $html = self::renderHtml($ctx, $draft);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $out = $dompdf->output();
        if (!is_string($out) || $out === '') {
            throw new RuntimeException('Lohn-PDF-Erzeugung fehlgeschlagen.');
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public static function renderHtml(array $ctx, bool $draft): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');

        $ag = is_array($ctx['employer'] ?? null) ? $ctx['employer'] : [];
        $ma = is_array($ctx['employee'] ?? null) ? $ctx['employee'] : [];
        $addr = is_array($ag['address'] ?? null) ? $ag['address'] : [];
        $amounts = is_array($ctx['amounts'] ?? null) ? $ctx['amounts'] : [];
        $lines = is_array($ctx['lines'] ?? null) ? $ctx['lines'] : [];
        $warnings = is_array($ctx['warnings'] ?? null) ? $ctx['warnings'] : [];

        $period = sprintf('%02d/%04d', (int) ($ctx['period_month'] ?? 0), (int) ($ctx['period_year'] ?? 0));
        $title = 'Lohnabrechnung ' . $period;
        $wm = $draft
            ? '<div class="wm">ENTWURF — nicht verbindlich</div>'
            : '';

        $lineRows = '';
        foreach ($lines as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $hours = $ln['hours'] ?? null;
            $hoursLabel = $hours !== null && $hours !== ''
                ? ' × ' . $e($hours) . ' h'
                : '';
            $lineRows .= '<tr>'
                . '<td>' . $e($ln['code'] ?? '') . '</td>'
                . '<td>' . $e($ln['source'] ?? '') . $hoursLabel . '</td>'
                . '<td class="num">' . $money($ln['amount'] ?? 0) . '</td>'
                . '</tr>';
        }
        if ($lineRows === '') {
            $lineRows = '<tr><td colspan="3">Keine Positionen</td></tr>';
        }

        $warnHtml = '';
        if ($warnings !== []) {
            $warnHtml = '<p class="small"><strong>Hinweise:</strong> '
                . $e(implode('; ', array_map('strval', $warnings))) . '</p>';
        }

        $agStreet = trim((string) ($addr['street'] ?? ''));
        $agCity = trim((string) (($addr['postal'] ?? '') . ' ' . ($addr['city'] ?? '')));

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10pt;color:#111;margin:24px}
h1{font-size:16pt;margin:0 0 8px}
h2{font-size:12pt;margin:18px 0 6px;border-bottom:1px solid #ccc;padding-bottom:2px}
table{width:100%;border-collapse:collapse;margin:6px 0 12px}
th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}
th{background:#f3f3f3}
.num{text-align:right;white-space:nowrap}
.meta{width:100%;border:none}
.meta td{border:none;padding:2px 0}
.small{font-size:8pt;color:#444}
.wm{position:fixed;top:40%;left:10%;font-size:28pt;color:rgba(180,0,0,.25);transform:rotate(-25deg);z-index:0}
.totals td{font-weight:bold}
</style></head><body>'
            . $wm
            . '<h1>' . $e($title) . '</h1>'
            . '<table class="meta"><tr><td>'
            . '<strong>' . $e($ag['employer_name'] ?? $ag['legal_name'] ?? '') . '</strong><br>'
            . $e($agStreet) . '<br>' . $e($agCity) . '<br>'
            . 'Betriebsnr.: ' . $e($ag['betriebsnummer'] ?? '') . '<br>'
            . 'Steuernr.: ' . $e($ag['tax_number_est'] ?? '')
            . '</td><td style="text-align:right">'
            . 'Run #' . $e($ctx['run_id'] ?? '') . ' (' . $e($ctx['kind_label'] ?? '') . ')<br>'
            . 'Status: ' . $e($ctx['run_status'] ?? '') . '<br>'
            . 'Erzeugt: ' . $e($ctx['generated_at'] ?? '') . '<br>'
            . ($draft ? 'Art: Entwurf' : 'Art: verbindlich')
            . '</td></tr></table>'

            . '<h2>Mitarbeiter</h2>'
            . '<table class="meta"><tr><td>'
            . '<strong>' . $e($ma['display_name'] ?? '') . '</strong><br>'
            . $e($ma['address'] ?? '') . '<br>'
            . 'IdNr.: ' . $e($ma['tax_id'] ?? '') . ' · StKl ' . $e($ma['tax_class'] ?? '') . '<br>'
            . 'KiSt: ' . $e($ma['religion_code'] ?? '') . ' · Kinderfreib.: ' . $e($ma['child_allowances'] ?? '') . '<br>'
            . 'SV-Nr.: ' . $e($ma['social_security_number'] ?? '') . '<br>'
            . 'Beschäftigung: ' . $e($ma['payroll_employment_type'] ?? '') . ' / Pfad ' . $e($ctx['path'] ?? '') . '<br>'
            . 'Eintritt: ' . $e($ma['entry_date'] ?? '')
            . '</td></tr></table>'

            . '<h2>Brutto-Positionen</h2>'
            . '<table><thead><tr><th>Code</th><th>Quelle</th><th>Betrag EUR</th></tr></thead><tbody>'
            . $lineRows . '</tbody></table>'

            . '<h2>Berechnung</h2>'
            . '<table>'
            . '<tr><td>Brutto gesamt</td><td class="num">' . $money($amounts['gross_total'] ?? 0) . '</td></tr>'
            . '<tr><td>steuerpflichtig / SV-Brutto</td><td class="num">'
            . $money($amounts['gross_taxable'] ?? 0) . ' / ' . $money($amounts['gross_sv'] ?? 0) . '</td></tr>'
            . '<tr><td>Lohnsteuer</td><td class="num">' . $money($amounts['lst'] ?? 0) . '</td></tr>'
            . '<tr><td>Solidaritätszuschlag</td><td class="num">' . $money($amounts['solz'] ?? 0) . '</td></tr>'
            . '<tr><td>Kirchensteuer</td><td class="num">' . $money($amounts['kst'] ?? 0) . '</td></tr>'
            . '<tr><td>KV/RV/AV/PV AN</td><td class="num">'
            . $money(($amounts['kv_an'] ?? 0) + ($amounts['rv_an'] ?? 0) + ($amounts['av_an'] ?? 0) + ($amounts['pv_an'] ?? 0))
            . '</td></tr>'
            . '<tr><td>Nettolohnabzüge</td><td class="num">' . $money($amounts['net_deductions'] ?? 0) . '</td></tr>'
            . '<tr class="totals"><td>Auszahlungsnetto</td><td class="num">' . $money($amounts['net_pay'] ?? 0) . '</td></tr>'
            . '</table>'

            . '<h2>Arbeitgeber-Anteile (Ausweis)</h2>'
            . '<table>'
            . '<tr><td>KV/RV/AV/PV AG</td><td class="num">'
            . $money(($amounts['kv_ag'] ?? 0) + ($amounts['rv_ag'] ?? 0) + ($amounts['av_ag'] ?? 0) + ($amounts['pv_ag'] ?? 0))
            . '</td></tr>'
            . '<tr><td>Umlagen U1/U2/U3</td><td class="num">'
            . $money($amounts['u1'] ?? 0) . ' / ' . $money($amounts['u2'] ?? 0) . ' / ' . $money($amounts['u3'] ?? 0)
            . '</td></tr>'
            . '<tr><td>Pauschalen (flat)</td><td class="num">'
            . $money($amounts['flat_lst'] ?? 0) . ' / ' . $money($amounts['flat_other'] ?? 0)
            . '</td></tr>'
            . '<tr class="totals"><td>AG-Kosten (intern)</td><td class="num">' . $money($amounts['employer_cost'] ?? 0) . '</td></tr>'
            . '</table>'

            . $warnHtml
            . '<p class="small">Dies ist eine Abrechnung aus dem DG-CRM. Keine ELStAM-/Behördenübermittlung. '
            . 'Fremde Lohnbüro-PDFs können weiterhin unter Mitarbeiterdokumente liegen.</p>'
            . '</body></html>';
    }

    public static function filename(int $contactId, int $year, int $month, int $runId, bool $draft): string
    {
        return basename(PayrollDocumentStorage::relativePath($year, $month, $contactId, $runId, $draft));
    }
}
