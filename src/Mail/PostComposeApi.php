<?php
declare(strict_types=1);

/** JSON: Kontakt- und Belegsuche für Post-Compose. */
final class PostComposeApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if ($user === null || !MenuRegistry::canAccess($user, 'post')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'GET'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $action = trim((string) ($_GET['action'] ?? ''));
        try {
            if ($action === 'contacts') {
                self::contacts($user);
                return;
            }
            if ($action === 'contact') {
                self::contactOne($user);
                return;
            }
            if ($action === 'vouchers') {
                self::vouchers($user);
                return;
            }
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'action=contacts|contact|vouchers'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function contacts(User $user): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $items = [];
        foreach (ContactRepository::pickerItems($q, 15, $user) as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'meta' => (string) ($row['meta'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    private static function contactOne(User $user): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $contact = $id > 0 ? ContactRepository::findById($id) : null;
        if ($contact === null || !ContactAccessResolver::canViewContact($user, $contact)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Kontakt nicht gefunden'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $email = trim($contact->email);
        if ($email === '') {
            $email = trim($contact->email2);
        }
        echo json_encode([
            'ok' => true,
            'id' => $contact->id,
            'label' => $contact->listLabel(),
            'email' => $email,
            'salutation' => $contact->salutation,
            'first_name' => $contact->firstName,
            'last_name' => $contact->lastName,
            'company_name' => $contact->companyName,
            'greetings' => PostComposePhrases::greetings($contact),
            'default_greeting' => PostComposePhrases::defaultGreetingId($contact),
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function vouchers(User $user): void
    {
        if (!MenuRegistry::canAccess($user, 'buchhaltung-belege') && !MenuRegistry::canAccess($user, 'buchhaltung-beleg-form')) {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '' || !Database::isConfigured()) {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        MigrationRunner::runPending();
        $like = '%' . $q . '%';
        $sql = "SELECT v.id, v.document_kind, v.invoice_number, v.supplier_name, v.voucher_date, v.contact_id,
                       c.display_name, c.company_name
                FROM dg_vouchers v
                LEFT JOIN dg_contacts c ON c.id = v.contact_id
                WHERE v.is_draft = 0
                  AND v.document_kind IN ('offer','work_order','order_confirmation','delivery_note','partial_invoice','invoice','final_invoice')
                  AND (
                    v.invoice_number LIKE ? OR v.supplier_name LIKE ? OR v.description LIKE ?
                    OR c.display_name LIKE ? OR c.company_name LIKE ?
                  )
                ORDER BY v.voucher_date DESC, v.id DESC
                LIMIT 15";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$like, $like, $like, $like, $like]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $kind = VoucherDocumentKind::label((string) ($row['document_kind'] ?? ''));
            $num = trim((string) ($row['invoice_number'] ?? ''));
            $who = trim((string) ($row['company_name'] ?? ''));
            if ($who === '') {
                $who = trim((string) ($row['display_name'] ?? $row['supplier_name'] ?? ''));
            }
            $label = trim($kind . ($num !== '' ? ' ' . $num : '') . ($who !== '' ? ' — ' . $who : ''));
            $items[] = [
                'id' => (int) $row['id'],
                'label' => $label !== '' ? $label : ('Beleg #' . (int) $row['id']),
                'meta' => (string) ($row['voucher_date'] ?? ''),
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }
}
