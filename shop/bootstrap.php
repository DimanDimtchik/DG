<?php

declare(strict_types=1);

define('SHOP_ROOT', __DIR__);

require_once SHOP_ROOT . '/src/ShopApp.php';
require_once SHOP_ROOT . '/src/ShopPlans.php';
require_once SHOP_ROOT . '/src/ShopView.php';
require_once SHOP_ROOT . '/src/ShopCheckout.php';
require_once SHOP_ROOT . '/src/ShopDomainCheck.php';
require_once SHOP_ROOT . '/src/ShopCookieConsent.php';
require_once SHOP_ROOT . '/src/ShopAccountApi.php';
require_once SHOP_ROOT . '/src/ShopAccountSession.php';
require_once SHOP_ROOT . '/src/ShopStripe.php';

ShopApp::boot();
