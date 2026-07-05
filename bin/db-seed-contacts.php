<?php
declare(strict_types=1);

/**
 * Demo-Kontakte für die Kontakte-Liste.
 * php bin/db-seed-contacts.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
$existing = (int) $pdo->query('SELECT COUNT(*) FROM dg_contacts')->fetchColumn();
if ($existing > 0) {
    echo "{$existing} Kontakte bereits vorhanden – übersprungen.\n";
    exit(0);
}

$contacts = [
    [
        'login' => 'mueller-gmbh',
        'salutation' => 'Firma',
        'display_name' => 'Müller GmbH',
        'company_name' => 'Müller GmbH',
        'email' => 'info@mueller-gmbh.de',
        'phone_1' => '+49 30 1234567',
        'customer_number' => 'K-10001',
        'tax_number' => '27/123/45678',
        'vat_id' => 'DE123456789',
        'address1_street' => 'Hauptstraße 12',
        'address1_postal' => '10115',
        'address1_city' => 'Berlin',
        'contact_role' => 'kunde',
        'contact_persons' => json_encode([
            [
                'salutation' => 'Frau',
                'first_name' => 'Sabine',
                'last_name' => 'Müller',
                'department' => 'Einkauf',
                'responsibility' => 'Bestellungen',
                'email' => 's.mueller@mueller-gmbh.de',
                'phone' => '+49 30 1234568',
            ],
        ], JSON_UNESCAPED_UNICODE),
    ],
    [
        'login' => 'schmidt-hans',
        'salutation' => 'Herr',
        'first_name' => 'Hans',
        'last_name' => 'Schmidt',
        'display_name' => 'Hans Schmidt',
        'email' => 'h.schmidt@example.de',
        'phone_1' => '+49 89 9876543',
        'customer_number' => 'K-10002',
        'address1_street' => 'Ringstraße 5',
        'address1_postal' => '80331',
        'address1_city' => 'München',
        'contact_role' => 'kunde',
    ],
    [
        'login' => 'lieferant-tech',
        'salutation' => 'Firma',
        'display_name' => 'TechSupply AG',
        'company_name' => 'TechSupply AG',
        'email' => 'auftrag@techsupply.de',
        'phone_1' => '+49 40 5551234',
        'supplier_number' => 'L-20001',
        'vat_id' => 'DE987654321',
        'address1_street' => 'Hafenweg 88',
        'address1_postal' => '20457',
        'address1_city' => 'Hamburg',
        'contact_role' => 'kunde',
    ],
    [
        'login' => 'weber-anna',
        'salutation' => 'Frau',
        'first_name' => 'Anna',
        'last_name' => 'Weber',
        'display_name' => 'Anna Weber',
        'email' => 'anna.weber@example.de',
        'phone_1' => '+49 711 445566',
        'customer_number' => 'K-10003',
        'address1_street' => 'Gartenallee 3',
        'address1_postal' => '70173',
        'address1_city' => 'Stuttgart',
        'contact_role' => 'kunde',
    ],
    [
        'login' => 'partner-ost',
        'salutation' => 'Firma',
        'display_name' => 'Partner Ost KG',
        'company_name' => 'Partner Ost KG',
        'email' => 'kontakt@partner-ost.de',
        'customer_number' => 'K-10004',
        'supplier_number' => 'L-20002',
        'address1_street' => 'Markt 1',
        'address1_postal' => '01067',
        'address1_city' => 'Dresden',
        'contact_role' => 'kunde',
    ],
];

$sql = 'INSERT INTO dg_contacts (
    login, salutation, first_name, last_name, display_name, company_name,
    email, phone_1, customer_number, supplier_number, tax_number, vat_id,
    address1_street, address1_postal, address1_city, contact_role, contact_persons
) VALUES (
    :login, :salutation, :first_name, :last_name, :display_name, :company_name,
    :email, :phone_1, :customer_number, :supplier_number, :tax_number, :vat_id,
    :address1_street, :address1_postal, :address1_city, :contact_role, :contact_persons
)';

$stmt = $pdo->prepare($sql);

foreach ($contacts as $contact) {
    $stmt->execute([
        'login' => $contact['login'],
        'salutation' => $contact['salutation'],
        'first_name' => $contact['first_name'] ?? '',
        'last_name' => $contact['last_name'] ?? '',
        'display_name' => $contact['display_name'],
        'company_name' => $contact['company_name'] ?? '',
        'email' => $contact['email'],
        'phone_1' => $contact['phone_1'] ?? '',
        'customer_number' => $contact['customer_number'] ?? '',
        'supplier_number' => $contact['supplier_number'] ?? '',
        'tax_number' => $contact['tax_number'] ?? '',
        'vat_id' => $contact['vat_id'] ?? '',
        'address1_street' => $contact['address1_street'] ?? '',
        'address1_postal' => $contact['address1_postal'] ?? '',
        'address1_city' => $contact['address1_city'] ?? '',
        'contact_role' => $contact['contact_role'],
        'contact_persons' => $contact['contact_persons'] ?? null,
    ]);
}

echo count($contacts) . " Demo-Kontakte angelegt.\n";
