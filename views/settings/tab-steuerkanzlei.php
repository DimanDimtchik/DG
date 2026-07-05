<?php
/**
 * @var array{contact_id: int, contact: Contact|null, employees: list<array<string, mixed>>} $taxAdvisorConfig
 * @var list<array{id: int, label: string}> $taxAdvisorCompanyOptions
 * @var bool $dbConnected
 */
$selectedContact = $taxAdvisorConfig['contact'] ?? null;
$employees = $taxAdvisorConfig['employees'] ?? [];
$selectedContactId = (int) ($taxAdvisorConfig['contact_id'] ?? 0);
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('steuerkanzlei')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    Wählen Sie Ihre <strong>Steuerkanzlei</strong> als Firmen-Kontakt. Die Mitarbeiterliste wird aus den
    unter <a href="/app?page=kontakte">Kontakte</a> verknüpften Personen dieser Firma übernommen.
  </p>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <?php if ($taxAdvisorCompanyOptions === []) : ?>
    <div class="dg-panel dg-panel--notice">
      <p>Es gibt noch keine Firmen-Kontakte. Legen Sie unter <a href="/app?page=kontakte&amp;action=new">Kontakte → Neu</a> zuerst eine Firma an (Anrede <strong>Firma</strong>).</p>
    </div>
  <?php endif; ?>

  <div class="dg-form-grid">
    <label class="dg-field dg-field--wide">
      <span>Steuerkanzlei (Firma aus Kontakten)</span>
      <select name="tax_advisor_contact_id"<?= !$dbConnected ? ' disabled' : '' ?>>
        <option value="0">— keine Steuerkanzlei hinterlegt —</option>
        <?php foreach ($taxAdvisorCompanyOptions as $option) : ?>
          <option
            value="<?= (int) $option['id'] ?>"
            <?= $selectedContactId === (int) $option['id'] ? ' selected' : '' ?>
          ><?= View::escape((string) $option['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="dg-field-hint">Nur Kontakte mit Anrede „Firma“ stehen zur Auswahl.</small>
    </label>
  </div>

  <p class="dg-form-actions">
    <button type="submit" name="tax_advisor_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Steuerkanzlei speichern</button>
  </p>
</form>

<?php if ($selectedContact !== null) : ?>
  <section class="dg-panel" style="margin-top: 20px;">
    <header class="dg-panel__toolbar dg-panel__toolbar--lead">
      <div>
        <h3 class="dg-subsection-title">Stammdaten der Steuerkanzlei</h3>
        <p class="dg-field-hint">Aus dem verknüpften Firmen-Kontakt</p>
      </div>
      <a class="dg-button dg-button--small" href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $selectedContact->id ?>">Kontakt bearbeiten</a>
    </header>
    <dl class="dg-dl">
      <dt>Firma</dt>
      <dd>
        <a href="/app?page=kontakte&amp;id=<?= (int) $selectedContact->id ?>"><?= View::escape($selectedContact->listLabel()) ?></a>
      </dd>
      <dt>E-Mail</dt>
      <dd><?= View::escape($selectedContact->email !== '' ? $selectedContact->email : '—') ?></dd>
      <dt>Telefon</dt>
      <dd><?= View::escape($selectedContact->phone1 !== '' ? $selectedContact->phone1 : '—') ?></dd>
      <dt>Adresse</dt>
      <dd><?= View::escape($selectedContact->addressLine1() !== '' ? $selectedContact->addressLine1() : '—') ?></dd>
      <dt>Steuernummer</dt>
      <dd><?= View::escape($selectedContact->taxNumber !== '' ? $selectedContact->taxNumber : '—') ?></dd>
      <dt>USt-IdNr.</dt>
      <dd><?= View::escape($selectedContact->vatId !== '' ? $selectedContact->vatId : '—') ?></dd>
      <dt>Website</dt>
      <dd>
        <?php if ($selectedContact->website !== '') : ?>
          <a href="<?= View::escape($selectedContact->website) ?>" target="_blank" rel="noopener"><?= View::escape($selectedContact->website) ?></a>
        <?php else : ?>
          —
        <?php endif; ?>
      </dd>
    </dl>
  </section>

  <section class="dg-panel dg-panel--wide" style="margin-top: 20px;">
    <header class="dg-panel__toolbar dg-panel__toolbar--lead">
      <div>
        <h3 class="dg-subsection-title">Mitarbeiter der Steuerkanzlei</h3>
        <p class="dg-field-hint">Verknüpfte Personen aus dem Firmen-Kontakt — Pflege unter „Mitarbeiter der Firma“ im Kontaktformular.</p>
      </div>
      <a class="dg-button dg-button--small" href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $selectedContact->id ?>">Mitarbeiter verwalten</a>
    </header>

    <?php if ($employees === []) : ?>
      <p class="dg-field-hint">Noch keine Mitarbeiter verknüpft. Bearbeiten Sie den Firmen-Kontakt und fügen Sie unter „Mitarbeiter der Firma“ Personen hinzu.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Person</th>
              <th>Zuständigkeit</th>
              <th>E-Mail (dienstlich)</th>
              <th>Telefon (dienstlich)</th>
              <th>Erreichbarkeit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $employee) : ?>
              <tr>
                <td>
                  <a href="/app?page=kontakte&amp;id=<?= (int) $employee['person_contact_id'] ?>">
                    <?= View::escape((string) $employee['person_label']) ?>
                  </a>
                </td>
                <td><?= View::escape((string) ($employee['responsibility'] !== '' ? $employee['responsibility'] : '—')) ?></td>
                <td>
                  <?php if (($employee['work_email'] ?? '') !== '') : ?>
                    <a href="mailto:<?= View::escape((string) $employee['work_email']) ?>"><?= View::escape((string) $employee['work_email']) ?></a>
                  <?php else : ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?= View::escape((string) ($employee['work_phone'] !== '' ? $employee['work_phone'] : '—')) ?></td>
                <td><?= View::escape((string) ($employee['availability'] !== '' ? $employee['availability'] : '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
