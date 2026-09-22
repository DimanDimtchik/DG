<?php
/**
 * HR: PIN-Anfrage erlauben/blockieren (Token-Link aus E-Mail).
 *
 * @var array<string, mixed> $kioskReset
 * @var Contact|null $kioskContact
 * @var string|null $kioskFlash
 * @var string $kioskFlashType
 * @var bool $kioskDone
 * @var string $kioskToken
 */
$reset = is_array($kioskReset ?? null) ? $kioskReset : [];
$contact = $kioskContact ?? null;
$flash = (string) ($kioskFlash ?? '');
$flashType = (string) ($kioskFlashType ?? 'info');
$done = !empty($kioskDone);
$token = (string) ($kioskToken ?? '');
$status = (string) ($reset['status'] ?? '');
$label = $contact !== null ? $contact->listLabel() : ('Kontakt #' . (int) ($reset['contact_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>PIN-Anfrage prüfen</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem; color: #0f172a; }
    .card { border: 1px solid #cbd5e1; border-radius: .75rem; padding: 1.25rem; }
    .actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1rem; }
    button { padding: .75rem 1.25rem; border: 0; border-radius: .5rem; font-weight: 600; cursor: pointer; }
    .allow { background: #16a34a; color: #fff; }
    .block { background: #dc2626; color: #fff; }
    .flash { padding: .75rem; border-radius: .5rem; background: #f1f5f9; margin-bottom: 1rem; }
    .flash.err { background: #fee2e2; }
    .flash.ok { background: #dcfce7; }
  </style>
</head>
<body>
  <h1>Stempeluhr — PIN-Anfrage</h1>
  <?php if ($flash !== '') : ?>
    <div class="flash <?= $flashType === 'error' ? 'err' : ($flashType === 'success' ? 'ok' : '') ?>"><?= View::escape($flash) ?></div>
  <?php endif; ?>
  <div class="card">
    <p>Mitarbeiter: <strong><?= View::escape($label) ?></strong></p>
    <p>Status: <?= View::escape($status) ?></p>
    <?php if (!$done && $status === 'pending_hr') : ?>
      <form method="post" action="/stempeluhr/pin-anfrage" class="actions">
        <input type="hidden" name="token" value="<?= View::escape($token) ?>">
        <button class="allow" type="submit" name="decision" value="allow">Erlauben</button>
        <button class="block" type="submit" name="decision" value="block">Blockieren</button>
      </form>
      <p style="color:#64748b;font-size:.9rem;margin-top:1rem;">
        Erlauben sendet dem Mitarbeiter eine E-Mail mit Link zur neuen PIN.
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
