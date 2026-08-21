<?php

declare(strict_types=1);

/**
 * Vorlage für Stripe – lokal als stripe.local.php kopieren (nicht committen).
 *
 * Testkeys: https://dashboard.stripe.com/test/apikeys
 * Webhook: Endpoint https://shop.ganz-soft.de/webhook/stripe
 *   Events: checkout.session.completed
 */
return [
    'secret_key' => 'sk_test_CHANGE_ME',
    'publishable_key' => 'pk_test_CHANGE_ME',
    'webhook_secret' => 'whsec_CHANGE_ME',
    // Öffentliche Shop-URL (ohne Slash am Ende)
    'public_base_url' => 'https://shop.ganz-soft.de',
    // Bearer-Key für POST dg.ganz-om.de/api/kdv/provision
    'kdv_api_key' => 'CHANGE_ME',
];
