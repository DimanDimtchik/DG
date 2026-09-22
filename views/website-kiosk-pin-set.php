<?php
/**
 * Mitarbeiter setzt neue PIN nach HR-Freigabe.
 *
 * @var string $kioskToken
 * @var string|null $kioskFlash
 * @var string $kioskFlashType
 * @var bool $kioskDone
 */
$token = (string) ($kioskToken ?? '');
$flash = (string) ($kioskFlash ?? '');
$flashType = (string) ($kioskFlashType ?? 'info');
$done = !empty($kioskDone);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Neue PIN festlegen</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 2rem auto; padding: 0 1rem; }
    label { display: block; margin: .75rem 0 .35rem; }
    input { width: 100%; padding: .75rem; font-size: 1.1rem; }
    button { margin-top: 1rem; padding: .85rem 1.25rem; border: 0; border-radius: .5rem; background: #0284c7; color: #fff; font-weight: 600; width: 100%; }
    .flash { padding: .75rem; border-radius: .5rem; background: #f1f5f9; margin-bottom: 1rem; }
    .flash.err { background: #fee2e2; }
    .flash.ok { background: #dcfce7; }
  </style>
</head>
<body>
  <h1>Neue Stempel-PIN</h1>
  <?php if ($flash !== '') : ?>
    <div class="flash <?= $flashType === 'error' ? 'err' : ($flashType === 'success' ? 'ok' : '') ?>"><?= View::escape($flash) ?></div>
  <?php endif; ?>
  <?php if (!$done) : ?>
    <form method="post" action="/stempeluhr/pin-setzen">
      <input type="hidden" name="token" value="<?= View::escape($token) ?>">
      <label for="pin">Neue PIN (4–8 Ziffern)</label>
      <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="8" required>
      <label for="pin2">PIN wiederholen</label>
      <input id="pin2" name="pin_confirm" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="8" required>
      <button type="submit">PIN speichern</button>
    </form>
  <?php else : ?>
    <p><a href="/stempeluhr">Zur Stempeluhr</a></p>
  <?php endif; ?>
</body>
</html>
