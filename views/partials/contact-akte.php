<?php
/**
 * Kontakt-Akte: Mails + Dokumente (Beleg / Verfasser / HR).
 *
 * @var array{
 *   mails: list<array<string, mixed>>,
 *   documents: list<array<string, mixed>>,
 *   can_view_mails: bool,
 *   can_view_vouchers: bool,
 *   can_view_writer: bool,
 *   can_view_hr: bool
 * }|null $contactAkte
 * @var bool $inAccordion
 */
$akte = is_array($contactAkte ?? null) ? $contactAkte : null;
if ($akte === null) {
    return;
}
$mails = is_array($akte['mails'] ?? null) ? $akte['mails'] : [];
$documents = is_array($akte['documents'] ?? null) ? $akte['documents'] : [];
$canMails = !empty($akte['can_view_mails']);
$inAccordion = !empty($inAccordion);
?>
<details class="dg-form-accordion<?= $inAccordion ? '' : ' dg-mt-4' ?>">
  <summary>Mails</summary>
  <div class="dg-form-accordion__body">
    <?php if (!$canMails) : ?>
      <p class="dg-muted">Kein Zugriff auf Post — verknüpfte Mails werden hier nicht angezeigt.</p>
    <?php elseif ($mails === []) : ?>
      <p class="dg-muted">Keine Mails zu diesem Kontakt (Verknüpfung oder E-Mail-Treffer).</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Richtung</th>
              <th>Betreff</th>
              <th>Gegenstelle</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mails as $mail) : ?>
              <?php
                $dir = (string) ($mail['direction'] ?? '');
                $dirLabel = $dir === 'out' ? 'Ausgang' : 'Eingang';
                $when = (string) ($mail['activity_at'] ?? $mail['received_at'] ?? $mail['sent_at'] ?? '');
                $party = trim((string) ($mail['party_display_name'] ?? $mail['party_address'] ?? $mail['from_address'] ?? ''));
                $mailId = (int) ($mail['id'] ?? 0);
              ?>
              <tr>
                <td><?= View::escape($when !== '' ? $when : '—') ?></td>
                <td><?= View::escape($dirLabel) ?><?= !empty($mail['linked']) ? ' · verknüpft' : '' ?></td>
                <td><?= View::escape((string) ($mail['subject'] ?? '')) ?></td>
                <td><?= View::escape($party !== '' ? $party : '—') ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if ($mailId > 0) : ?>
                      <a class="dg-button dg-button--small" href="/app?page=post&amp;id=<?= $mailId ?>">Öffnen</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</details>

<details class="dg-form-accordion">
  <summary>Dokumente</summary>
  <div class="dg-form-accordion__body">
    <?php if ($documents === []) : ?>
      <p class="dg-muted">Keine Belege, Verfasser-Dokumente oder HR-Dateien zu diesem Kontakt.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Art</th>
              <th>Titel</th>
              <th>Hinweis</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($documents as $doc) : ?>
              <tr>
                <td><?= View::escape((string) (($doc['date'] ?? '') !== '' ? $doc['date'] : '—')) ?></td>
                <td><?= View::escape((string) ($doc['kind_label'] ?? '')) ?></td>
                <td><?= View::escape((string) ($doc['title'] ?? '')) ?></td>
                <td><?= View::escape((string) (($doc['meta'] ?? '') !== '' ? $doc['meta'] : '—')) ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if (!empty($doc['url'])) : ?>
                      <a class="dg-button dg-button--small" href="<?= View::escape((string) $doc['url']) ?>">
                        <?= View::escape((string) ($doc['url_label'] ?? 'Öffnen')) ?>
                      </a>
                    <?php endif; ?>
                    <?php if (!empty($doc['download_url'])) : ?>
                      <a class="dg-button dg-button--small" href="<?= View::escape((string) $doc['download_url']) ?>">Download</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</details>
