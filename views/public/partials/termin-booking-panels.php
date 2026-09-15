<?php
/** Booking step panels — shared by termin.php and termin-widget.php. */
?>
      <div class="tk-book__panel<?= $panelVisible('1') ? ' is-active' : '' ?>" data-step-panel="1"<?= !$panelVisible('1') ? ' hidden' : '' ?>>
        <h2 class="tk-book__panel-title">Leistung wählen</h2>
        <?php if ($bookingArticles === []) : ?>
          <p class="tk-book__muted">Derzeit sind keine Leistungen für die Online-Buchung hinterlegt.</p>
        <?php else : ?>
          <div class="tk-book__services" role="list">
            <?php foreach ($bookingArticles as $article) : ?>
              <button
                type="button"
                class="tk-book__service<?= $isAcademyDemo && $activeDemoStep >= 2 && (int) ($article['id'] ?? 0) === $demoArticleId ? ' is-selected' : '' ?>"
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

      <div class="tk-book__panel<?= $panelVisible('2') ? ' is-active' : '' ?>" data-step-panel="2"<?= !$panelVisible('2') ? ' hidden' : '' ?>>
        <h2 class="tk-book__panel-title">Termin wählen</h2>
        <p class="tk-book__selection" id="tk-book-selected-service"<?= $isAcademyDemo && $activeDemoStep >= 2 ? '' : ' hidden' ?>><?= $isAcademyDemo && $activeDemoStep >= 2 ? View::escape($demoArticleTitle) : '' ?></p>
        <div class="tk-book__datetime">
          <label class="tk-book__field">
            <span>Datum</span>
            <input type="date" id="tk-book-date" class="tk-book__input"<?= $isAcademyDemo && $activeDemoStep >= 2 ? ' value="' . View::escape($demoDate) . '"' : '' ?>>
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
          <?php if ($isAcademyDemo && $activeDemoStep >= 2) : ?>
            <div class="tk-book__slots" id="tk-book-slots" aria-live="polite">
              <button type="button" class="tk-book__slot is-selected"><?= View::escape($demoTime) ?></button>
              <button type="button" class="tk-book__slot">11:00</button>
              <button type="button" class="tk-book__slot">11:15</button>
              <button type="button" class="tk-book__slot">11:30</button>
            </div>
            <p class="tk-book__hint" id="tk-book-slots-hint" hidden>Bitte zuerst ein Datum wählen.</p>
          <?php else : ?>
            <div class="tk-book__slots" id="tk-book-slots" aria-live="polite"></div>
            <p class="tk-book__hint" id="tk-book-slots-hint">Bitte zuerst ein Datum wählen.</p>
          <?php endif; ?>
        </div>
        <div class="tk-book__nav">
          <button type="button" class="tk-book__btn tk-book__btn--ghost" data-step-back="1">Zurück</button>
        </div>
      </div>

      <div class="tk-book__panel<?= $panelVisible('3') ? ' is-active' : '' ?>" data-step-panel="3"<?= !$panelVisible('3') ? ' hidden' : '' ?>>
        <h2 class="tk-book__panel-title">Ihre Kontaktdaten</h2>
        <p class="tk-book__selection" id="tk-book-selected-slot"><?= $isAcademyDemo && $activeDemoStep >= 3 ? View::escape($demoSlotLabel) : '' ?></p>
        <form id="tk-book-form" class="tk-book__form" novalidate>
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="article_id" id="tk-book-article-id" value="<?= $isAcademyDemo && $activeDemoStep >= 3 ? $demoArticleId : '' ?>">
          <input type="hidden" name="employee_id" id="tk-book-employee-id" value="0">
          <input type="hidden" name="slot_datetime" id="tk-book-slot-datetime" value="<?= $isAcademyDemo && $activeDemoStep >= 3 ? View::escape($demoDate . 'T' . $demoTime) : '' ?>">
          <div class="tk-book__hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>
          <label class="tk-book__field">
            <span>Name *</span>
            <input type="text" name="customer_name" class="tk-book__input" required autocomplete="name"<?= $isAcademyDemo && $activeDemoStep >= 3 ? ' value="' . View::escape($demoCustomerName) . '"' : '' ?>>
          </label>
          <label class="tk-book__field">
            <span>E-Mail *</span>
            <input type="email" name="customer_email" class="tk-book__input" required autocomplete="email"<?= $isAcademyDemo && $activeDemoStep >= 3 ? ' value="' . View::escape($demoCustomerEmail) . '"' : '' ?>>
          </label>
          <label class="tk-book__field">
            <span>Telefon (optional)</span>
            <input type="tel" name="customer_phone" class="tk-book__input" autocomplete="tel"<?= $isAcademyDemo && $activeDemoStep >= 3 ? ' value="' . View::escape($demoCustomerPhone) . '"' : '' ?>>
          </label>
          <p class="tk-book__error" id="tk-book-error" hidden role="alert"></p>
          <div class="tk-book__nav">
            <button type="button" class="tk-book__btn tk-book__btn--ghost" data-step-back="2">Zurück</button>
            <button type="submit" class="tk-book__btn tk-book__btn--primary" id="tk-book-submit">Termin verbindlich buchen</button>
          </div>
        </form>
      </div>

      <div class="tk-book__panel tk-book__panel--success<?= $panelVisible('done') ? ' is-active' : '' ?>" data-step-panel="done"<?= !$panelVisible('done') ? ' hidden' : '' ?>>
        <div class="tk-book__success-icon" aria-hidden="true">✓</div>
        <h2 class="tk-book__panel-title">Termin gebucht</h2>
        <p class="tk-book__lead" id="tk-book-success-message"><?= View::escape(CalendarEmbedSettings::successMessage()) ?></p>
      </div>
