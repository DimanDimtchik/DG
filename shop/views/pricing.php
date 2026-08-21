<?php
/** @var list<array<string, mixed>> $plans */
/** @var string $billing */
$isYearly = $billing === 'jaehrlich';
?>
<section class="shop-section shop-section--tight">
  <h1>Transparente Preise</h1>
  <p class="shop-lead">Alles inklusive: Domain, E-Mail, SSL, Hosting, Backups, Updates und Support.</p>

  <div class="shop-billing-toggle" role="group" aria-label="Abrechnungszeitraum">
    <a class="shop-toggle<?= !$isYearly ? ' is-active' : '' ?>" href="/preise?billing=monatlich">Monatlich</a>
    <a class="shop-toggle<?= $isYearly ? ' is-active' : '' ?>" href="/preise?billing=jaehrlich">Jährlich <span class="shop-badge">1 Monat gratis</span></a>
  </div>

  <div class="shop-plans">
    <?php foreach ($plans as $p) :
        $net = $isYearly ? (float) $p['yearly_net'] : (float) $p['monthly_net'];
        $period = $isYearly ? '/ Jahr' : '/ Monat';
        ?>
      <article class="shop-plan<?= !empty($p['featured']) ? ' shop-plan--featured' : '' ?>">
        <?php if (trim((string) ($p['image_url'] ?? '')) !== '') : ?>
          <img class="shop-plan__img" src="<?= ShopView::escape((string) $p['image_url']) ?>" alt="<?= ShopView::escape((string) $p['name']) ?>" loading="lazy">
        <?php endif; ?>
        <h2><?= ShopView::escape((string) $p['name']) ?></h2>
        <p class="shop-plan__price">
          <span class="shop-plan__amount"><?= ShopView::escape(ShopPlans::formatMoney($net)) ?></span>
          <span class="shop-plan__period"><?= ShopView::escape($period) ?></span>
        </p>
        <?php if ($isYearly) : ?>
          <p class="shop-plan__save">statt <?= ShopView::escape(ShopPlans::formatMoney((float) $p['monthly_net'] * 12)) ?>/Jahr</p>
        <?php endif; ?>
        <p class="shop-plan__tag"><?= ShopView::escape((string) $p['tagline']) ?></p>
        <ul>
          <?php foreach (($p['features'] ?? []) as $f) : ?>
            <li><?= ShopView::escape((string) $f) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="shop-btn shop-btn--primary" href="/checkout?plan=<?= rawurlencode((string) $p['id']) ?>&amp;billing=<?= rawurlencode($billing) ?>">Jetzt starten</a>
      </article>
    <?php endforeach; ?>
  </div>

  <p class="shop-vat"><?= ShopView::escape(ShopPlans::vatNote()) ?>
    Quelle: <a href="https://ganz-soft.de/preise">ganz-soft.de/preise</a>.
    <?php if ($isYearly) : ?>Jahrespreis = 11 × Monatspreis.<?php endif; ?>
  </p>
  <p class="shop-lead">Individuelle Anforderungen? <a href="mailto:<?= ShopView::escape($contactEmail) ?>"><?= ShopView::escape($contactEmail) ?></a></p>
</section>
