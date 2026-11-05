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

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_contract_templates WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array{draft: string, published: string} */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Entwurf',
            'published' => 'Freigegeben',
        ];
    }

    /**
     * Speichert Metadaten + Bausteine als Entwurf (V1). Freigabe = V2.
     *
     * @param array<string, mixed> $post
     * @return array{ok: bool, message: string, id: int}
     */
    public static function saveDraft(int $templateId, array $post, int $userId): array
    {
        if (!self::tableReady()) {
            return ['ok' => false, 'message' => 'Vertragsvorlagen-Tabellen fehlen — Migration 140 ausführen.', 'id' => 0];
        }
        $tpl = self::findById($templateId);
        if ($tpl === null) {
            return ['ok' => false, 'message' => 'Vorlage nicht gefunden.', 'id' => 0];
        }
        $title = mb_substr(trim((string) ($post['title'] ?? '')), 0, 255);
        if ($title === '') {
            return ['ok' => false, 'message' => 'Bitte einen Titel angeben.', 'id' => $templateId];
        }
        $notes = mb_substr(trim((string) ($post['notes'] ?? '')), 0, 5000);
        $rawBlocks = $post['blocks'] ?? [];
        if (!is_array($rawBlocks) || $rawBlocks === []) {
            return ['ok' => false, 'message' => 'Keine Bausteine übermittelt.', 'id' => $templateId];
        }

        $existing = [];
        foreach (self::blocksForTemplate($templateId) as $row) {
            $existing[(int) ($row['id'] ?? 0)] = $row;
        }

        $updates = [];
        $sort = 0;
        foreach ($rawBlocks as $row) {
            if (!is_array($row)) {
                continue;
            }
            $bid = (int) ($row['id'] ?? 0);
            if ($bid < 1 || !isset($existing[$bid])) {
                continue;
            }
            $body = (string) ($row['body_html'] ?? '');
            if (class_exists('MailHtmlSanitizer')) {
                $body = MailHtmlSanitizer::clean($body);
            } else {
                $body = strip_tags(
                    $body,
                    '<p><br><strong><b><em><i><u><ul><ol><li><span><a>'
                );
            }
            $blockTitle = mb_substr(trim((string) ($row['title'] ?? '')), 0, 255);
            if ($blockTitle === '') {
                $blockTitle = (string) ($existing[$bid]['title'] ?? 'Abschnitt');
            }
            $updates[] = [
                'id' => $bid,
                'title' => $blockTitle,
                'body_html' => $body,
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'sort_order' => $sort++,
            ];
        }
        if ($updates === []) {
            return ['ok' => false, 'message' => 'Keine gültigen Bausteine zum Speichern.', 'id' => $templateId];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE dg_contract_templates
                 SET title = :title, notes = :notes, status = \'draft\', updated_by = :uid, updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'title' => $title,
                'notes' => $notes !== '' ? $notes : null,
                'uid' => $userId > 0 ? $userId : null,
                'id' => $templateId,
            ]);
            $stmt = $pdo->prepare(
                'UPDATE dg_contract_template_blocks
                 SET title = :title, body_html = :body, enabled = :en, sort_order = :sort, updated_at = NOW()
                 WHERE id = :id AND template_id = :tid'
            );
            foreach ($updates as $u) {
                $stmt->execute([
                    'title' => $u['title'],
                    'body' => $u['body_html'],
                    'en' => $u['enabled'],
                    'sort' => $u['sort_order'],
                    'id' => $u['id'],
                    'tid' => $templateId,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ok' => true,
            'message' => 'Entwurf gespeichert. Zum Nutzen in neuen PDFs bitte freigeben.',
            'id' => $templateId,
        ];
    }

    /**
     * Bausteine für Vorschau: POST-Overlay auf DB-Stand (ohne Speichern).
     *
     * @param array<string, mixed> $post
     * @return list<array<string, mixed>>
     */
    public static function blocksOverlayFromPost(int $templateId, array $post): array
    {
        $existing = self::blocksForTemplate($templateId);
        if ($existing === []) {
            return [];
        }
        $byId = [];
        foreach ($existing as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }
        $rawBlocks = $post['blocks'] ?? [];
        if (!is_array($rawBlocks) || $rawBlocks === []) {
            return $existing;
        }
        $out = [];
        $sort = 0;
        foreach ($rawBlocks as $row) {
            if (!is_array($row)) {
                continue;
            }
            $bid = (int) ($row['id'] ?? 0);
            if ($bid < 1 || !isset($byId[$bid])) {
                continue;
            }
            $base = $byId[$bid];
            $body = (string) ($row['body_html'] ?? $base['body_html'] ?? '');
            if (class_exists('MailHtmlSanitizer')) {
                $body = MailHtmlSanitizer::clean($body);
            } else {
                $body = strip_tags($body, '<p><br><strong><b><em><i><u><ul><ol><li><span><a>');
            }
            $blockTitle = mb_substr(trim((string) ($row['title'] ?? '')), 0, 255);
            if ($blockTitle === '') {
                $blockTitle = (string) ($base['title'] ?? 'Abschnitt');
            }
            $out[] = [
                'id' => $bid,
                'block_key' => (string) ($base['block_key'] ?? ''),
                'title' => $blockTitle,
                'body_html' => $body,
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'is_annex' => (int) ($base['is_annex'] ?? 0),
                'sort_order' => $sort++,
            ];
        }

        return $out !== [] ? $out : $existing;
    }

    /**
     * Freigabe: Version erhöhen, Status published, Audit (V2).
     *
     * @return array{ok: bool, message: string, id: int, version: string}
     */
    public static function publish(int $templateId, int $userId): array
    {
        if (!self::tableReady()) {
            return ['ok' => false, 'message' => 'Vertragsvorlagen-Tabellen fehlen — Migration 140 ausführen.', 'id' => 0, 'version' => ''];
        }
        $tpl = self::findById($templateId);
        if ($tpl === null) {
            return ['ok' => false, 'message' => 'Vorlage nicht gefunden.', 'id' => 0, 'version' => ''];
        }

        $blocks = self::blocksForTemplate($templateId);
        $enabledWithText = 0;
        $snapshot = [];
        foreach ($blocks as $b) {
            $en = (int) ($b['enabled'] ?? 0) === 1;
            $body = trim(strip_tags((string) ($b['body_html'] ?? '')));
            if ($en && $body !== '') {
                ++$enabledWithText;
            }
            $snapshot[] = [
                'block_key' => (string) ($b['block_key'] ?? ''),
                'title' => (string) ($b['title'] ?? ''),
                'enabled' => $en ? 1 : 0,
                'is_annex' => (int) ($b['is_annex'] ?? 0),
                'sort_order' => (int) ($b['sort_order'] ?? 0),
                'body_sha256' => hash('sha256', (string) ($b['body_html'] ?? '')),
            ];
        }
        if ($enabledWithText < 1) {
            return [
                'ok' => false,
                'message' => 'Freigabe nicht möglich: mindestens ein aktiver Baustein mit Text nötig.',
                'id' => $templateId,
                'version' => (string) ($tpl['version'] ?? ''),
            ];
        }

        $oldVersion = trim((string) ($tpl['version'] ?? '1.0'));
        if ($oldVersion === '') {
            $oldVersion = '1.0';
        }
        $newVersion = self::bumpVersion($oldVersion);
        $wasPublished = (($tpl['status'] ?? '') === 'published');

        // Erneute Freigabe ohne Entwurf: trotzdem neue Version (kein stilles Überschreiben).
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_contract_templates
             SET status = \'published\', version = :ver, updated_by = :uid,
                 published_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'ver' => $newVersion,
            'uid' => $userId > 0 ? $userId : null,
            'id' => $templateId,
        ]);

        if (class_exists('AuditLog')) {
            AuditLog::record('contract_template_publish', $userId > 0 ? $userId : null, json_encode([
                'template_id' => $templateId,
                'code' => (string) ($tpl['code'] ?? ''),
                'title' => (string) ($tpl['title'] ?? ''),
                'from_status' => (string) ($tpl['status'] ?? ''),
                'was_published' => $wasPublished,
                'version_from' => $oldVersion,
                'version_to' => $newVersion,
                'enabled_blocks' => $enabledWithText,
                'blocks' => $snapshot,
            ], JSON_UNESCAPED_UNICODE));
        }

        return [
            'ok' => true,
            'message' => 'Vorlage freigegeben als Version ' . $newVersion . '.',
            'id' => $templateId,
            'version' => $newVersion,
        ];
    }

    public static function bumpVersion(string $current): string
    {
        $current = trim($current);
        if (preg_match('/^(\d+)\.(\d+)$/', $current, $m)) {
            return $m[1] . '.' . ((int) $m[2] + 1);
        }
        if (preg_match('/^(\d+)$/', $current, $m)) {
            return ((int) $m[1] + 1) . '.0';
        }

        return '1.1';
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
