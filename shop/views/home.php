<?php
/** @var list<array<string, mixed>> $plans */
/** @var bool|null $notFound */
?>
<?php if (!empty($notFound)) : ?>
  <section class="shop-section">
    <p class="shop-lead">Seite nicht gefunden.</p>
    <p><a class="shop-btn shop-btn--primary" href="/">Zur Startseite</a></p>
  </section>
<?php endif; ?>

<section class="shop-hero">
  <div class="shop-hero__content">
    <p class="shop-hero__brand">DG CRM</p>
    <h1>Ihr Business.<br>Eine Software.</h1>
    <p class="shop-hero__sub">Terminkalender, Buchhaltung, Kundenmanagement und Website – Domain, E-Mail und Hosting inklusive.</p>
    <div class="shop-hero__actions">
      <a class="shop-btn shop-btn--primary" href="/preise">Tarife ansehen</a>
      <a class="shop-btn shop-btn--ghost" href="<?= ShopView::escape($marketingUrl) ?>">Mehr erfahren</a>
    </div>
  </div>
</section>

<section class="shop-section" id="pakete">
  <h2>Drei klare Pakete</h2>
  <p class="shop-lead">Preise wie auf ganz-soft.de – zzgl. MwSt. Jahresabo: 1 Monat gratis.</p>
  <div class="shop-plans">
    <?php foreach ($plans as $p) : ?>
      <article class="shop-plan<?= !empty($p['featured']) ? ' shop-plan--featured' : '' ?>">
        <?php if (trim((string) ($p['image_url'] ?? '')) !== '') : ?>
          <img class="shop-plan__img" src="<?= ShopView::escape((string) $p['image_url']) ?>" alt="<?= ShopView::escape((string) $p['name']) ?>" loading="lazy">
        <?php endif; ?>
        <h3><?= ShopView::escape((string) $p['name']) ?></h3>
        <p class="shop-plan__price">
          <span class="shop-plan__amount"><?= ShopView::escape(ShopPlans::formatMoney((float) $p['monthly_net'])) ?></span>
          <span class="shop-plan__period">/ Monat</span>
        </p>
        <p class="shop-plan__tag"><?= ShopView::escape((string) $p['tagline']) ?></p>
        <ul>
          <?php foreach (($p['features'] ?? []) as $f) : ?>
            <li><?= ShopView::escape((string) $f) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="shop-btn shop-btn--primary" href="/checkout?plan=<?= rawurlencode((string) $p['id']) ?>">Jetzt starten</a>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="shop-vat"><?= ShopView::escape(ShopPlans::vatNote()) ?> Jahresabo = 11 Monatspreise (1 Monat gratis).</p>
</section>
