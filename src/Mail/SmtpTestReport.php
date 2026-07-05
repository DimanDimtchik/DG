<?php
declare(strict_types=1);

final class SmtpTestReport
{
    private const SESSION_KEY = 'dg_smtp_test_report';

    /** @param array<string, mixed> $report */
    public static function store(array $report): void
    {
        $_SESSION[self::SESSION_KEY] = $report;
    }

    /** @return array<string, mixed>|null */
    public static function pull(): ?array
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return null;
        }
        $report = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        return is_array($report) ? $report : null;
    }
}
