<?php
/**
 * Sichtbare TSE-Ausfall-Warnung (K14b).
 *
 * @var list<array<string, mixed>>|null $cashTseOutages
 * @var bool|null $cashTseOutageActive  wenn gesetzt und outages leer: Einzel-Flag
 * @var string|null $cashTseOutageMessage
 */
$outages = $cashTseOutages ?? [];
$active = !empty($cashTseOutageActive) || $outages !== [];
if (!$active) {
    return;
}
$singleMsg = trim((string) ($cashTseOutageMessage ?? ''));
?>
<div class="dg-flash dg-flash--error" role="alert">
  <strong>TSE-Ausfall</strong>
  — Die technische Sicherheitseinrichtung ist für mindestens eine Kasse nicht erreichbar.
  Verkäufe ohne gültige Signatur sind nicht als normal abgeschlossen zu behandeln.
  <?php if ($singleMsg !== '') : ?>
    <br><?= View::escape($singleMsg) ?>
  <?php elseif ($outages !== []) : ?>
    <div class="dg-stack">
      <?php foreach ($outages as $row) : ?>
        <?php
          $rid = (int) ($row['register_id'] ?? 0);
          $label = trim((string) ($row['register_code'] ?? ''));
          if (trim((string) ($row['register_name'] ?? '')) !== '') {
              $label .= ($label !== '' ? ' — ' : '') . trim((string) $row['register_name']);
          }
          $err = trim((string) ($row['last_error'] ?? ''));
          $since = trim((string) ($row['outage_since'] ?? ''));
        ?>
        <p>
          <?php if ($rid > 0) : ?>
            <a class="dg-button dg-button--small" href="/app?page=registerkasse-tse-form&amp;register_id=<?= $rid ?>">
              <?= View::escape($label !== '' ? $label : 'Kasse #' . $rid) ?>
            </a>
          <?php else : ?>
            <?= View::escape($label !== '' ? $label : 'Kasse') ?>
          <?php endif; ?>
          <?php if ($since !== '') : ?>
            · seit <?= View::escape($since) ?>
          <?php endif; ?>
          <?php if ($err !== '') : ?>
            · <?= View::escape($err) ?>
          <?php endif; ?>
        </p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <a class="dg-button dg-button--small" href="/app?page=registerkasse-tse">Zur TSE-Zuordnung</a>
</div>
