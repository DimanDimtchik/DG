<?php
declare(strict_types=1);

/**
 * Kalender-Buchung (Entity).
 */
final class Booking
{
    /**
     * Konstruktor.
     * @param int $id Datensatz-ID
     * @param int $articleId Artikel-ID
     * @param int $employeeId Mitarbeiter-/Benutzer-ID
     * @param string $slotDatetime
     * @param string $customerName
     * @param string $customerEmail
     * @param string $customerPhone
     * @param string $status
     * @param string $adminNotes
     * @param string $createdAt
     */
    public function __construct(
        public readonly int $id,
        public readonly int $articleId,
        public readonly int $employeeId,
        public readonly string $slotDatetime,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $customerPhone,
        public readonly string $status,
        public readonly string $adminNotes,
        public readonly string $createdAt,
        public readonly string $bookingCode = '',
    ) {
    }

    /** Public reference for e-mails / forms (falls back to numeric id display). */
    public function publicCode(): string
    {
        $code = trim($this->bookingCode);

        return $code !== '' ? $code : ('#' . $this->id);
    }

    /**
     * Methode status label.
     * @return string
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'gebucht' => 'Gebucht',
            'bestätigt' => 'Bestätigt',
            'storniert' => 'Storniert',
            'abgeschlossen' => 'Abgeschlossen',
            default => ucfirst($this->status),
        };
    }

    /**
     * Methode slot formatted.
     * @return string
     */
    public function slotFormatted(): string
    {
        $ts = strtotime($this->slotDatetime);

        return $ts ? date('d.m.Y H:i', $ts) : $this->slotDatetime;
    }
}
