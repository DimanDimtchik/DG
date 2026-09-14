<?php
/**
 * @var string $academyView
 * @var list<array<string, mixed>> $academyAreas
 * @var list<array<string, mixed>> $academyAssignments
 * @var list<array<string, mixed>> $academyCatalog
 * @var list<array<string, mixed>> $academyPendingHr
 * @var array<string, mixed>|null $academyCourse
 * @var array<string, mixed>|null $academyModule
 * @var array<string, mixed>|null $academyAssignment
 * @var array<string, mixed>|null $academySummary
 * @var bool $academyRulesAccepted
 * @var string $academyTierPlan
 * @var bool $canManageAcademy
 * @var bool $canAcademyHr
 * @var array<string, mixed>|null $academyAdminCourse
 * @var list<array<string, mixed>> $academyAllCourses
 * @var list<array<string, mixed>> $academyUserOptions
 * @var array{type: string, message: string}|null $flash
 */
$academyView = $academyView ?? 'meine';
$academyAreas = $academyAreas ?? [];
$academyAssignments = $academyAssignments ?? [];
$academyCatalog = $academyCatalog ?? [];
$academyPendingHr = $academyPendingHr ?? [];
$academyTierPlan = $academyTierPlan ?? AcademyTier::currentPlan();
$canManageAcademy = $canManageAcademy ?? false;
$canAcademyHr = $canAcademyHr ?? false;
$academyRulesAccepted = $academyRulesAccepted ?? false;
$csrf = Csrf::token();

$flagLabels = [
    'playback_rate_exceeded' => 'Wiedergabe schneller als erlaubt (> 2×)',
    'insufficient_watch_time' => 'Zu wenig angesehen',
    'wall_clock_too_short' => 'Gesamtzeit unrealistisch kurz',
    'course_too_fast' => 'Kurs insgesamt zu schnell abgeschlossen',
    'tab_hidden' => 'Tab/Fenster war ausgeblendet',
];

$riskClass = static function (?string $level): string {
    return match ($level) {
        'red' => 'dg-academy-risk--red',
        'yellow' => 'dg-academy-risk--yellow',
        'green' => 'dg-academy-risk--green',
        default => '',
    };
};
?>
<div class="dg-wrap dg-akademie">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Akademie</h1>
      <p class="dg-lead">Schulungen, Erklärvideos und Zertifikate — Tarif: <?= View::escape(AcademyTier::labels()[$academyTierPlan] ?? $academyTierPlan) ?>.</p>
    </div>
  </header>

  <nav class="dg-subtabs" aria-label="Akademie-Bereiche">
    <a href="/app?page=akademie&amp;view=meine" class="dg-subtabs__link<?= $academyView === 'meine' ? ' is-active' : '' ?>">Meine Schulungen</a>
    <a href="/app?page=akademie&amp;view=katalog" class="dg-subtabs__link<?= $academyView === 'katalog' ? ' is-active' : '' ?>">Katalog</a>
    <?php if ($canAcademyHr) : ?>
      <a href="/app?page=akademie&amp;view=hr" class="dg-subtabs__link<?= $academyView === 'hr' ? ' is-active' : '' ?>">HR-Prüfung<?= $academyPendingHr !== [] ? ' (' . count($academyPendingHr) . ')' : '' ?></a>
    <?php endif; ?>
    <?php if ($canManageAcademy) : ?>
      <a href="/app?page=akademie&amp;view=admin" class="dg-subtabs__link<?= $academyView === 'admin' ? ' is-active' : '' ?>">Verwaltung</a>
    <?php endif; ?>
  </nav>

  <?php if ($academyView === 'meine') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Meine Schulungen</h2>
    <?php if ($academyAssignments === []) : ?>
      <p class="dg-muted">Noch keine Schulungen zugewiesen. Im <a href="/app?page=akademie&amp;view=katalog">Katalog</a> einen Kurs starten.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr><th>Kurs</th><th>Bereich</th><th>Status</th><th>Modus</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($academyAssignments as $asn) : ?>
              <tr>
                <td><?= View::escape((string) ($asn['course_title'] ?? '')) ?></td>
                <td><?= View::escape((string) ($asn['area_label'] ?? '')) ?></td>
                <td><?= View::escape((string) ($asn['status'] ?? '')) ?></td>
                <td><?= View::escape(AcademyAccessMode::labels()[(string) ($asn['access_mode'] ?? '')] ?? '') ?></td>
                <td><a class="dg-button dg-button--small" href="/app?page=akademie&amp;view=kurs&amp;slug=<?= rawurlencode((string) ($asn['course_slug'] ?? '')) ?>">Öffnen</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($academyView === 'katalog') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Katalog</h2>
    <?php if ($academyCatalog === []) : ?>
      <p class="dg-muted">Keine Kurse für Ihren Tarif verfügbar.</p>
    <?php else : ?>
      <div class="dg-academy-catalog">
        <?php foreach ($academyCatalog as $course) : ?>
          <article class="dg-academy-card">
            <h3><?= View::escape((string) ($course['title'] ?? '')) ?></h3>
            <p class="dg-muted"><?= View::escape((string) ($course['area_label'] ?? '')) ?> · v<?= View::escape((string) ($course['version'] ?? '')) ?></p>
            <p><?= nl2br(View::escape((string) ($course['description'] ?? ''))) ?></p>
            <a class="dg-button dg-button--primary" href="/app?page=akademie&amp;view=kurs&amp;slug=<?= rawurlencode((string) ($course['slug'] ?? '')) ?>">Kurs öffnen</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($academyView === 'kurs' && $academyCourse !== null) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title"><?= View::escape((string) ($academyCourse['title'] ?? '')) ?></h2>
    <p class="dg-field-hint"><?= View::escape((string) ($academyCourse['area_label'] ?? '')) ?> · Version <?= View::escape((string) ($academyCourse['version'] ?? '')) ?></p>
    <p><?= nl2br(View::escape((string) ($academyCourse['description'] ?? ''))) ?></p>

    <?php if (!$academyRulesAccepted) : ?>
    <form method="post" action="/app?page=akademie&amp;view=kurs&amp;slug=<?= rawurlencode((string) ($academyCourse['slug'] ?? '')) ?>" class="dg-panel dg-academy-rules">
      <h3 class="dg-subsection-title">Schulungsregeln (Pflicht)</h3>
      <ul class="dg-academy-rules__list">
        <li>Ihr Fortschritt wird protokolliert (Zeit, Geschwindigkeit, Sitzungen).</li>
        <li><strong>Max. 2× Wiedergabegeschwindigkeit</strong> — schneller zählt nicht.</li>
        <li>Ein 3-Stunden-Kurs in einem Viertel der erwarteten Zeit ist auffällig und wird geprüft.</li>
        <li>Tab/Fenster im Blick behalten — bei Wechsel pausiert die Zählung.</li>
        <li>Zertifikate werden erst nach <strong>HR-Freigabe</strong> ausgestellt.</li>
        <li>Manipulation oder Umgehung führt zur Ablehnung.</li>
      </ul>
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="view" value="kurs">
      <input type="hidden" name="course_id" value="<?= (int) ($academyCourse['id'] ?? 0) ?>">
      <label class="dg-field dg-field--checkbox dg-field--wide dg-academy-rules__confirm">
        <span>
          <input type="checkbox" name="confirm_rules" value="1" required>
          Ich habe die Schulungsregeln gelesen und verstanden.
        </span>
      </label>
      <div class="dg-form-actions dg-academy-rules__actions">
        <button type="submit" name="academy_accept_rules" value="1" class="dg-button dg-button--primary">Schulung starten</button>
      </div>
    </form>
    <?php else : ?>
      <?php if ($academySummary !== null) : ?>
        <p class="dg-field-hint">Fortschritt: <?= (int) ($academySummary['completed_modules'] ?? 0) ?> / <?= (int) ($academySummary['total_modules'] ?? 0) ?> Module</p>
      <?php endif; ?>
      <div class="dg-academy-modules">
        <?php foreach (($academySummary['modules'] ?? []) as $mod) : ?>
          <?php
            $modId = (int) ($mod['id'] ?? 0);
            $prog = ($academySummary['progress'][$modId] ?? null);
            $status = is_array($prog) ? (string) ($prog['status'] ?? 'not_started') : 'not_started';
          ?>
          <article class="dg-academy-card">
            <h3><?= View::escape((string) ($mod['title'] ?? '')) ?></h3>
            <p><?= nl2br(View::escape((string) ($mod['description'] ?? ''))) ?></p>
            <p class="dg-muted">Status: <?= View::escape($status) ?><?php if ((int) ($mod['duration_sec'] ?? 0) > 0) : ?> · ca. <?= (int) ceil(((int) $mod['duration_sec']) / 60) ?> Min.<?php endif; ?></p>
            <a class="dg-button" href="/app?page=akademie&amp;view=modul&amp;slug=<?= rawurlencode((string) ($academyCourse['slug'] ?? '')) ?>&amp;module_id=<?= $modId ?>">Modul öffnen</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($academyView === 'modul' && $academyCourse !== null && $academyModule !== null && $academyAssignment !== null && $academyRulesAccepted) : ?>
  <?php
    $videoPath = trim((string) ($academyModule['video_path'] ?? ''));
    $hasVideo = $videoPath !== '' && is_file(DG_ROOT . '/storage/' . ltrim($videoPath, '/'));
    $vttPath = trim((string) ($academyModule['subtitle_vtt_path'] ?? ''));
    $hasVtt = $vttPath !== '' && is_file(DG_ROOT . '/storage/' . ltrim($vttPath, '/'));
  ?>
  <section class="dg-panel dg-academy-player-wrap">
    <h2 class="dg-subsection-title"><?= View::escape((string) ($academyModule['title'] ?? '')) ?></h2>
    <p class="dg-field-hint"><?= View::escape((string) ($academyCourse['title'] ?? '')) ?></p>
    <div class="dg-academy-player__desc"><?= nl2br(View::escape((string) ($academyModule['description'] ?? ''))) ?></div>

    <?php if ($hasVideo) : ?>
    <video id="dg-academy-video" class="dg-academy-player" controls playsinline
      data-max-rate="<?= View::escape((string) ($academyModule['max_playback_rate'] ?? '2')) ?>"
      <?php if ($hasVtt) : ?>
        crossorigin="anonymous"
      <?php endif; ?>>
      <source src="/app?page=akademie&amp;download=video&amp;module_id=<?= (int) ($academyModule['id'] ?? 0) ?>" type="video/mp4">
      <?php if ($hasVtt) : ?>
        <track kind="subtitles" src="/app?page=akademie&amp;download=vtt&amp;module_id=<?= (int) ($academyModule['id'] ?? 0) ?>" srclang="de" label="Deutsch" default>
      <?php endif; ?>
    </video>
    <?php else : ?>
    <div class="dg-panel dg-panel--muted">
      <p><strong>Video folgt.</strong> Lesen Sie die Beschreibung oben. Für dieses Modul ist noch keine Videodatei hinterlegt (<code>storage/media/training/</code>).</p>
      <p class="dg-muted">Dauer für Fortschritt: <?= (int) ($academyModule['duration_sec'] ?? 0) ?> Sekunden (Admin pflegt <code>duration_sec</code>).</p>
    </div>
    <?php endif; ?>

    <div id="dg-academy-player-message" class="dg-scan-result" hidden></div>
    <div class="dg-form-actions">
      <a class="dg-button" href="/app?page=akademie&amp;view=kurs&amp;slug=<?= rawurlencode((string) ($academyCourse['slug'] ?? '')) ?>">Zurück zum Kurs</a>
      <button type="button" class="dg-button dg-button--primary" id="dg-academy-complete-btn"
        data-assignment-id="<?= (int) ($academyAssignment['id'] ?? 0) ?>"
        data-module-id="<?= (int) ($academyModule['id'] ?? 0) ?>"
        data-has-video="<?= $hasVideo ? '1' : '0' ?>"
        data-duration="<?= (int) ($academyModule['duration_sec'] ?? 180) ?>">Modul abschließen</button>
    </div>
  </section>
  <script>
  window.dgAcademyConfig = {
    apiUrl: '/api/academy',
    csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>,
    assignmentId: <?= (int) ($academyAssignment['id'] ?? 0) ?>,
    moduleId: <?= (int) ($academyModule['id'] ?? 0) ?>,
    hasVideo: <?= $hasVideo ? 'true' : 'false' ?>,
    durationSec: <?= (int) ($academyModule['duration_sec'] ?? 180) ?>
  };
  </script>
  <script src="<?= View::escape(Asset::url('/assets/js/academy-player.js')) ?>" defer></script>

  <?php elseif ($academyView === 'hr' && $canAcademyHr) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">HR-Prüfung</h2>
    <?php if ($academyPendingHr === []) : ?>
      <p class="dg-muted">Keine offenen Prüfungen.</p>
    <?php else : ?>
      <?php foreach ($academyPendingHr as $pending) : ?>
        <?php $summary = AcademyProgressService::assignmentSummary((int) ($pending['id'] ?? 0)); ?>
        <article class="dg-panel dg-panel--nested dg-academy-hr-card <?= View::escape($riskClass((string) ($summary['risk_level'] ?? ''))) ?>">
          <h3><?= View::escape((string) ($pending['user_name'] ?? '')) ?> — <?= View::escape((string) ($pending['course_title'] ?? '')) ?></h3>
          <p>Status: <strong><?= View::escape((string) ($pending['status'] ?? '')) ?></strong>
            · Module: <?= (int) ($summary['completed_modules'] ?? 0) ?>/<?= (int) ($summary['total_modules'] ?? 0) ?>
            · Wandzeit: <?= (int) ($summary['total_wall_clock_sec'] ?? 0) ?> s (min. <?= (int) ($summary['minimum_wall_clock_sec'] ?? 0) ?> s)</p>
          <?php if (($summary['anomaly_flags'] ?? []) !== []) : ?>
            <ul class="dg-academy-flags">
              <?php foreach ($summary['anomaly_flags'] as $flag) : ?>
                <li class="dg-academy-flag dg-academy-flag--red"><?= View::escape($flagLabels[$flag] ?? $flag) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <form method="post" class="dg-form-grid dg-form-grid--compact">
            <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
            <input type="hidden" name="view" value="hr">
            <input type="hidden" name="assignment_id" value="<?= (int) ($pending['id'] ?? 0) ?>">
            <label class="dg-field dg-field--wide">
              <span>Notiz (Pflicht bei Ablehnung)</span>
              <textarea name="review_note" rows="2" maxlength="1000"></textarea>
            </label>
            <div class="dg-field dg-field--actions">
              <button type="submit" name="academy_hr_approve" value="1" class="dg-button dg-button--primary">Freigeben</button>
              <button type="submit" name="academy_hr_reject" value="1" class="dg-button">Ablehnen</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <?php elseif ($academyView === 'admin' && $canManageAcademy) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Kurs-Verwaltung (Admin/Chef)</h2>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <thead><tr><th>Kurs</th><th>Bereich</th><th>Tarif</th><th>Modus</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($academyAllCourses as $course) : ?>
            <tr>
              <td><?= View::escape((string) ($course['title'] ?? '')) ?></td>
              <td><?= View::escape((string) ($course['area_label'] ?? '')) ?></td>
              <td><?= View::escape(AcademyTier::labels()[(string) ($course['min_tier'] ?? '')] ?? '') ?></td>
              <td><?= View::escape(AcademyAccessMode::labels()[(string) ($course['access_mode_default'] ?? '')] ?? '') ?></td>
              <td><a href="/app?page=akademie&amp;view=admin&amp;course_id=<?= (int) ($course['id'] ?? 0) ?>">Bearbeiten</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($academyAdminCourse !== null) : ?>
  <section class="dg-panel">
    <h3 class="dg-subsection-title"><?= View::escape((string) ($academyAdminCourse['title'] ?? '')) ?> — Zuweisung & Sperre</h3>
    <form method="post" class="dg-form-grid dg-form-grid--compact">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="view" value="admin">
      <input type="hidden" name="course_id" value="<?= (int) ($academyAdminCourse['id'] ?? 0) ?>">
      <label class="dg-field">
        <span>Mitarbeiter</span>
        <select name="user_id" required>
          <option value="">— wählen —</option>
          <?php foreach ($academyUserOptions as $opt) : ?>
            <option value="<?= (int) ($opt['id'] ?? 0) ?>"><?= View::escape((string) ($opt['display_name'] ?? '')) ?> (<?= View::escape((string) ($opt['email'] ?? '')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Zugriffsmodus</span>
        <select name="access_mode">
          <?php foreach (AcademyAccessMode::labels() as $key => $label) : ?>
            <option value="<?= View::escape($key) ?>"><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" name="academy_assign" value="1" class="dg-button dg-button--primary">Zuweisen</button>
      </div>
    </form>
    <form method="post" class="dg-form-grid dg-form-grid--compact dg-academy-gate-form">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="view" value="admin">
      <input type="hidden" name="course_id" value="<?= (int) ($academyAdminCourse['id'] ?? 0) ?>">
      <input type="hidden" name="module_key" value="lager">
      <label class="dg-field">
        <span><input type="checkbox" name="gate_active" value="1"> Lager-Modul sperren bis Schulung abgeschlossen (hart, wenn Modus „hart“)</span>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" name="academy_set_gate" value="1" class="dg-button">Gate speichern</button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <?php else : ?>
  <section class="dg-panel">
    <p class="dg-muted">Ansicht nicht verfügbar.</p>
  </section>
  <?php endif; ?>
</div>
