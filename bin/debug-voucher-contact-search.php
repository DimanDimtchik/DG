<?php
require 'C:/Users/dietr/Projects/DG/bootstrap.php';
if (!Database::isConfigured()) {
    echo "DB_NOT_CONFIGURED\n";
    exit(0);
}
$pdo = Database::pdo();
foreach (['müller','mueller','gmbh'] as $q) {
    $stmt = $pdo->prepare("SELECT id, company_name, display_name, first_name, last_name, email FROM dg_contacts WHERE login LIKE :q OR display_name LIKE :q OR company_name LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR email LIKE :q LIMIT 10");
    $stmt->execute(['q' => '%' . $q . '%']);
    echo "QUERY=$q\n";
    foreach ($stmt->fetchAll() as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
