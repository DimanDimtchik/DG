<?php
/**
 * @var list<array<string, mixed>> $calendarArticles
 * @var list<array<string, mixed>> $calendarAreas
 * @var bool $dbConnected
 * @var string $catalogFilter
 * @var array{type: string, message: string}|null $flash
 */
$catalogBaseUrl = '/app?page=artikel-leistungen';
$catalogFilter = $catalogFilter ?? 'all';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Artikel &amp; Leistungen</h1>
  </header>

  <?php require DG_ROOT . '/views/settings/tab-calendar-articles.php'; ?>
</div>
