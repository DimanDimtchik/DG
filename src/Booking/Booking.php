<?php
declare(strict_types=1);

final class Booking
{
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
    ) {
    }

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

    public function slotFormatted(): string
    {
        $ts = strtotime($this->slotDatetime);

        return $ts ? date('d.m.Y H:i', $ts) : $this->slotDatetime;
    }
}
