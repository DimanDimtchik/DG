<?php
/** @var array<string, mixed> $embedConfig */
/** @var list<array<string, mixed>> $bookingArticles */
/** @var list<array<string, mixed>> $bookingEmployees */
$embedConfig = $embedConfig ?? CalendarEmbedSettings::config();
$bookingArticles = $bookingArticles ?? [];
$bookingEmployees = $bookingEmployees ?? [];
$pageTitle = CalendarEmbedSettings::pageTitle() . ' – ' . CompanySettings::displayName();
$companyName = CompanySettings::displayName();
$intro = CalendarEmbedSettings::introText();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
<style><?= CalendarFrontendTheme::inlineCss() ?></style>
</head>
<body class="tk-book-page">
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
      <li class="tk-book__step is-active" data-step-indicator="1"><span>1</span> Leistung</li>
      <li class="tk-book__step" data-step-indicator="2"><span>2</span> Termin</li>
      <li class="tk-book__step" data-step-indicator="3"><span>3</span> Kontakt</li>
    </ol>

    <div class="tk-book__card" id="tk-book-panel">
      <div class="tk-book__panel is-active" data-step-panel="1">
        <h2 class="tk-book__panel-title">Leistung wählen</h2>
        <?php if ($bookingArticles === []) : ?>
          <p class="tk-book__muted">Derzeit sind keine Leistungen für die Online-Buchung hinterlegt.</p>
        <?php else : ?>
          <div class="tk-book__services" role="list">
            <?php foreach ($bookingArticles as $article) : ?>
              <button
                type="button"
                class="tk-book__service"
                data-article-id="<?= (int) ($article['id'] ?? 0) ?>"
                data-uses-employees="<?= !empty($article['uses_employees']) ? '1' : '0' ?>"
                data-duration="<?= (int) ($article['work_minutes'] ?? 0) ?>"
              >
                <span class="tk-book__service-title"><?= View::escape((string) ($article['title'] ?? '')) ?></span>
                <span class="tk-book__service-meta">
                  <?= View::escape(CalendarArticleRepository::formatDuration((int) ($article['work_minutes'] ?? 0))) ?>
                  <?php if (!empty($article['price_label']) && (float) ($article['price_gross'] ?? 0) > 0) : ?>
                    · <?= View::escape((string) $article['price_label']) ?>
                  <?php endif; ?>
                </span>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="tk-book__panel" data-step-panel="2" hidden>
        <h2 class="tk-book__panel-title">Termin wählen</h2>
        <p class="tk-book__selection" id="tk-book-selected-service" hidden></p>
        <div class="tk-book__datetime">
          <label class="tk-book__field">
            <span>Datum</span>
            <input type="date" id="tk-book-date" class="tk-book__input">
          </label>
          <label class="tk-book__field" id="tk-book-employee-field" hidden>
            <span>Mitarbeiter (optional)</span>
            <select id="tk-book-employee" class="tk-book__input">
              <option value="0">— Beliebiger verfügbarer Mitarbeiter —</option>
              <?php foreach ($bookingEmployees as $employee) : ?>
                <option value="<?= (int) ($employee['id'] ?? 0) ?>"><?= View::escape((string) ($employee['label'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="tk-book__slots-wrap">
          <p class="tk-book__slots-label">Verfügbare Zeiten</p>
          <div class="tk-book__slots" id="tk-book-slots" aria-live="polite"></div>
          <p class="tk-book__hint" id="tk-book-slots-hint">Bitte zuerst ein Datum wählen.</p>
        </div>
        <div class="tk-book__nav">
          <button type="button" class="tk-book__btn tk-book__btn--ghost" data-step-back="1">Zurück</button>
        </div>
      </div>

      <div class="tk-book__panel" data-step-panel="3" hidden>
        <h2 class="tk-book__panel-title">Ihre Kontaktdaten</h2>
        <p class="tk-book__selection" id="tk-book-selected-slot"></p>
        <form id="tk-book-form" class="tk-book__form" novalidate>
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="article_id" id="tk-book-article-id" value="">
          <input type="hidden" name="employee_id" id="tk-book-employee-id" value="0">
          <input type="hidden" name="slot_datetime" id="tk-book-slot-datetime" value="">
          <div class="tk-book__hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>
          <label class="tk-book__field">
            <span>Name *</span>
            <input type="text" name="customer_name" class="tk-book__input" required autocomplete="name">
          </label>
          <label class="tk-book__field">
            <span>E-Mail *</span>
            <input type="email" name="customer_email" class="tk-book__input" required autocomplete="email">
          </label>
          <label class="tk-book__field">
            <span>Telefon (optional)</span>
            <input type="tel" name="customer_phone" class="tk-book__input" autocomplete="tel">
          </label>
          <p class="tk-book__error" id="tk-book-error" hidden role="alert"></p>
          <div class="tk-book__nav">
            <button type="button" class="tk-book__btn tk-book__btn--ghost" data-step-back="2">Zurück</button>
            <button type="submit" class="tk-book__btn tk-book__btn--primary" id="tk-book-submit">Termin verbindlich buchen</button>
          </div>
        </form>
      </div>

      <div class="tk-book__panel tk-book__panel--success" data-step-panel="done" hidden>
        <div class="tk-book__success-icon" aria-hidden="true">✓</div>
        <h2 class="tk-book__panel-title">Termin gebucht</h2>
        <p class="tk-book__lead" id="tk-book-success-message"><?= View::escape(CalendarEmbedSettings::successMessage()) ?></p>
      </div>
    </div>
  </div>

  <script>
    window.tkPublicBooking = {
      apiSlots: '/api/booking-slots',
      apiBook: '/api/public-booking',
      articles: <?= json_encode($bookingArticles, JSON_UNESCAPED_UNICODE) ?>,
      employees: <?= json_encode($bookingEmployees, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="<?= View::escape(Asset::url('/assets/js/public-booking.js')) ?>" defer></script>
</body>
</html>
