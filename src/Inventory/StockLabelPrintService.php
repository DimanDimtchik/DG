<?php
declare(strict_types=1);

/** Druckvorschau für Lager-Etiketten (Browser → Drucker / PDF) */
final class StockLabelPrintService
{
    /**
     * @param list<array{level: string, level_label: string, display_code: string, barcode: string, subtitle: string}> $labels
     */
    public static function render(array $labels, string $formatKey): string
    {
        $presets = StockLabelService::formatPresets();
        $formatKey = StockLabelService::sanitizeFormat($formatKey);
        $format = $presets[$formatKey];
        $layout = (string) ($format['layout'] ?? 'single');
        $labelW = (float) ($format['width_mm'] ?? 100);
        $labelH = (float) ($format['height_mm'] ?? 50);

        $company = CompanySettings::displayName();
        if ($company === '') {
            $company = 'DG CRM';
        }

        $title = 'Lager-Etiketten';
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedCompany = htmlspecialchars($company, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formatLabel = htmlspecialchars((string) ($format['label'] ?? $formatKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $jsBarcodeUrl = htmlspecialchars(Asset::url('/assets/js/vendor/JsBarcode.all.min.js'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $count = count($labels);

        $pageCss = '';
        $sheetClass = 'dg-label-sheet';
        if ($layout === 'a4-grid') {
            $pageW = (float) ($format['page_width_mm'] ?? 210);
            $pageH = (float) ($format['page_height_mm'] ?? 297);
            $cols = max(1, (int) ($format['cols'] ?? 3));
            $rows = max(1, (int) ($format['rows'] ?? 8));
            $margin = (float) ($format['margin_mm'] ?? 4);
            $gap = (float) ($format['gap_mm'] ?? 2);
            $pageCss = "@page { size: {$pageW}mm {$pageH}mm; margin: {$margin}mm; }";
            $sheetClass = 'dg-label-sheet dg-label-sheet--grid';
            $gridCss = ".dg-label-sheet--grid { display: grid; grid-template-columns: repeat({$cols}, {$labelW}mm); gap: {$gap}mm; justify-content: start; align-content: start; }";
        } else {
            $pageCss = "@page { size: {$labelW}mm {$labelH}mm; margin: 1.5mm; }";
            $gridCss = '.dg-label-sheet--single .dg-label { page-break-after: always; break-after: page; } .dg-label-sheet--single .dg-label:last-child { page-break-after: auto; break-after: auto; }';
            $sheetClass = 'dg-label-sheet dg-label-sheet--single';
        }

        $labelHtml = '';
        foreach ($labels as $index => $label) {
            $levelLabel = htmlspecialchars((string) ($label['level_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $displayCode = htmlspecialchars((string) ($label['display_code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $barcode = htmlspecialchars((string) ($label['barcode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $subtitle = htmlspecialchars((string) ($label['subtitle'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $subtitleHtml = $subtitle !== '' ? '<div class="dg-label__subtitle">' . $subtitle . '</div>' : '';

            $labelHtml .= <<<HTML
<article class="dg-label" style="width: {$labelW}mm; height: {$labelH}mm;">
  <div class="dg-label__kind">{$levelLabel}</div>
  <div class="dg-label__code">{$displayCode}</div>
  {$subtitleHtml}
  <svg class="dg-label__barcode" data-barcode="{$barcode}" aria-label="Strichcode {$barcode}"></svg>
  <div class="dg-label__barcode-text">{$barcode}</div>
</article>

HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{$escapedTitle}</title>
<style>
  {$pageCss}
  * { box-sizing: border-box; }
  body { font-family: "Segoe UI", Arial, sans-serif; color: #1c2330; margin: 0; background: #eef1f6; }
  .dg-label-toolbar { padding: 10mm; background: #fff; border-bottom: 1px solid #dfe3ea; }
  .dg-label-toolbar h1 { font-size: 16pt; margin: 0 0 4mm; }
  .dg-label-toolbar p { margin: 0 0 4mm; color: #5c6678; font-size: 10pt; }
  .dg-label-toolbar button { font-size: 11pt; padding: 8px 16px; cursor: pointer; }
  .dg-label-preview-wrap { padding: 8mm; }
  {$gridCss}
  .dg-label {
    background: #fff;
    border: 0.2mm solid #c5cad3;
    border-radius: 1mm;
    padding: 2mm 2.5mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
  }
  .dg-label__kind { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.04em; color: #5c6678; }
  .dg-label__code { font-size: 13pt; font-weight: 700; line-height: 1.15; word-break: break-all; margin: 1mm 0; }
  .dg-label__subtitle { font-size: 7.5pt; color: #5c6678; margin-bottom: 1mm; }
  .dg-label__barcode { width: 100%; height: auto; max-height: 14mm; }
  .dg-label__barcode-text { font-size: 7pt; text-align: center; letter-spacing: 0.06em; color: #1c2330; margin-top: 0.5mm; }
  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .dg-label-preview-wrap { padding: 0; }
    .dg-label { border-color: #999; }
  }
</style>
</head>
<body>
  <div class="dg-label-toolbar no-print">
    <h1>{$escapedTitle}</h1>
    <p>{$escapedCompany} · Format: {$formatLabel} · {$count} Etikett(en)</p>
    <p><strong>Hinweis:</strong> „Bogen A4“ = Papierformat 210×297&nbsp;mm mit vielen kleinen Etiketten darauf — nicht ein großes A4-Aufkleber pro Code. Die mm-Angabe gilt pro Einzeletikett. Feinabstimmung in der Druckersoftware.</p>
    <button type="button" onclick="window.print()">Drucken / PDF speichern</button>
  </div>
  <div class="dg-label-preview-wrap">
    <div class="{$sheetClass}">
      {$labelHtml}
    </div>
  </div>
  <script src="{$jsBarcodeUrl}"></script>
  <script>
  document.querySelectorAll('.dg-label__barcode').forEach(function (svg) {
    var value = svg.getAttribute('data-barcode') || '';
    if (!value || typeof JsBarcode === 'undefined') {
      return;
    }
    try {
      JsBarcode(svg, value, {
        format: 'CODE128',
        width: 1.4,
        height: 36,
        displayValue: false,
        margin: 0
      });
    } catch (e) {
      svg.outerHTML = '<div class="dg-label__barcode-text">Strichcode: ' + value + '</div>';
    }
  });
  </script>
</body>
</html>
HTML;
    }

    public static function send(string $filename, string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $html;
    }
}
