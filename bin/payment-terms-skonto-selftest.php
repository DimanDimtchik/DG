<?php
declare(strict_types=1);

/** PA2b: Skonto-Stufe = engstes passendes Zeitfenster (nicht die letzte Stufe). */
require dirname(__DIR__) . '/bootstrap.php';

function ok(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$tiers = [
    ['days' => 7, 'adjustment_percent' => -3.0, 'label' => 'Skonto'],
    ['days' => 30, 'adjustment_percent' => 0.0, 'label' => 'Netto'],
    ['days' => 90, 'adjustment_percent' => 1.5, 'label' => 'Verzug'],
];

$day5 = PaymentTermsService::tierForPaymentDate('2026-10-01', '2026-10-05', $tiers);
ok(($day5['label'] ?? '') === 'Skonto', 'Tag 5 → Skonto-Stufe');

$day20 = PaymentTermsService::tierForPaymentDate('2026-10-01', '2026-10-20', $tiers);
ok(($day20['label'] ?? '') === 'Netto', 'Tag 20 → Netto-Stufe');

$day100 = PaymentTermsService::tierForPaymentDate('2026-10-01', '2027-01-10', $tiers);
ok(($day100['label'] ?? '') === 'Verzug', 'jenseits 90 Tage → Verzug');

$settle = PaymentTermsService::settlementAmounts(1190.0, '2026-10-01', '2026-10-05', $tiers);
ok(abs((float) $settle['discount_percent'] - 3.0) < 0.01, '3 % Skonto');
ok(abs((float) $settle['discount_amount'] - 35.70) < 0.02, 'Skonto-Betrag 35,70');
ok(abs((float) $settle['paid_amount'] - 1154.30) < 0.02, 'Zahlbetrag 1154,30');

echo "Fertig.\n";
