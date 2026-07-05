<?php
/**
 * @var array<string, array{label: string, tabs: array<string, array{label: string, lead: string, template: string}>}> $settingsNav
 * @var array{tab: string, tabLabel: string, section: string, sectionLabel: string, lead: string, template: string} $settingsSelection
 * @var array<string, mixed> $dbConfig
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
?>
<div class="dg-wrap dg-settings">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-settings__header">
    <h1 class="dg-page-title">Einstellungen</h1>
    <?php if ($settingsSelection['lead'] !== '') : ?>
      <p class="dg-lead"><?= View::escape($settingsSelection['lead']) ?></p>
    <?php endif; ?>
  </header>

  <div class="dg-settings-layout">
    <aside class="dg-settings-nav" aria-label="Einstellungen">
      <?php foreach ($settingsNav as $sectionId => $section) : ?>
        <section class="dg-settings-nav__section">
          <h2 class="dg-settings-nav__title"><?= View::escape($section['label']) ?></h2>
          <ul class="dg-settings-nav__list">
            <?php foreach ($section['tabs'] as $tabId => $tab) : ?>
              <?php $isActive = $settingsSelection['tab'] === $tabId; ?>
              <li>
                <a
                  href="<?= View::escape(SettingsRegistry::tabUrl($tabId)) ?>"
                  class="dg-settings-nav__link<?= $isActive ? ' is-active' : '' ?>"
                  <?= $isActive ? 'aria-current="page"' : '' ?>
                ><?= View::escape($tab['label']) ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    </aside>

    <div class="dg-settings-main">
      <header class="dg-settings-main__head">
        <div>
          <p class="dg-settings-main__eyebrow"><?= View::escape($settingsSelection['sectionLabel']) ?></p>
          <h2 class="dg-settings-main__title"><?= View::escape($settingsSelection['tabLabel']) ?></h2>
        </div>
      </header>

      <div class="dg-settings-main__body">
        <?php if ($settingsSelection['template'] === 'database') : ?>
          <?php View::render('settings/tab-system', compact('dbConfig', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'email') : ?>
          <?php View::render('settings/tab-email', compact('mailConfig', 'mailReady', 'mailRecent', 'smtpTestReport', 'mailAddressConfig')); ?>
        <?php elseif ($settingsSelection['template'] === 'postboxes') : ?>
          <?php View::render('settings/tab-postboxes', compact('postboxes', 'postboxMemberOptions', 'kasConfigured')); ?>
        <?php elseif ($settingsSelection['template'] === 'fonts') : ?>
          <?php View::render('settings/tab-fonts', compact('appearanceConfig')); ?>
        <?php elseif ($settingsSelection['template'] === 'crm-appearance') : ?>
          <?php View::render('settings/tab-crm-appearance', compact('crmThemeConfig', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'departments') : ?>
          <?php View::render('settings/tab-departments', compact('departmentsData', 'departmentEmployees', 'notificationTemplateData', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'calendar-team') : ?>
          <?php View::render('settings/tab-calendar-team', compact('dbConnected', 'calendarTeamTab', 'calendarAreas', 'calendarEmployees', 'calendarAbsences', 'calendarLinkUsers', 'calendarDepartmentOptions', 'calendarLinkContacts', 'calendarDepartmentSuggestions')); ?>
        <?php elseif ($settingsSelection['template'] === 'company') : ?>
          <?php View::render('settings/tab-company', compact('companyConfig', 'companyExtended')); ?>
        <?php elseif ($settingsSelection['template'] === 'number-ranges') : ?>
          <?php View::render('settings/tab-number-ranges', compact('numberRangeType', 'numberRangeDoc', 'numberRangeTypes', 'numberRangeHistory', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'working-hours') : ?>
          <?php View::render('settings/tab-working-hours', compact('calendarWorkingHours', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'notifications') : ?>
          <?php View::render('settings/tab-notifications', compact('calendarEmailTemplates', 'calendarNotificationDelivery', 'emailLayout', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'calendar-appearance') : ?>
          <?php View::render('settings/tab-calendar-appearance', compact('calendarAppearanceConfig', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'calendar-embed') : ?>
          <?php View::render('settings/tab-calendar-embed', compact('calendarEmbedConfig', 'dbConnected')); ?>
        <?php elseif ($settingsSelection['template'] === 'steuerkanzlei') : ?>
          <?php View::render('settings/tab-steuerkanzlei', compact('taxAdvisorConfig', 'taxAdvisorCompanyOptions', 'dbConnected')); ?>
        <?php else : ?>
          <?php View::render('settings/tab-placeholder', [
              'tabLabel' => $settingsSelection['tabLabel'],
              'lead' => $settingsSelection['lead'],
          ]); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
