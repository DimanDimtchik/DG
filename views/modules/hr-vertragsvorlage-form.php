<?php
/**
 * @var array<string, mixed> $contractTemplate
 * @var list<array<string, mixed>> $contractBlocks
 * @var string|null $formError
 * @var array{type: string, message: string}|null $flash
 * @var bool $canEdit
 */
$contractTemplate = is_array($contractTemplate ?? null) ? $contractTemplate : [];
$contractBlocks = is_array($contractBlocks ?? null) ? $contractBlocks : [];
$canEdit = !empty($canEdit);
$statusLabels = ContractTemplateRepository::statusLabels();
$status = (string) ($contractTemplate['status'] ?? '');
$tid = (int) ($contractTemplate['id'] ?? 0);
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Vorlage bearbeiten</h1>
      <p class="dg-lead">
        Kennung <?= View::escape((string) ($contractTemplate['code'] ?? '')) ?>
        · Status <?= View::escape($statusLabels[$status] ?? $status) ?>
        · Version <?= View::escape((string) ($contractTemplate['version'] ?? '')) ?>
        <?php
          $publishedAt = trim((string) ($contractTemplate['published_at'] ?? ''));
          if ($publishedAt !== '') :
        ?>
          · Freigegeben <?= View::escape($publishedAt) ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=hr-vertragsvorlagen">Zur Liste</a>
    </div>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape((string) $formError) ?></div>
  <?php endif; ?>

  <p class="dg-field-hint">
    Platzhalter wie <code>{{employee_name}}</code> oder <code>{{clause_salary}}</code> nicht entfernen —
    sie werden beim PDF-Erzeugen befüllt. HTML: Absätze, Listen, Fett/Kursiv.
    Speichern erzeugt einen Entwurf; Freigabe setzt eine neue Versionsnummer und protokolliert den Stand.
  </p>

  <form class="dg-form dg-panel" method="post" action="/app?page=hr-vertragsvorlage-form&amp;id=<?= $tid ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= $tid ?>">
    <input type="hidden" name="contract_template_save" value="1">

    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Titel</span>
        <input type="text" name="title" value="<?= View::escape((string) ($contractTemplate['title'] ?? '')) ?>" required maxlength="255"<?= $canEdit ? '' : ' disabled' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Interne Notiz</span>
        <textarea name="notes" rows="2" maxlength="5000"<?= $canEdit ? '' : ' disabled' ?>><?= View::escape((string) ($contractTemplate['notes'] ?? '')) ?></textarea>
      </label>
    </div>

    <h2 class="dg-subsection-title">Bausteine</h2>
    <div class="dg-stack">
      <?php foreach ($contractBlocks as $i => $block) : ?>
        <?php
          $bid = (int) ($block['id'] ?? 0);
          $enabled = (int) ($block['enabled'] ?? 1) === 1;
          $isAnnex = (int) ($block['is_annex'] ?? 0) === 1;
        ?>
        <div class="dg-panel">
          <input type="hidden" name="blocks[<?= (int) $i ?>][id]" value="<?= $bid ?>">
          <input type="hidden" name="blocks[<?= (int) $i ?>][sort_order]" value="<?= (int) $i ?>">
          <div class="dg-form-grid">
            <label class="dg-field dg-field--wide">
              <span>
                <?= View::escape((string) ($block['title'] ?? 'Abschnitt')) ?>
                <?php if ($isAnnex) : ?> · Anlage<?php endif; ?>
                · <span class="dg-muted"><?= View::escape((string) ($block['block_key'] ?? '')) ?></span>
              </span>
              <input type="text" name="blocks[<?= (int) $i ?>][title]" value="<?= View::escape((string) ($block['title'] ?? '')) ?>" maxlength="255"<?= $canEdit ? '' : ' disabled' ?>>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span>
                <input type="checkbox" name="blocks[<?= (int) $i ?>][enabled]" value="1"<?= $enabled ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?>>
                Aktiv
              </span>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Text (HTML)</span>
              <textarea name="blocks[<?= (int) $i ?>][body_html]" rows="8"<?= $canEdit ? '' : ' disabled' ?>><?= View::escape((string) ($block['body_html'] ?? '')) ?></textarea>
            </label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($canEdit) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Als Entwurf speichern</button>
        <a class="dg-button" href="/app?page=hr-vertragsvorlagen">Abbrechen</a>
      </div>
    <?php endif; ?>
  </form>

  <?php if ($canEdit) : ?>
    <form
      class="dg-form dg-panel"
      method="post"
      action="/app?page=hr-vertragsvorlage-form&amp;id=<?= $tid ?>"
      onsubmit="return confirm('Vorlage freigeben? Es wird eine neue Version gesetzt und der Stand protokolliert.');"
    >
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= $tid ?>">
      <input type="hidden" name="contract_template_publish" value="1">
      <p class="dg-lead">
        Freigabe macht den aktuellen Baustein-Stand zur gültigen Vorlage (neue Version).
        Offene Entwurfsänderungen bitte vorher speichern.
      </p>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Freigeben</button>
      </div>
    </form>
  <?php endif; ?>
</div>
