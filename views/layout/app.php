<?php
/** @var string $title */
/** @var User $user */
/** @var string|null $navMode */
/** @var list<array{id: string, name: string, member_role: string}> $departments */
/** @var string $contentTemplate */
/** @var list<array{slug: string, label: string, icon: string, href: string}> $sidebarItems */
/** @var array{slug: string, label: string, icon: string}|null $settingsItem */
/** @var array{label: string, items: list<array{slug: string, label: string, icon: string, href: string}>}|null $buchhaltungSection */
/** @var string $currentPage */
$homeHref = RoleResolver::isCustomer($user) ? '/app?area=profile' : '/app';
$pageTitle = $title . ' – ' . App::config('crm_name');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
</head>
<body class="dg-app">
  <header id="dg-adminbar" class="dg-adminbar" role="banner">
    <div class="dg-adminbar__left">
      <a class="dg-adminbar__brand" href="<?= View::escape($homeHref) ?>">
        <img src="<?= View::escape(AppearanceSettings::logoUrl()) ?>" alt="<?= View::escape(AppearanceSettings::logoAlt()) ?>" width="22" height="22" class="dg-adminbar__logo">
        <span class="dg-adminbar__name"><?= View::escape((string) App::config('crm_name')) ?></span>
      </a>
    </div>

    <div class="dg-adminbar__center">
      <?php if ($navMode === 'admin') : ?>
        <div class="dg-adminbar__menu" data-menu>
          <button type="button" class="dg-adminbar__menu-toggle" aria-expanded="false" aria-haspopup="true" data-menu-toggle>
            Admin
            <span class="dg-adminbar__caret" aria-hidden="true"></span>
          </button>
          <div class="dg-adminbar__dropdown" role="menu" hidden data-menu-panel>
            <a href="/app" role="menuitem">Übersicht</a>
            <a href="/app?area=users" role="menuitem">Benutzer &amp; Mitarbeiter</a>
            <a href="/app?area=departments" role="menuitem">Abteilungen</a>
          </div>
        </div>
      <?php elseif ($navMode === 'department') : ?>
        <div class="dg-adminbar__menu" data-menu>
          <button type="button" class="dg-adminbar__menu-toggle" aria-expanded="false" aria-haspopup="true" data-menu-toggle>
            Abteilung
            <span class="dg-adminbar__caret" aria-hidden="true"></span>
          </button>
          <div class="dg-adminbar__dropdown" role="menu" hidden data-menu-panel>
            <?php foreach ($departments as $department) : ?>
              <a href="/app?dept=<?= View::escape($department['id']) ?>" role="menuitem">
                <?= View::escape($department['name']) ?>
                <?php if ($department['member_role'] === 'leader') : ?>
                  <span class="dg-adminbar__badge">Leitung</span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="dg-adminbar__right">
      <div class="dg-adminbar__menu dg-adminbar__menu--user" data-menu>
        <button type="button" class="dg-adminbar__user-toggle" aria-expanded="false" aria-haspopup="true" data-menu-toggle>
          <span class="dg-adminbar__avatar" aria-hidden="true"><?= View::escape(strtoupper(substr($user->displayName, 0, 1))) ?></span>
          <span class="dg-adminbar__username"><?= View::escape($user->displayName) ?></span>
          <span class="dg-adminbar__caret" aria-hidden="true"></span>
        </button>
        <div class="dg-adminbar__dropdown dg-adminbar__dropdown--right" role="menu" hidden data-menu-panel>
          <div class="dg-adminbar__dropdown-meta">
            <strong><?= View::escape($user->displayName) ?></strong>
            <span><?= View::escape($user->email) ?></span>
            <span><?= View::escape(RoleResolver::roleLabel($user)) ?></span>
          </div>
          <?php if (!RoleResolver::isCustomer($user)) : ?>
            <a href="/app?area=profile" role="menuitem">Mein Profil</a>
          <?php endif; ?>
          <a href="/logout" role="menuitem">Abmelden</a>
        </div>
      </div>
    </div>
  </header>

  <div class="dg-shell">
    <aside id="dg-sidebar" class="dg-sidebar" aria-label="Seitenleiste">
      <section class="dg-sidebar__section" aria-labelledby="dg-sidebar-nav">
        <h2 id="dg-sidebar-nav" class="dg-sidebar__heading">Navigation</h2>
        <ul class="dg-sidebar__list">
          <?php foreach ($sidebarItems as $item) : ?>
            <li>
              <a href="<?= View::escape($item['href']) ?>" class="dg-sidebar__link<?= $currentPage === $item['slug'] ? ' is-active' : '' ?>">
                <?php View::render('partials/icon', ['name' => $item['icon']]); ?>
                <span><?= View::escape($item['label']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <?php if (!empty($buchhaltungSection)) : ?>
        <section class="dg-sidebar__section dg-sidebar__section--buchhaltung" aria-labelledby="dg-sidebar-buchhaltung">
          <h2 id="dg-sidebar-buchhaltung" class="dg-sidebar__heading"><?= View::escape($buchhaltungSection['label']) ?></h2>
          <ul class="dg-sidebar__list">
            <?php foreach ($buchhaltungSection['items'] as $item) : ?>
              <li>
                <a href="<?= View::escape($item['href']) ?>" class="dg-sidebar__link<?= $currentPage === $item['slug'] ? ' is-active' : '' ?>">
                  <?php View::render('partials/icon', ['name' => $item['icon']]); ?>
                  <span><?= View::escape($item['label']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <?php if ($settingsItem) : ?>
        <section class="dg-sidebar__section dg-sidebar__section--system" aria-labelledby="dg-sidebar-system">
          <h2 id="dg-sidebar-system" class="dg-sidebar__heading">System</h2>
          <ul class="dg-sidebar__list">
            <li>
              <a href="/app?page=einstellungen" class="dg-sidebar__link<?= $currentPage === 'einstellungen' ? ' is-active' : '' ?>">
                <?php View::render('partials/icon', ['name' => 'settings']); ?>
                <span><?= View::escape($settingsItem['label']) ?></span>
              </a>
            </li>
          </ul>
        </section>
      <?php endif; ?>
    </aside>

    <main id="dg-content" class="dg-content">
      <?php View::render($contentTemplate, get_defined_vars()); ?>
    </main>
  </div>

  <?php if (($settingsSelection['template'] ?? '') === 'company') : ?>
    <script>
      window.dgCompanySettings = {
        finanzamtUrl: '/api/finanzamt-lookup',
        csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>,
        industryUvMap: <?= json_encode(IndustryBranches::industryUvMap(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        uvCarriers: <?= json_encode(UvCarriers::all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>
      };
      window.dgBankConfig = {
        url: '/api/bank-suggest',
        csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/bank-autocomplete.js')) ?>" defer></script>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-company.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'fonts') : ?>
    <script>
      window.dgFontSettings = {
        families: <?= json_encode(AppearanceSettings::fontFamilyMap(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-fonts.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'departments') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-departments.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'number-ranges') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-number-ranges.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'calendar-team') : ?>
    <script>
      window.dgCalendarStaff = {
        timeOptions: <?= json_encode(CalendarStaffRepository::timeOptions(), JSON_THROW_ON_ERROR) ?>,
        linkContacts: <?= json_encode($calendarLinkContacts ?? [], JSON_THROW_ON_ERROR) ?>,
        areaDepartments: <?= json_encode(CalendarStaffRepository::areaDepartmentMap(), JSON_THROW_ON_ERROR) ?>,
        departmentSuggestions: <?= json_encode($calendarDepartmentSuggestions ?? [], JSON_THROW_ON_ERROR) ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-calendar-team.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($contentTemplate ?? '') === 'modules/kontakte-form') : ?>
    <script>
      window.dgBankConfig = {
        url: '/api/bank-suggest',
        csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>
      };
      window.dgHealthInsurerConfig = {
        url: '/api/health-insurer-suggest',
        csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/bank-autocomplete.js')) ?>" defer></script>
    <script src="<?= View::escape(Asset::url('/assets/js/health-insurer-autocomplete.js')) ?>" defer></script>
    <script src="<?= View::escape(Asset::url('/assets/js/disability-supplementary.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'crm-appearance') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-crm-appearance.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'calendar-appearance') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-calendar-appearance.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (in_array(($settingsSelection['template'] ?? ''), ['notifications', 'departments'], true)) : ?>
    <script>
      window.dgCalendarEmailPreview = {
        url: '/api/calendar-email-preview',
        csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>
      };
    </script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'notifications') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-notifications.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($settingsSelection['template'] ?? '') === 'calendar-articles' || ($contentTemplate ?? '') === 'modules/artikel-leistungen') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/settings-calendar-articles.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($contentTemplate ?? '') === 'modules/terminkalender-form') : ?>
    <script>
      window.dgBookingForm = {
        apiUrl: '/api/booking-slots',
        articleId: <?= (int) ($form['article_id'] ?? 0) ?>,
        employeeId: <?= (int) ($form['employee_id'] ?? 0) ?>,
        excludeBookingId: <?= (int) ($bookingId ?? 0) ?>,
        initialDate: <?= json_encode($form['slot_date'] ?? '', JSON_THROW_ON_ERROR) ?>,
        initialTime: <?= json_encode($form['slot_time'] ?? '', JSON_THROW_ON_ERROR) ?>,
        slotStepMinutes: <?= (int) BookingSlotService::slotStepMinutes() ?>,
        articles: <?= json_encode($bookingArticleOptions ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        employees: <?= json_encode($bookingEmployeeOptions ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/terminkalender-form.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (in_array(($contentTemplate ?? ''), ['modules/bilder', 'modules/bilder-edit'], true)) : ?>
    <link rel="stylesheet" href="<?= View::escape(Asset::url('/assets/vendor/cropper.min.css')) ?>">
    <script>
      window.dgMedia = {
        apiUrl: '/api/media',
        csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/vendor/cropper.min.js')) ?>" defer></script>
    <script src="<?= View::escape(Asset::url('/assets/js/bilder.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($contentTemplate ?? '') === 'modules/kontakte-form') : ?>
    <script src="<?= View::escape(Asset::url('/assets/js/contact-company-links.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (($contentTemplate ?? '') === 'modules/buchhaltung-konten') : ?>
    <script>
      window.dgBuchhaltungKonten = {
        apiUrl: '/api/chart-account',
        accountDigits: <?= (int) ChartOfAccountsSettings::accountDigits() ?>
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/buchhaltung-konten.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if (in_array(($contentTemplate ?? ''), ['modules/buchhaltung-belege', 'modules/buchhaltung-beleg-form'], true)) : ?>
    <script>
      window.dgBuchhaltungBelege = {
        apiUrl: '/api/voucher',
        chartApiUrl: '/api/chart-account'
      };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/buchhaltung-belege.js')) ?>" defer></script>
  <?php endif; ?>
  <script src="<?= View::escape(Asset::url('/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
