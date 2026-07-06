<?php
/** @var list<array<string, mixed>> $postMailboxes */
/** @var list<array<string, mixed>> $postSendableMailboxes */
/** @var list<array<string, mixed>> $postInbox */
/** @var list<array{path: string, label: string, source: string}> $postFolders */
/** @var int $postMailboxFilter */
/** @var string $postFolder */
/** @var string $postFolderLabel */
/** @var bool $postIsSentFolder */
/** @var bool $postImapLive */
/** @var int $postUnreadCount */
/** @var array<string, mixed>|null $postMessage */
/** @var bool $postCompose */
/** @var array<string, string> $postComposeForm */

$postMailboxes = $postMailboxes ?? [];
$postSendableMailboxes = $postSendableMailboxes ?? [];
$postInbox = $postInbox ?? [];
$postFolders = $postFolders ?? [];
$postMailboxFilter = $postMailboxFilter ?? 0;
$postFolder = $postFolder ?? 'INBOX';
$postFolderLabel = $postFolderLabel ?? 'Posteingang';
$postIsSentFolder = $postIsSentFolder ?? false;
$postImapLive = $postImapLive ?? false;
$postUnreadCount = $postUnreadCount ?? 0;
$postMessage = $postMessage ?? null;
$postCompose = $postCompose ?? false;
$postComposeForm = array_merge([
    'mailbox_id' => '',
    'to' => '',
    'subject' => '',
    'body' => '',
    'reply_to_id' => '',
], $postComposeForm ?? []);

/** @var bool $postImapAsync */
$postImapAsync = $postImapAsync ?? false;
$postFolderQuery = static function (int $mailboxId, string $folderPath) use ($postMailboxFilter, $postFolder): string {
    $params = ['page' => 'post', 'folder' => $folderPath];
    if ($mailboxId > 0) {
        $params['mailbox'] = (string) $mailboxId;
    }

    return '/app?' . http_build_query($params);
};
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Post</h1>
      <p class="dg-lead">Mehrere Postfächer — Ordner werden per IMAP vom Provider geladen (z.&nbsp;B. All-Inkl).</p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($postMailboxes !== [] && $postSendableMailboxes !== []) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=post&amp;compose=1">Neue Nachricht</a>
      <?php endif; ?>
      <?php if ($postUnreadCount > 0) : ?>
        <span class="dg-badge"><?= (int) $postUnreadCount ?> ungelesen</span>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($postMailboxes === []) : ?>
    <div class="dg-panel">
      <p class="dg-lead">Kein Postfach zugewiesen. Unter <a href="/app?page=einstellungen&tab=postfaecher">Einstellungen → Postfächer</a> Google, Legacy oder weitere Adressen anlegen.</p>
    </div>
  <?php elseif ($postCompose) : ?>
    <?php View::partial('partials/back-nav', [
        'href' => $postFolderQuery($postMailboxFilter, $postFolder),
        'label' => 'Zurück zu ' . $postFolderLabel,
    ]); ?>
    <form class="dg-form dg-panel" method="post" action="/app?page=post">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="reply_to_id" value="<?= View::escape($postComposeForm['reply_to_id']) ?>">
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Von (Postfach)</span>
          <select name="mailbox_id" required>
            <option value="">— wählen —</option>
            <?php foreach ($postSendableMailboxes as $box) : ?>
              <option value="<?= (int) $box['id'] ?>"<?= (string) $postComposeForm['mailbox_id'] === (string) $box['id'] ? ' selected' : '' ?>>
                <?= View::escape((string) ($box['name'] ?? $box['email_address'])) ?> &lt;<?= View::escape((string) $box['email_address']) ?>&gt;
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field dg-field--wide">
          <span>An</span>
          <input type="text" name="to" required value="<?= View::escape($postComposeForm['to']) ?>" placeholder="empfaenger@example.de">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Betreff</span>
          <input type="text" name="subject" required value="<?= View::escape($postComposeForm['subject']) ?>">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Nachricht</span>
          <textarea name="body" rows="12" required><?= View::escape($postComposeForm['body']) ?></textarea>
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" name="post_send" value="1" class="dg-button dg-button--primary">Senden</button>
        <a class="dg-button" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder)) ?>">Abbrechen</a>
      </div>
    </form>
  <?php elseif ($postMessage !== null) : ?>
    <?php
      $postDetailBackFolder = ($postMessage['direction'] ?? '') === 'out'
          ? '__sent__'
          : ((string) ($postMessage['imap_folder'] ?? '') !== '' ? (string) $postMessage['imap_folder'] : $postFolder);
      $postDetailBackLabel = MailFolderCatalog::usesLocalSent($postDetailBackFolder) ? 'Gesendet' : $postFolderLabel;
    ?>
    <?php View::partial('partials/back-nav', [
        'href' => $postFolderQuery($postMailboxFilter, $postDetailBackFolder),
        'label' => 'Zurück zu ' . $postDetailBackLabel,
    ]); ?>
    <article class="dg-panel dg-post-message">
      <header class="dg-post-message__head">
        <h2><?= View::escape((string) ($postMessage['subject'] ?? '(Ohne Betreff)')) ?></h2>
        <p>
          <?php
            $fromLabel = MailPartyLabel::forMessageRow($postMessage);
            $fromEmail = (string) ($postMessage['from_address'] ?? '');
            echo View::escape($fromLabel !== '' ? $fromLabel . ' <' . $fromEmail . '>' : $fromEmail);
          ?><br>
          <?= View::escape((string) ($postMessage['received_at'] ?? $postMessage['created_at'] ?? '')) ?>
        </p>
      </header>
      <div class="dg-post-message__body">
        <?php
          $bodyHtml = trim((string) ($postMessage['body_html'] ?? ''));
          $bodyText = trim((string) ($postMessage['body_text'] ?? ''));
          $bodyPreview = trim((string) ($postMessage['body_preview'] ?? ''));
        ?>
        <?php if ($bodyHtml !== '') : ?>
          <iframe
            class="dg-post-message__frame"
            title="E-Mail-Inhalt"
            sandbox=""
            srcdoc="<?= htmlspecialchars($bodyHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          ></iframe>
        <?php elseif ($bodyText !== '') : ?>
          <?= nl2br(View::escape($bodyText)) ?>
        <?php else : ?>
          <?= nl2br(View::escape($bodyPreview)) ?>
        <?php endif; ?>
      </div>
      <p class="dg-form-actions">
        <?php if ($postSendableMailboxes !== [] && (int) ($postMessage['id'] ?? 0) > 0) : ?>
          <a class="dg-button dg-button--primary" href="/app?page=post&amp;reply=<?= (int) $postMessage['id'] ?>">Antworten</a>
        <?php endif; ?>
        <?php if (!empty($postMessage['storage_path'])) : ?>
          <a class="dg-button" href="/app?page=post&amp;mail_archive=<?= (int) $postMessage['id'] ?>">.eml herunterladen</a>
        <?php endif; ?>
      </p>
    </article>
  <?php else : ?>
    <div class="dg-post-layout">
      <aside class="dg-post-folders" aria-label="E-Mail-Ordner">
        <form class="dg-post-folders__mailbox" method="get" action="/app">
          <input type="hidden" name="page" value="post">
          <input type="hidden" name="folder" value="<?= View::escape($postFolder) ?>">
          <label>
            <span class="dg-post-folders__mailbox-label">Postfach</span>
            <select name="mailbox" onchange="this.form.folder.value='INBOX'; this.form.submit()">
              <option value="0"<?= $postMailboxFilter <= 0 ? ' selected' : '' ?>>Alle</option>
              <?php foreach ($postMailboxes as $box) : ?>
                <option value="<?= (int) $box['id'] ?>"<?= $postMailboxFilter === (int) $box['id'] ? ' selected' : '' ?>><?= View::escape((string) ($box['name'] ?? $box['email_address'])) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>
        <nav class="dg-post-folders__list">
          <?php foreach ($postFolders as $folderRow) : ?>
            <?php
              $folderPath = (string) ($folderRow['path'] ?? '');
              $isActive = $folderPath === $postFolder;
            ?>
            <a
              class="dg-post-folders__link<?= $isActive ? ' is-active' : '' ?>"
              href="<?= View::escape($postFolderQuery($postMailboxFilter, $folderPath)) ?>"
            ><?= View::escape((string) ($folderRow['label'] ?? $folderPath)) ?></a>
          <?php endforeach; ?>
        </nav>
        <?php if ($postMailboxFilter <= 0) : ?>
          <p class="dg-post-folders__hint">Für alle Ordner (Gesendet, Spam, …) oben ein Postfach wählen.</p>
        <?php elseif (!ImapMailboxClient::isAvailable()) : ?>
          <p class="dg-post-folders__hint">PHP-IMAP nicht aktiv — Standardordner angezeigt.</p>
        <?php else :
          $postSelectedHasImap = false;
          foreach ($postMailboxes as $box) {
              if ((int) ($box['id'] ?? 0) === $postMailboxFilter) {
                  $postSelectedHasImap = ImapMailboxClient::hasCredentials($box);
                  break;
              }
          }
          if ($postSelectedHasImap && count($postFolders) <= 2) : ?>
          <p class="dg-post-folders__hint">IMAP-Ordner nicht geladen. <a href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder) . '&refresh=1') ?>">Erneut abrufen</a></p>
        <?php endif; endif; ?>
      </aside>

      <div
        class="dg-post-main"
        data-post-sync-root
        data-post-sync="<?= $postImapAsync ? '1' : '0' ?>"
        data-mailbox="<?= (int) $postMailboxFilter ?>"
        data-folder="<?= View::escape($postFolder) ?>"
        data-is-sent="<?= $postIsSentFolder ? '1' : '0' ?>"
        data-refresh="<?= !empty($_GET['refresh']) ? '1' : '0' ?>"
      >
        <h2 class="dg-post-main__title"><?= View::escape($postFolderLabel) ?></h2>
        <?php if ($postImapAsync) : ?>
          <div class="dg-post-sync" data-post-sync-panel aria-live="polite">
            <div class="dg-post-sync__bar" aria-hidden="true">
              <span class="dg-post-sync__bar-fill" data-post-sync-bar style="width:12%"></span>
            </div>
            <ol class="dg-post-sync__steps">
              <li class="dg-post-sync__step is-active" data-step="connect">Verbindung zum Mailserver …</li>
              <li class="dg-post-sync__step" data-step="fetch">Nachrichten lesen …</li>
              <li class="dg-post-sync__step" data-step="done">Fertig</li>
            </ol>
            <p class="dg-post-sync__status" data-post-sync-status>Postfach wird geladen …</p>
            <p class="dg-post-sync__error" data-post-sync-error hidden></p>
          </div>
        <?php endif; ?>

        <div class="dg-panel" data-post-sync-empty<?= $postInbox !== [] ? ' hidden' : '' ?>>
          <p class="dg-lead">Keine Nachrichten in „<?= View::escape($postFolderLabel) ?>“.</p>
        </div>
        <div class="dg-table-wrap" data-post-sync-table<?= $postInbox === [] ? ' hidden' : '' ?>>
          <table class="dg-table">
            <thead>
              <tr>
                <th>Name</th>
                <th><?= $postIsSentFolder ? 'An' : 'Von' ?></th>
                <th>Betreff</th>
                <th>Datum</th>
              </tr>
            </thead>
            <tbody data-post-sync-body>
              <?php foreach ($postInbox as $row) : ?>
                <?php
                  $rowId = (int) ($row['id'] ?? 0);
                  $displayName = trim((string) ($row['party_display_name'] ?? $row['from_name'] ?? ''));
                  $address = (string) ($row['from_address'] ?? '');
                  $detailUrl = $rowId > 0
                      ? '/app?page=post&id=' . $rowId . ($postMailboxFilter > 0 ? '&mailbox=' . $postMailboxFilter : '')
                      : '';
                ?>
                <tr class="<?= empty($row['is_read']) ? ' dg-row--unread' : '' ?>">
                  <td><?= View::escape($displayName) ?></td>
                  <td><?= View::escape($address) ?></td>
                  <td>
                    <?php if ($detailUrl !== '') : ?>
                      <a href="<?= View::escape($detailUrl) ?>"><?= View::escape((string) ($row['subject'] ?? '')) ?></a>
                    <?php else : ?>
                      <?= View::escape((string) ($row['subject'] ?? '')) ?>
                    <?php endif; ?>
                  </td>
                  <td><?= View::escape((string) ($row['received_at'] ?? $row['created_at'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($postImapAsync) : ?>
          <p class="dg-form-actions">
            <a class="dg-button" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder) . '&refresh=1') ?>">Jetzt vom Server abrufen</a>
          </p>
          <script src="<?= View::escape(Asset::url('/assets/js/post-sync.js')) ?>" defer></script>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
