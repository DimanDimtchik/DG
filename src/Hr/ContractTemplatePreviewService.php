<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Vorschau-PDF für Vertragsvorlagen — Demo-Platzhalter, keine echte Akte (V4).
 */
final class ContractTemplatePreviewService
{
    /**
     * @param array<string, mixed>|null $post Formular-POST oder null = nur DB
     */
    public static function renderPdf(int $templateId, ?array $post = null): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'PDF-Bibliothek (Dompdf) nicht geladen. Bitte vendor/ auf dem Server deployen.'
            );
        }
        $tpl = ContractTemplateRepository::findById($templateId);
        if ($tpl === null) {
            throw new RuntimeException('Vorlage nicht gefunden.');
        }
        $code = (string) ($tpl['code'] ?? '');
        $blocks = $post !== null
            ? ContractTemplateRepository::blocksOverlayFromPost($templateId, $post)
            : ContractTemplateRepository::blocksForTemplate($templateId);
        if ($blocks === []) {
            throw new RuntimeException('Keine Bausteine für die Vorschau.');
        }

        $placeholders = self::demoPlaceholders();
        $opts = self::demoOpts($code);
        $extra = ContractTemplateCatalog::isHrEventCode($code)
            ? self::demoWarningExtras($placeholders)
            : [];
        $missing = [];
        $bodyHtml = ContractTemplateRenderer::render($blocks, $placeholders, $opts, $extra, $missing);
        if ($missing !== []) {
            $bodyHtml = '<p><em>Hinweis Vorschau: unbekannte Platzhalter (leer gelassen): '
                . htmlspecialchars(implode(', ', $missing), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</em></p>' . "\n" . $bodyHtml;
        }
        if (trim(strip_tags($bodyHtml)) === '') {
            throw new RuntimeException('Vorschau leer — Bausteine prüfen.');
        }

        $title = (string) ($tpl['title'] ?? 'Vorlage');
        $subject = 'Vorschau — ' . $title;
        $isHr = ContractTemplateCatalog::isHrEventCode($code);
        $doc = [
            'recipient_lines' => array_values(array_filter([
                (string) $placeholders['employee_name'],
                ...EmploymentContractContext::addressLines((string) $placeholders['employee_address']),
            ], static fn(string $l): bool => trim($l) !== '')),
            'info' => [
                'our_ref' => 'VORSCHAU-' . date('Ymd'),
                'our_handler' => '',
                'date' => (string) $placeholders['today'],
            ],
            'subject' => $subject,
            'greeting' => $isHr
                ? ('Sehr geehrte/r ' . $placeholders['employee_name'] . ',')
                : '',
            'body_html' => $bodyHtml,
            'paragraphs' => [],
            'closing' => $isHr ? 'Mit freundlichen Grüßen' : '',
            'signature' => (string) $placeholders['employer_name'],
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
            throw new RuntimeException('Vorschau-PDF fehlgeschlagen.');
        }

        return $pdf;
    }

    /** @return array<string, string> */
    public static function demoPlaceholders(): array
    {
        return [
            'employer_name' => 'Muster Arbeitgeber GmbH',
            'employer_address' => "Musterweg 1\n12345 Musterstadt",
            'employer_email' => 'hr@example.test',
            'employer_phone' => '0123 456789',
            'employee_name' => 'Erika Mustermann',
            'employee_address' => "Beispielstr. 2\n12345 Musterstadt",
            'employee_birth_date' => '01.01.1990',
            'legal_guardian_name' => '',
            'legal_guardian_address' => '',
            'contract_start' => '01.11.2026',
            'contract_end' => '',
            'probation_period' => '3 Monate',
            'job_type' => 'Bürokauffrau',
            'employment_relationship' => 'Vollzeit',
            'work_location' => 'Musterstadt',
            'employer_site' => 'Zentrale',
            'working_hours' => '40 Stunden / Woche',
            'daily_work_minutes' => '480',
            'salary' => '3.000 EUR brutto / Monat',
            'vacation_days' => '30 Tage',
            'training_video_url' => 'https://example.test/video',
            'contract_type' => EmploymentContractContext::TYPE_FULLTIME,
            'contract_type_label' => 'Vollzeit',
            'min_wage' => '13,90 EUR/St.',
            'geringfuegig_month' => '603 EUR/Monat',
            'rates_from' => '01.01.2026',
            'today' => date('d.m.Y'),
            'food_hygiene_duty' => '0',
        ];
    }

    /** @return array<string, mixed> */
    public static function demoOpts(string $code): array
    {
        $meta = ContractTemplateCatalog::templates()[$code] ?? [];
        $type = (string) ($meta['contract_type'] ?? EmploymentContractContext::TYPE_FULLTIME);

        return [
            'contract_type' => $type,
            'is_minor' => false,
            'annex_training' => true,
            'annex_pension' => $type === EmploymentContractContext::TYPE_MINIJOB,
            'annex_ifsg' => false,
            'annex_video' => true,
        ];
    }

    /**
     * @param array<string, string> $p
     * @return array<string, string>
     */
    public static function demoWarningExtras(array $p): array
    {
        return HrPersonnelEventPdfService::warningExtraBag($p, [
            'facts_text' => "Demo-Sachverhalt (Vorschau):\nAm 01.11.2026 erschien die Arbeitnehmerin ohne Entschuldigung nicht zur Arbeit.",
            'expectation_text' => '',
            'threat_text' => '',
        ]);
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
