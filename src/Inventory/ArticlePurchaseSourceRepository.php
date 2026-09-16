<?php
declare(strict_types=1);

/** Einkaufsquellen (Lieferant, EK, Shop-URL) je Artikel. */
final class ArticlePurchaseSourceRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forArticle(int $articleId): array
    {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_article_purchase_sources
             WHERE article_id = :aid
             ORDER BY is_preferred DESC, sort_order ASC, id ASC'
        );
        $stmt->execute(['aid' => $articleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalize'], $rows);
    }

    /**
     * @param list<int> $articleIds
     * @return array<int, list<array<string, mixed>>>
     */
    public static function forArticles(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === [] || !Database::isConfigured() || !self::tableReady()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_article_purchase_sources
             WHERE article_id IN ({$placeholders})
             ORDER BY article_id ASC, is_preferred DESC, sort_order ASC, id ASC"
        );
        $stmt->execute($articleIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $aid = (int) ($row['article_id'] ?? 0);
            $map[$aid][] = self::normalize($row);
        }

        return $map;
    }

    /** @return array<string, mixed>|null */
    public static function preferredForArticle(int $articleId): ?array
    {
        $sources = self::forArticle($articleId);
        if ($sources === []) {
            return null;
        }
        foreach ($sources as $source) {
            if (!empty($source['is_preferred'])) {
                return $source;
            }
        }

        return $sources[0];
    }

    public static function preferredOrderUrl(int $articleId): string
    {
        $preferred = self::preferredForArticle($articleId);
        if ($preferred === null) {
            return '';
        }

        return (string) ($preferred['order_url'] ?? '');
    }

    /**
     * Ersetzt alle Quellen eines Artikels aus POST-Daten.
     *
     * @param array<string, mixed> $input
     */
    public static function replaceFromInput(int $articleId, array $input): void
    {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return;
        }

        $raw = $input['purchase_sources'] ?? null;
        if (!is_array($raw)) {
            // Kein Feld im Formular → Quellen unverändert lassen
            return;
        }

        $rows = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = self::normalizeInputRow($entry);
            if ($normalized === null) {
                continue;
            }
            $rows[] = $normalized;
        }

        // Genau eine bevorzugte Quelle
        $preferredSeen = false;
        foreach ($rows as &$row) {
            if (!empty($row['is_preferred']) && !$preferredSeen) {
                $preferredSeen = true;
                $row['is_preferred'] = 1;
            } else {
                $row['is_preferred'] = 0;
            }
        }
        unset($row);
        if (!$preferredSeen && $rows !== []) {
            $rows[0]['is_preferred'] = 1;
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_article_purchase_sources WHERE article_id = :aid')
            ->execute(['aid' => $articleId]);

        if ($rows === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_article_purchase_sources
             (article_id, supplier_contact_id, supplier_name, purchase_price, currency, order_url, external_sku, is_preferred, sort_order, note)
             VALUES
             (:article_id, :supplier_contact_id, :supplier_name, :purchase_price, :currency, :order_url, :external_sku, :is_preferred, :sort_order, :note)'
        );
        foreach ($rows as $index => $row) {
            $stmt->execute([
                'article_id' => $articleId,
                'supplier_contact_id' => $row['supplier_contact_id'],
                'supplier_name' => $row['supplier_name'],
                'purchase_price' => $row['purchase_price'],
                'currency' => $row['currency'],
                'order_url' => $row['order_url'],
                'external_sku' => $row['external_sku'],
                'is_preferred' => $row['is_preferred'],
                'sort_order' => $index,
                'note' => $row['note'],
            ]);
        }
    }

    public static function deleteForArticle(int $articleId): void
    {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_article_purchase_sources WHERE article_id = :aid')
            ->execute(['aid' => $articleId]);
    }

    /**
     * Firmen-/Lieferanten-Kontakte für Einkaufsquellen.
     * Hinweis: CRM kennt keine eigene Rolle „lieferant“ (wird auf dg_kunde normalisiert).
     * Deshalb: Anrede Firma bzw. Kontakte mit Firmennamen.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function supplierContactOptions(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        try {
            $stmt = Database::pdo()->query(
                "SELECT id, company_name, display_name, first_name, last_name, supplier_number, salutation, contact_role
                 FROM dg_contacts
                 WHERE salutation = 'Firma'
                    OR TRIM(COALESCE(company_name, '')) <> ''
                 ORDER BY
                   CASE WHEN salutation = 'Firma' THEN 0 ELSE 1 END,
                   company_name ASC,
                   display_name ASC,
                   last_name ASC,
                   first_name ASC,
                   id ASC
                 LIMIT 500"
            );
        } catch (Throwable) {
            return [];
        }

        $options = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $company = trim((string) ($row['company_name'] ?? ''));
            $display = trim((string) ($row['display_name'] ?? ''));
            $person = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
            $label = $company !== '' ? $company : ($display !== '' ? $display : $person);
            if ($label === '') {
                $label = 'Kontakt #' . $id;
            }
            $snr = trim((string) ($row['supplier_number'] ?? ''));
            if ($snr !== '') {
                $label .= ' (Lief.-Nr. ' . $snr . ')';
            }
            $options[] = ['id' => $id, 'label' => $label];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>|null
     */
    private static function normalizeInputRow(array $entry): ?array
    {
        $supplierContactId = (int) ($entry['supplier_contact_id'] ?? 0);
        if ($supplierContactId < 1) {
            $supplierContactId = null;
        }
        $supplierName = mb_substr(trim((string) ($entry['supplier_name'] ?? '')), 0, 191);
        $orderUrl = self::sanitizeUrl((string) ($entry['order_url'] ?? ''));
        $externalSku = mb_substr(trim((string) ($entry['external_sku'] ?? '')), 0, 100);
        $note = mb_substr(trim((string) ($entry['note'] ?? '')), 0, 500);
        $price = round((float) str_replace(',', '.', (string) ($entry['purchase_price'] ?? '0')), 4);
        if ($price < 0) {
            $price = 0.0;
        }
        $currency = strtoupper(trim((string) ($entry['currency'] ?? 'EUR')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        // Leere Zeile überspringen
        if ($supplierContactId === null && $supplierName === '' && $orderUrl === '' && $externalSku === '' && $price < 0.00005) {
            return null;
        }

        return [
            'supplier_contact_id' => $supplierContactId,
            'supplier_name' => $supplierName,
            'purchase_price' => $price,
            'currency' => $currency,
            'order_url' => $orderUrl,
            'external_sku' => $externalSku,
            'is_preferred' => !empty($entry['is_preferred']) ? 1 : 0,
            'note' => $note,
        ];
    }

    private static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return mb_substr($url, 0, 500);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'article_id' => (int) ($row['article_id'] ?? 0),
            'supplier_contact_id' => isset($row['supplier_contact_id']) && $row['supplier_contact_id'] !== null
                ? (int) $row['supplier_contact_id'] : null,
            'supplier_name' => (string) ($row['supplier_name'] ?? ''),
            'purchase_price' => round((float) ($row['purchase_price'] ?? 0), 4),
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'order_url' => (string) ($row['order_url'] ?? ''),
            'external_sku' => (string) ($row['external_sku'] ?? ''),
            'is_preferred' => !empty($row['is_preferred']),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'note' => (string) ($row['note'] ?? ''),
            'label' => self::displayLabel($row),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function displayLabel(array $row): string
    {
        $name = trim((string) ($row['supplier_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $sku = trim((string) ($row['external_sku'] ?? ''));
        if ($sku !== '') {
            return 'SKU ' . $sku;
        }
        $url = trim((string) ($row['order_url'] ?? ''));
        if ($url !== '') {
            $host = parse_url($url, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $host : 'Shop-Link';
        }

        return 'Einkaufsquelle';
    }

    private static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $ready = Database::pdo()->query("SHOW TABLES LIKE 'dg_article_purchase_sources'")->fetchColumn() !== false;
        } catch (Throwable) {
            $ready = false;
        }

        return $ready;
    }
}
