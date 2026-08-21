<?php
declare(strict_types=1);

/** Abgabe-Historie ELSTER — aktiv nach Migration 053. */
final class ElsterSubmissionRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 20): array
    {
        if (!Database::isConfigured() || !self::tableExists()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_elster_submissions ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue('lim', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function tableExists(): bool
    {
        $pdo = Database::pdo();

        return $pdo->query("SHOW TABLES LIKE 'dg_elster_submissions'")->fetchColumn() !== false;
    }
}
