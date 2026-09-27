<?php
declare(strict_types=1);

/** Einmal-Token zum Selbstsetzen des Postfach-Passworts. */
final class MailboxPasswordTokenRepository
{
    public static function issue(int $contactId, int $mailboxId, int $ttlSeconds, ?int $createdBy): string
    {
        if ($contactId < 1 || $mailboxId < 1) {
            throw new InvalidArgumentException('Kontakt/Postfach fehlt.');
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_mailbox_password_tokens SET used_at = NOW()
             WHERE contact_id = :cid AND used_at IS NULL'
        )->execute(['cid' => $contactId]);

        $plain = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable('+' . max(3600, $ttlSeconds) . ' seconds'))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO dg_mailbox_password_tokens
             (contact_id, mailbox_id, token_hash, expires_at, created_by)
             VALUES (:cid, :mid, :hash, :exp, :by)'
        );
        $stmt->execute([
            'cid' => $contactId,
            'mid' => $mailboxId,
            'hash' => hash('sha256', $plain),
            'exp' => $expires,
            'by' => $createdBy !== null && $createdBy > 0 ? $createdBy : null,
        ]);

        return $plain;
    }

    /** @return array{id: int, contact_id: int, mailbox_id: int} */
    public static function consumeValid(string $plainToken): array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw new InvalidArgumentException('Link ungültig oder abgelaufen.');
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, contact_id, mailbox_id
                 FROM dg_mailbox_password_tokens
                 WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['hash' => hash('sha256', $plainToken)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new InvalidArgumentException('Link ungültig oder abgelaufen.');
            }
            $id = (int) ($row['id'] ?? 0);
            $pdo->prepare('UPDATE dg_mailbox_password_tokens SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => $id]);
            $pdo->commit();

            return [
                'id' => $id,
                'contact_id' => (int) ($row['contact_id'] ?? 0),
                'mailbox_id' => (int) ($row['mailbox_id'] ?? 0),
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function isValid(string $plainToken): bool
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || !Database::isConfigured()) {
            return false;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_mailbox_password_tokens
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $plainToken)]);

        return $stmt->fetchColumn() !== false;
    }
}
