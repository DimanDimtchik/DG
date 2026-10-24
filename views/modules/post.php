<?php
/** @var User $user */
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
/** @var bool $postListMode */
/** @var list<array<string, mixed>> $postMailboxCards */
/** @var array<string, string> $postComposeForm */
/** @var array{contact: ?Contact, suggested: ?Contact, voucher: ?array<string, mixed>, party_email: string} $postLinkContext */

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
$postListMode = $postListMode ?? false;
$postMailboxCards = $postMailboxCards ?? [];
$postHasMore = $postHasMore ?? false;
$postPageNum = max(1, (int) ($postPageNum ?? 1));
$postPageCount = max(1, (int) ($postPageCount ?? 1));
$postPageLimit = max(1, (int) ($postPageLimit ?? PostUiSettings::messagesPerPage()));
$postTotalCount = max(0, (int) ($postTotalCount ?? 0));
$postSearchQuery = $postSearchQuery ?? '';
$postSearchContactId = $postSearchContactId ?? 0;
$postSearchMode = $postSearchMode ?? false;
$postLinkContext = $postLinkContext ?? [
    'contact' => null,
    'suggested' => null,
    'voucher' => null,
    'party_email' => '',
];
$postComposeForm = array_merge([
    'mailbox_id' => '',
    'to' => '',
    'subject' => '',
    'body' => '',
    'reply_to_id' => '',
    'forward_from_id' => '',
    'mode' => 'new',
    'contact_id' => '',
], $postComposeForm ?? []);
$postComposeMode = (string) ($postComposeForm['mode'] ?? 'new');
$postComposeTitle = match ($postComposeMode) {
    'reply' => 'Antworten',
    'forward' => 'Weiterleiten',
    default => 'Neue Nachricht',
};
$postCanLinkVoucher = MenuRegistry::canAccess($user, 'buchhaltung-belege')
    || MenuRegistry::canAccess($user, 'buchhaltung-beleg-form');

/** @var bool $postImapAsync */
$postImapAsync = $postImapAsync ?? false;
$postFolderQuery = static function (int $mailboxId, string $folderPath, int $pageNum = 1) use ($postSearchQuery, $postSearchContactId): string {
    $params = ['page' => 'post', 'folder' => $folderPath];
    if ($mailboxId > 0) {
        $params['mailbox'] = (string) $mailboxId;
    }
    if ($pageNum > 1) {
        $params['p'] = (string) $pageNum;
    }
    if ($postSearchQuery !== '') {
        $params['q'] = $postSearchQuery;
    }
    if ($postSearchContactId > 0) {
        $params['contact'] = (string) $postSearchContactId;
    }

    return '/app?' . http_build_query($params);
};
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">E-Mail-Postfächer</h1>
      <p class="dg-lead"><?= $postListMode
          ? 'Wählen Sie ein Postfach — Ordner und Nachrichten laden danach per IMAP.'
          : 'Ordner werden per IMAP vom Provider geladen (z.&nbsp;B. All-Inkl).' ?></p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($postMailboxes !== [] && $postSendableMailboxes !== []) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=post&amp;compose=1<?= $postMailboxFilter > 0 ? '&amp;mailbox=' . (int) $postMailboxFilter : '' ?>">Neue Nachricht</a>
      <?php endif; ?>
      <?php if (RoleResolver::isAdmin($user)) : ?>
        <a class="dg-button" href="/app?page=einstellungen&amp;tab=postfaecher">Postfächer verwalten</a>
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
  <?php else : ?>
  <?php if (!$postCompose && $postMessage === null) : ?>
  <form class="dg-panel dg-post-search" method="get" action="/app" style="margin-bottom:16px">
    <input type="hidden" name="page" value="post">
    <?php if ($postMailboxFilter > 0) : ?>
      <input type="hidden" name="mailbox" value="<?= (int) $postMailboxFilter ?>">
    <?php endif; ?>
    <div class="dg-form-grid" style="align-items:end">
      <label class="dg-field dg-field--wide">
        <span>Suche (Betreff, Adresse, Vorschau)</span>
        <input type="search" name="q" value="<?= View::escape($postSearchQuery) ?>" placeholder="z. B. Rechnung, kunde@…">
      </label>
      <label class="dg-field">
        <span>Kontakt-ID</span>
        <input type="number" name="contact" min="1" value="<?= $postSearchContactId > 0 ? (int) $postSearchContactId : '' ?>" placeholder="optional">
      </label>
      <div class="dg-form-actions" style="margin:0">
        <button type="submit" class="dg-button dg-button--primary">Suchen</button>
        <?php if ($postSearchMode) : ?>
          <a class="dg-button" href="<?= View::escape($postMailboxFilter > 0 ? $postFolderQuery($postMailboxFilter, $postFolder) : '/app?page=post') ?>">Zurücksetzen</a>
        <?php endif; ?>
      </div>
    </div>
  </form>
  <?php endif; ?>

  <?php if ($postListMode) : ?>
    <div class="dg-mailbox-cards" role="list">
      <?php foreach ($postMailboxCards as $card) : ?>
        <a class="dg-mailbox-card" role="listitem" href="<?= View::escape((string) $card['open_href']) ?>">
          <div class="dg-mailbox-card__main">
            <strong class="dg-mailbox-card__name"><?= View::escape((string) $card['name']) ?></strong>
            <span class="dg-mailbox-card__email"><?= View::escape((string) $card['email']) ?></span>
          </div>
          <div class="dg-mailbox-card__meta">
            <span class="dg-badge"><?= View::escape((string) $card['type_label']) ?></span>
            <?php if ((int) ($card['unread'] ?? 0) > 0) : ?>
              <span class="dg-badge dg-badge--accent"><?= (int) $card['unread'] ?> ungelesen</span>
            <?php endif; ?>
            <span class="dg-mailbox-card__status dg-mailbox-card__status--<?= View::escape((string) $card['status_severity']) ?>">
              <?= View::escape((string) $card['status_label']) ?>
            </span>
            <?php if (!empty($card['last_activity'])) : ?>
              <span class="dg-mailbox-card__activity">Zuletzt: <?= View::escape((string) $card['last_activity']) ?></span>
            <?php endif; ?>
          </div>
          <span class="dg-mailbox-card__cta">Öffnen</span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="dg-field-hint" style="margin-top:12px">Status ohne Live-Verbindung zum Mailserver (Zugangsdaten in den Einstellungen). Fremde Webmailer werden nicht eingebunden.</p>
  <?php elseif ($postCompose) : ?>
    <?php View::partial('partials/back-nav', [
        'href' => $postMailboxFilter > 0
            ? $postFolderQuery($postMailboxFilter, $postFolder)
            : '/app?page=post',
        'label' => $postMailboxFilter > 0 ? ('Zurück zu ' . $postFolderLabel) : 'Zurück zur Postfach-Liste',
    ]); ?>
    <?php if ($postSendableMailboxes === []) : ?>
      <div class="dg-panel">
        <p class="dg-lead">Kein Postfach mit Senderecht. Unter <a href="/app?page=einstellungen&amp;tab=postfaecher">Einstellungen → Postfächer</a> prüfen.</p>
      </div>
    <?php else : ?>
    <?php
      $postAuthorName = trim((string) ($user->displayName ?? ''));
      $postGreetings = PostComposePhrases::greetings(null);
      $postClosings = PostComposePhrases::closingParagraphs();
      $postSignatures = PostComposePhrases::signatures($postAuthorName !== '' ? $postAuthorName : 'Ihr Team');
      $postIsNewCompose = $postComposeMode === 'new' && trim((string) ($postComposeForm['body'] ?? '')) === '';
      $postDefaultBody = $postIsNewCompose
          ? PostComposePhrases::buildBody(
              $postGreetings[0]['text'],
              $postClosings[0]['text'],
              $postSignatures[0]['text']
          )
          : (string) ($postComposeForm['body'] ?? '');
    ?>
    <form class="dg-form dg-panel" method="post" action="/app?page=post" enctype="multipart/form-data" id="dg-post-compose-form">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="reply_to_id" value="<?= View::escape($postComposeForm['reply_to_id']) ?>">
      <input type="hidden" name="forward_from_id" value="<?= View::escape($postComposeForm['forward_from_id']) ?>">
      <input type="hidden" name="compose_mode" value="<?= View::escape($postComposeMode) ?>">
      <input type="hidden" name="contact_id" id="dg-post-contact-id" value="<?= View::escape((string) ($postComposeForm['contact_id'] ?? '')) ?>">
      <h2 class="dg-subsection-title"><?= View::escape($postComposeTitle) ?></h2>
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
        <div class="dg-field dg-field--wide">
          <span>An (Kontakt oder manuell)</span>
          <input type="search" id="dg-post-contact-search" placeholder="Kontakt suchen…" autocomplete="off">
          <div id="dg-post-contact-results" class="dg-voucher-contact-results" hidden></div>
          <input type="text" name="to" id="dg-post-to" required value="<?= View::escape($postComposeForm['to']) ?>" placeholder="empfaenger@example.de" autocomplete="email">
          <span class="dg-field-hint">Kontakt wählen setzt die E-Mail; manuell bleibt möglich (Komma/Semikolon für mehrere).</span>
        </div>
        <label class="dg-field dg-field--wide">
          <span>Betreff</span>
          <input type="text" name="subject" required value="<?= View::escape($postComposeForm['subject']) ?>">
        </label>
        <div class="dg-field dg-field--wide dg-post-compose-phrases">
          <span>Textbausteine</span>
          <div class="dg-post-compose-phrases__row">
            <label class="dg-field">
              <span>Begrüßung</span>
              <select id="dg-post-greeting">
                <?php foreach ($postGreetings as $g) : ?>
                  <option value="<?= View::escape($g['id']) ?>" data-text="<?= View::escape($g['text']) ?>"><?= View::escape($g['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Schlussabsatz</span>
              <select id="dg-post-closing">
                <?php foreach ($postClosings as $c) : ?>
                  <option value="<?= View::escape($c['id']) ?>" data-text="<?= View::escape($c['text']) ?>"><?= View::escape($c['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Grußformel</span>
              <select id="dg-post-signature">
                <?php foreach ($postSignatures as $s) : ?>
                  <option value="<?= View::escape($s['id']) ?>" data-text="<?= View::escape($s['text']) ?>"><?= View::escape($s['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <button type="button" class="dg-button" id="dg-post-apply-phrases">Bausteine in Nachricht übernehmen</button>
        </div>
        <label class="dg-field dg-field--wide">
          <span>Nachricht</span>
          <textarea name="body" id="dg-post-body" rows="14" required><?= View::escape($postDefaultBody) ?></textarea>
        </label>
        <div class="dg-field dg-field--wide">
          <span>CRM-Belege anhängen</span>
          <input type="search" id="dg-post-voucher-search" placeholder="Angebot, Auftrag, Rechnung…" autocomplete="off">
          <div id="dg-post-voucher-results" class="dg-voucher-contact-results" hidden></div>
          <ul id="dg-post-voucher-chips" class="dg-post-attach-chips"></ul>
          <span class="dg-field-hint">Als HTML-Druckanhang (Angebot, AB, Lieferschein, Rechnung …).</span>
        </div>
        <label class="dg-field dg-field--wide">
          <span>Dateien vom PC</span>
          <input type="file" name="attachments[]" id="dg-post-files" multiple>
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" name="post_send" value="1" class="dg-button dg-button--primary">Senden</button>
        <a class="dg-button" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder)) ?>">Abbrechen</a>
      </div>
    </form>
    <script>
      window.dgPostCompose = {
        api: '/api/post-compose',
        isNew: <?= $postIsNewCompose ? 'true' : 'false' ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/post-compose.js')) ?>" defer></script>
    <?php endif; ?>
  <?php elseif ($postMessage !== null) : ?>
    <?php
      $postDetailBackFolder = ($postMessage['direction'] ?? '') === 'out'
          ? '__sent__'
          : ((string) ($postMessage['imap_folder'] ?? '') !== '' ? (string) $postMessage['imap_folder'] : $postFolder);
      $postDetailBackLabel = MailFolderCatalog::usesLocalSent($postDetailBackFolder) ? 'Gesendet' : $postFolderLabel;
    ?>
    <?php View::partial('partials/back-nav', [
        'href' => $postFolderQuery($postMailboxFilter > 0 ? $postMailboxFilter : (int) ($postMessage['mailbox_id'] ?? 0), $postDetailBackFolder),
        'label' => 'Zurück zu ' . $postDetailBackLabel,
    ]); ?>
    <p class="dg-field-hint" style="margin:0 0 12px">
      <a href="/app?page=post">Alle Postfächer</a>
    </p>
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
            referrerpolicy="no-referrer"
            srcdoc="<?= htmlspecialchars(MailHtmlSanitizer::clean($bodyHtml), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          ></iframe>
        <?php elseif ($bodyText !== '') : ?>
          <?= nl2br(View::escape($bodyText)) ?>
        <?php else : ?>
          <?= nl2br(View::escape($bodyPreview)) ?>
        <?php endif; ?>
      </div>
      <?php
        $postAttachments = $postMessage['attachments'] ?? [];
        if (is_array($postAttachments) && $postAttachments !== []) :
      ?>
        <div class="dg-post-attachments">
          <h3 class="dg-subsection-title">Anhänge</h3>
          <ul class="dg-post-attachments__list">
            <?php foreach ($postAttachments as $attIndex => $att) : ?>
              <?php
                $attName = (string) ($att['filename'] ?? 'anhang.bin');
                $attSize = (int) ($att['size'] ?? strlen((string) ($att['content'] ?? '')));
                $attHref = (int) ($postMessage['id'] ?? 0) > 0
                    ? '/app?page=post&id=' . (int) $postMessage['id'] . '&mail_attachment=' . (int) $attIndex
                    : '/app?page=post&mailbox=' . (int) ($postMessage['mailbox_id'] ?? $postMailboxFilter)
                        . '&uid=' . (int) ($postMessage['imap_uid'] ?? 0)
                        . '&folder=' . rawurlencode((string) ($postMessage['imap_folder'] ?? $postFolder))
                        . '&mail_attachment=' . (int) $attIndex;
              ?>
              <li>
                <a href="<?= View::escape($attHref) ?>"><?= View::escape($attName) ?></a>
                <span class="dg-field-hint"><?= $attSize > 0 ? number_format($attSize / 1024, 1, ',', '.') . ' KB' : '' ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <p class="dg-form-actions">
        <?php if ($postSendableMailboxes !== [] && (int) ($postMessage['id'] ?? 0) > 0) : ?>
          <?php if (($postMessage['direction'] ?? '') === 'in') : ?>
            <a class="dg-button dg-button--primary" href="/app?page=post&amp;reply=<?= (int) $postMessage['id'] ?>">Antworten</a>
          <?php endif; ?>
          <a class="dg-button" href="/app?page=post&amp;forward=<?= (int) $postMessage['id'] ?>">Weiterleiten</a>
        <?php endif; ?>
        <?php if (!empty($postMessage['storage_path']) && (int) ($postMessage['id'] ?? 0) > 0) : ?>
          <a class="dg-button" href="/app?page=post&amp;mail_archive=<?= (int) $postMessage['id'] ?>">.eml herunterladen</a>
        <?php endif; ?>
      </p>
    </article>

    <?php if ((int) ($postMessage['id'] ?? 0) > 0) : ?>
      <?php
        $linkedContact = $postLinkContext['contact'] ?? null;
        $suggestedContact = $postLinkContext['suggested'] ?? null;
        $linkedVoucher = $postLinkContext['voucher'] ?? null;
        $partyEmail = (string) ($postLinkContext['party_email'] ?? '');
      ?>
      <section class="dg-panel dg-post-crm-links" style="margin-top:16px">
        <h2 class="dg-subsection-title">CRM-Verknüpfung</h2>
        <p class="dg-field-hint">Kontakt und Beleg für die Belegkette — Verknüpfungen werden im Audit-Protokoll festgehalten.</p>

        <div class="dg-post-crm-links__block">
          <h3 class="dg-subsection-title" style="font-size:1rem">Kontakt</h3>
          <?php if ($linkedContact instanceof Contact) : ?>
            <p>
              Verknüpft:
              <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $linkedContact->id ?>"><?= View::escape($linkedContact->listLabel()) ?></a>
              <?php if ($partyEmail !== '') : ?>
                <span class="dg-field-hint">(<?= View::escape($partyEmail) ?>)</span>
              <?php endif; ?>
            </p>
            <form method="post" action="/app?page=post" class="dg-inline-form">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="mail_id" value="<?= (int) $postMessage['id'] ?>">
              <button type="submit" name="post_unlink_contact" value="1" class="dg-button">Verknüpfung lösen</button>
              <a class="dg-button" href="/app?page=post&amp;contact=<?= (int) $linkedContact->id ?>">Mails dieses Kontakts</a>
            </form>
          <?php else : ?>
            <?php if ($suggestedContact instanceof Contact) : ?>
              <form method="post" action="/app?page=post" class="dg-form" style="margin-bottom:12px">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="mail_id" value="<?= (int) $postMessage['id'] ?>">
                <input type="hidden" name="suggested_contact_id" value="<?= (int) $suggestedContact->id ?>">
                <p>
                  Vorschlag:
                  <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $suggestedContact->id ?>"><?= View::escape($suggestedContact->listLabel()) ?></a>
                  <?php if ($partyEmail !== '') : ?>
                    <span class="dg-field-hint">(<?= View::escape($partyEmail) ?>)</span>
                  <?php endif; ?>
                </p>
                <button type="submit" name="post_link_contact" value="1" class="dg-button dg-button--primary">Vorschlag übernehmen</button>
              </form>
            <?php elseif ($partyEmail !== '') : ?>
              <p class="dg-field-hint">Kein Kontakt zu <?= View::escape($partyEmail) ?> — manuell verknüpfen oder in Kontakten anlegen.</p>
            <?php endif; ?>
            <form method="post" action="/app?page=post" class="dg-form">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="mail_id" value="<?= (int) $postMessage['id'] ?>">
              <label class="dg-field dg-field--wide">
                <span>Kontakt-ID oder E-Mail</span>
                <input type="text" name="contact_ref" value="<?= View::escape($partyEmail) ?>" placeholder="123 oder name@firma.de" required>
              </label>
              <div class="dg-form-actions">
                <button type="submit" name="post_link_contact" value="1" class="dg-button">Kontakt verknüpfen</button>
              </div>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($postCanLinkVoucher) : ?>
          <div class="dg-post-crm-links__block" style="margin-top:16px">
            <h3 class="dg-subsection-title" style="font-size:1rem">Beleg</h3>
            <?php if (is_array($linkedVoucher) && (int) ($linkedVoucher['id'] ?? 0) > 0) : ?>
              <p>
                Verknüpft:
                <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $linkedVoucher['id'] ?>">
                  <?= View::escape((string) ($linkedVoucher['type_label'] ?? 'Beleg')) ?>
                  <?= View::escape(trim((string) ($linkedVoucher['invoice_number'] ?? '')) !== ''
                      ? (string) $linkedVoucher['invoice_number']
                      : '#' . (int) $linkedVoucher['id']) ?>
                </a>
              </p>
              <form method="post" action="/app?page=post" class="dg-inline-form">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="mail_id" value="<?= (int) $postMessage['id'] ?>">
                <button type="submit" name="post_unlink_voucher" value="1" class="dg-button">Verknüpfung lösen</button>
              </form>
            <?php else : ?>
              <form method="post" action="/app?page=post" class="dg-form">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="mail_id" value="<?= (int) $postMessage['id'] ?>">
                <label class="dg-field dg-field--wide">
                  <span>Beleg-ID oder Belegnummer</span>
                  <input type="text" name="voucher_ref" required placeholder="z. B. 42 oder RE-2026-001">
                </label>
                <div class="dg-form-actions">
                  <button type="submit" name="post_link_voucher" value="1" class="dg-button">Beleg verknüpfen</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php else : ?>
    <?php View::partial('partials/back-nav', [
        'href' => '/app?page=post',
        'label' => 'Zurück zur Postfach-Liste',
    ]); ?>
    <div class="dg-post-layout">
      <aside class="dg-post-folders" aria-label="E-Mail-Ordner">
        <form class="dg-post-folders__mailbox" method="get" action="/app">
          <input type="hidden" name="page" value="post">
          <input type="hidden" name="folder" value="<?= View::escape($postFolder) ?>">
          <label>
            <span class="dg-post-folders__mailbox-label">Postfach</span>
            <select name="mailbox" onchange="if(this.value==='0'){window.location='/app?page=post';return false;} this.form.folder.value='INBOX'; this.form.submit()">
              <option value="0">← Liste</option>
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
            ><?= View::escape(MailFolderLabels::labelForPath($folderPath !== '' ? $folderPath : (string) ($folderRow['label'] ?? ''))) ?></a>
          <?php endforeach; ?>
        </nav>
        <?php if (!ImapMailboxClient::isAvailable()) : ?>
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

      <?php $postDoRefresh = !empty($_GET['refresh']); ?>
      <div
        class="dg-post-main"
        data-post-sync-root
        data-post-sync="0"
        data-mailbox="<?= (int) $postMailboxFilter ?>"
        data-folder="<?= View::escape($postFolder) ?>"
        data-is-sent="<?= $postIsSentFolder ? '1' : '0' ?>"
        data-refresh="<?= $postDoRefresh ? '1' : '0' ?>"
        data-limit="<?= (int) $postPageLimit ?>"
        data-list-url="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder, 1)) ?>"
      >
        <h2 class="dg-post-main__title"><?= View::escape($postFolderLabel) ?></h2>
        <?php if ($postDoRefresh) : ?>
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

        <?php if (!$postSearchMode && $postMailboxFilter > 0) : ?>
          <p class="dg-form-actions dg-post-list-actions">
            <a class="dg-button" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder, 1) . '&refresh=1') ?>" title="Postfach neu vom Mailserver laden">Jetzt vom Server abrufen</a>
          </p>
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

        <?php if ($postPageCount > 1 || $postTotalCount > 0) : ?>
          <?php
            $postPagerCanPrev = $postPageNum > 1;
            $postPagerCanNext = $postPageNum < $postPageCount;
            $postPagerCountLabel = $postTotalCount === 1
                ? '1 Eintrag'
                : ((int) $postTotalCount . ' Einträge');
          ?>
          <nav class="dg-post-pager" aria-label="Seitenblätterung">
            <span class="dg-post-pager__count"><?= View::escape($postPagerCountLabel) ?></span>
            <?php if ($postPagerCanPrev) : ?>
              <a class="dg-post-pager__btn" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder, 1)) ?>" title="Erste Seite" aria-label="Erste Seite">&laquo;</a>
              <a class="dg-post-pager__btn" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder, $postPageNum - 1)) ?>" title="Vorherige Seite" aria-label="Vorherige Seite">&lt;</a>
            <?php else : ?>
              <span class="dg-post-pager__btn is-disabled" aria-disabled="true" title="Erste Seite">&laquo;</span>
              <span class="dg-post-pager__btn is-disabled" aria-disabled="true" title="Vorherige Seite">&lt;</span>
            <?php endif; ?>
            <span class="dg-post-pager__page"><?= (int) $postPageNum ?> von <?= (int) $postPageCount ?></span>
            <?php if ($postPagerCanNext) : ?>
              <a class="dg-post-pager__btn" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder, $postPageNum + 1)) ?>" title="Nächste Seite" aria-label="Nächste Seite">&gt;</a>
              <a class="dg-post-pager__btn" href="<?= View::escape($postFolderQuery($postMailboxFilter, $postFolder, $postPageCount)) ?>" title="Letzte Seite" aria-label="Letzte Seite">&raquo;</a>
            <?php else : ?>
              <span class="dg-post-pager__btn is-disabled" aria-disabled="true" title="Nächste Seite">&gt;</span>
              <span class="dg-post-pager__btn is-disabled" aria-disabled="true" title="Letzte Seite">&raquo;</span>
            <?php endif; ?>
          </nav>
        <?php endif; ?>

        <?php if ($postMailboxFilter > 0 && !$postSearchMode) : ?>
          <script src="<?= View::escape(Asset::url('/assets/js/post-sync.js')) ?>" defer></script>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
