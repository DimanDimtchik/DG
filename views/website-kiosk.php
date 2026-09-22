<?php
/**
 * Öffentliche Stempeluhr (Kiosk) — kein CRM-Login.
 *
 * @var array{contact_id: int, label: string}|null $kioskSession
 * @var array<string, mixed>|null $kioskSummary
 * @var array{state: string, label: string, since_display?: string|null}|null $kioskStatus
 * @var string|null $kioskFlash
 * @var string $kioskFlashType
 * @var string $kioskView  login|clock|forgot
 */
$session = $kioskSession ?? null;
$summary = is_array($kioskSummary ?? null) ? $kioskSummary : [];
$status = is_array($kioskStatus ?? null) ? $kioskStatus : ['state' => 'off', 'label' => 'Nicht eingestempelt'];
$flash = (string) ($kioskFlash ?? '');
$flashType = (string) ($kioskFlashType ?? 'info');
$view = (string) ($kioskView ?? ($session ? 'clock' : 'login'));
$state = (string) ($status['state'] ?? 'off');
$siteName = (string) (App::config('crm_name') ?: 'DG CRM');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <title>Stempeluhr – <?= View::escape($siteName) ?></title>
  <style>
    :root { --k-bg: #0f172a; --k-card: #1e293b; --k-text: #f8fafc; --k-muted: #94a3b8; --k-accent: #38bdf8; --k-ok: #22c55e; --k-warn: #f59e0b; --k-danger: #ef4444; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; font-family: system-ui, sans-serif; background: var(--k-bg); color: var(--k-text); display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
    .kiosk { width: 100%; max-width: 28rem; background: var(--k-card); border-radius: 1rem; padding: 1.75rem; box-shadow: 0 20px 50px rgba(0,0,0,.35); }
    h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
    .lead { color: var(--k-muted); margin: 0 0 1.25rem; font-size: .95rem; }
    label { display: block; margin: .75rem 0 .35rem; font-size: .9rem; color: var(--k-muted); }
    input { width: 100%; padding: .85rem 1rem; border-radius: .5rem; border: 1px solid #334155; background: #0f172a; color: var(--k-text); font-size: 1.15rem; }
    .actions { display: flex; flex-direction: column; gap: .75rem; margin-top: 1.25rem; }
    button, .btn { appearance: none; border: 0; border-radius: .6rem; padding: 1rem 1.1rem; font-size: 1.1rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; display: block; color: #0f172a; background: var(--k-accent); }
    button.secondary, .btn.secondary { background: #475569; color: #fff; }
    button.warn { background: var(--k-warn); }
    button.danger { background: var(--k-danger); color: #fff; }
    button.ok { background: var(--k-ok); }
    .flash { margin-bottom: 1rem; padding: .75rem 1rem; border-radius: .5rem; background: #334155; }
    .flash.err { background: #7f1d1d; }
    .flash.ok { background: #14532d; }
    .status { font-size: 1.15rem; margin: 0 0 1rem; }
    .status strong { color: var(--k-accent); }
    .muted { color: var(--k-muted); font-size: .85rem; margin-top: 1rem; }
    .links { margin-top: 1rem; text-align: center; }
    .links a { color: var(--k-accent); }
  </style>
</head>
<body>
  <main class="kiosk">
    <h1>Stempeluhr</h1>
    <p class="lead"><?= View::escape($siteName) ?> — ohne CRM-Anmeldung</p>

    <?php if ($flash !== '') : ?>
      <div class="flash <?= $flashType === 'error' ? 'err' : ($flashType === 'success' ? 'ok' : '') ?>">
        <?= View::escape($flash) ?>
      </div>
    <?php endif; ?>

    <?php if ($view === 'forgot') : ?>
      <form method="post" action="/stempeluhr">
        <input type="hidden" name="kiosk_action" value="forgot">
        <label for="ident">Login, E-Mail oder Name</label>
        <input id="ident" name="identifier" required autocomplete="username" inputmode="email">
        <div class="actions">
          <button type="submit">PIN-Hilfe anfordern</button>
          <a class="btn secondary" href="/stempeluhr">Zurück</a>
        </div>
      </form>
      <p class="muted">HR erhält eine E-Mail und kann die Anfrage erlauben oder blockieren. Danach bekommen Sie einen Link zur neuen PIN.</p>

    <?php elseif ($session) : ?>
      <p class="status">
        <?= View::escape((string) ($session['label'] ?? '')) ?> —
        <strong><?= View::escape((string) ($status['label'] ?? '')) ?></strong>
        <?php if (!empty($status['since_display'])) : ?>
          <span class="muted">seit <?= View::escape((string) $status['since_display']) ?></span>
        <?php endif; ?>
      </p>
      <p class="muted">
        Heute: <?= View::escape((string) ($summary['worked_display'] ?? '0:00')) ?> h
        (Pause <?= View::escape((string) ($summary['break_display'] ?? '0:00')) ?> h)
      </p>
      <div class="actions">
        <?php if ($state === 'off') : ?>
          <form method="post" action="/stempeluhr">
            <input type="hidden" name="kiosk_action" value="clock">
            <input type="hidden" name="event_type" value="clock_in">
            <button type="submit" class="ok">Einstempeln</button>
          </form>
        <?php elseif ($state === 'working') : ?>
          <form method="post" action="/stempeluhr">
            <input type="hidden" name="kiosk_action" value="clock">
            <input type="hidden" name="event_type" value="break_start">
            <button type="submit" class="warn">Pause starten</button>
          </form>
          <form method="post" action="/stempeluhr">
            <input type="hidden" name="kiosk_action" value="clock">
            <input type="hidden" name="event_type" value="clock_out">
            <button type="submit" class="danger">Ausstempeln</button>
          </form>
        <?php elseif ($state === 'break') : ?>
          <form method="post" action="/stempeluhr">
            <input type="hidden" name="kiosk_action" value="clock">
            <input type="hidden" name="event_type" value="break_end">
            <button type="submit" class="ok">Pause beenden</button>
          </form>
        <?php endif; ?>
        <form method="post" action="/stempeluhr">
          <input type="hidden" name="kiosk_action" value="logout">
          <button type="submit" class="secondary">Abmelden</button>
        </form>
      </div>

    <?php else : ?>
      <form method="post" action="/stempeluhr">
        <input type="hidden" name="kiosk_action" value="login">
        <label for="ident">Login, E-Mail oder Name</label>
        <input id="ident" name="identifier" required autocomplete="username">
        <label for="pin">PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="8" required autocomplete="current-password">
        <div class="actions">
          <button type="submit">Anmelden</button>
        </div>
      </form>
      <p class="links"><a href="/stempeluhr?view=forgot">PIN vergessen?</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
