<?php
/**
 * @var array<string, mixed> $elsterConfig
 * @var bool $dbConnected
 */
$cfg = $elsterConfig ?? ElsterSettings::forForm();
$readiness = is_array($cfg['readiness'] ?? null) ? $cfg['readiness'] : ElsterEricClient::readiness();
$supportsEric = (bool) ($cfg['server_supports_eric'] ?? false);
$mode = (string) ($cfg['mode'] ?? ElsterSettings::MODE_CSV);
$expiresAt = (string) ($cfg['certificate_expires_at'] ?? '');
$expiryStatus = (string) ($cfg['expiry_status'] ?? 'unknown');
$daysLeft = $cfg['days_until_expiry'] ?? null;
$loginUrl = (string) ($cfg['login_url'] ?? ElsterCertificateStorage::LOGIN_URL);
$buchhaltungMails = is_array($cfg['buchhaltung_emails'] ?? null) ? $cfg['buchhaltung_emails'] : [];
$gfMails = is_array($cfg['gf_emails'] ?? null) ? $cfg['gf_emails'] : [];
$previewRecipients = is_array($cfg['reminder_recipients_preview'] ?? null) ? $cfg['reminder_recipients_preview'] : [];
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('elster')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <div class="dg-panel dg-panel--notice">
    <p>
      <strong>Aktuell:</strong> UStVA und EÜR als <strong>CSV</strong> für manuelle ELSTER-Eingabe
      (Buchhaltung → UStVA). Direkte Übermittlung via <strong>ERiC</strong> ist vorbereitet,
      aber erst nach dem Umzug auf einen Root-Server (Favorit: <strong>Hetzner SX65-2</strong>).
    </p>
    <p>
      Zertifikat und Passwort können jetzt schon hinterlegt werden (Frist + Erinnerung).
      Ein automatischer Login bei Mein ELSTER per Link ist technisch nicht möglich —
      der Link öffnet nur die Anmeldeseite.
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <fieldset class="dg-fieldset dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Betriebsmodus</legend>
    <label class="dg-field dg-field--wide">
      <span>ELSTER-Modus</span>
      <select name="elster_mode">
        <option value="csv"<?= $mode === ElsterSettings::MODE_CSV ? ' selected' : '' ?>>
          CSV — Kennziffern exportieren, manuell in ELSTER eintragen (Standard)
        </option>
        <option value="eric"<?= $mode === ElsterSettings::MODE_ERIC ? ' selected' : '' ?> <?= !$supportsEric ? ' disabled' : '' ?>>
          ERiC — direkte Übermittlung ans Finanzamt (nur nach Server-Umzug)
        </option>
      </select>
      <?php if (!$supportsEric) : ?>
        <small class="dg-field-hint">
          ERiC ist auf dem derzeitigen Kasserver nicht verfügbar. Nach Umzug
          <code>config/elster.local.php</code> anlegen und <code>server_ready = true</code> setzen.
        </small>
      <?php endif; ?>
    </label>

    <label class="dg-field">
      <span>Hersteller-Kennung (Software)</span>
      <input type="text" name="elster_manufacturer_id" value="<?= View::escape((string) ($cfg['manufacturer_id'] ?? '')) ?>" placeholder="z. B. 34573">
      <small class="dg-field-hint">
        Kennung für ERiC — nur für die Software-Anbindung, nicht für Endnutzer im CRM.
      </small>
    </label>

    <label class="dg-field dg-field--wide">
      <span>ERiC-Worker-URL (Vorbereitung)</span>
      <input type="url" name="elster_eric_worker_url" value="<?= View::escape((string) ($cfg['eric_worker_url'] ?? '')) ?>" placeholder="http://127.0.0.1:9109">
      <small class="dg-field-hint">Interner Worker nach ERiC-Installation — nicht öffentlich ohne Absicherung.</small>
    </label>

    <label class="dg-field">
      <span>Testmodus (für spätere ERiC-Tests)</span>
      <select name="elster_eric_test_mode">
        <option value="1"<?= !empty($cfg['eric_test_mode']) ? ' selected' : '' ?>>Ja — Testmerker + Test-Zertifikat</option>
        <option value="0"<?= empty($cfg['eric_test_mode']) ? ' selected' : '' ?>>Nein — Produktions-Abgaben</option>
      </select>
    </label>
  </fieldset>

  <p class="dg-form-actions">
    <button type="submit" name="elster_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Betriebsmodus speichern</button>
  </p>
</form>

<div class="dg-stack">
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('elster')) ?>" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <fieldset class="dg-fieldset dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Softwarezertifikat (Mein ELSTER)</legend>

    <div class="dg-field dg-field--wide">
      <span>Anmeldung bei ELSTER</span>
      <p class="dg-muted">
        Öffnet die offizielle Anmeldeseite. Zertifikat und Passwort müssen Sie dort selbst wählen —
        das CRM kann diese nicht vorausfüllen.
      </p>
      <p>
        <a class="dg-button dg-button--secondary" href="<?= View::escape($loginUrl) ?>" target="_blank" rel="noopener noreferrer">
          Zur ELSTER-Anmeldeseite
        </a>
      </p>
    </div>

    <div class="dg-field dg-field--wide">
      <span>Status</span>
      <?php if (!empty($cfg['certificate_uploaded'])) : ?>
        <p>
          Datei: <strong><?= View::escape((string) ($cfg['certificate_label'] ?? 'hinterlegt')) ?></strong>
          <?php if (trim((string) ($cfg['certificate_subject'] ?? '')) !== '') : ?>
            <span class="dg-muted"> (<?= View::escape((string) $cfg['certificate_subject']) ?>)</span>
          <?php endif; ?>
        </p>
        <p>
          Ablauf:
          <?php if ($expiresAt !== '') : ?>
            <strong><?= View::escape($expiresAt) ?></strong>
            <?php if ($daysLeft !== null) : ?>
              <span class="dg-badge <?= $expiryStatus === 'ok' || $expiryStatus === 'soon' ? 'dg-badge--ok' : 'dg-badge--pending' ?>">
                <?php if ((int) $daysLeft < 0) : ?>
                  abgelaufen
                <?php elseif ((int) $daysLeft === 0) : ?>
                  läuft heute ab
                <?php else : ?>
                  noch <?= (int) $daysLeft ?> Tag(e)
                <?php endif; ?>
              </span>
            <?php endif; ?>
          <?php else : ?>
            <span class="dg-muted">nicht gesetzt</span>
          <?php endif; ?>
        </p>
        <p>Passwort: <?= !empty($cfg['certificate_password_set']) ? 'gespeichert (verschlüsselt)' : 'fehlt' ?></p>
      <?php else : ?>
        <p class="dg-muted">Noch kein Zertifikat hinterlegt.</p>
      <?php endif; ?>
    </div>

    <label class="dg-field dg-field--wide">
      <span>Zertifikatsdatei (.pfx / .p12)</span>
      <input type="file" name="elster_certificate" accept=".pfx,.p12,application/x-pkcs12">
      <small class="dg-field-hint">Beim Upload Passwort angeben — das CRM prüft die Datei und liest das Ablaufdatum.</small>
    </label>

    <label class="dg-field">
      <span>Ablaufdatum</span>
      <input type="date" name="elster_certificate_expires_at" value="<?= View::escape($expiresAt) ?>">
      <small class="dg-field-hint">Wird beim Upload aus dem Zertifikat übernommen; bei Bedarf manuell korrigieren.</small>
    </label>

    <label class="dg-field">
      <span>Erinnerungen</span>
      <select name="elster_certificate_reminder_enabled">
        <option value="1"<?= !empty($cfg['reminder_enabled']) ? ' selected' : '' ?>>Ja — 90 / 40 / 30 / 2 Tage vorher</option>
        <option value="0"<?= empty($cfg['reminder_enabled']) ? ' selected' : '' ?>>Nein</option>
      </select>
      <small class="dg-field-hint">
        E-Mail an Buchhaltung. An die Geschäftsführung nur, wenn im CRM keine Buchhaltungs-Mitarbeiter hinterlegt sind.
      </small>
    </label>

    <label class="dg-field">
      <span>Passwort (Zertifikatsdatei)</span>
      <input type="password" name="elster_certificate_password" autocomplete="new-password" minlength="10" maxlength="128" placeholder="<?= !empty($cfg['certificate_password_set']) ? '•••••••• (unverändert lassen)' : '' ?>">
      <small class="dg-field-hint">
        10–128 Zeichen, Buchstaben und Ziffern, erlaubte ELSTER-Sonderzeichen.
        Wird verschlüsselt gespeichert (für spätere ERiC-Abgaben) — nicht im Klartext angezeigt.
      </small>
    </label>

    <label class="dg-field">
      <span>Passwort wiederholen</span>
      <input type="password" name="elster_certificate_password_confirm" autocomplete="new-password" minlength="10" maxlength="128">
    </label>

    <label class="dg-field dg-field--wide">
      <span class="dg-checkbox">
        <input type="checkbox" name="elster_certificate_clear_password" value="1">
        Gespeichertes Passwort löschen
      </span>
    </label>

    <?php if (!empty($cfg['certificate_uploaded'])) : ?>
      <label class="dg-field dg-field--wide">
        <span class="dg-checkbox">
          <input type="checkbox" name="elster_certificate_remove" value="1">
          Zertifikatsdatei aus dem CRM entfernen
        </span>
      </label>
    <?php endif; ?>

    <div class="dg-field dg-field--wide">
      <span>Erinnerungsempfänger (Vorschau)</span>
      <?php if ($buchhaltungMails !== []) : ?>
        <p>Buchhaltung: <?= View::escape(implode(', ', $buchhaltungMails)) ?></p>
        <p class="dg-muted">GF wird nicht angeschrieben (Buchhalter vorhanden).</p>
      <?php else : ?>
        <p class="dg-muted">Keine Buchhaltung-Mitglieder mit E-Mail — Fallback Geschäftsführung / Firma:</p>
        <p><?= $previewRecipients !== [] ? View::escape(implode(', ', $previewRecipients)) : '— bitte E-Mails in Abteilungen oder Firmendaten hinterlegen' ?></p>
        <?php if ($gfMails !== []) : ?>
          <p class="dg-muted">GF-Kandidaten: <?= View::escape(implode(', ', $gfMails)) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </fieldset>

  <p class="dg-form-actions">
    <button type="submit" name="elster_certificate_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Zertifikat speichern</button>
  </p>
</form>

  <section class="dg-panel">
    <h3 class="dg-subsection-title">Readiness-Check</h3>
    <ul>
      <?php foreach ($readiness['items'] as $item) : ?>
        <li>
          <span class="dg-badge <?= !empty($item['ok']) ? 'dg-badge--ok' : 'dg-badge--pending' ?>">
            <?= !empty($item['ok']) ? 'OK' : 'Offen' ?>
          </span>
          <?= View::escape((string) ($item['label'] ?? '')) ?>
          — <?= View::escape((string) ($item['detail'] ?? '')) ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="dg-muted">CLI: <code>php bin/elster-readiness.php</code></p>
  </section>

  <section class="dg-panel">
    <h3 class="dg-subsection-title">Server-Favorit &amp; Mindestanforderungen</h3>
    <dl class="dg-dl">
      <div><dt>Favorit</dt><dd>Hetzner SX65-2 (Dedicated Storage, Ryzen 7 3700X, 64 GB RAM, NVMe + HDD)</dd></div>
      <div><dt>Minimum ERiC</dt><dd>2 vCPU, 2 GB RAM, 20 GB SSD, Linux x64</dd></div>
      <div><dt>Empfohlen DG+ERiC</dt><dd>4+ Kerne, 8–16 GB RAM, 40 GB NVMe (+ Backup-Disk)</dd></div>
      <div><dt>Derzeit</dt><dd>Kasserver Shared Hosting — kein Docker, kein ERiC</dd></div>
    </dl>
  </section>
</div>
