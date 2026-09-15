<?php
/** @var array<string, mixed> $embedConfig */
/** @var list<array<string, mixed>> $bookingArticles */
/** @var list<array<string, mixed>> $bookingEmployees */
$embedConfig = $embedConfig ?? CalendarEmbedSettings::config();
$bookingArticles = $bookingArticles ?? [];
$bookingEmployees = $bookingEmployees ?? [];
$previewMode = !empty($previewMode);
$onlineBookingEnabled = $onlineBookingEnabled ?? CalendarEmbedSettings::isOnlineBookingEnabled();
$embedded = !isset($embedded) || $embedded;
$academyDemoStep = $academyDemoStep ?? null;
$isAcademyDemo = $academyDemoStep !== null && $academyDemoStep !== '';
$demoArticle = $bookingArticles[0] ?? null;
$demoArticleTitle = $demoArticle !== null ? (string) ($demoArticle['title'] ?? 'Massage') : 'Massage';
$demoArticleId = $demoArticle !== null ? (int) ($demoArticle['id'] ?? 0) : 0;
$demoDate = date('Y-m-d', strtotime('+3 days'));
$demoTime = '10:45';
$demoSlotLabel = $demoArticleTitle . ' — ' . date('d.m.Y', strtotime($demoDate)) . ', ' . $demoTime . ' Uhr';
$demoCustomerName = 'Maria Beispiel';
$demoCustomerEmail = 'maria.beispiel@example.de';
$demoCustomerPhone = '+49 170 1234567';
$activeDemoStep = $isAcademyDemo ? (int) ($academyDemoStep === 'done' ? 4 : $academyDemoStep) : 1;
$companyName = CompanySettings::displayName();
$intro = CalendarEmbedSettings::introText();

$stepIndicatorClass = static function (int $step) use ($activeDemoStep, $isAcademyDemo): string {
    if (!$isAcademyDemo) {
        return $step === 1 ? 'is-active' : '';
    }
    if ($step < $activeDemoStep) {
        return 'is-done';
    }
    if ($step === $activeDemoStep) {
        return 'is-active';
    }

    return '';
};

$panelVisible = static function (string $panel) use ($activeDemoStep, $isAcademyDemo): bool {
    if (!$isAcademyDemo) {
        return $panel === '1';
    }
    if ($panel === 'done') {
        return $activeDemoStep >= 4;
    }

    return (int) $panel === $activeDemoStep;
};
?>
<div class="tk-book-wrap<?= $embedded ? ' tk-book-wrap--embedded' : '' ?>">
<?php if ($previewMode && !$embedded) : ?>
<div class="tk-book__preview-banner">
  Vorschau · Online-Terminbuchung
  <?php if (!$onlineBookingEnabled) : ?>
    · <strong>Öffentlich deaktiviert</strong> (nur hier sichtbar)
  <?php endif; ?>
  · <a href="/app?page=website-seiten">Zurück zu Website → Seiten</a>
</div>
<?php elseif ($previewMode && $embedded && !$onlineBookingEnabled) : ?>
<p class="ws-booking-preview-hint">Vorschau: Buchungsformular — öffentlich derzeit deaktiviert (Einstellungen → Kalender-Einbindung).</p>
<?php endif; ?>
  <div class="tk-book" id="tk-public-booking"<?= CalendarFrontendTheme::wrapperStyleAttribute() ?>>
    <header class="tk-book__header">
      <?php if (AppearanceSettings::logoUrl() !== '') : ?>
        <img class="tk-book__logo <?= View::escape(AppearanceSettings::logoShapeClass()) ?>" src="<?= View::escape(AppearanceSettings::logoUrl()) ?>" alt="<?= View::escape(AppearanceSettings::logoAlt()) ?>">
      <?php endif; ?>
      <div>
        <h1 class="tk-book__title"><?= View::escape(CalendarEmbedSettings::pageTitle()) ?></h1>
        <?php if ($companyName !== '') : ?>
          <p class="tk-book__subtitle"><?= View::escape($companyName) ?></p>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($intro !== '') : ?>
      <p class="tk-book__intro"><?= View::escape($intro) ?></p>
    <?php endif; ?>

    <ol class="tk-book__steps" aria-label="Buchungsschritte">
      <li class="tk-book__step <?= $stepIndicatorClass(1) ?>" data-step-indicator="1"><span>1</span> Leistung</li>
      <li class="tk-book__step <?= $stepIndicatorClass(2) ?>" data-step-indicator="2"><span>2</span> Termin</li>
      <li class="tk-book__step <?= $stepIndicatorClass(3) ?>" data-step-indicator="3"><span>3</span> Kontakt</li>
    </ol>

    <div class="tk-book__card" id="tk-book-panel">
      <?php View::render('public/partials/termin-booking-panels', get_defined_vars()); ?>
    </div>
  </div>

  <?php if (!$isAcademyDemo) : ?>
  <script>
    window.tkPublicBooking = window.tkPublicBooking || {
      apiSlots: '/api/booking-slots',
      apiBook: '/api/public-booking',
      articles: <?= json_encode($bookingArticles, JSON_UNESCAPED_UNICODE) ?>,
      employees: <?= json_encode($bookingEmployees, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="<?= View::escape(Asset::url('/assets/js/public-booking.js')) ?>" defer></script>
  <?php endif; ?>
</div>
