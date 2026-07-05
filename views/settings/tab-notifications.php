<?php
/**
 * @var array<string, mixed> $calendarEmailTemplates
 * @var array{send_customer_email: bool, send_admin_email: bool, notify_admin_email: string} $calendarNotificationDelivery
 * @var array<string, mixed> $emailLayout
 * @var bool $dbConnected
 */
$notificationData = $calendarEmailTemplates;
$delivery = $calendarNotificationDelivery ?? CalendarNotificationSettings::forForm();
$layout = $emailLayout ?? EmailLayoutSettings::forForm();
$softwareDesignUrl = SettingsRegistry::tabUrl('crm-darstellung');
$allTemplates = $notificationData['templates'] ?? [];
$templateOwners = $notificationData['template_owners'] ?? NotificationTemplateSettings::templateOwners();
$tokenGroups = CalendarEmailTokens::referenceGroups();
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('benachrichtigungen')) ?>" id="dg-notifications-form">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    E-Mail-Vorlagen für <strong>Terminkalender</strong> und alle <strong>Abteilungen</strong>.
    Leere Entwürfe werden nicht angezeigt. Welche Vorlagen eine Abteilung nutzt, stellen Sie unter
    <a href="<?= View::escape(SettingsRegistry::tabUrl('abteilungen')) ?>">Einstellungen → Abteilungen</a> ein.
  </p>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <div class="dg-panel__toolbar dg-panel__toolbar--lead">
    <div>
      <h3 class="dg-subsection-title">Kopf- und Fußzeile</h3>
      <p class="dg-field-hint">Gilt für alle E-Mails aus diesem Bereich — unabhängig von Terminkalender oder Abteilung.</p>
    </div>
  </div>

  <div class="dg-notify-accordion dg-notify-accordion--layout" id="dg-email-layout-accordion">
    <details class="dg-notify-section">
      <summary class="dg-notify-section__summary">
        <strong>Kopfzeile bearbeiten</strong>
        <span class="dg-muted">Logo, Titel und farbiger Balken oben in der E-Mail</span>
      </summary>
      <div class="dg-notify-section__body">
        <p class="dg-email-design-hint">
          Farben und Linkgestaltung kommen aus <a href="<?= View::escape($softwareDesignUrl) ?>">Software Design</a>.
        </p>
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>
              <input type="checkbox" name="header_show_logo" value="1"<?= !empty($layout['header_show_logo']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
              Logo in der Kopfzeile anzeigen
            </span>
            <small class="dg-field-hint">Das Logo wird unter <a href="/app?page=bilder">Bilder</a> als Firmenlogo hinterlegt. Für Dark Mode in Mail-Apps: helles Logo auf transparentem Hintergrund verwenden — der farbige Balken bleibt sichtbar.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Titel in der Kopfzeile</span>
            <input type="text" name="header_title" value="<?= View::escape((string) ($layout['header_title'] ?? '')) ?>" placeholder="<?= View::escape(CompanySettings::displayName()) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
            <small class="dg-field-hint">Leer = Firmenname aus den <a href="<?= View::escape(SettingsRegistry::tabUrl('firmendaten')) ?>">Firmendaten</a>. Platzhalter wie {firma} möglich.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Unterzeile (optional)</span>
            <input type="text" name="header_subline" value="<?= View::escape((string) ($layout['header_subline'] ?? '')) ?>" placeholder="z. B. Ihr Termin bei uns"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
        </div>

        <section class="dg-email-layout-preview" aria-label="Vorschau Kopfzeile">
          <h4 class="dg-email-layout-preview__title">Vorschau</h4>
          <div id="dg-email-header-preview"><?= CalendarEmailLayout::settingsHeaderPreview() ?></div>
        </section>
      </div>
    </details>

    <details class="dg-notify-section">
      <summary class="dg-notify-section__summary">
        <strong>Fußzeile bearbeiten</strong>
        <span class="dg-muted">Grußformel, Signatur und Firmenblock am Ende der E-Mail</span>
      </summary>
      <div class="dg-notify-section__body">
        <p class="dg-email-design-hint">
          Farben und Linkgestaltung kommen aus <a href="<?= View::escape($softwareDesignUrl) ?>">Software Design</a>.
        </p>
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Anrede (E-Mail-Anfang)</span>
            <input type="text" name="body_opening_greeting" value="<?= View::escape((string) ($layout['body_opening_greeting'] ?? '')) ?>" placeholder="Sehr geehrte Damen und Herren,"<?= !$dbConnected ? ' disabled' : '' ?>>
            <small class="dg-field-hint">Leer = Standardtext. Gilt für Post-Versand und E-Mail-Vorlagen.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Danke-Zeile</span>
            <input type="text" name="footer_thanks_line" value="<?= View::escape((string) ($layout['footer_thanks_line'] ?? '')) ?>" placeholder="Vielen Dank im Voraus"<?= !$dbConnected ? ' disabled' : '' ?>>
            <small class="dg-field-hint">Leer = Standardtext.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Grußformel</span>
            <input type="text" name="footer_salutation" value="<?= View::escape((string) ($layout['footer_salutation'] ?? '')) ?>" placeholder="Mit freundlichen Grüßen"<?= !$dbConnected ? ' disabled' : '' ?>>
            <small class="dg-field-hint">Leer = Standardtext.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Signatur (allgemeine Postfächer)</span>
            <input type="text" name="footer_signature" value="<?= View::escape((string) ($layout['footer_signature'] ?? '')) ?>" placeholder="Ihr {firma} Team"<?= !$dbConnected ? ' disabled' : '' ?>>
            <small class="dg-field-hint">
              Gilt für Postfächer <strong>ohne</strong> Adress-Formel (z.&nbsp;B. info@).
              Bei Postfächern nach <a href="<?= View::escape(SettingsRegistry::tabUrl('email')) ?>">Mitarbeiter-Formel</a> wird automatisch der <strong>Anzeigename des Kontakts</strong> verwendet.
            </small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>
              <input type="checkbox" name="footer_show_company_block" value="1"<?= !empty($layout['footer_show_company_block']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
              Firmenblock unter der Signatur anzeigen
            </span>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Firmenname im Fuß</span>
            <input type="text" name="footer_company_name" value="<?= View::escape((string) ($layout['footer_company_name'] ?? '')) ?>" placeholder="<?= View::escape(CompanySettings::displayName()) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Straße</span>
            <input type="text" name="footer_street" value="<?= View::escape((string) ($layout['footer_street'] ?? '')) ?>" placeholder="<?= View::escape((string) (CompanySettings::config()['street'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
          <label class="dg-field">
            <span>PLZ</span>
            <input type="text" name="footer_postal" value="<?= View::escape((string) ($layout['footer_postal'] ?? '')) ?>" placeholder="<?= View::escape((string) (CompanySettings::config()['postal'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
          <label class="dg-field">
            <span>Ort</span>
            <input type="text" name="footer_city" value="<?= View::escape((string) ($layout['footer_city'] ?? '')) ?>" placeholder="<?= View::escape((string) (CompanySettings::config()['city'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Website</span>
            <input type="url" name="footer_website" value="<?= View::escape((string) ($layout['footer_website'] ?? '')) ?>" placeholder="<?= View::escape((string) (CompanySettings::config()['website'] ?? 'https://')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>

          <fieldset class="dg-fieldset dg-field--wide">
            <legend class="dg-subsection-title">Social Media</legend>
            <label class="dg-field dg-field--wide">
              <span>
                <input type="checkbox" name="footer_show_social_links" value="1"<?= !empty($layout['footer_show_social_links']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
                Social-Media-Leiste im Fuß anzeigen
              </span>
              <small class="dg-field-hint">Nur ausgefüllte Profile erscheinen in der E-Mail.</small>
            </label>
            <div class="dg-form-grid">
              <?php foreach (EmailLayoutSettings::socialNetworkLabels() as $networkKey => $networkLabel) : ?>
                <label class="dg-field dg-field--wide">
                  <span><?= View::escape($networkLabel) ?></span>
                  <input
                    type="url"
                    name="footer_social_<?= View::escape($networkKey) ?>"
                    value="<?= View::escape((string) ($layout['footer_social_' . $networkKey] ?? '')) ?>"
                    placeholder="https://"
                    <?= !$dbConnected ? 'disabled' : '' ?>
                  >
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <fieldset class="dg-fieldset dg-field--wide">
            <legend class="dg-subsection-title">Rechtliche Links</legend>
            <label class="dg-field dg-field--wide">
              <span>
                <input type="checkbox" name="footer_show_legal_links" value="1"<?= !empty($layout['footer_show_legal_links']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
                Impressum, Datenschutz und AGB verlinken
              </span>
            </label>
            <div class="dg-form-grid">
              <label class="dg-field dg-field--wide">
                <span>Impressum (URL)</span>
                <input type="url" name="footer_url_impressum" value="<?= View::escape((string) ($layout['footer_url_impressum'] ?? '')) ?>" placeholder="https://ihre-domain.de/impressum"<?= !$dbConnected ? ' disabled' : '' ?>>
              </label>
              <label class="dg-field dg-field--wide">
                <span>Datenschutz (URL)</span>
                <input type="url" name="footer_url_datenschutz" value="<?= View::escape((string) ($layout['footer_url_datenschutz'] ?? '')) ?>" placeholder="https://ihre-domain.de/datenschutz"<?= !$dbConnected ? ' disabled' : '' ?>>
              </label>
              <label class="dg-field dg-field--wide">
                <span>AGB (URL)</span>
                <input type="url" name="footer_url_agb" value="<?= View::escape((string) ($layout['footer_url_agb'] ?? '')) ?>" placeholder="https://ihre-domain.de/agb"<?= !$dbConnected ? ' disabled' : '' ?>>
              </label>
            </div>
          </fieldset>

          <label class="dg-field dg-field--wide">
            <span>Zusätzlicher Text</span>
            <textarea name="footer_extra_text" rows="3" placeholder="z. B. Öffnungszeiten oder Hinweise"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($layout['footer_extra_text'] ?? '')) ?></textarea>
            <small class="dg-field-hint">Erscheint unter den Link-Leisten. Leere Felder oben werden aus den Firmendaten übernommen.</small>
          </label>
        </div>

        <section class="dg-email-layout-preview" aria-label="Vorschau Fußzeile">
          <h4 class="dg-email-layout-preview__title">Vorschau</h4>
          <div id="dg-email-footer-preview"><?= CalendarEmailLayout::settingsFooterPreview() ?></div>
        </section>
      </div>
    </details>
  </div>

  <div class="dg-panel__toolbar dg-panel__toolbar--lead">
    <div>
      <h3 class="dg-subsection-title">Vorlagen</h3>
      <p class="dg-field-hint">„+ Neue Vorlage“ legt eine zusätzliche Vorlage an — zuerst für Terminkalender oder eine Abteilung.</p>
    </div>
    <button type="button" class="dg-button dg-button--primary dg-button--small" id="dg-email-tpl-add"<?= !$dbConnected ? ' disabled' : '' ?>>+ Neue Vorlage</button>
  </div>

  <section class="dg-email-token-ref" aria-label="Platzhalter">
    <h4 class="dg-subsection-title">Platzhalter</h4>
    <?php foreach ($tokenGroups as $group) : ?>
      <div class="dg-number-range-code-group">
        <h5 class="dg-number-range-code-group__title"><?= View::escape((string) $group['title']) ?></h5>
        <ul class="dg-number-range-code-list">
          <?php foreach ($group['items'] as $item) : ?>
            <li class="dg-number-range-code-list__item">
              <span class="dg-number-range-code-list__label"><?= View::escape((string) $item['label']) ?></span>
              <span class="dg-number-range-code-list__codes">
                <?php foreach ($item['codes'] as $codeIndex => $code) : ?>
                  <?php if ($codeIndex > 0) : ?><span class="dg-number-range-code-list__sep">oder</span><?php endif; ?>
                  <button type="button" class="dg-code-chip" data-insert-token="<?= View::escape($code) ?>"><?= View::escape($code) ?></button>
                <?php endforeach; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </section>

  <div class="dg-notify-accordion" id="dg-notify-accordion">
    <?php foreach ($templateOwners as $ownerIndex => $owner) : ?>
      <?php
        $ownerId = (string) $owner['id'];
        $isCalendar = !empty($owner['is_calendar']);
        $ownerTemplates = NotificationTemplateSettings::templatesForOwner($ownerId, $allTemplates, true);
      ?>
      <details class="dg-notify-section" data-tpl-owner-section data-owner-id="<?= View::escape($ownerId) ?>">
        <summary class="dg-notify-section__summary">
          <strong><?= View::escape((string) $owner['name']) ?></strong>
          <?php if ($isCalendar) : ?>
            <span class="dg-muted">Buchungsbestätigung, Storno und interne Benachrichtigung</span>
          <?php else : ?>
            <span class="dg-muted">Freie E-Mail-Vorlagen dieser Abteilung</span>
          <?php endif; ?>
        </summary>
        <div class="dg-notify-section__body">
          <?php if ($isCalendar) : ?>
            <section class="dg-panel dg-panel--compact">
              <h4 class="dg-subsection-title">Automatischer Versand</h4>
              <div class="dg-form-grid">
                <label class="dg-field dg-field--wide">
                  <span>
                    <input type="checkbox" name="calendar_send_customer_email" value="1"<?= !empty($delivery['send_customer_email']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
                    Kunden-E-Mails senden (Bestätigung und Storno)
                  </span>
                </label>
                <label class="dg-field dg-field--wide">
                  <span>
                    <input type="checkbox" name="calendar_send_admin_email" value="1"<?= !empty($delivery['send_admin_email']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
                    Interne Benachrichtigung bei neuer Buchung
                  </span>
                </label>
                <label class="dg-field dg-field--wide">
                  <span>Empfänger interne Benachrichtigung</span>
                  <input type="email" name="calendar_notify_admin_email" value="<?= View::escape((string) ($delivery['notify_admin_email'] ?? '')) ?>" placeholder="<?= View::escape(CompanySettings::mailEmail() ?: 'studio@beispiel.de') ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
                </label>
              </div>
            </section>
          <?php endif; ?>

          <div class="dg-email-tpl-list" data-tpl-list data-owner-id="<?= View::escape($ownerId) ?>">
            <?php if ($ownerTemplates === []) : ?>
              <p class="dg-field-hint" data-tpl-empty>Noch keine ausgefüllten Vorlagen.</p>
            <?php else : ?>
              <?php foreach ($ownerTemplates as $template) : ?>
                <?php View::render('settings/partials/email-template-card', [
                    'template' => $template,
                    'disabled' => false,
                    'showCategorySelect' => false,
                ]); ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>

  <div id="dg-notification-delete-fields" hidden aria-hidden="true"></div>

  <template id="dg-email-tpl-card-template">
    <details class="dg-email-tpl-type" data-email-tpl-card data-tpl-id="" data-tpl-category="calendar" data-tpl-owner="" open>
      <summary class="dg-subsection-title dg-collapsible-form__summary dg-email-tpl-type__summary">
        <span data-tpl-summary-name>Neue Vorlage</span>
      </summary>
      <div class="dg-collapsible-form__body dg-email-tpl-type__body">
        <input type="hidden" name="" value="" data-field-id>
        <input type="hidden" name="" value="" data-field-department>
        <input type="hidden" name="" value="0" data-field-builtin>
        <input type="hidden" name="" value="" data-field-event-slug>
        <div class="dg-form-grid">
          <input type="hidden" name="" value="calendar" data-tpl-category-fixed>
          <label class="dg-field dg-field--wide">
            <span>Bezeichnung der Vorlage *</span>
            <input type="text" name="" value="" data-email-field="name" data-tpl-name placeholder="z. B. Rechnungsversand">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Betreff</span>
            <input type="text" name="" value="" data-email-field="subject" data-template-key="">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Überschrift in der E-Mail</span>
            <input type="text" name="" value="" data-email-field="title">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Einleitungstext</span>
            <textarea name="" rows="3" data-email-field="intro"></textarea>
          </label>
        </div>
        <p class="dg-form-actions dg-form-actions--split">
          <button type="button" class="dg-button dg-button--small dg-email-preview-btn" data-template-key="" data-event-slug="">Vorschau aktualisieren</button>
          <button type="button" class="dg-button dg-button--small dg-button--danger dg-email-tpl-delete" data-tpl-delete>Entwurf verwerfen</button>
        </p>
        <div class="dg-email-preview" data-email-preview="" hidden>
          <p class="dg-email-preview__subject"><strong>Betreff:</strong> <span data-email-preview-subject></span></p>
          <iframe class="dg-email-preview__frame" title="E-Mail-Vorschau" sandbox=""></iframe>
        </div>
      </div>
    </details>
  </template>

  <p class="dg-form-actions">
    <button type="submit" name="notification_templates_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Änderungen speichern</button>
  </p>
</form>

<dialog class="dg-dialog dg-dialog--tpl-owner" id="dg-tpl-owner-dialog" aria-labelledby="dg-tpl-owner-title">
  <div class="dg-dialog__inner">
    <header class="dg-dialog__header">
      <h4 class="dg-dialog__title" id="dg-tpl-owner-title">Neue Vorlage anlegen</h4>
      <p class="dg-dialog__lead">Für welche Einheit soll die Vorlage gelten?</p>
    </header>
    <div class="dg-dialog__body" id="dg-tpl-owner-panel">
      <label class="dg-field dg-field--wide">
        <span class="dg-field__label">Terminkalender oder Abteilung</span>
        <select id="dg-tpl-owner-select" class="dg-dialog__select">
          <?php foreach ($templateOwners as $owner) : ?>
            <option
              value="<?= View::escape((string) $owner['id']) ?>"
              data-is-calendar="<?= !empty($owner['is_calendar']) ? '1' : '0' ?>"
            ><?= View::escape((string) $owner['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Der Terminkalender zählt hier als eigene Einheit — wie eine Abteilung.</small>
      </label>
      <footer class="dg-dialog__footer">
        <button type="button" class="dg-button" id="dg-tpl-owner-cancel">Abbrechen</button>
        <button type="button" class="dg-button dg-button--primary" id="dg-tpl-owner-confirm">Vorlage erstellen</button>
      </footer>
    </div>
  </div>
</dialog>
