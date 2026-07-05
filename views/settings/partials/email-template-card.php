<?php
/**
 * @var array<string, mixed> $template
 * @var bool $disabled
 * @var bool $showCategorySelect
 */
$id = (string) ($template['id'] ?? '');
$builtin = !empty($template['builtin']);
$prefix = 'notification_templates[' . $id . ']';
$category = (string) ($template['category'] ?? NotificationTemplateSettings::CATEGORY_CALENDAR);
?>
<details class="dg-email-tpl-type" data-email-tpl-card data-tpl-id="<?= View::escape($id) ?>" data-tpl-category="<?= View::escape($category) ?>" data-tpl-owner="<?= View::escape(NotificationTemplateSettings::departmentIdToOwnerId((string) ($template['department_id'] ?? ''))) ?>" open>
  <summary class="dg-subsection-title dg-collapsible-form__summary dg-email-tpl-type__summary">
    <span data-tpl-summary-name><?= View::escape((string) ($template['name'] ?? 'Vorlage')) ?></span>
    <?php if ($builtin) : ?>
      <span class="dg-badge dg-badge--muted">System</span>
    <?php endif; ?>
  </summary>
  <div class="dg-collapsible-form__body dg-email-tpl-type__body">
    <input type="hidden" name="<?= View::escape($prefix) ?>[id]" value="<?= View::escape($id) ?>">
    <input type="hidden" name="<?= View::escape($prefix) ?>[department_id]" value="<?= View::escape((string) ($template['department_id'] ?? '')) ?>" data-tpl-department>
    <input type="hidden" name="<?= View::escape($prefix) ?>[builtin]" value="<?= $builtin ? '1' : '0' ?>">
    <input type="hidden" name="<?= View::escape($prefix) ?>[event_slug]" value="<?= View::escape((string) ($template['event_slug'] ?? '')) ?>">
    <div class="dg-form-grid">
      <?php if ($showCategorySelect && !$builtin) : ?>
        <label class="dg-field dg-field--wide">
          <span>Bereich</span>
          <select name="<?= View::escape($prefix) ?>[category]" data-tpl-category-select data-email-field="category"<?= $disabled ? ' disabled' : '' ?>>
            <?php foreach (NotificationTemplateSettings::categories() as $categoryKey => $categoryLabel) : ?>
              <option value="<?= View::escape($categoryKey) ?>"<?= $category === $categoryKey ? ' selected' : '' ?>><?= View::escape($categoryLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php else : ?>
        <input type="hidden" name="<?= View::escape($prefix) ?>[category]" value="<?= View::escape($category) ?>" data-tpl-category>
      <?php endif; ?>
      <label class="dg-field dg-field--wide">
        <span>Bezeichnung der Vorlage *</span>
        <input type="text" name="<?= View::escape($prefix) ?>[name]" value="<?= View::escape((string) ($template['name'] ?? '')) ?>" data-email-field="name" data-tpl-name<?= $disabled ? ' disabled' : '' ?><?= $builtin ? ' readonly' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Betreff</span>
        <input type="text" name="<?= View::escape($prefix) ?>[subject]" value="<?= View::escape((string) ($template['subject'] ?? '')) ?>" data-email-field="subject" data-template-key="<?= View::escape($id) ?>"<?= $disabled ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Überschrift in der E-Mail</span>
        <input type="text" name="<?= View::escape($prefix) ?>[title]" value="<?= View::escape((string) ($template['title'] ?? '')) ?>" data-email-field="title"<?= $disabled ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Einleitungstext</span>
        <textarea name="<?= View::escape($prefix) ?>[intro]" rows="3" data-email-field="intro"<?= $disabled ? ' disabled' : '' ?>><?= View::escape((string) ($template['intro'] ?? '')) ?></textarea>
      </label>
    </div>
    <p class="dg-form-actions dg-form-actions--split">
      <button type="button" class="dg-button dg-button--small dg-email-preview-btn" data-template-key="<?= View::escape($id) ?>" data-event-slug="<?= View::escape((string) ($template['event_slug'] ?? '')) ?>"<?= $disabled ? ' disabled' : '' ?>>Vorschau aktualisieren</button>
      <?php if (!$builtin) : ?>
        <button type="button" class="dg-button dg-button--small dg-button--danger dg-email-tpl-delete" data-tpl-delete<?= $disabled ? ' disabled' : '' ?>>Vorlage entfernen</button>
      <?php endif; ?>
    </p>
    <div class="dg-email-preview" data-email-preview="<?= View::escape($id) ?>" hidden>
      <p class="dg-email-preview__subject"><strong>Betreff:</strong> <span data-email-preview-subject></span></p>
      <iframe class="dg-email-preview__frame" title="E-Mail-Vorschau" sandbox=""></iframe>
    </div>
  </div>
</details>
