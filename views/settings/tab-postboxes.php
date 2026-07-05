<?php

/** @var list<array<string, mixed>> $postboxes */

/** @var list<array{user_id: int, label: string}> $postboxMemberOptions */

/** @var bool $kasConfigured */

$postboxes = $postboxes ?? [];

$postboxMemberOptions = $postboxMemberOptions ?? MailboxMemberResolver::staffOptions();

$kasConfigured = $kasConfigured ?? KasSettings::isConfigured();

$editId = (int) ($_GET['edit'] ?? 0);

$editBox = $editId > 0 ? MailboxRepository::findById($editId) : null;

$editMembers = $editBox ? MailboxRepository::memberUserIds($editId) : [];

$presetLabels = MailboxProviderPresets::labels();

$presetGroups = MailboxProviderPresets::groupedLabels();

$presetGroupLabels = MailboxProviderPresets::groupLabels();

$currentPreset = (string) ($editBox['provider_preset'] ?? 'manual');

$presetHints = MailboxProviderPresets::connectionDefaults($currentPreset, (string) ($editBox['email_address'] ?? ''));

$presetNote = MailboxProviderPresets::presetNote($currentPreset);

$postboxCount = count($postboxes);

?>

<div class="dg-notify-accordion" id="dg-postboxes-accordion">

  <details class="dg-notify-section">

    <summary class="dg-notify-section__summary">

      <strong><?= $editBox ? 'Postfach bearbeiten' : 'Postfach anlegen' ?></strong>

      <span class="dg-muted">Bezeichnung, Anbieter, IMAP- und SMTP-Zugangsdaten</span>

    </summary>

    <div class="dg-notify-section__body">

      <?php if (!$kasConfigured) : ?>

        <div class="dg-flash dg-flash--warning">

          <strong>KAS-API nicht konfiguriert.</strong> Automatische Postfächer bei All-Inkl werden nicht angelegt, solange <code>config/kas.local.php</code> fehlt.

          Vorlage: <code>config/kas.local.php.example</code>

        </div>

      <?php endif; ?>

      <p class="dg-lead">

        <strong>Empfang:</strong> IMAP-Daten in den IMAP→Webhook-Dienst eintragen + Webhook-URL in der Übersicht.<br>

        <strong>Versand:</strong> SMTP-Daten hier hinterlegen (z.&nbsp;B. Google, Legacy-Adresse, Kasserver).

      </p>



      <form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('postfaecher')) ?>">

        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

        <?php if ($editBox) : ?>

          <input type="hidden" name="mailbox_id" value="<?= (int) $editBox['id'] ?>">

        <?php endif; ?>



        <div class="dg-form-grid">

          <label class="dg-field">

            <span>Bezeichnung</span>

            <input name="mailbox_name" value="<?= View::escape((string) ($editBox['name'] ?? '')) ?>" placeholder="z. B. Google / Legacy Info">

          </label>

          <label class="dg-field">

            <span>E-Mail-Adresse *</span>

            <input type="email" name="mailbox_email" required value="<?= View::escape((string) ($editBox['email_address'] ?? '')) ?>"<?= $editBox ? ' readonly' : '' ?>>

          </label>

          <label class="dg-field">

            <span>Absendername (optional)</span>

            <input name="from_name" value="<?= View::escape((string) ($editBox['from_name'] ?? '')) ?>" placeholder="Anzeige beim Versand">

          </label>

          <label class="dg-field">

            <span>Mail-Anbieter</span>

            <select name="provider_preset">

              <?php foreach ($presetGroupLabels as $groupId => $groupLabel) : ?>

                <?php if (!empty($presetGroups[$groupId])) : ?>

                  <optgroup label="<?= View::escape($groupLabel) ?>">

                    <?php foreach ($presetGroups[$groupId] as $presetId => $presetLabel) : ?>

                      <option value="<?= View::escape($presetId) ?>"<?= $currentPreset === $presetId ? ' selected' : '' ?>><?= View::escape($presetLabel) ?></option>

                    <?php endforeach; ?>

                  </optgroup>

                <?php endif; ?>

              <?php endforeach; ?>

            </select>

            <?php if ($presetNote !== '') : ?>

              <small class="dg-field-hint"><?= View::escape($presetNote) ?></small>

            <?php endif; ?>

          </label>

          <?php if (!$editBox && $kasConfigured) : ?>

            <label class="dg-field dg-field--wide">

              <span><input type="checkbox" name="provision_kas" value="1"> Stattdessen bei Kasserver per KAS-API anlegen</span>

            </label>

          <?php endif; ?>

        </div>



        <h4>IMAP (Empfang → Webhook-Dienst)</h4>

        <div class="dg-form-grid">

          <label class="dg-field"><span>Host</span><input name="imap_host" value="<?= View::escape((string) ($editBox['imap_host'] ?? $presetHints['imap_host'])) ?>" placeholder="<?= View::escape($presetHints['imap_host']) ?>"></label>

          <label class="dg-field"><span>Port</span><input type="number" name="imap_port" value="<?= (int) ($editBox['imap_port'] ?? $presetHints['imap_port']) ?>"></label>

          <label class="dg-field">

            <span>Verschlüsselung</span>

            <select name="imap_encryption">

              <?php foreach (['ssl' => 'SSL', 'tls' => 'TLS', '' => 'Keine'] as $val => $lbl) : ?>

                <option value="<?= View::escape($val) ?>"<?= ((string) ($editBox['imap_encryption'] ?? $presetHints['imap_encryption']) === $val) ? ' selected' : '' ?>><?= View::escape($lbl) ?></option>

              <?php endforeach; ?>

            </select>

          </label>

          <label class="dg-field"><span>Benutzer</span><input name="imap_username" value="<?= View::escape((string) ($editBox['imap_username'] ?? '')) ?>" placeholder="<?= View::escape($presetHints['imap_username_hint']) ?>"></label>

          <label class="dg-field dg-field--wide">

            <span>Passwort</span>

            <input type="password" name="imap_password" value="" placeholder="<?= ($editBox && ($editBox['imap_password'] ?? '') !== '') ? 'Leer = gespeichertes Passwort' : 'App-Passwort / Postfach-Passwort' ?>">

          </label>

        </div>



        <h4>SMTP (Versand aus Post)</h4>

        <div class="dg-form-grid">

          <label class="dg-field"><span>Host</span><input name="smtp_host" value="<?= View::escape((string) ($editBox['smtp_host'] ?? $presetHints['smtp_host'])) ?>"></label>

          <label class="dg-field"><span>Port</span><input type="number" name="smtp_port" value="<?= (int) ($editBox['smtp_port'] ?? $presetHints['smtp_port']) ?>"></label>

          <label class="dg-field">

            <span>Verschlüsselung</span>

            <select name="smtp_encryption">

              <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', '' => 'Keine'] as $val => $lbl) : ?>

                <option value="<?= View::escape($val) ?>"<?= ((string) ($editBox['smtp_encryption'] ?? $presetHints['smtp_encryption']) === $val) ? ' selected' : '' ?>><?= View::escape($lbl) ?></option>

              <?php endforeach; ?>

            </select>

          </label>

          <label class="dg-field"><span>Benutzer</span><input name="smtp_username" value="<?= View::escape((string) ($editBox['smtp_username'] ?? '')) ?>" placeholder="<?= View::escape($presetHints['smtp_username_hint']) ?>"></label>

          <label class="dg-field dg-field--wide">

            <span>Passwort</span>

            <input type="password" name="smtp_password" value="" placeholder="<?= ($editBox && ($editBox['smtp_password'] ?? '') !== '') ? 'Leer = gespeichertes Passwort' : '' ?>">

          </label>

        </div>



        <?php if (($editBox['type'] ?? 'shared') === 'shared' || !$editBox) : ?>

          <label class="dg-field dg-field--wide">

            <span>Berechtigte Mitarbeiter (nur CRM-Benutzer mit aktivem Konto)</span>

            <select name="mailbox_members[]" multiple size="8" class="dg-select-multi">

              <?php foreach ($postboxMemberOptions as $opt) : ?>

                <option value="<?= (int) $opt['user_id'] ?>"<?= in_array((int) $opt['user_id'], $editMembers, true) ? ' selected' : '' ?>><?= View::escape($opt['label']) ?></option>

              <?php endforeach; ?>

            </select>

          </label>

        <?php endif; ?>



        <div class="dg-form-actions">

          <button type="submit" name="postbox_save" value="1" class="dg-button dg-button--primary"><?= $editBox ? 'Speichern' : 'Postfach anlegen' ?></button>

          <?php if ($editBox) : ?>

            <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('postfaecher')) ?>">Abbrechen</a>

          <?php endif; ?>

        </div>

      </form>

    </div>

  </details>



  <details class="dg-notify-section">

    <summary class="dg-notify-section__summary">

      <strong>Postfächer<?= $postboxCount > 0 ? ' (' . $postboxCount . ')' : '' ?></strong>

      <span class="dg-muted">Übersicht, All-Inkl-Status, Webhook-URLs und Bearbeiten</span>

    </summary>

    <div class="dg-notify-section__body">

      <?php if ($postboxes === []) : ?>

        <p class="dg-lead">Noch keine Postfächer.</p>

      <?php else : ?>

        <div class="dg-table-wrap">

          <table class="dg-table">

            <thead>

              <tr>

                <th>Typ</th>

                <th>Name</th>

                <th>Adresse</th>

                <th>Anbieter</th>

                <th>All-Inkl</th>

                <th>SMTP</th>

                <th>Webhook</th>

                <th></th>

              </tr>

            </thead>

            <tbody>

              <?php foreach ($postboxes as $box) : ?>

                <tr>

                  <td><?= ($box['type'] ?? '') === 'private' ? 'Privat' : 'Allgemein' ?></td>

                  <td><?= View::escape((string) ($box['name'] ?? '')) ?></td>

                  <td><code><?= View::escape((string) ($box['email_address'] ?? '')) ?></code></td>

                  <td><?= View::escape((string) (MailboxProviderPresets::labels()[$box['provider_preset'] ?? 'manual'] ?? 'Manuell')) ?></td>

                  <td>

                    <?php if (!empty($box['kas_provisioned'])) : ?>

                      ✓ angelegt

                      <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('postfaecher')) ?>" class="dg-inline-form" style="margin-top:6px">

                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

                        <input type="hidden" name="mailbox_id" value="<?= (int) $box['id'] ?>">

                        <button type="submit" name="postbox_repair_imap" value="1" class="dg-button dg-button--small" title="Setzt das Passwort bei All-Inkl zurück und speichert es im CRM">IMAP-Passwort zurücksetzen</button>

                      </form>

                    <?php elseif ($kasConfigured) : ?>

                      <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('postfaecher')) ?>" class="dg-inline-form">

                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

                        <input type="hidden" name="mailbox_id" value="<?= (int) $box['id'] ?>">

                        <button type="submit" name="postbox_provision_kas" value="1" class="dg-button dg-button--small">Bei All-Inkl anlegen</button>

                      </form>

                    <?php else : ?>

                      <span class="dg-badge">nur CRM</span>

                    <?php endif; ?>

                  </td>

                  <td><?= MailboxRepository::smtpIsConfigured($box) ? '✓' : '—' ?></td>

                  <td>

                    <input type="text" class="dg-input-copy" readonly value="<?= View::escape(MailboxRepository::inboundWebhookUrl($box)) ?>" onclick="this.select()">

                  </td>

                  <td><a href="<?= View::escape(SettingsRegistry::tabUrl('postfaecher') . '&edit=' . (int) $box['id']) ?>">Bearbeiten</a></td>

                </tr>

              <?php endforeach; ?>

            </tbody>

          </table>

        </div>

      <?php endif; ?>

    </div>

  </details>

</div>

