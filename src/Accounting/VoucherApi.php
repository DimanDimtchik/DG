<?php
declare(strict_types=1);

final class VoucherApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !MenuRegistry::canAccess($user, 'buchhaltung-belege')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Nur GET erlaubt.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $action = trim((string) ($_GET['action'] ?? ''));

        try {
            if ($action === 'contacts') {
                self::handleContactSearch();
                return;
            }

            if ($action === 'account') {
                self::handleAccountLookup();
                return;
            }

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter action erforderlich (contacts, account).',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function handleContactSearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            echo json_encode([
                'success' => true,
                'data' => ['items' => []],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Database::isConfigured()) {
            echo json_encode([
                'success' => true,
                'data' => ['items' => []],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, display_name, company_name, email, supplier_number, customer_number
             FROM dg_contacts
             WHERE display_name LIKE :q OR company_name LIKE :q OR email LIKE :q
                OR supplier_number LIKE :q OR customer_number LIKE :q
             ORDER BY company_name ASC, display_name ASC
             LIMIT 15'
        );
        $stmt->execute(['q' => '%' . $query . '%']);

        $items = [];
        while ($row = $stmt->fetch()) {
            $label = trim((string) ($row['company_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['display_name'] ?? ''));
            }
            $meta = [];
            if ((string) ($row['email'] ?? '') !== '') {
                $meta[] = (string) $row['email'];
            }
            if ((string) ($row['supplier_number'] ?? '') !== '') {
                $meta[] = 'Lief.-Nr. ' . (string) $row['supplier_number'];
            }

            $items[] = [
                'id' => (int) $row['id'],
                'label' => $label,
                'meta' => implode(' · ', $meta),
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => ['items' => $items],
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function handleAccountLookup(): void
    {
        $number = preg_replace('/\D/', '', (string) ($_GET['number'] ?? '')) ?? '';
        if ($number === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter number erforderlich.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $skrType = ChartOfAccountsSettings::activeSkrType();
        ChartAccountRepository::ensureSeeded($skrType);
        $account = ChartAccountRepository::findByNumber($number, $skrType);
        if ($account === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Konto nicht gefunden.',
                'valid' => false,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'success' => true,
            'valid' => true,
            'data' => [
                'account_number' => (string) ($account['account_number'] ?? ''),
                'name' => (string) ($account['name'] ?? ''),
                'section_label' => (string) ($account['section_label'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
