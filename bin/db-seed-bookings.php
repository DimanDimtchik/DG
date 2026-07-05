<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
if ((int) $pdo->query('SELECT COUNT(*) FROM dg_bookings')->fetchColumn() > 0) {
    echo "Buchungen bereits vorhanden.\n";
    exit(0);
}

$bookings = [
    ['2026-07-02 10:00:00', 'Hans Schmidt', 'h.schmidt@example.de', '+49 89 9876543', 'gebucht', 'Erstberatung'],
    ['2026-07-03 14:30:00', 'Müller GmbH', 'info@mueller-gmbh.de', '+49 30 1234567', 'bestätigt', ''],
    ['2026-07-05 09:00:00', 'Anna Weber', 'anna.weber@example.de', '', 'gebucht', ''],
    ['2026-06-28 11:00:00', 'Partner Ost KG', 'kontakt@partner-ost.de', '+49 351 112233', 'abgeschlossen', ''],
    ['2026-07-10 16:00:00', 'TechSupply AG', 'auftrag@techsupply.de', '+49 40 5551234', 'storniert', 'Kunde abgesagt'],
];

$stmt = $pdo->prepare(
    'INSERT INTO dg_bookings (slot_datetime, customer_name, customer_email, customer_phone, status, admin_notes)
     VALUES (:slot, :name, :email, :phone, :status, :notes)'
);

foreach ($bookings as [$slot, $name, $email, $phone, $status, $notes]) {
    $stmt->execute([
        'slot' => $slot,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'status' => $status,
        'notes' => $notes,
    ]);
}

echo count($bookings) . " Demo-Buchungen angelegt.\n";
