<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= ShopView::escape($title ?? 'Shop-Verwaltung') ?></title>
  <link rel="stylesheet" href="<?= ShopView::escape(ShopView::asset('assets/css/shop.css')) ?>">
</head>
<body class="shop-admin-body">
  <main class="shop-admin-wrap">
    <?php require $templateFile; ?>
  </main>
</body>
</html>
