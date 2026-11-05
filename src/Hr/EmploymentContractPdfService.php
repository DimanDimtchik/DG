<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Erzeugt Arbeitsvertrags-PDFs über Dompdf + Briefpapier.
 */
final class EmploymentContractPdfService
{
    /**
     * @param array<string, string> $placeholders
     * @param array<string, mixed> $opts
     */
    public static function renderPdf(array $placeholders, array $opts): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'PDF-Bibliothek (Dompdf) nicht geladen. Bitte vendor/ auf dem Server deployen.'
            );
        }

        $bodyHtml = EmploymentContractClauses::bodyHtml($placeholders, $opts);
        $typeLabel = (string) ($placeholders['contract_type_label'] ?? 'Arbeitsvertrag');
        $employee = (string) ($placeholders['employee_name'] ?? '');
        $subject = 'Arbeitsvertrag — ' . $typeLabel . ($employee !== '' ? ' — ' . $employee : '');

        $recipientLines = [(string) ($placeholders['employee_name'] ?? '')];
        foreach (EmploymentContractContext::addressLines((string) ($placeholders['employee_address'] ?? '')) as $line) {
            $recipientLines[] = $line;
        }
        $doc = [
            'recipient_lines' => array_values(array_filter(
                $recipientLines,
                static fn(string $l): bool => trim($l) !== ''
            )),
            'info' => [
                'our_ref' => 'AV-' . date('Ymd'),
                'our_handler' => '',
                'date' => (string) ($placeholders['today'] ?? date('d.m.Y')),
            ],
            'subject' => $subject,
            'greeting' => '',
            'body_html' => $bodyHtml,
            'paragraphs' => [],
            'closing' => '',
            'signature' => (string) ($placeholders['employer_name'] ?? ''),
        ];

        $letterCfg = LetterheadSettings::config();
        $html = LetterheadRenderer::renderFullHtml(
            $doc,
            $letterCfg,
            ['show_guides' => false, 'title' => $subject, 'for_pdf' => true]
        );
        $html = self::absolutizeResourceUrls($html);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        LetterheadRenderer::attachPdfPageDecorations($dompdf, $letterCfg);
        $dompdf->render();
        $pdf = $dompdf->output();
        if (!is_string($pdf) || $pdf === '') {
            throw new RuntimeException('PDF-Erzeugung fehlgeschlagen.');
        }

        return $pdf;
    }

    private static function absolutizeResourceUrls(string $html): string
    {
        if (!class_exists('EmailLayoutSettings')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/\s(src|href)=(["\'])([^"\']+)\2/i',
            static function (array $m): string {
                $url = trim($m[3]);
                if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, '#')) {
                    return $m[0];
                }
                if (preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
                    return $m[0];
                }

                return ' ' . $m[1] . '=' . $m[2] . EmailLayoutSettings::absoluteUrl($url) . $m[2];
            },
            $html
        );
    }
}
