<?php
declare(strict_types=1);

/** Druckoptimierte HTML-Auswertungen (Browser → PDF speichern). */
final class AccountingPrintService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data, string $title): string
    {
        $company = trim((string) (SettingsStore::get('company_name', '') ?? ''));
        if ($company === '') {
            $company = 'DG CRM';
        }

        ob_start();
        $fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
        extract($data, EXTR_SKIP);
        include __DIR__ . '/../../views/print/' . $template . '.php';
        $body = (string) ob_get_clean();

        return self::wrapDocument($title, $company, $body);
    }

    public static function send(string $filename, string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $html;
    }

    private static function wrapDocument(string $title, string $company, string $body): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedCompany = htmlspecialchars($company, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date = date('d.m.Y H:i');

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
  <div class="no-print" style="margin-bottom:8mm;">
    <button onclick="window.print()">Drucken / PDF speichern</button>
  </div>
  <h1>{$escapedTitle}</h1>
  <p class="meta">{$escapedCompany} · erstellt {$date}</p>
  {$body}
</body>
</html>
HTML;
    }
}
