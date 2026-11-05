<?php
declare(strict_types=1);

/**
 * Persistenz Vertragsvorlagen / Bausteine (V0 Read + Seed).
 */
final class ContractTemplateRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $pdo = Database::pdo();
            $a = $pdo->query("SHOW TABLES LIKE 'dg_contract_templates'");
            $b = $pdo->query("SHOW TABLES LIKE 'dg_contract_template_blocks'");

            return $a !== false && $a->fetchColumn() && $b !== false && $b->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function allTemplates(): array
    {
        if (!self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_contract_templates ORDER BY title ASC, id ASC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_contract_templates WHERE code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public static function blocksForTemplate(int $templateId): array
    {
        if ($templateId < 1 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_contract_template_blocks
             WHERE template_id = :tid
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tid' => $templateId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Published-Vorlage inkl. Bausteine — oder null.
     *
     * @return array{template: array<string, mixed>, blocks: list<array<string, mixed>>}|null
     */
    public static function publishedWithBlocks(string $code): ?array
    {
        $tpl = self::findByCode($code);
        if ($tpl === null || ($tpl['status'] ?? '') !== 'published') {
            return null;
        }
        $blocks = self::blocksForTemplate((int) $tpl['id']);
        if ($blocks === []) {
            return null;
        }

        return ['template' => $tpl, 'blocks' => $blocks];
    }

    /**
     * Seed aus Katalog, wenn code noch fehlt (idempotent).
     *
     * @return array{created: int, skipped: int}
     */
    public static function ensureSeeded(?int $userId = null): array
    {
        if (!self::tableReady()) {
            return ['created' => 0, 'skipped' => 0];
        }
        $created = 0;
        $skipped = 0;
        $pdo = Database::pdo();
        foreach (ContractTemplateCatalog::templates() as $code => $meta) {
            if (self::findByCode($code) !== null) {
                ++$skipped;
                continue;
            }
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO dg_contract_templates
                        (code, title, status, version, notes, updated_by, published_at)
                     VALUES
                        (:code, :title, \'published\', \'1.0\', :notes, :uid, NOW())'
                );
                $ins->execute([
                    'code' => $code,
                    'title' => (string) $meta['title'],
                    'notes' => 'Seed V0 aus ContractTemplateCatalog',
                    'uid' => $userId !== null && $userId > 0 ? $userId : null,
                ]);
                $tid = (int) $pdo->lastInsertId();
                $blockIns = $pdo->prepare(
                    'INSERT INTO dg_contract_template_blocks
                        (template_id, sort_order, block_key, title, body_html, is_annex, enabled)
                     VALUES
                        (:tid, :sort, :bkey, :title, :body, :annex, :en)'
                );
                foreach (ContractTemplateCatalog::blocksForCode($code) as $block) {
                    $blockIns->execute([
                        'tid' => $tid,
                        'sort' => (int) ($block['sort_order'] ?? 0),
                        'bkey' => (string) $block['block_key'],
                        'title' => (string) $block['title'],
                        'body' => (string) $block['body_html'],
                        'annex' => (int) ($block['is_annex'] ?? 0),
                        'en' => (int) ($block['enabled'] ?? 1),
                    ]);
                }
                $pdo->commit();
                ++$created;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
