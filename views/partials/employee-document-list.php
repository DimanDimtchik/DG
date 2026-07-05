<?php
/** @var array<string, array<string, string>|list<array<string, string>>> $employeeFiles */
/** @var int|null $contactId */
/** @var bool $compact */
$employeeFiles = $employeeFiles ?? ContactFileStorage::emptyFiles();
$contactId = (int) ($contactId ?? 0);
$compact = $compact ?? false;
$uploadedDocs = EmployeeDocuments::listUploaded($employeeFiles);
if ($uploadedDocs === [] || $contactId <= 0) {
    return;
}
?><div class="dg-doc-list<?= $compact ? ' dg-doc-list--compact' : '' ?>">
  <p class="dg-doc-list__title">Hochgeladene Dokumente</p>
  <ul class="dg-doc-list__items">
    <?php foreach ($uploadedDocs as $doc) : ?>
      <li class="dg-doc-list__item">
        <span class="dg-doc-list__label"><?= View::escape($doc['label']) ?></span>
        <a
          class="dg-doc-list__view"
          href="<?= View::escape(EmployeeDocuments::viewUrl($contactId, $doc['type'], $doc['fileIndex'])) ?>"
          target="_blank"
          rel="noopener"
        ><?= View::escape($doc['name']) ?></a>
        <a
          class="dg-doc-list__download"
          href="<?= View::escape(EmployeeDocuments::downloadUrl($contactId, $doc['type'], $doc['fileIndex'])) ?>"
          download
        >Herunterladen</a>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="dg-lead dg-doc-list__hint">Klick auf den Dateinamen öffnet PDF und Bilder im Browser.</p>
</div>
