<?php
/** @var array<string, mixed> $websiteForm */
/** @var list<array<string, mixed>> $websiteFormSubmissions */
/** @var array<string, mixed>|null $websiteFormSubmission */
/** @var bool $canEdit */
/** @var array{type: string, message: string}|null $flash */
$formRow = $websiteForm ?? null;
$subs = $websiteFormSubmissions ?? [];
$detail = $websiteFormSubmission ?? null;
$formId = (int) ($formRow['id'] ?? 0);
?>
<div class="dg-wrap">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=website-formulare',
        'label' => 'Zurück zu den Formularen',
    ]);
  ?>
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Eingänge: <?= View::escape((string) ($formRow['title'] ?? 'Formular')) ?></h1>
      <p class="dg-lead"><?= count($subs) ?> Einträge</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=website-formular-form&amp;action=edit&amp;id=<?= $formId ?>">Formular bearbeiten</a>
    </div>
  </header>

  <?php if ($detail !== null) : ?>
    <section class="dg-panel" style="margin-bottom:20px;">
      <h2>Einsendung #<?= (int) $detail['id'] ?></h2>
      <p class="dg-muted"><?= View::escape(date('d.m.Y H:i', strtotime((string) ($detail['created_at'] ?? '')) ?: 'now')) ?>
        · IP <?= View::escape((string) ($detail['ip'] ?? '')) ?></p>
      <dl class="dg-media-meta-dl">
        <?php foreach ((array) ($detail['payload'] ?? []) as $entry) : ?>
          <?php if (!is_array($entry)) continue; ?>
          <div>
            <dt><?= View::escape((string) ($entry['label'] ?? '')) ?></dt>
            <dd><?php
              $v = $entry['value'] ?? '';
              if (is_array($v)) {
                  echo View::escape(implode(', ', array_map('strval', $v)));
              } else {
                  echo nl2br(View::escape((string) $v));
              }
            ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
      <?php if (!empty($detail['files'])) : ?>
        <h3>Dateien</h3>
        <ul>
          <?php foreach ((array) $detail['files'] as $file) : ?>
            <?php if (!is_array($file)) continue; ?>
            <li>
              <a href="/app?page=website-formular-inbox&amp;action=download&amp;id=<?= $formId ?>&amp;submission=<?= (int) $detail['id'] ?>&amp;file=<?= rawurlencode((string) ($file['stored_name'] ?? '')) ?>">
                <?= View::escape((string) ($file['original_name'] ?? $file['stored_name'] ?? 'Datei')) ?>
              </a>
              (<?= View::escape(number_format(((int) ($file['size'] ?? 0)) / 1024, 1, ',', '.') . ' KB') ?>)
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($canEdit) : ?>
        <form method="post" action="/app?page=website-formular-inbox&amp;id=<?= $formId ?>" style="margin-top:12px;" onsubmit="return confirm('Einsendung löschen?');">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="website_form_submission_delete" value="1">
          <input type="hidden" name="submission_id" value="<?= (int) $detail['id'] ?>">
          <button type="submit" class="dg-button dg-button--danger">Löschen</button>
          <a class="dg-button" href="/app?page=website-formular-inbox&amp;id=<?= $formId ?>">Zur Liste</a>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th></th>
          <th>Datum</th>
          <th>Vorschau</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($subs === []) : ?>
          <tr><td colspan="4" class="dg-table__empty">Noch keine Eingänge.</td></tr>
        <?php else : ?>
          <?php foreach ($subs as $sub) : ?>
            <?php
              $preview = '';
              foreach ((array) ($sub['payload'] ?? []) as $entry) {
                  if (!is_array($entry)) continue;
                  $val = $entry['value'] ?? '';
                  if (is_array($val)) $val = implode(', ', $val);
                  $val = trim((string) $val);
                  if ($val !== '') {
                      $preview = mb_substr($val, 0, 80);
                      break;
                  }
              }
              $created = (string) ($sub['created_at'] ?? '');
            ?>
            <tr class="<?= empty($sub['is_read']) ? 'is-unread' : '' ?>">
              <td><?= empty($sub['is_read']) ? '<span class="dg-badge dg-badge--ok">Neu</span>' : '' ?></td>
              <td><?= View::escape($created !== '' ? date('d.m.Y H:i', strtotime($created) ?: time()) : '—') ?></td>
              <td><?= View::escape($preview !== '' ? $preview : '—') ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=website-formular-inbox&amp;id=<?= $formId ?>&amp;submission=<?= (int) ($sub['id'] ?? 0) ?>">Öffnen</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
