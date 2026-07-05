<?php

/** @var array<string, mixed> $mailConfig */

/** @var bool $mailReady */

/** @var list<array<string, mixed>> $mailRecent */

$storedUser = trim((string) ($mailConfig['smtp_username'] ?? ''));

$hasStoredPass = ($mailConfig['smtp_password'] ?? '') !== '';

?>

<form class="dg-form dg-smtp-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('email')) ?>">

  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">



  <p class="dg-lead">

    Versand über <strong>SMTP mit TLS/SSL</strong> — nicht über <code>mail()</code>.

    SMTP-Zugangsdaten werden in der <strong>Datenbank</strong> dieser CRM-Instanz gespeichert (hosting-unabhängig).

    Kopien ausgehehender Mails: Metadaten in der DB, Volltext als <strong>.eml</strong> unter <code>storage/mail/sent/</code>.

  </p>



  <?php if ($mailReady) : ?>

    <p class="dg-status-line dg-status-line--ok">Versand aktiv — Host und Benutzer sind hinterlegt.</p>

  <?php else : ?>

    <p class="dg-status-line dg-status-line--warn">SMTP noch nicht vollständig eingerichtet.</p>

  <?php endif; ?>



  <?php if (empty($mailConfig['company_configured'])) : ?>

    <div class="dg-flash dg-flash--warning">

      Absender fehlt — bitte

      <a href="<?= View::escape(SettingsRegistry::tabUrl('firmendaten')) ?>">Firmendaten</a>

      (Firmenname und E-Mail) eintragen.

    </div>

  <?php else : ?>

    <div class="dg-sender-preview">

      <p><strong>Absender:</strong> <?= View::escape((string) $mailConfig['sender_name']) ?> &lt;<?= View::escape((string) $mailConfig['sender_email']) ?>&gt;</p>

      <p class="dg-lead">Name und Adresse kommen aus <a href="<?= View::escape(SettingsRegistry::tabUrl('firmendaten')) ?>">Firmendaten</a>.</p>

    </div>

  <?php endif; ?>



  <label class="dg-field dg-field--wide">

    <span><input type="checkbox" name="enabled" value="1"<?= !empty($mailConfig['enabled']) ? ' checked' : '' ?>> E-Mail-Versand aktiviert</span>

  </label>



  <?php if ($storedUser !== '' || $hasStoredPass) : ?>

    <div class="dg-stored-mail" aria-live="polite">

      <p><strong>Gespeichert in der Datenbank</strong></p>

      <?php if ($storedUser !== '') : ?>

        <p>SMTP-Login: <code><?= View::escape($storedUser) ?></code></p>

      <?php endif; ?>

      <?php if ($hasStoredPass) : ?>

        <p>Passwort: <code>••••••••</code> (gespeichert)</p>

      <?php endif; ?>

    </div>

  <?php endif; ?>



  <div class="dg-form-grid">

    <label class="dg-field">

      <span>SMTP-Host *</span>

      <input type="text" name="smtp_host" value="<?= View::escape((string) $mailConfig['smtp_host']) ?>" placeholder="z. B. w0217246.kasserver.com" required autocomplete="off" autocapitalize="off">

    </label>

    <label class="dg-field">

      <span>Port</span>

      <input type="number" name="smtp_port" value="<?= (int) $mailConfig['smtp_port'] ?>" min="1" max="65535" autocomplete="off">

    </label>

    <label class="dg-field">

      <span>Verschlüsselung</span>

      <select name="smtp_encryption" autocomplete="off">

        <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', '' => 'Keine (nur intern)'] as $val => $label) : ?>

          <option value="<?= View::escape($val) ?>"<?= ($mailConfig['smtp_encryption'] ?? '') === $val ? ' selected' : '' ?>><?= View::escape($label) ?></option>

        <?php endforeach; ?>

      </select>

    </label>

    <label class="dg-field">

      <span>SMTP-Benutzer (Mailbox) *</span>

      <input

        type="email"

        id="smtp_username"

        name="smtp_username"

        value="<?= View::escape($storedUser) ?>"

        autocomplete="username"

        placeholder="z. B. dg_user@ganz-soft.de"

        required

      >

      <small class="dg-field-hint">Chrome, Kaspersky und andere Passwortmanager können Login und Passwort hier vorschlagen.</small>

    </label>

    <label class="dg-field dg-field--wide">

      <span>SMTP-Passwort</span>

      <input

        type="password"

        id="smtp_password"

        name="smtp_password"

        value=""

        autocomplete="current-password"

        placeholder="<?= $hasStoredPass ? 'Leer lassen = gespeichertes Passwort' : 'Passwort der Mailbox' ?>"

      >

      <small class="dg-field-hint">Beim Speichern leer lassen, wenn das gespeicherte Passwort unverändert bleiben soll.</small>

    </label>

  </div>



  <div class="dg-form-actions">

    <button type="submit" name="mail_action" value="test" class="dg-button">SMTP testen</button>

    <button type="submit" name="mail_action" value="save" class="dg-button dg-button--primary">Speichern</button>

  </div>

  <p class="dg-lead dg-form-hint">Nach dem Speichern steht der Wert aus der Datenbank in den Feldern. Falsche Vorschläge (z.&nbsp;B. <code>info@ganz-om.de</code> von der Hauptseite) bitte in Kaspersky/Chrome für <strong>dg.ganz-om.de</strong> korrigieren oder löschen.</p>

</form>



<?php View::render('partials/smtp-test-report', compact('smtpTestReport')); ?>



<form class="dg-form dg-panel dg-mail-test-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('email')) ?>" style="margin-top:24px" autocomplete="off">

  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <h3>Test-E-Mail senden</h3>

  <p class="dg-lead">Verwendet die <strong>gespeicherten</strong> SMTP-Daten aus der Datenbank.</p>

  <div class="dg-mail-test-decoy" aria-hidden="true">

    <input type="email" name="email" tabindex="-1" autocomplete="email">

  </div>

  <div class="dg-form-grid">

    <label class="dg-field dg-field--wide">

      <span>Empfänger</span>

      <input

        type="text"

        name="mail_test_to"

        id="mail_test_to"

        value=""

        inputmode="email"

        autocomplete="off"

        autocorrect="off"

        autocapitalize="off"

        spellcheck="false"

        placeholder="ihre@adresse.de"

        required

        readonly

        onfocus="this.removeAttribute('readonly')"

      >

      <small class="dg-field-hint">Bitte bewusst eintragen — Passwortmanager füllen hier oft eine andere Adresse (z.&nbsp;B. info@ganz-om.de) vor.</small>

    </label>

  </div>

  <div class="dg-form-actions">

    <button type="submit" name="mail_action" value="send_test" class="dg-button"<?= !$mailReady ? ' disabled' : '' ?>>Test senden</button>

  </div>

</form>



<?php if ($mailRecent !== []) : ?>

  <section class="dg-panel" style="margin-top:24px">

    <h3>Letzte ausgehende E-Mails</h3>

    <table class="dg-table dg-table--compact">

      <thead>

        <tr>

          <th>Datum</th>

          <th>An</th>

          <th>Betreff</th>

          <th>Status</th>

          <th>Größe</th>

          <th></th>

        </tr>

      </thead>

      <tbody>

        <?php foreach ($mailRecent as $row) : ?>

          <?php

            $toList = json_decode((string) ($row['to_addresses'] ?? '[]'), true);

            $toLabel = is_array($toList) ? implode(', ', $toList) : '';

            $when = $row['sent_at'] ?? $row['created_at'] ?? '';

            $status = (string) ($row['status'] ?? '');

            $size = (int) ($row['size_bytes'] ?? 0);

          ?>

          <tr>

            <td><?= View::escape((string) $when) ?></td>

            <td><?= View::escape($toLabel) ?></td>

            <td><?= View::escape((string) ($row['subject'] ?? '')) ?></td>

            <td>

              <?php if ($status === 'sent') : ?>

                <span class="dg-badge dg-badge--ok">Gesendet</span>

              <?php elseif ($status === 'failed') : ?>

                <span class="dg-badge dg-badge--error" title="<?= View::escape((string) ($row['error_message'] ?? '')) ?>">Fehler</span>

              <?php else : ?>

                <?= View::escape($status) ?>

              <?php endif; ?>

            </td>

            <td><?= $size > 0 ? View::escape(number_format($size / 1024, 1, ',', '.') . ' KB') : '—' ?></td>

            <td>

              <?php if ($status === 'sent') : ?>

                <a href="/app?page=einstellungen&amp;tab=email&amp;mail_archive=<?= (int) $row['id'] ?>">.eml</a>

              <?php endif; ?>

            </td>

          </tr>

        <?php endforeach; ?>

      </tbody>

    </table>

  </section>

<?php endif; ?>

<?php
/** @var array<string, mixed> $mailAddressConfig */
$mailAddressConfig = $mailAddressConfig ?? MailAddressSettings::forForm();
$kasConfigured = KasSettings::isConfigured();
?>
<section class="dg-panel" style="margin-top:24px" id="dg-mail-address-settings">
  <h3>Mitarbeiter-E-Mail-Adressen (Formel)</h3>
  <p class="dg-lead">
    Beim Anlegen von Mitarbeitern/Administratoren kann automatisch ein Postfach erzeugt werden.
    Standard: <code>{V1}.{NN}@domain</code> (z.&nbsp;B. <strong>m.mustermann@…</strong>).
  </p>

  <form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('email')) ?>" id="dg-mail-address-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

    <label class="dg-field dg-field--wide">
      <span><input type="checkbox" name="mail_address_enabled" value="1"<?= !empty($mailAddressConfig['enabled']) ? ' checked' : '' ?>> Automatische Postfach-Anlage aktiviert</span>
    </label>
    <label class="dg-field dg-field--wide">
      <span><input type="checkbox" name="auto_on_contact_create" value="1"<?= !empty($mailAddressConfig['auto_on_contact_create']) ? ' checked' : '' ?>> Standard-Checkbox im Kontaktformular (Mitarbeiter/Admin)</span>
    </label>

    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Domain</span>
        <input type="text" name="mail_domain" value="<?= View::escape((string) ($mailAddressConfig['domain'] ?? '')) ?>" placeholder="<?= View::escape((string) ($mailAddressConfig['effective_domain'] ?? 'firma.de')) ?>">
        <small class="dg-field-hint">Leer = Domain aus Firmen-E-Mail</small>
      </label>
      <label class="dg-field">
        <span>Formel-Vorlage</span>
        <select name="mail_preset" id="dg-mail-preset" data-mail-preset>
          <?php foreach (($mailAddressConfig['presets'] ?? []) as $presetId => $presetLabel) : ?>
            <option value="<?= View::escape($presetId) ?>"<?= ($mailAddressConfig['preset'] ?? '') === $presetId ? ' selected' : '' ?>><?= View::escape($presetLabel) ?></option>
          <?php endforeach; ?>
          <option value="custom"<?= ($mailAddressConfig['preset'] ?? '') === 'custom' ? ' selected' : '' ?>>Eigene Formel</option>
        </select>
      </label>
      <label class="dg-field">
        <span>Trennzeichen {TRENNER}</span>
        <input type="text" name="mail_separator" id="dg-mail-separator" maxlength="3" value="<?= View::escape((string) ($mailAddressConfig['separator'] ?? '.')) ?>">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Lokaler Teil (Platzhalter)</span>
        <input type="text" name="local_pattern" id="dg-mail-local-pattern" value="<?= View::escape((string) ($mailAddressConfig['local_pattern'] ?? '')) ?>">
      </label>
    </div>

    <div class="dg-mail-address-tokens">
      <?php foreach (($mailAddressConfig['token_groups'] ?? []) as $group) : ?>
        <p><strong><?= View::escape((string) ($group['title'] ?? '')) ?>:</strong>
          <?php foreach ($group['items'] as $item) : ?>
            <?php foreach ($item['codes'] as $code) : ?>
              <button type="button" class="dg-token-chip" data-mail-token="<?= View::escape($code) ?>"><?= View::escape($code) ?></button>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </p>
      <?php endforeach; ?>
    </div>

    <p class="dg-status-line" id="dg-mail-address-preview">Vorschau: —</p>

    <?php
      $signatureExamples = MailSignatureResolver::settingsExamples();
      $notificationsUrl = SettingsRegistry::tabUrl('benachrichtigungen');
    ?>
    <div class="dg-mail-signature-rules" aria-label="Signatur beim Post-Versand">
      <h4 class="dg-subsection-title">Signatur beim Post-Versand</h4>
      <p class="dg-field-hint">Die letzte Zeile unter Grußformel und Danke-Zeile hängt vom Postfach ab — <strong>entweder</strong> personalisiert <strong>oder</strong> Team-Signatur:</p>
      <div class="dg-mail-signature-rules__grid">
        <div class="dg-mail-signature-rules__card">
          <strong class="dg-mail-signature-rules__title">Postfach nach Formel</strong>
          <p class="dg-mail-signature-rules__desc">
            Adresse entspricht der Formel oben<?= $signatureExamples['formula_email'] !== '' ? ' (z.&nbsp;B. <code>' . View::escape($signatureExamples['formula_email']) . '</code>)' : '' ?>.
          </p>
          <div class="dg-mail-signature-rules__sample">
            <?php if ($signatureExamples['thanks_line'] !== '') : ?>
              <span><?= View::escape($signatureExamples['thanks_line']) ?></span><br>
            <?php endif; ?>
            <?php if ($signatureExamples['salutation'] !== '') : ?>
              <span><?= View::escape($signatureExamples['salutation']) ?></span><br>
            <?php endif; ?>
            <em><?= View::escape($signatureExamples['formula_signature_example']) ?></em>
            <small class="dg-muted">(Anzeigename des Kontakts)</small>
          </div>
        </div>
        <div class="dg-mail-signature-rules__card">
          <strong class="dg-mail-signature-rules__title">Allgemeines / manuelles Postfach</strong>
          <p class="dg-mail-signature-rules__desc">Keine Übereinstimmung mit der Formel — z.&nbsp;B. <code>info@…</code>, Legacy-Adressen.</p>
          <div class="dg-mail-signature-rules__sample">
            <?php if ($signatureExamples['thanks_line'] !== '') : ?>
              <span><?= View::escape($signatureExamples['thanks_line']) ?></span><br>
            <?php endif; ?>
            <?php if ($signatureExamples['salutation'] !== '') : ?>
              <span><?= View::escape($signatureExamples['salutation']) ?></span><br>
            <?php endif; ?>
            <em><?= View::escape($signatureExamples['generic_signature']) ?></em>
            <small class="dg-muted">(unter <a href="<?= View::escape($notificationsUrl) ?>">Benachrichtigungen → Fußzeile → Signatur</a>)</small>
          </div>
        </div>
      </div>
      <p class="dg-field-hint">Grußformel und Danke-Zeile gelten für beide Fälle und werden unter <a href="<?= View::escape($notificationsUrl) ?>">Benachrichtigungen → Fußzeile</a> gepflegt.</p>
    </div>

    <div class="dg-form-actions">
      <button type="submit" name="mail_address_save" value="1" class="dg-button dg-button--primary">Formel speichern</button>
    </div>
  </form>

  <p class="dg-lead" style="margin-top:12px">
    KAS-API: <?= $kasConfigured ? '<span class="dg-badge dg-badge--ok">konfiguriert</span>' : '<span class="dg-badge">nicht konfiguriert</span> (<code>config/kas.local.php</code>)' ?>
  </p>
</section>
<script src="<?= View::escape(Asset::url('/assets/js/settings-mail-address.js')) ?>" defer></script>

