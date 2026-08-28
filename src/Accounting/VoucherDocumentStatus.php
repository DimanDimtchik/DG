<?php
declare(strict_types=1);

/** Workflow-Status für Ausgangsbelege in der Verkaufskette. */
final class VoucherDocumentStatus
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const ACCEPTED = 'accepted';
    public const BILLED = 'billed';
    public const CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DRAFT => 'Entwurf',
            self::SENT => 'Versendet',
            self::ACCEPTED => 'Angenommen',
            self::BILLED => 'Abgerechnet',
            self::CANCELLED => 'Storniert',
        ];
    }

    public static function sanitize(string $status): string
    {
        $status = strtolower(trim($status));

        return isset(self::options()[$status]) ? $status : '';
    }

    public static function label(string $status): string
    {
        $status = self::sanitize($status);

        return $status !== '' ? (self::options()[$status] ?? $status) : '';
    }

    public static function badgeClass(string $status): string
    {
        return match (self::sanitize($status)) {
            self::SENT => 'dg-badge--pending',
            self::ACCEPTED, self::BILLED => 'dg-badge--ok',
            self::CANCELLED => 'dg-badge--error',
            self::DRAFT => 'dg-badge--muted',
            default => 'dg-badge--muted',
        };
    }

    public static function defaultForKind(string $documentKind): string
    {
        $kind = VoucherDocumentKind::sanitize($documentKind);
        if ($kind === '') {
            return '';
        }

        return self::DRAFT;
    }

    /**
     * @return list<string>
     */
    public static function allowedForKind(string $documentKind): array
    {
        $kind = VoucherDocumentKind::sanitize($documentKind);

        return match ($kind) {
            VoucherDocumentKind::OFFER,
            VoucherDocumentKind::ORDER_CONFIRMATION => [
                self::DRAFT,
                self::SENT,
                self::ACCEPTED,
                self::CANCELLED,
            ],
            VoucherDocumentKind::DELIVERY_NOTE => [
                self::DRAFT,
                self::SENT,
                self::BILLED,
                self::CANCELLED,
            ],
            VoucherDocumentKind::PARTIAL_INVOICE,
            VoucherDocumentKind::INVOICE,
            VoucherDocumentKind::FINAL_INVOICE => [
                self::DRAFT,
                self::SENT,
                self::BILLED,
                self::CANCELLED,
            ],
            default => [],
        };
    }

    /**
     * Schnellaktionen — typische nächste Schritte.
     *
     * @return list<string>
     */
    public static function nextStatuses(string $current, string $documentKind): array
    {
        $current = self::sanitize($current);
        $kind = VoucherDocumentKind::sanitize($documentKind);
        $allowed = self::allowedForKind($kind);
        if ($allowed === []) {
            return [];
        }

        $next = match ($kind) {
            VoucherDocumentKind::OFFER,
            VoucherDocumentKind::ORDER_CONFIRMATION => match ($current) {
                '' => [self::SENT],
                self::DRAFT => [self::SENT],
                self::SENT => [self::ACCEPTED, self::CANCELLED],
                self::ACCEPTED => [self::BILLED, self::CANCELLED],
                default => [],
            },
            VoucherDocumentKind::DELIVERY_NOTE => match ($current) {
                '' => [self::SENT],
                self::DRAFT => [self::SENT],
                self::SENT => [self::BILLED, self::CANCELLED],
                default => [],
            },
            VoucherDocumentKind::PARTIAL_INVOICE,
            VoucherDocumentKind::INVOICE,
            VoucherDocumentKind::FINAL_INVOICE => match ($current) {
                '' => [self::SENT],
                self::DRAFT => [self::SENT],
                self::SENT => [self::BILLED, self::CANCELLED],
                default => [],
            },
            default => [],
        };

        $filtered = [];
        foreach ($next as $status) {
            if (in_array($status, $allowed, true) && $status !== $current) {
                $filtered[] = $status;
            }
        }

        return $filtered;
    }

    public static function actionLabel(string $status): string
    {
        return match (self::sanitize($status)) {
            self::SENT => 'Als versendet markieren',
            self::ACCEPTED => 'Als angenommen markieren',
            self::BILLED => 'Als abgerechnet markieren',
            self::CANCELLED => 'Stornieren',
            self::DRAFT => 'Als Entwurf markieren',
            default => self::label($status),
        };
    }

    public static function isValidForKind(string $status, string $documentKind): bool
    {
        $status = self::sanitize($status);
        if ($status === '') {
            return true;
        }

        return in_array($status, self::allowedForKind($documentKind), true);
    }
}
