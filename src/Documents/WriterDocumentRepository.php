<?php

declare(strict_types=1);

/**
 * Dokumentenverfasser D1 — Persistenz für dg_writer_documents + Versionshistorie.
 *
 * Entwurf: frei speicherbar.
 * Final: kein stilles Überschreiben — nur finalize / reopenAsDraft / archive.
 */
final class WriterDocumentRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT d.id, d.status, d.version, d.title, d.subject, d.contact_id, d.voucher_id,
                       d.created_by, d.updated_by, d.finalized_at, d.created_at, d.updated_at,
                       u.username AS updated_by_username, u.display_name AS updated_by_display_name
                FROM dg_writer_documents d
                LEFT JOIN dg_users u ON u.id = d.updated_by';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE d.status = :status';
            $params['status'] = WriterDocument::normalizeStatus($status);
        }
        $sql .= ' ORDER BY d.updated_at DESC, d.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null Rohzeile
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_writer_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null Normalisierte Dokumentstruktur
     */
    public static function findDocument(int $id): ?array
    {
        $row = self::find($id);

        return $row !== null ? WriterDocument::fromRow($row) : null;
    }

    /**
     * Neues Dokument oder Entwurf speichern.
     * Final/Archiv: Exception — zuerst reopenAsDraft oder neues Dokument.
     *
     * @param array<string, mixed> $input
     */
    public static function saveDraft(array $input, ?int $id, ?int $userId): int
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $data = WriterDocument::sanitize($input);
        $pdo = Database::pdo();

        if ($id !== null && $id > 0) {
            $existing = self::find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Dokument nicht gefunden.');
            }
            $status = WriterDocument::normalizeStatus((string) ($existing['status'] ?? ''));
            if ($status !== WriterDocument::STATUS_DRAFT) {
                throw new InvalidArgumentException(
                    'Finalisierte oder archivierte Dokumente können nicht still überschrieben werden. Bitte erneut als Entwurf öffnen.'
                );
            }

            $stmt = $pdo->prepare(
                'UPDATE dg_writer_documents
                 SET title = :title,
                     subject = :subject,
                     greeting = :greeting,
                     closing = :closing,
                     signature = :signature,
                     recipient_json = :recipient_json,
                     info_json = :info_json,
                     body_json = :body_json,
                     body_html_cache = :body_html_cache,
                     contact_id = :contact_id,
                     voucher_id = :voucher_id,
                     updated_by = :updated_by
                 WHERE id = :id AND status = :draft_status'
            );
            $stmt->execute(self::bindPayload($data, [
                'updated_by' => $userId,
                'id' => $id,
                'draft_status' => WriterDocument::STATUS_DRAFT,
            ]));
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Dokument konnte nicht gespeichert werden (Status geändert?).');
            }

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_writer_documents
                (status, version, title, subject, greeting, closing, signature,
                 recipient_json, info_json, body_json, body_html_cache,
                 contact_id, voucher_id, created_by, updated_by)
             VALUES
                (:status, 1, :title, :subject, :greeting, :closing, :signature,
                 :recipient_json, :info_json, :body_json, :body_html_cache,
                 :contact_id, :voucher_id, :created_by, :updated_by)'
        );
        $stmt->execute(self::bindPayload($data, [
            'status' => WriterDocument::STATUS_DRAFT,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]));

        return (int) $pdo->lastInsertId();
    }

    /**
     * Entwurf → Final: Snapshot der aktuellen Version, Status final, Audit.
     */
    public static function finalize(int $id, ?int $userId, string $note = ''): void
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('Ungültige Dokument-ID.');
        }

        $pdo = Database::pdo();
        $version = 1;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM dg_writer_documents WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Dokument nicht gefunden.');
            }
            $status = WriterDocument::normalizeStatus((string) ($row['status'] ?? ''));
            if ($status !== WriterDocument::STATUS_DRAFT) {
                throw new InvalidArgumentException('Nur Entwürfe können finalisiert werden.');
            }

            $doc = WriterDocument::fromRow($row);
            $version = max(1, (int) $doc['version']);
            self::insertVersionSnapshot($pdo, $id, $version, WriterDocument::STATUS_FINAL, $doc, $userId, $note !== '' ? $note : 'Finalisiert');

            $upd = $pdo->prepare(
                'UPDATE dg_writer_documents
                 SET status = :status,
                     finalized_by = :finalized_by,
                     finalized_at = NOW(),
                     updated_by = :updated_by
                 WHERE id = :id AND status = :draft'
            );
            $upd->execute([
                'status' => WriterDocument::STATUS_FINAL,
                'finalized_by' => $userId,
                'updated_by' => $userId,
                'id' => $id,
                'draft' => WriterDocument::STATUS_DRAFT,
            ]);
            if ($upd->rowCount() === 0) {
                throw new InvalidArgumentException('Finalisierung fehlgeschlagen (Status geändert?).');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuditLog::record('writer_document_finalize', $userId, json_encode([
            'document_id' => $id,
            'version' => $version,
            'note' => self::clipNote($note),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Final → neuer Entwurf: Snapshot behalten, Version +1, Status draft.
     */
    public static function reopenAsDraft(int $id, ?int $userId, string $note = ''): void
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('Ungültige Dokument-ID.');
        }

        $pdo = Database::pdo();
        $oldVersion = 1;
        $newVersion = 2;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM dg_writer_documents WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Dokument nicht gefunden.');
            }
            $status = WriterDocument::normalizeStatus((string) ($row['status'] ?? ''));
            if ($status !== WriterDocument::STATUS_FINAL && $status !== WriterDocument::STATUS_ARCHIVED) {
                throw new InvalidArgumentException('Nur finale oder archivierte Dokumente können erneut als Entwurf geöffnet werden.');
            }

            $doc = WriterDocument::fromRow($row);
            $oldVersion = max(1, (int) $doc['version']);
            // Sicherstellen, dass die finale Fassung in der Historie steht
            if (!self::versionExists($pdo, $id, $oldVersion)) {
                self::insertVersionSnapshot(
                    $pdo,
                    $id,
                    $oldVersion,
                    $status,
                    $doc,
                    $userId,
                    $note !== '' ? $note : 'Stand vor erneutem Entwurf'
                );
            }

            $newVersion = $oldVersion + 1;
            $upd = $pdo->prepare(
                'UPDATE dg_writer_documents
                 SET status = :status,
                     version = :version,
                     finalized_by = NULL,
                     finalized_at = NULL,
                     updated_by = :updated_by
                 WHERE id = :id'
            );
            $upd->execute([
                'status' => WriterDocument::STATUS_DRAFT,
                'version' => $newVersion,
                'updated_by' => $userId,
                'id' => $id,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuditLog::record('writer_document_reopen', $userId, json_encode([
            'document_id' => $id,
            'from_version' => $oldVersion,
            'to_version' => $newVersion,
            'note' => self::clipNote($note),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Archivieren (Final oder Entwurf). Schreibt Snapshot bei Bedarf.
     */
    public static function archive(int $id, ?int $userId, string $note = ''): void
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('Ungültige Dokument-ID.');
        }

        $pdo = Database::pdo();
        $version = 1;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM dg_writer_documents WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('Dokument nicht gefunden.');
            }
            $status = WriterDocument::normalizeStatus((string) ($row['status'] ?? ''));
            if ($status === WriterDocument::STATUS_ARCHIVED) {
                $pdo->commit();

                return;
            }

            $doc = WriterDocument::fromRow($row);
            $version = max(1, (int) $doc['version']);
            if (!self::versionExists($pdo, $id, $version)) {
                self::insertVersionSnapshot(
                    $pdo,
                    $id,
                    $version,
                    WriterDocument::STATUS_ARCHIVED,
                    $doc,
                    $userId,
                    $note !== '' ? $note : 'Archiviert'
                );
            }

            $upd = $pdo->prepare(
                'UPDATE dg_writer_documents
                 SET status = :status, updated_by = :updated_by
                 WHERE id = :id'
            );
            $upd->execute([
                'status' => WriterDocument::STATUS_ARCHIVED,
                'updated_by' => $userId,
                'id' => $id,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuditLog::record('writer_document_archive', $userId, json_encode([
            'document_id' => $id,
            'version' => $version,
            'note' => self::clipNote($note),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listVersions(int $documentId): array
    {
        self::ensureReady();
        if ($documentId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT v.id, v.document_id, v.version, v.status, v.note, v.created_by, v.created_at,
                    u.username AS created_by_username
             FROM dg_writer_document_versions v
             LEFT JOIN dg_users u ON u.id = v.created_by
             WHERE v.document_id = :id
             ORDER BY v.version DESC, v.id DESC'
        );
        $stmt->execute(['id' => $documentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null Snapshot-Payload
     */
    public static function findVersionSnapshot(int $documentId, int $version): ?array
    {
        self::ensureReady();
        if ($documentId <= 0 || $version <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT snapshot_json FROM dg_writer_document_versions
             WHERE document_id = :id AND version = :ver LIMIT 1'
        );
        $stmt->execute(['id' => $documentId, 'ver' => $version]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $decoded = json_decode((string) $row['snapshot_json'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data sanitize()-Ergebnis
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function bindPayload(array $data, array $extra = []): array
    {
        $recipientJson = json_encode($data['recipient_lines'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $infoJson = json_encode($data['info'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $bodyJson = json_encode($data['body'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return array_merge([
            'title' => $data['title'],
            'subject' => $data['subject'],
            'greeting' => $data['greeting'],
            'closing' => $data['closing'],
            'signature' => $data['signature'],
            'recipient_json' => $recipientJson,
            'info_json' => $infoJson,
            'body_json' => $bodyJson,
            'body_html_cache' => $data['body_html_cache'] !== '' ? $data['body_html_cache'] : null,
            'contact_id' => $data['contact_id'] > 0 ? $data['contact_id'] : null,
            'voucher_id' => $data['voucher_id'] > 0 ? $data['voucher_id'] : null,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $doc
     */
    private static function insertVersionSnapshot(
        PDO $pdo,
        int $documentId,
        int $version,
        string $status,
        array $doc,
        ?int $userId,
        string $note
    ): void {
        $payload = WriterDocument::snapshotPayload(array_merge($doc, [
            'status' => $status,
            'version' => $version,
        ]));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $pdo->prepare(
            'INSERT INTO dg_writer_document_versions
                (document_id, version, status, snapshot_json, created_by, note)
             VALUES
                (:document_id, :version, :status, :snapshot_json, :created_by, :note)'
        );
        $stmt->execute([
            'document_id' => $documentId,
            'version' => $version,
            'status' => WriterDocument::normalizeStatus($status),
            'snapshot_json' => $json,
            'created_by' => $userId,
            'note' => self::clipNote($note),
        ]);
    }

    private static function versionExists(PDO $pdo, int $documentId, int $version): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM dg_writer_document_versions
             WHERE document_id = :id AND version = :ver LIMIT 1'
        );
        $stmt->execute(['id' => $documentId, 'ver' => $version]);

        return (bool) $stmt->fetchColumn();
    }

    private static function clipNote(string $note): string
    {
        $note = trim($note);
        if (mb_strlen($note) <= 255) {
            return $note;
        }

        return mb_substr($note, 0, 255);
    }
}
