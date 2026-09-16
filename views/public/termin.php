<?php
/** @var array<string, mixed> $embedConfig */
/** @var list<array<string, mixed>> $bookingArticles */
/** @var list<array<string, mixed>> $bookingEmployees */
$pageTitle = CalendarEmbedSettings::pageTitle() . ' – ' . CompanySettings::displayName();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
<style><?= CalendarFrontendTheme::inlineCss() ?></style>
</head>
<body class="tk-book-page">
<?php View::render('public/termin-widget', array_merge(get_defined_vars(), ['embedded' => false])); ?>
</body>
</html>
