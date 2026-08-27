<?php
declare(strict_types=1);

/** Druckoptimierte HTML-Auswertungen (Browser → PDF speichern). */
final class AccountingPrintService
{
    /**
     * @param array<string, mixed> $data
     * @param array{for_email?: bool, hide_print_button?: bool, document_styles?: bool} $wrapOptions
     */
    public static function render(string $template, array $data, string $title, array $wrapOptions = []): string
    {
        $body = self::renderBody($template, $data);

        $company = CompanySettings::displayName();
        if ($company === '') {
            $company = 'DG CRM';
        }

        return self::wrapDocument($title, $company, $body, $wrapOptions);
    }

    /**
     * Nur Template-Inhalt ohne äußeres HTML-Dokument.
     *
     * @param array<string, mixed> $data
     */
    public static function renderBody(string $template, array $data): string
    {
        ob_start();
        $fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
        extract($data, EXTR_SKIP);
        include __DIR__ . '/../../views/print/' . $template . '.php';

        return (string) ob_get_clean();
    }

    public static function send(string $filename, string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $html;
    }

    /**
     * @param array{for_email?: bool, hide_print_button?: bool, document_styles?: bool} $options
     */
    private static function wrapDocument(string $title, string $company, string $body, array $options = []): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedCompany = htmlspecialchars($company, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date = date('d.m.Y H:i');
        $forEmail = !empty($options['for_email']);
        $showPrintButton = !$forEmail && empty($options['hide_print_button']);
        $documentStyles = !empty($options['document_styles']);

        $extraCss = '';
        if ($documentStyles) {
            $extraCss = '
  .vd-letterhead { display: table; width: 100%; margin-bottom: 8mm; }
  .vd-letterhead__col { display: table-cell; vertical-align: top; width: 50%; }
  .vd-letterhead__col--right { text-align: right; }
  .vd-doc-title { font-size: 18pt; font-weight: 700; margin: 0 0 2mm; color: #1c2330; }
  .vd-doc-meta { margin: 0 0 6mm; color: #5c6678; font-size: 9pt; }
  .vd-notice { background: #f4f6f9; border-left: 3px solid #b8942f; padding: 3mm 4mm; margin: 4mm 0 6mm; font-size: 9pt; }
  .vd-notice--warn { background: #fff8e6; border-left-color: #c9a227; color: #8a6410; }
  .vd-intro { margin: 4mm 0 5mm; font-size: 10pt; line-height: 1.45; white-space: pre-wrap; color: #1c2330; }
  .vd-document-footer { margin-top: 5mm; font-size: 9.5pt; line-height: 1.45; white-space: pre-wrap; color: #1c2330; }
  .vd-payment-terms { margin-top: 4mm; padding: 2.5mm 3mm; background: #fafbfc; border: 1px solid #e5e8ee; font-size: 9pt; line-height: 1.4; white-space: pre-wrap; color: #1c2330; }
  .vd-payment-terms strong { display: block; margin-bottom: 1mm; font-size: 9pt; }
  .vd-legal-clauses { margin-top: 4mm; }
  .vd-legal-clause { margin: 0 0 3mm; padding: 2mm 3mm; background: #f8f9fb; border-left: 3px solid #b8942f; font-size: 9pt; line-height: 1.4; color: #1c2330; }
  .vd-legal-clause:last-child { margin-bottom: 0; }
  .vd-footer { margin-top: 8mm; font-size: 8.5pt; color: #5c6678; border-top: 1px solid #dfe3ea; padding-top: 3mm; }
  .vd-bank { margin-top: 5mm; font-size: 9pt; }
  .vd-logo { margin-bottom: 4mm; max-width: 55mm; }
  .vd-logo__img { max-width: 100%; height: auto; max-height: 22mm; }
  .vd-logo--wide .vd-logo__img { max-height: 16mm; }
  .vd-logo--square .vd-logo__img { max-height: 22mm; }
  .vd-mandatory { margin-top: 6mm; font-size: 8pt; color: #5c6678; border-top: 1px solid #dfe3ea; padding-top: 2mm; }';
        }

        $printButton = $showPrintButton
            ? '<div class="no-print" style="margin-bottom:8mm;"><button onclick="window.print()">Drucken / PDF speichern</button></div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{$escapedTitle}</title>
<style>
  @page { size: A4; margin: 14mm; }
  body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #1c2330; margin: 0; }
  h1 { font-size: 16pt; margin: 0 0 4mm; }
  h2 { font-size: 12pt; margin: 6mm 0 2mm; }
  .meta { color: #5c6678; font-size: 9pt; margin-bottom: 6mm; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
  th, td { border-bottom: 1px solid #dfe3ea; padding: 2mm 1.5mm; text-align: left; }
  th { font-size: 8.5pt; text-transform: uppercase; color: #5c6678; }
  .num { text-align: right; white-space: nowrap; }
  .total { font-weight: 700; border-top: 2px solid #b8942f; }
  @media print { .no-print { display: none; } }
  {$extraCss}
</style>
</head>
<body>
  {$printButton}
  <h1>{$escapedTitle}</h1>
  <p class="meta">{$escapedCompany} · erstellt {$date}</p>
  {$body}
</body>
</html>
HTML;
    }
}
