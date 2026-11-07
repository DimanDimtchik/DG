<?php

declare(strict_types=1);

/**
 * K7e — Bon-Druck/HTML-Stub (KassenSichV-Pflichtfelder + QR-Platz).
 * TSE-Signaturdaten kommen aus dem Bon (nach K7d); fehlen sie → klarer Platzhalter.
 */
final class CashReceiptPrintService
{
    /**
     * @param array<string, mixed> $receipt
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $payments
     * @param array<string, mixed>|null $register
     */
    public static function renderHtml(
        array $receipt,
        array $lines,
        array $payments,
        ?array $register = null
    ): string {
        $company = class_exists('CompanySettings') ? CompanySettings::config() : [];
        $firm = trim((string) ($company['name'] ?? ''));
        if ($firm === '') {
            $firm = 'Öffentliche Firmendaten noch nicht gesetzt.';
        }
        $street = trim((string) ($company['street'] ?? ''));
        $plzCity = trim(
            trim((string) ($company['postal'] ?? '')) . ' ' . trim((string) ($company['city'] ?? ''))
        );
        $tax = trim((string) ($company['tax_number'] ?? ''));
        $vatId = trim((string) ($company['vat_id'] ?? ''));

        $display = trim((string) ($receipt['receipt_number_display'] ?? ''));
        if ($display === '') {
            $display = (string) (int) ($receipt['receipt_number'] ?? 0);
        }
        $status = (string) ($receipt['status'] ?? '');
        $type = (string) ($receipt['receipt_type'] ?? 'sale');
        $started = (string) ($receipt['started_at'] ?? '');
        $finished = (string) ($receipt['finished_at'] ?? '');
        $when = $finished !== '' ? $finished : $started;

        $regLabel = '';
        if (is_array($register)) {
            $regLabel = trim((string) ($register['code'] ?? '') . ' — ' . (string) ($register['name'] ?? ''));
        }

        $gross = round((float) ($receipt['total_gross'] ?? 0), 2);
        $net = round((float) ($receipt['total_net'] ?? 0), 2);
        $vatRows = self::vatRows($receipt);
        $methodLabels = class_exists('CashPaymentRepository')
            ? CashPaymentRepository::methodLabels()
            : [];

        $tse = self::tsePrintBlock($receipt);

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (float $n): string => number_format($n, 2, ',', '.') . ' €';

        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">';
        $html .= '<title>Bon ' . $esc($display) . '</title>';
        $html .= '<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#111;margin:24px}
h1{font-size:14pt;margin:0 0 8px}
.meta,.firm{margin:0 0 12px;line-height:1.35}
table{width:100%;border-collapse:collapse;margin:12px 0}
th,td{border-bottom:1px solid #ccc;padding:4px 6px;text-align:left;vertical-align:top}
th.num,td.num{text-align:right;white-space:nowrap}
.totals{margin-top:8px}
.tse{margin-top:20px;padding:10px;border:1px dashed #666}
.tse-placeholder{min-height:90px;display:flex;align-items:center;justify-content:center;
  border:1px solid #999;background:#f5f5f5;color:#444;font-size:10pt;text-align:center}
.muted{color:#555;font-size:9pt}
.qr svg{max-width:140px;height:auto}
</style></head><body>';

        $html .= '<div class="firm"><strong>' . $esc($firm) . '</strong><br>';
        if ($street !== '') {
            $html .= $esc($street) . '<br>';
        }
        if ($plzCity !== '') {
            $html .= $esc($plzCity) . '<br>';
        }
        if ($tax !== '') {
            $html .= 'Steuernummer: ' . $esc($tax) . '<br>';
        }
        if ($vatId !== '') {
            $html .= 'USt-IdNr.: ' . $esc($vatId);
        }
        $html .= '</div>';

        $html .= '<h1>Kassenbeleg ' . $esc($display) . '</h1>';
        $html .= '<div class="meta">';
        $html .= 'Datum/Zeit: ' . $esc($when !== '' ? $when : '—') . '<br>';
        $html .= 'Bonnummer: ' . $esc($display !== '' ? $display : '—') . '<br>';
        $html .= 'Typ: ' . $esc($type) . ' · Status: ' . $esc($status) . '<br>';
        if ($regLabel !== '' && $regLabel !== ' — ') {
            $html .= 'Kasse: ' . $esc($regLabel) . '<br>';
        }
        $html .= '</div>';

        $html .= '<table><thead><tr>';
        $html .= '<th>#</th><th>Bezeichnung</th><th class="num">Menge</th>';
        $html .= '<th class="num">Einzel</th><th class="num">Summe</th><th class="num">USt %</th>';
        $html .= '</tr></thead><tbody>';
        if ($lines === []) {
            $html .= '<tr><td colspan="6">Keine Positionen.</td></tr>';
        } else {
            foreach ($lines as $line) {
                $qty = (float) ($line['quantity'] ?? 0);
                $qtyLabel = rtrim(rtrim(number_format($qty, 3, ',', ''), '0'), ',');
                $unit = trim((string) ($line['unit'] ?? ''));
                $html .= '<tr>';
                $html .= '<td>' . $esc((string) (int) ($line['line_no'] ?? 0)) . '</td>';
                $html .= '<td>' . $esc((string) ($line['title'] ?? ''));
                $art = trim((string) ($line['article_number'] ?? ''));
                if ($art !== '') {
                    $html .= ' <span class="muted">(' . $esc($art) . ')</span>';
                }
                $html .= '</td>';
                $html .= '<td class="num">' . $esc($qtyLabel . ($unit !== '' ? ' ' . $unit : '')) . '</td>';
                $html .= '<td class="num">' . $esc($money((float) ($line['unit_price_gross'] ?? 0))) . '</td>';
                $html .= '<td class="num">' . $esc($money((float) ($line['gross_amount'] ?? 0))) . '</td>';
                $html .= '<td class="num">' . $esc((string) (int) ($line['tax_rate'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        $html .= '<div class="totals">';
        $html .= '<div>Netto: <strong>' . $esc($money($net)) . '</strong></div>';
        foreach ($vatRows as $vr) {
            $html .= '<div>USt ' . $esc((string) (int) $vr['rate']) . '%: '
                . $esc($money((float) $vr['tax'])) . '</div>';
        }
        $html .= '<div>Brutto: <strong>' . $esc($money($gross)) . '</strong></div>';
        $html .= '</div>';

        $html .= '<h2 style="font-size:12pt;margin:16px 0 6px">Zahlungen</h2>';
        $html .= '<table><thead><tr><th>Art</th><th class="num">Betrag</th><th>Referenz</th></tr></thead><tbody>';
        if ($payments === []) {
            $html .= '<tr><td colspan="3">Keine Zahlungen.</td></tr>';
        } else {
            foreach ($payments as $pay) {
                $m = (string) ($pay['payment_method'] ?? '');
                $label = $methodLabels[$m] ?? $m;
                $ref = trim((string) ($pay['external_ref'] ?? ''));
                $html .= '<tr>';
                $html .= '<td>' . $esc($label) . '</td>';
                $html .= '<td class="num">' . $esc($money((float) ($pay['amount'] ?? 0))) . '</td>';
                $html .= '<td>' . $esc($ref !== '' ? $ref : '—') . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        $html .= '<div class="tse">';
        $html .= '<strong>TSE / KassenSichV</strong>';
        if ($tse !== null) {
            $html .= '<div class="qr" style="margin:8px 0">' . $tse['qr_svg'] . '</div>';
            $html .= '<div class="muted">';
            if ($tse['tse_serial'] !== '') {
                $html .= 'TSE-Seriennr.: ' . $esc($tse['tse_serial']) . '<br>';
            }
            if ($tse['signature_counter'] !== '') {
                $html .= 'Signaturzähler: ' . $esc($tse['signature_counter']) . '<br>';
            }
            if ($tse['transaction_number'] !== '') {
                $html .= 'Transaktion: ' . $esc($tse['transaction_number']) . '<br>';
            }
            if ($tse['algorithm'] !== '') {
                $html .= 'Algorithmus: ' . $esc($tse['algorithm']) . '<br>';
            }
            if ($tse['time_label'] !== '') {
                $html .= 'Signaturzeit: ' . $esc($tse['time_label']) . '<br>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="tse-placeholder">Platz für TSE-QR-Code<br>'
                . '<span class="muted">Signatur folgt nach TSE-Abschluss; hier erscheinen QR und Prüfdaten.</span>'
                . '</div>';
            if (!empty($receipt['tse_outage'])) {
                $html .= '<p class="muted">Hinweis: Bon mit TSE-Ausfall gekennzeichnet.</p>';
            }
        }
        $html .= '</div>';

        $html .= '<p class="muted" style="margin-top:20px">Kassenbeleg — bitte aufbewahren.</p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * @param array<string, mixed> $receipt
     * @return list<array{rate: int, gross: float, net: float, tax: float}>
     */
    public static function vatRows(array $receipt): array
    {
        $raw = $receipt['vat_summary_json'] ?? null;
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'rate' => (int) ($row['rate'] ?? 0),
                'gross' => round((float) ($row['gross'] ?? 0), 2),
                'net' => round((float) ($row['net'] ?? 0), 2),
                'tax' => round((float) ($row['tax'] ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * TSE-Druckblock aus Bon-Feldern (nach K7d gefüllt).
     *
     * @param array<string, mixed> $receipt
     * @return array{
     *   qr_svg: string,
     *   qr_payload: string,
     *   tse_serial: string,
     *   signature_counter: string,
     *   transaction_number: string,
     *   algorithm: string,
     *   time_label: string
     * }|null
     */
    public static function tsePrintBlock(array $receipt): ?array
    {
        $qr = trim((string) ($receipt['qr_code_data'] ?? ''));
        if ($qr === '' || !class_exists('QrCodeSvg')) {
            return null;
        }
        $start = self::formatUnix((int) ($receipt['time_start'] ?? 0));
        $end = self::formatUnix((int) ($receipt['time_end'] ?? 0));
        $timeLabel = $start;
        if ($end !== '' && $end !== $start) {
            $timeLabel = $start . ' – ' . $end;
        }
        $txNo = $receipt['transaction_number'] ?? null;

        return [
            'qr_svg' => QrCodeSvg::render($qr),
            'qr_payload' => $qr,
            'tse_serial' => trim((string) ($receipt['tse_serial'] ?? '')),
            'signature_counter' => trim((string) ($receipt['signature_counter'] ?? '')),
            'transaction_number' => $txNo !== null && $txNo !== '' ? (string) $txNo : '',
            'algorithm' => trim((string) ($receipt['signature_algorithm'] ?? '')),
            'time_label' => $timeLabel,
        ];
    }

    /** Dateiname ohne Pfad. */
    public static function filename(array $receipt, string $ext = 'pdf'): string
    {
        $display = trim((string) ($receipt['receipt_number_display'] ?? ''));
        if ($display === '') {
            $display = 'bon-' . (int) ($receipt['id'] ?? 0);
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $display) ?? 'bon';
        $safe = trim($safe, '-') ?: 'bon';
        $ext = ltrim(strtolower($ext), '.');

        return 'Kassenbeleg-' . $safe . '.' . $ext;
    }

    private static function formatUnix(int $ts): string
    {
        if ($ts <= 0) {
            return '';
        }

        return date('d.m.Y H:i:s', $ts);
    }
}
