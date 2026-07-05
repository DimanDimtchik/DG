<?php
declare(strict_types=1);

final class MediaId
{
    public const PATTERN = '/\d{14}_[a-f0-9]{6}/';

    public static function generate(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(3));
    }

    public static function isValid(string $id): bool
    {
        return (bool) preg_match('/^\d{14}_[a-f0-9]{6}$/', $id);
    }
}
