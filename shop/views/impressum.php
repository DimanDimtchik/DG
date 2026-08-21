<?php
/** @var array<string, mixed> $legal */
$legal = $legal ?? [];
$appName = (string) ShopApp::config('name');
?>
<section class="shop-section shop-section--tight shop-legal">
  <h1>Impressum</h1>
  <p class="shop-lead">Angaben gemäß § 5 TMG</p>

  <h2>Anbieter</h2>
  <p>
    <?= ShopView::escape((string) ($legal['company_name'] ?? '')) ?><br>
    <?php if (trim((string) ($legal['street'] ?? '')) !== '') : ?>
      <?= ShopView::escape((string) $legal['street']) ?><br>
      <?= ShopView::escape(trim(($legal['postal'] ?? '') . ' ' . ($legal['city'] ?? ''))) ?><br>
    <?php else : ?>
      <em>(Anschrift bitte in der Shop-Config ergänzen)</em><br>
    <?php endif; ?>
    <?= ShopView::escape((string) ($legal['country'] ?? 'Deutschland')) ?>
  </p>

  <h2>Kontakt</h2>
  <p>
    E-Mail: <a href="mailto:<?= ShopView::escape((string) ($legal['email'] ?? '')) ?>"><?= ShopView::escape((string) ($legal['email'] ?? '')) ?></a>
    <?php if (trim((string) ($legal['phone'] ?? '')) !== '') : ?>
      <br>Telefon: <?= ShopView::escape((string) $legal['phone']) ?>
    <?php endif; ?>
    <?php if (trim((string) ($legal['website'] ?? '')) !== '') : ?>
      <br>Web: <a href="<?= ShopView::escape((string) $legal['website']) ?>"><?= ShopView::escape((string) $legal['website']) ?></a>
    <?php endif; ?>
  </p>

  <?php if (trim((string) ($legal['vat_id'] ?? '')) !== '' || trim((string) ($legal['tax_number'] ?? '')) !== '') : ?>
    <h2>Steuerliche Angaben</h2>
    <p>
      <?php if (trim((string) ($legal['vat_id'] ?? '')) !== '') : ?>
        USt-IdNr.: <?= ShopView::escape((string) $legal['vat_id']) ?><br>
      <?php endif; ?>
      <?php if (trim((string) ($legal['tax_number'] ?? '')) !== '') : ?>
        Steuernummer: <?= ShopView::escape((string) $legal['tax_number']) ?>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <h2>Verantwortlich für den Inhalt</h2>
  <p><?= ShopView::escape((string) ($legal['responsible'] ?? $legal['company_name'] ?? '')) ?></p>

  <h2>Hosting</h2>
  <p><?= ShopView::escape((string) ($legal['hosting'] ?? '')) ?></p>

  <h2>Haftung für Inhalte und Links</h2>
  <p>Als Diensteanbieter sind wir für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich.
    Für Inhalte verlinkter Seiten übernehmen wir keine Gewähr. Bei Bekanntwerden von Rechtsverletzungen entfernen wir entsprechende Inhalte umgehend.</p>

  <p class="shop-vat">Marke: <?= ShopView::escape($appName) ?> / <?= ShopView::escape((string) ($legal['brand'] ?? '')) ?></p>
</section>
