<?php
declare(strict_types=1);

/** JSON: Kontakt- und Belegsuche für Reklamation. */
final class ComplaintApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if ($user === null || !MenuRegistry::canAccess($user, 'reklamation-form')) {
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
            if ($action === 'vouchers') {
                self::vouchers($user);
                return;
            }
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'action=contacts|vouchers'], JSON_UNESCAPED_UNICODE);
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
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    private static function vouchers(User $user): void
    {
        if (!Database::isConfigured()) {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $contactId = (int) ($_GET['contact_id'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($contactId < 1 && $q === '') {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($contactId > 0) {
            $contact = ContactRepository::findById($contactId);
            if ($contact === null || !ContactAccessResolver::canViewContact($user, $contact)) {
                echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        MigrationRunner::runPending();
        // document_kind wie VoucherDocumentKind (kein „credit“ — Kundengutschrift ist Belegart, nicht Dokumentart)
        $docKinds = "'offer','work_order','order_confirmation','delivery_note','partial_invoice','invoice','final_invoice'";
        $params = [];
        $where = [
            'v.is_draft = 0',
            "v.document_kind IN ($docKinds)",
        ];
        if ($contactId > 0) {
            $where[] = 'v.contact_id = ?';
            $params[] = $contactId;
        }
        if ($q === '') {
            $where[] = 'v.voucher_date >= ?';
            $params[] = date('Y-m-d', strtotime('-24 months'));
            $limit = 80;
        } else {
            $like = '%' . $q . '%';
            $where[] = '(
                v.invoice_number LIKE ? OR v.supplier_name LIKE ? OR v.description LIKE ?
                OR c.display_name LIKE ? OR c.company_name LIKE ?
            )';
            array_push($params, $like, $like, $like, $like, $like);
            $limit = 25;
        }

        $sql = 'SELECT v.id, v.document_kind, v.invoice_number, v.supplier_name, v.voucher_date, v.contact_id,
                       c.display_name, c.company_name
                FROM dg_vouchers v
                LEFT JOIN dg_contacts c ON c.id = v.contact_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY v.voucher_date DESC, v.id DESC
                LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rawKind = (string) ($row['document_kind'] ?? '');
            $kind = VoucherDocumentKind::label($rawKind);
            if ($kind === '') {
                $kind = $rawKind !== '' ? $rawKind : 'Beleg';
            }
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
                'kind' => $rawKind,
                'contact_id' => (int) ($row['contact_id'] ?? 0),
                'contact_label' => $who,
            ];
        }
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }
}
