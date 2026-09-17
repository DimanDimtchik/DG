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
  body.vd-document { background: #d8dce4; }
  .vd-a4 {
    width: 210mm;
    min-height: 297mm;
    margin: 8mm auto;
    padding: 14mm;
    background-color: #fff;
    background-image: repeating-linear-gradient(
      to bottom,
      transparent 0,
      transparent calc(297mm - 1px),
      #c5cad3 calc(297mm - 1px),
      #c5cad3 297mm
    );
    background-size: 100% 297mm;
    background-position: 0 0;
    box-shadow: 0 2px 10px rgba(28, 35, 48, 0.18);
    box-sizing: border-box;
    position: relative;
  }
  .vd-a4__page-mark {
    position: absolute;
    right: 14mm;
    font-size: 8pt;
    color: #8a93a3;
    pointer-events: none;
  }
  .vd-letterhead { display: table; width: 100%; margin-bottom: 8mm; }
  .vd-letterhead__col { display: table-cell; vertical-align: top; width: 50%; }
  .vd-letterhead__col--right { text-align: right; }
  .vd-doc-title { font-size: 18pt; font-weight: 700; margin: 0 0 2mm; color: #1c2330; }
  .vd-doc-meta { margin: 0 0 6mm; color: #5c6678; font-size: 9pt; }
  .vd-notice { background: #f4f6f9; border-left: 3px solid #b8942f; padding: 3mm 4mm; margin: 4mm 0 6mm; font-size: 9pt; }
  .vd-notice--warn { background: #fff8e6; border-left-color: #c9a227; color: #8a6410; }
  .vd-notice__hint { font-size: 8pt; color: #8a93a3; }
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
  .vd-mandatory { margin-top: 6mm; font-size: 8pt; color: #5c6678; border-top: 1px solid #dfe3ea; padding-top: 2mm; }
  .vd-totals { width: 55%; margin-left: auto; margin-top: 4mm; }
  .vd-totals td { border-bottom: 1px solid #dfe3ea; padding: 1.5mm 1.5mm; }
  .vd-totals .total td { border-top: 2px solid #b8942f; border-bottom: none; font-weight: 700; font-size: 11pt; }
  .vd-deposit { margin-top: 4mm; padding: 2.5mm 3mm; background: #fafbfc; border: 1px solid #e5e8ee; font-size: 9pt; line-height: 1.4; }
  .vd-kleinunternehmer { margin-top: 4mm; padding: 2mm 3mm; background: #f8f9fb; border-left: 3px solid #b8942f; font-size: 9pt; line-height: 1.4; }
  .vd-provenance { margin-top: 5mm; font-size: 8.5pt; color: #5c6678; border-top: 1px solid #dfe3ea; padding-top: 2mm; }
  .vd-provenance a { color: #5c6678; }
  .vd-signatures { margin-top: 10mm; font-size: 9.5pt; page-break-inside: avoid; }
  .vd-signatures__place { margin: 0 0 6mm; }
  .vd-signatures__cols { display: table; width: 100%; }
  .vd-signatures__col { display: table-cell; width: 48%; vertical-align: top; padding-right: 4%; }
  .vd-signatures__col:last-child { padding-right: 0; }
  .vd-signatures__name { margin: 1mm 0 8mm; font-size: 9pt; color: #5c6678; }
  .vd-signatures__line { border-bottom: 1px solid #1c2330; height: 14mm; margin-bottom: 1.5mm; }
  .vd-signatures__caption { font-size: 8pt; color: #5c6678; }
  .vd-page-footer {
    margin-top: 8mm;
    padding-top: 2mm;
    border-top: 1px solid #dfe3ea;
    font-size: 8pt;
    color: #5c6678;
    display: flex;
    justify-content: space-between;
  }
  @media print {
    body.vd-document { background: #fff; }
    .vd-a4 {
      width: auto;
      min-height: 0;
      margin: 0;
      padding: 0;
      box-shadow: none;
      background: none;
    }
    .vd-a4__page-mark { display: none; }
  }';
        }

        $printButton = $showPrintButton
            ? '<div class="no-print" style="margin:8mm auto;max-width:210mm;"><button onclick="window.print()">Drucken / PDF speichern</button></div>'
            : '';

        if ($documentStyles) {
            $pageMarkScript = <<<'JS'
<script>
(function () {
  function mmToPx(mm) {
    var probe = document.createElement('div');
    probe.style.cssText = 'position:absolute;left:-9999px;width:100mm;height:1px;';
    document.body.appendChild(probe);
    var px = probe.offsetWidth / 100;
    document.body.removeChild(probe);
    return mm * px;
  }
  function markPages() {
    var sheet = document.querySelector('.vd-a4');
    if (!sheet) return;
    sheet.querySelectorAll('.vd-a4__page-mark').forEach(function (n) { n.remove(); });
    var pageH = mmToPx(297);
    var pages = Math.max(1, Math.ceil(sheet.scrollHeight / pageH));
    for (var i = 1; i <= pages; i++) {
      var mark = document.createElement('div');
      mark.className = 'vd-a4__page-mark no-print';
      mark.style.top = ((i - 1) * pageH + 6) + 'px';
      mark.textContent = 'Seite ' + i + ' / ' + pages;
      sheet.appendChild(mark);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markPages);
  } else {
    markPages();
  }
  window.addEventListener('resize', markPages);
})();
</script>
JS;

            return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{$escapedTitle}</title>
<style>
  @page { size: A4; margin: 14mm; }
  body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #1c2330; margin: 0; }
  h2 { font-size: 12pt; margin: 6mm 0 2mm; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
  th, td { border-bottom: 1px solid #dfe3ea; padding: 2mm 1.5mm; text-align: left; }
  th { font-size: 8.5pt; text-transform: uppercase; color: #5c6678; }
  .num { text-align: right; white-space: nowrap; }
  .total { font-weight: 700; border-top: 2px solid #b8942f; }
  @media print { .no-print { display: none !important; } }
  {$extraCss}
</style>
</head>
<body class="vd-document">
  {$printButton}
  <div class="vd-a4">
    {$body}
    <div class="vd-page-footer">
      <span>{$escapedCompany}</span>
      <span>{$escapedTitle}</span>
    </div>
  </div>
  {$pageMarkScript}
</body>
</html>
HTML;
        }

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
