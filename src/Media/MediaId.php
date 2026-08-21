<?php
declare(strict_types=1);

/**
 * Media library primary keys: `{YmdHis}_{6 hex chars}` (e.g. 20260820133325_2ede84).
 */
final class MediaId
{
    /** Regex fragment matching a media id (without anchors). */
    public const PATTERN = '/\d{14}_[a-f0-9]{6}/';

    /**
     * Create a new unique media id based on the current time.
     */
    public static function generate(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(3));
    }

    /**
     * Whether $id matches the media id format.
     */
    public static function isValid(string $id): bool
    {
        return (bool) preg_match('/^\d{14}_[a-f0-9]{6}$/', $id);
    }
}
