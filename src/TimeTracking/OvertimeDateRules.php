<?php
declare(strict_types=1);

/** Datumsregeln für Überstunden-Verfall und Erinnerung. */
final class OvertimeDateRules
{
    /**
     * Addiert Kalendermonate zu einem Datum (YYYY-MM-DD).
     * Beispiel: 2026-01-31 + 1 Monat → 2026-02-28
     */
    public static function addMonths(string $date, int $months): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        try {
            $dt = new DateTimeImmutable($date);

            return $dt->modify('+' . $months . ' months')->format('Y-m-d');
        } catch (Throwable) {
            return $date;
        }
    }

    /**
     * Prüft, ob heute im Erinnerungsfenster liegt (ab reminder_due_at, vor expires_at).
     */
    public static function isReminderDue(string $today, string $reminderDueAt, string $expiresAt): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reminderDueAt)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            return false;
        }

        return $today >= $reminderDueAt && $today <= $expiresAt;
    }
}
