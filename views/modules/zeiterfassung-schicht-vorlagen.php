<?php
/**
 * @var list<array<string, mixed>> $timeShiftTemplates
 * @var array<string, mixed>|null $timeShiftTemplateEdit
 * @var array{type: string, message: string}|null $flash
 */
$templates = is_array($timeShiftTemplates ?? null) ? $timeShiftTemplates : [];
$edit = is_array($timeShiftTemplateEdit ?? null) ? $timeShiftTemplateEdit : null;
$editId = (int) ($edit['id'] ?? 0);
?>
<div class="dg-wrap dg-zeiterfassung-schicht-vorlagen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Schicht-Vorlagen</h1>
      <p class="dg-lead">Z3b — Früh/Spät/Nacht oder frei; Zuordnung folgt in Z3c</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-schichten">Schichtplan</a>
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <a class="dg-button" href="/app?page=zeiterfassung-konto">Korrektur / Konto</a>
    </div>
  </header>

  <section class="dg-panel">
    <h2 class="dg-subsection-title"><?= $editId > 0 ? 'Vorlage bearbeiten' : 'Neue Vorlage' ?></h2>
    <form method="post" action="/app?page=zeiterfassung-schicht-vorlagen" class="dg-form">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="shift_template_action" value="save">
      <?php if ($editId > 0) : ?>
        <input type="hidden" name="id" value="<?= $editId ?>">
      <?php endif; ?>
      <label class="dg-field">
        <span class="dg-field-label">Name</span>
        <input type="text" name="name" required maxlength="80"
               value="<?= View::escape((string) ($edit['name'] ?? '')) ?>"
               placeholder="z. B. Früh">
      </label>
      <label class="dg-field">
        <span class="dg-field-label">Start</span>
        <input type="time" name="start_time" required
               value="<?= View::escape((string) ($edit['start_time_hm'] ?? '06:00')) ?>">
      </label>
      <label class="dg-field">
        <span class="dg-field-label">Ende</span>
        <input type="time" name="end_time" required
               value="<?= View::escape((string) ($edit['end_time_hm'] ?? '14:00')) ?>">
      </label>
      <label class="dg-field">
        <span class="dg-field-label">Sortierung</span>
        <input type="number" name="sort_order" step="1"
               value="<?= (int) ($edit['sort_order'] ?? 0) ?>">
      </label>
      <label class="dg-field">
        <input type="checkbox" name="active" value="1"<?= ($edit === null || !empty($edit['active'])) ? ' checked' : '' ?>>
        Aktiv
      </label>
      <p class="dg-field-hint">Ende ≤ Start = Nacht über Mitternacht (Soll-Dauer = Rest + Ende).</p>
      <button type="submit" class="dg-button dg-button--primary"><?= $editId > 0 ? 'Speichern' : 'Anlegen' ?></button>
      <?php if ($editId > 0) : ?>
        <a class="dg-button" href="/app?page=zeiterfassung-schicht-vorlagen">Abbrechen</a>
      <?php endif; ?>
    </form>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Vorlagen</h2>
    <?php if ($templates === []) : ?>
      <p class="dg-muted">Noch keine Vorlagen (Migration 091 prüfen).</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Zeit</th>
              <th class="dg-table__num">Dauer</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($templates as $tpl) : ?>
              <tr>
                <td><?= View::escape((string) ($tpl['name'] ?? '')) ?></td>
                <td>
                  <?= View::escape((string) ($tpl['start_time_hm'] ?? '')) ?>
                  –
                  <?= View::escape((string) ($tpl['end_time_hm'] ?? '')) ?>
                  <?= !empty($tpl['overnight']) ? ' <span class="dg-muted">(Nacht)</span>' : '' ?>
                </td>
                <td class="dg-table__num"><?= View::escape((string) ($tpl['duration_display'] ?? '')) ?> h</td>
                <td><?= !empty($tpl['active']) ? 'aktiv' : 'inaktiv' ?></td>
                <td>
                  <a class="dg-button dg-button--small" href="/app?page=zeiterfassung-schicht-vorlagen&amp;id=<?= (int) ($tpl['id'] ?? 0) ?>">Bearbeiten</a>
                  <form method="post" action="/app?page=zeiterfassung-schicht-vorlagen" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                    <input type="hidden" name="shift_template_action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) ($tpl['id'] ?? 0) ?>">
                    <input type="hidden" name="active" value="<?= !empty($tpl['active']) ? '0' : '1' ?>">
                    <button type="submit" class="dg-button dg-button--small"><?= !empty($tpl['active']) ? 'Deaktivieren' : 'Aktivieren' ?></button>
                  </form>
                  <form method="post" action="/app?page=zeiterfassung-schicht-vorlagen" style="display:inline"
                        onsubmit="return confirm('Vorlage wirklich löschen?');">
                    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                    <input type="hidden" name="shift_template_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) ($tpl['id'] ?? 0) ?>">
                    <button type="submit" class="dg-button dg-button--small dg-button--danger">Löschen</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
