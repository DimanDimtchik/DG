<?php
/**
 * @var array<string, mixed> $giftVoucherForm
 * @var int|null $giftVoucherId
 * @var string $giftFormMode view|new
 * @var list<array<string, mixed>> $giftFlyerTemplates
 * @var array<string, mixed>|null $giftFlyerTemplate
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $giftVoucherForm ?? [];
$mode = $giftFormMode ?? 'new';
$isView = $mode === 'view';
$id = (int) ($giftVoucherId ?? 0);
$typeLabels = GiftVoucherRepository::typeOptions();
$templates = $giftFlyerTemplates ?? [];
$selectedTpl = $giftFlyerTemplate ?? null;
$occasionLabels = FlyerService::occasionLabels();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="/app?page=gutscheine">← Zurück</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= $isView ? 'Gutschein' : 'Gutschein ausstellen' ?></h1>
    <p class="dg-lead">Mehrzweckgutschein — beim Verkauf fällt noch keine USt an. Optional mit Flyer-Formular (Nummer wird mitgedruckt).</p>
  </header>

  <?php if ($isView) : ?>
    <div class="dg-panel">
      <dl class="dg-dl">
        <dt>Code</dt><dd><strong><?= View::escape((string) ($form['code'] ?? '')) ?></strong></dd>
        <dt>Typ</dt><dd><?= View::escape($typeLabels[(string) ($form['voucher_type'] ?? '')] ?? '') ?></dd>
        <dt>Betrag</dt><dd><?= View::escape(number_format((float) ($form['face_value_gross'] ?? $form['amount'] ?? 0), 2, ',', '.')) ?> €</dd>
        <dt>Restwert</dt><dd><?= View::escape(number_format((float) ($form['remaining_gross'] ?? $form['remaining'] ?? 0), 2, ',', '.')) ?> €</dd>
        <dt>Gültig bis</dt><dd><?= View::escape((string) ($form['valid_until'] ?? '—')) ?></dd>
        <dt>Titel</dt><dd><?= View::escape((string) ($form['title'] ?? '—')) ?></dd>
        <dt>Flyer</dt>
        <dd>
          <?php if (is_array($selectedTpl)) : ?>
            <?= View::escape((string) ($selectedTpl['title'] ?? '')) ?>
            <?php
              $occ = (string) ($selectedTpl['occasion'] ?? '');
              $occLabel = $occasionLabels[$occ] ?? $occ;
            ?>
            <?php if ($occLabel !== '') : ?>
              <span class="dg-muted">(<?= View::escape($occLabel) ?>)</span>
            <?php endif; ?>
          <?php else : ?>
            —
          <?php endif; ?>
        </dd>
        <dt>Notiz</dt><dd><?= nl2br(View::escape((string) ($form['notes'] ?? '—'))) ?></dd>
      </dl>
      <p class="dg-btn-row">
        <a class="dg-button dg-button--primary" href="/app?page=website-gutschein-flyer&amp;id=<?= $id ?>">Flyer mit Nummer drucken</a>
        <?php if (is_array($selectedTpl)) : ?>
          <a class="dg-button" href="/app?page=gutscheine-flyer-vorlagen&amp;action=edit&amp;id=<?= (int) ($selectedTpl['id'] ?? 0) ?>">Vorlage</a>
        <?php endif; ?>
        <?php if ($canEdit && (string) ($form['status'] ?? '') === 'active' && (float) ($form['remaining_gross'] ?? $form['remaining'] ?? 0) >= (float) ($form['face_value_gross'] ?? $form['amount'] ?? 0) - 0.001) : ?>
          <form method="post" action="/app?page=gutscheine-form" class="dg-inline-form">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="gift_voucher_id" value="<?= $id ?>">
            <button type="submit" name="gift_cancel" value="1" class="dg-button dg-button--danger">Stornieren</button>
          </form>
        <?php endif; ?>
      </p>
      <p class="dg-field-hint">Kunde kann Rechnung <em>oder</em> diesen Flyer vorlegen — die Gutscheinnummer steht auf dem Druck.</p>
    </div>
  <?php else : ?>
    <form class="dg-form dg-panel" method="post" action="/app?page=gutscheine-form">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Betrag (€) *</span>
          <input type="text" name="amount" inputmode="decimal" required placeholder="50,00"<?= !$dbConnected ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Gültig bis</span>
          <input type="date" name="valid_until"<?= !$dbConnected ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Titel</span>
          <input type="text" name="title" maxlength="255" placeholder="Geschenk-Gutschein"<?= !$dbConnected ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Flyer-Vorlage</span>
          <select name="flyer_template_id"<?= !$dbConnected || $templates === [] ? ' disabled' : '' ?>>
            <?php
              $defaultId = FlyerService::defaultGiftTemplateId();
              foreach ($templates as $tpl) :
                $tid = (int) ($tpl['id'] ?? 0);
                $occ = (string) ($tpl['occasion'] ?? '');
                $occLabel = $occasionLabels[$occ] ?? '';
            ?>
              <option value="<?= $tid ?>"<?= $tid === $defaultId ? ' selected' : '' ?>>
                <?= View::escape((string) ($tpl['title'] ?? '')) ?>
                <?php if ($occLabel !== '') : ?> — <?= View::escape($occLabel) ?><?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="dg-field-hint">Formular-Flyer (nicht der Terminkalender-QR). Beim Druck erscheint die neu vergebene Gutscheinnummer. <a href="/app?page=gutscheine-flyer-vorlagen">Vorlagen bearbeiten</a></span>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Notiz</span>
          <textarea name="notes" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>></textarea>
        </label>
      </div>
      <p class="dg-field-hint">Typ: Geschenkverkauf (Manuell). Für Entschuldigungen bitte aus der Reklamation ausstellen.</p>
      <div class="dg-form-actions">
        <button type="submit" name="gift_create" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Ausstellen</button>
      </div>
    </form>
  <?php endif; ?>
</div>
