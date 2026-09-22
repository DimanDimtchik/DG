<?php
/**
 * HR: Kiosk-PIN-Anfragen + PIN für Mitarbeiter setzen.
 *
 * @var list<array<string, mixed>> $kioskPendingResets
 * @var list<array{id: int, label: string}> $kioskStaffOptions
 * @var array{type: string, message: string}|null $flash
 */
$pending = is_array($kioskPendingResets ?? null) ? $kioskPendingResets : [];
$staff = is_array($kioskStaffOptions ?? null) ? $kioskStaffOptions : [];
?>
<div class="dg-wrap dg-zeiterfassung-kiosk">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Stempeluhr-PIN</h1>
      <p class="dg-lead">PIN setzen · PIN-Vergessen-Anfragen freigeben · Kiosk: <a href="/stempeluhr" target="_blank" rel="noopener">/stempeluhr</a></p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <a class="dg-button" href="/app?page=zeiterfassung">Meine Zeiterfassung</a>
    </div>
  </header>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Offene PIN-Anfragen</h2>
    <?php if ($pending === []) : ?>
      <p class="dg-muted">Keine offenen Anfragen.</p>
    <?php else : ?>
      <table class="dg-table">
        <thead>
          <tr>
            <th>Mitarbeiter</th>
            <th>Anfrage</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $row) : ?>
            <?php
              $rid = (int) ($row['id'] ?? 0);
              $name = trim((string) ($row['display_name'] ?? ''));
              if ($name === '') {
                  $name = trim((string) ($row['login'] ?? '')) ?: ('#' . (int) ($row['contact_id'] ?? 0));
              }
            ?>
            <tr>
              <td><?= View::escape($name) ?></td>
              <td><?= View::escape((string) ($row['requested_at'] ?? '')) ?></td>
              <td>
                <form method="post" action="/app?page=zeiterfassung-kiosk" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="kiosk_hr_decide" value="1">
                  <input type="hidden" name="reset_id" value="<?= $rid ?>">
                  <button type="submit" name="decision" value="allow" class="dg-button dg-button--primary">Erlauben</button>
                  <button type="submit" name="decision" value="block" class="dg-button">Blockieren</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">PIN für Mitarbeiter setzen</h2>
    <form method="post" action="/app?page=zeiterfassung-kiosk" class="dg-form">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="kiosk_set_pin" value="1">
      <label>
        <span>Mitarbeiter</span>
        <select name="contact_id" required>
          <option value="">— wählen —</option>
          <?php foreach ($staff as $opt) : ?>
            <option value="<?= (int) ($opt['id'] ?? 0) ?>"><?= View::escape((string) ($opt['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        <span>Neue PIN (4–8 Ziffern)</span>
        <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="8" required autocomplete="new-password">
      </label>
      <label>
        <span>PIN wiederholen</span>
        <input type="password" name="pin_confirm" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="8" required autocomplete="new-password">
      </label>
      <button type="submit" class="dg-button dg-button--primary">PIN speichern</button>
    </form>
  </section>
</div>
