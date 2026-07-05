<?php
$pageTitle = View::escape((string) App::config('crm_name')) . ' – In Vorbereitung';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $pageTitle ?></title>
  <link rel="stylesheet" href="<?= View::escape(Asset::url('/assets/css/dg.min.css')) ?>">
</head>
<body class="dg-offline">
  <main class="dg-offline__main">
    <p class="dg-offline__text">Die Seite ist noch nicht online.</p>
    <p class="dg-offline__text">
      <a class="dg-offline__link" href="<?= View::escape((string) App::config('home_url')) ?>">ganz-om.de</a>
    </p>
  </main>
</body>
</html>
