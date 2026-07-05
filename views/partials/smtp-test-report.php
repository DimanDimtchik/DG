<?php
/** @var array<string, mixed>|null $smtpTestReport */
if ($smtpTestReport === null) {
    return;
}
$encryptionLabel = match ($smtpTestReport['encryption'] ?? '') {
    'ssl' => 'SSL',
    'tls' => 'STARTTLS',
    default => 'Keine',
};
?>
<section class="dg-smtp-test<?= !empty($smtpTestReport['ok']) ? ' dg-smtp-test--ok' : ' dg-smtp-test--fail' ?>">
  <h3>SMTP-Testergebnis</h3>
  <p class="dg-smtp-test__summary"><?= View::escape((string) ($smtpTestReport['summary'] ?? '')) ?></p>
  <dl class="dg-dl dg-dl--compact">
    <dt>Host</dt>
    <dd><?= View::escape((string) ($smtpTestReport['host'] ?? '')) ?>:<?= (int) ($smtpTestReport['port'] ?? 0) ?></dd>
    <dt>Verschlüsselung</dt>
    <dd><?= View::escape($encryptionLabel) ?></dd>
    <dt>SMTP-Login</dt>
    <dd><?= View::escape((string) (($smtpTestReport['username'] ?? '') !== '' ? $smtpTestReport['username'] : '—')) ?></dd>
    <?php if (($smtpTestReport['sender_email'] ?? '') !== '') : ?>
      <dt>Absender (Firmendaten)</dt>
      <dd>
        <?= View::escape((string) ($smtpTestReport['sender_name'] ?? '')) ?>
        &lt;<?= View::escape((string) $smtpTestReport['sender_email']) ?>&gt;
      </dd>
    <?php endif; ?>
  </dl>
  <?php
    $login = (string) ($smtpTestReport['username'] ?? '');
    $sender = (string) ($smtpTestReport['sender_email'] ?? '');
    if ($login !== '' && $sender !== '' && strcasecmp($login, $sender) !== 0) :
  ?>
    <p class="dg-lead dg-smtp-test__note">
      Der SMTP-Login (<strong><?= View::escape($login) ?></strong>) weicht vom Absender
      (<strong><?= View::escape($sender) ?></strong>) ab — das ist erlaubt, wenn die Mailbox zum Login passt.
    </p>
  <?php endif; ?>
  <ol class="dg-smtp-test__steps">
    <?php foreach (($smtpTestReport['steps'] ?? []) as $step) : ?>
      <li class="dg-smtp-test__step<?= !empty($step['ok']) ? ' is-ok' : ' is-fail' ?>">
        <strong><?= View::escape((string) ($step['label'] ?? '')) ?></strong>
        <span><?= View::escape((string) ($step['detail'] ?? '')) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
