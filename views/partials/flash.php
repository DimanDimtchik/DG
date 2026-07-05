<?php
/** @var array{type: string, message: string}|null $flash */
if (!$flash || ($flash['message'] ?? '') === '') {
    return;
}
$type = $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success');
?>
<div class="dg-flash dg-flash--<?= View::escape($type) ?>" role="status">
  <?= View::escape($flash['message']) ?>
</div>
