<?php
declare(strict_types=1);

/**
 * Build printable HTML (+ optional PDF via Chrome) from domains-report.json
 * Usage: php bin/build-domains-pdf.php
 */
$root = dirname(__DIR__);
$jsonPath = $root . '/docs/domains-report.json';
$htmlPath = $root . '/docs/Server-Domains-Ueberblick.html';
$pdfPath = $root . '/docs/Server-Domains-Ueberblick.pdf';

$report = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($report)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$e = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$domains = [];
$infra = [];
foreach ($report['entries'] as $row) {
    if (($row['kind'] ?? 'domain') === 'infra') {
        $infra[] = $row;
    } else {
        $domains[] = $row;
    }
}

ob_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Server &amp; Domains – ganz soft / DG</title>
<style>
  @page { size: A4; margin: 14mm 12mm; }
  * { box-sizing: border-box; }
  body {
    font-family: "Segoe UI", Arial, Helvetica, sans-serif;
    font-size: 10.5pt;
    color: #1a1816;
    line-height: 1.35;
    margin: 0;
  }
  h1 { font-size: 18pt; margin: 0 0 4px; }
  h2 { font-size: 12pt; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
  .meta { color: #555; font-size: 9pt; margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  th, td { border: 1px solid #bbb; padding: 5px 6px; vertical-align: top; text-align: left; }
  th { background: #eee; font-size: 9pt; }
  td { font-size: 9.5pt; }
  .yes { color: #1a6b2e; font-weight: 600; }
  .no { color: #777; }
  .note { font-size: 8.5pt; color: #555; margin-top: 12px; }
  ul.users { margin: 0; padding-left: 1.1em; }
  .page-break { page-break-before: always; }
  @media print {
    a { color: inherit; text-decoration: none; }
  }
</style>
</head>
<body>
  <h1>Server &amp; Domains</h1>
  <div class="meta">
    Account: <?= $e((string) $report['account']) ?><br>
    SSH: <?= $e((string) $report['ssh']) ?><br>
    Document-Root: <?= $e((string) $report['document_root']) ?><br>
    Erstellt: <?= $e((string) ($report['generated_at_de'] ?? $report['generated_at'])) ?><br>
    Regel: Bei mehr als 5 login-fähigen CRM-Benutzern nur die Anzahl.
  </div>

  <h2>1. Domains &amp; Subdomains</h2>
  <table>
    <thead>
      <tr>
        <th style="width:18%">Domain</th>
        <th style="width:12%">CRM?</th>
        <th style="width:14%">System</th>
        <th style="width:10%">Version</th>
        <th style="width:22%">Benutzer (Login)</th>
        <th>Zweck</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($domains as $row) :
        $users = $row['users'] ?? [];
        $crmYes = !empty($row['crm']);
        ?>
      <tr>
        <td>
          <strong><?= $e((string) $row['domain']) ?></strong>
          <?php if (($row['folder'] ?? '') === '__ROOT__') : ?>
            <br><span style="font-size:8pt;color:#666">Live-Root</span>
          <?php elseif (($row['folder'] ?? '') === 'ganz-soft.de') : ?>
            <br><span style="font-size:8pt;color:#666">Ordner-Spiegel</span>
          <?php endif; ?>
          <br><span style="font-size:8pt"><?= $e((string) $row['url']) ?></span>
        </td>
        <td class="<?= $crmYes ? 'yes' : 'no' ?>"><?= $crmYes ? 'Ja' : 'Nein' ?></td>
        <td><?= $e((string) $row['stack']) ?></td>
        <td><?= $e((string) (($row['version'] ?? '') !== '' ? $row['version'] : '–')) ?></td>
        <td>
          <?php if (($users['mode'] ?? '') === 'count_only') : ?>
            <strong><?= (int) $users['count'] ?></strong> Benutzer
          <?php elseif (($users['mode'] ?? '') === 'list' && !empty($users['users'])) : ?>
            <ul class="users">
              <?php foreach ($users['users'] as $u) : ?>
                <li><?= $e((string) $u['username']) ?>
                  <?php if (($u['role'] ?? '') !== '') : ?>
                    <span style="color:#666">(<?= $e((string) $u['role']) ?>)</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            –
          <?php endif; ?>
        </td>
        <td><?= $e((string) $row['purpose']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>2. Infrastruktur-Ordner (kein öffentliches Domain-Ziel)</h2>
  <table>
    <thead>
      <tr>
        <th style="width:22%">Ordner</th>
        <th style="width:18%">Art</th>
        <th>Zweck</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($infra as $row) : ?>
      <tr>
        <td><?= $e((string) $row['domain']) ?></td>
        <td><?= $e((string) $row['stack']) ?></td>
        <td><?= $e((string) $row['purpose']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <p class="note">
    Quelle: Dateisystem unter <?= $e((string) $report['document_root']) ?> am <?= $e((string) ($report['generated_at_de'] ?? '')) ?>.
    CRM-Benutzer = Accounts mit CRM-Login (RoleResolver::canAccessCrm). Keine Passwörter.
    Zwecke bei Bedarf manuell in der Inventar-Config nachpflegen.
  </p>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents($htmlPath, $html);
echo "Wrote $htmlPath\n";

$chromeCandidates = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
];
$browser = null;
foreach ($chromeCandidates as $c) {
    if (is_file($c)) {
        $browser = $c;
        break;
    }
}

if ($browser === null) {
    fwrite(STDERR, "No Chrome/Edge found – open HTML and print to PDF manually.\n");
    exit(0);
}

$htmlUri = 'file:///' . str_replace('\\', '/', $htmlPath);
$cmd = escapeshellarg($browser)
    . ' --headless=new --disable-gpu --no-pdf-header-footer'
    . ' --print-to-pdf=' . escapeshellarg($pdfPath)
    . ' ' . escapeshellarg($htmlUri);
passthru($cmd, $code);
if ($code === 0 && is_file($pdfPath)) {
    echo "Wrote $pdfPath (" . filesize($pdfPath) . " bytes)\n";
} else {
    fwrite(STDERR, "PDF generation failed (exit $code). Use HTML print.\n");
    exit(1);
}
