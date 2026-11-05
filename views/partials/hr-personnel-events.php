<?php
/** @var int|null $contactId */
/** @var array<string, string> $employeeData */
/** @var bool $showEmployeeFields */
/** @var bool $canEdit */
/** @var Contact|null $hrPersonnelContact */
$contactId = (int) ($contactId ?? 0);
$employeeData = $employeeData ?? EmployeeData::empty();
$showEmployeeFields = !empty($showEmployeeFields);
$canEdit = !empty($canEdit);
if ($contactId <= 0 || !$showEmployeeFields) {
    return;
}

$events = HrPersonnelEventRepository::listForContact($contactId);
$typeLabels = HrPersonnelEventRepository::typeLabels();
$statusLabels = HrPersonnelEventRepository::statusLabels();
$channelLabels = HrPersonnelEventRepository::channelLabels();
$deadlineContact = $hrPersonnelContact ?? ContactRepository::findById($contactId);
$deadline = $deadlineContact instanceof Contact
    ? HrPersonnelDeadlineHelper::hintsForContact($deadlineContact)
    : ['ok' => false, 'lines' => [], 'disclaimer' => ''];
?>
<section class="dg-panel">
  <h2>Personalvorgänge (Abmahnung / Kündigung)</h2>
  <p class="dg-lead">Akte am Mitarbeiter. PDF-Entwürfe mit Briefpapier — Zugang und Rechtswirkung prüfen. Kein Ersatz für Rechtsberatung.</p>

  <?php if ($events === []) : ?>
    <p class="dg-lead">Noch keine Vorgänge.</p>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <thead>
          <tr>
            <th>Datum</th>
            <th>Art</th>
            <th>Titel</th>
            <th>Status</th>
            <th>Zugang</th>
            <th>Datei</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $ev) : ?>
            <?php
              $type = (string) ($ev['event_type'] ?? '');
              $status = (string) ($ev['status'] ?? '');
              $docType = (string) ($ev['file_doc_type'] ?? '');
              $fileIndex = $ev['file_index'] ?? null;
              $hasFile = $docType !== '' && $fileIndex !== null;
            ?>
            <tr>
              <td><?= View::escape(EmploymentContractContext::formatDateDe((string) ($ev['event_date'] ?? ''))) ?></td>
              <td><?= View::escape($typeLabels[$type] ?? $type) ?></td>
              <td><?= View::escape((string) ($ev['title'] ?? '')) ?></td>
              <td><?= View::escape($statusLabels[$status] ?? $status) ?></td>
              <td>
                <?php if (trim((string) ($ev['delivery_date'] ?? '')) !== '') : ?>
                  <?= View::escape(EmploymentContractContext::formatDateDe((string) $ev['delivery_date'])) ?>
                  <?php if (trim((string) ($ev['delivery_channel'] ?? '')) !== '') : ?>
                    <br><small><?= View::escape($channelLabels[(string) $ev['delivery_channel']] ?? (string) $ev['delivery_channel']) ?></small>
                  <?php endif; ?>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php if ($hasFile) : ?>
                  <div class="dg-table__actions">
                    <div class="dg-table__actions-group">
                      <a class="dg-button dg-button--small" href="<?= View::escape(EmployeeDocuments::viewUrl($contactId, $docType, (int) $fileIndex)) ?>" target="_blank" rel="noopener">Anzeigen</a>
                      <a class="dg-button dg-button--small" href="<?= View::escape(EmployeeDocuments::downloadUrl($contactId, $docType, (int) $fileIndex)) ?>">Download</a>
                    </div>
                  </div>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($canEdit) : ?>
    <form class="dg-form" method="post" action="/app?page=kontakte&amp;action=edit&amp;id=<?= $contactId ?>" enctype="multipart/form-data">
      <h3>Aktennotiz / Scan hochladen</h3>
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= $contactId ?>">
      <input type="hidden" name="hr_personnel_add_note" value="1">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Datum</span>
          <input type="date" name="event_date" value="<?= View::escape(date('Y-m-d')) ?>" required>
        </label>
        <label class="dg-field">
          <span>Ablage</span>
          <select name="file_doc_type">
            <option value="written_warnings">Bei Abmahnungen</option>
            <option value="termination_letters">Bei Kündigungen</option>
          </select>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Titel</span>
          <input type="text" name="title" maxlength="255" placeholder="z. B. Scan Abmahnung">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Kurztext</span>
          <textarea name="summary" rows="2"></textarea>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Datei (PDF/Bild, optional)</span>
          <input type="file" name="hr_event_file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Notiz speichern</button>
      </div>
    </form>

    <form class="dg-form" method="post" action="/app?page=kontakte&amp;action=edit&amp;id=<?= $contactId ?>">
      <h3>Abmahnung erzeugen</h3>
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= $contactId ?>">
      <input type="hidden" name="hr_personnel_generate_warning" value="1">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Datum</span>
          <input type="date" name="event_date" value="<?= View::escape(date('Y-m-d')) ?>" required>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Titel</span>
          <input type="text" name="title" value="Abmahnung" maxlength="255">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Sachverhalt</span>
          <textarea name="facts_text" rows="4" required></textarea>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Erwartung (optional)</span>
          <textarea name="expectation_text" rows="2" placeholder="Leer = Standardtext"></textarea>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Androhung (optional)</span>
          <textarea name="threat_text" rows="2" placeholder="Leer = Standardtext"></textarea>
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Abmahnung als PDF</button>
      </div>
    </form>

    <form class="dg-form" method="post" action="/app?page=kontakte&amp;action=edit&amp;id=<?= $contactId ?>">
      <h3>Kündigung / Aufhebung erzeugen</h3>
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= $contactId ?>">
      <input type="hidden" name="hr_personnel_generate_termination" value="1">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Art</span>
          <select name="event_type">
            <option value="<?= View::escape(HrPersonnelEventRepository::TYPE_TERM_ORDINARY) ?>">Ordentliche Kündigung</option>
            <option value="<?= View::escape(HrPersonnelEventRepository::TYPE_TERM_SUMMARY) ?>">Fristlose Kündigung</option>
            <option value="<?= View::escape(HrPersonnelEventRepository::TYPE_TERM_AGREEMENT) ?>">Aufhebungsvertrag</option>
          </select>
        </label>
        <label class="dg-field">
          <span>Schreiben vom</span>
          <input type="date" name="event_date" value="<?= View::escape(date('Y-m-d')) ?>" required>
        </label>
        <label class="dg-field">
          <span>Beendigung zum</span>
          <input type="date" name="end_date" required>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Begründung / Hinweise</span>
          <textarea name="facts_text" rows="3" placeholder="Bei fristloser Kündigung Pflicht"></textarea>
        </label>
      </div>
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span><input type="checkbox" name="set_exit_date" value="1"> Austrittsdatum im Mitarbeiterdatensatz auf „Beendigung zum“ setzen</span>
      </label>
      <p class="dg-field-hint">Nach Austritt: Lohnlauf und SV-Abmeldung prüfen.</p>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Schreiben als PDF</button>
      </div>
    </form>

    <?php if ($events !== []) : ?>
      <form class="dg-form" method="post" action="/app?page=kontakte&amp;action=edit&amp;id=<?= $contactId ?>">
        <h3>Zugang dokumentieren</h3>
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= $contactId ?>">
        <input type="hidden" name="hr_personnel_update_delivery" value="1">
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Vorgang</span>
            <select name="event_id" required>
              <?php foreach ($events as $ev) : ?>
                <option value="<?= (int) $ev['id'] ?>">
                  <?= View::escape(EmploymentContractContext::formatDateDe((string) $ev['event_date']) . ' — ' . ($typeLabels[(string) $ev['event_type']] ?? '') . ' — ' . (string) $ev['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Zugangsdatum</span>
            <input type="date" name="delivery_date" value="<?= View::escape(date('Y-m-d')) ?>">
          </label>
          <label class="dg-field">
            <span>Kanal</span>
            <select name="delivery_channel">
              <option value="">—</option>
              <?php foreach ($channelLabels as $ck => $cl) : ?>
                <option value="<?= View::escape($ck) ?>"><?= View::escape($cl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Aktennotiz Zugang</span>
            <textarea name="delivery_note" rows="2"></textarea>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Zugang speichern</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <div class="dg-stack">
    <h3>Fristenhilfe</h3>
    <p class="dg-field-hint"><?= View::escape((string) ($deadline['disclaimer'] ?? '')) ?></p>
    <?php if (!empty($deadline['lines']) && is_array($deadline['lines'])) : ?>
      <ul>
        <?php foreach ($deadline['lines'] as $line) : ?>
          <li><?= View::escape((string) $line) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
