<?php
declare(strict_types=1);

/**
 * Antwort auf Angebots-Mail → Auftragsbestätigung + Annahme-Vermerk.
 */
final class OfferAcceptanceMailService
{
    /**
     * Nach Inbound: wenn Antwort auf Angebots-Mail, AB anlegen (einmalig).
     *
     * @return array{ok: bool, created_voucher_id?: int, reason?: string}
     */
    public static function processInboundMailLog(int $inboundLogId): array
    {
        if (!Database::isConfigured() || $inboundLogId < 1) {
            return ['ok' => false, 'reason' => 'unavailable'];
        }

        $inbound = MailLogRepository::findById($inboundLogId);
        if ($inbound === null || ($inbound['direction'] ?? '') !== 'in') {
            return ['ok' => false, 'reason' => 'not_inbound'];
        }

        $inReplyTo = MailLogRepository::normalizeMessageId((string) ($inbound['in_reply_to'] ?? ''));
        if ($inReplyTo === '') {
            $inReplyTo = self::extractInReplyToFromArchive((string) ($inbound['storage_path'] ?? ''));
        }
        if ($inReplyTo === '') {
            return ['ok' => false, 'reason' => 'no_in_reply_to'];
        }

        $outbound = MailLogRepository::findOutboundByMessageId($inReplyTo);
        if ($outbound === null) {
            $refs = self::normalizeMessageIdList((string) ($inbound['references_header'] ?? ''));
            foreach ($refs as $ref) {
                $outbound = MailLogRepository::findOutboundByMessageId($ref);
                if ($outbound !== null) {
                    break;
                }
            }
        }
        if ($outbound === null) {
            return ['ok' => false, 'reason' => 'outbound_not_found'];
        }

        $offerId = (int) ($outbound['voucher_id'] ?? 0);
        if ($offerId < 1) {
            return ['ok' => false, 'reason' => 'no_voucher_on_mail'];
        }

        $offer = VoucherRepository::findById($offerId);
        if ($offer === null) {
            return ['ok' => false, 'reason' => 'offer_missing'];
        }
        if (VoucherDocumentKind::sanitize((string) ($offer['document_kind'] ?? '')) !== VoucherDocumentKind::OFFER) {
            return ['ok' => false, 'reason' => 'not_offer'];
        }

        if (self::looksLikeRejection((string) ($inbound['subject'] ?? ''), (string) ($inbound['body_preview'] ?? ''))) {
            return ['ok' => false, 'reason' => 'rejection'];
        }

        if (self::existingOrderConfirmationId($offerId) > 0) {
            return ['ok' => false, 'reason' => 'ab_exists'];
        }

        $acceptance = [
            'channel' => 'mail',
            'at' => date('c'),
            'by_email' => trim((string) ($inbound['from_address'] ?? '')),
            'by_name' => trim((string) ($inbound['from_name'] ?? '')),
            'mail_log_id' => $inboundLogId,
            'outbound_mail_log_id' => (int) ($outbound['id'] ?? 0),
        ];

        try {
            $form = VoucherDocumentChain::prefillFollowUp($offerId, VoucherDocumentKind::ORDER_CONFIRMATION);
            $form['document_status'] = VoucherDocumentStatus::defaultForKind(VoucherDocumentKind::ORDER_CONFIRMATION);
            $form['document_acceptance'] = $acceptance;
            // notes nur intern
            $note = '[Auto] Auftragsbestätigung aus E-Mail-Antwort #' . $inboundLogId;
            $form['notes'] = trim((string) ($form['notes'] ?? ''));
            $form['notes'] = $form['notes'] !== '' ? $form['notes'] . "\n" . $note : $note;

            $abId = VoucherRepository::save($form, null, null);
            VoucherRepository::updateDocumentAcceptance($abId, $acceptance);

            // Angebot als angenommen markieren, wenn Übergang erlaubt
            try {
                $current = VoucherDocumentStatus::sanitize((string) ($offer['document_status'] ?? ''));
                $next = VoucherDocumentStatus::nextStatuses($current, VoucherDocumentKind::OFFER);
                if (in_array(VoucherDocumentStatus::ACCEPTED, $next, true)) {
                    VoucherRepository::updateDocumentStatus($offerId, VoucherDocumentStatus::ACCEPTED);
                }
            } catch (Throwable) {
            }

            MailLogRepository::setVoucherId($inboundLogId, $abId);

            return ['ok' => true, 'created_voucher_id' => $abId];
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'save_failed:' . $e->getMessage()];
        }
    }

    public static function existingOrderConfirmationId(int $offerId): int
    {
        foreach (VoucherDocumentChain::chainDocuments($offerId) as $doc) {
            if ((string) ($doc['document_kind'] ?? '') !== VoucherDocumentKind::ORDER_CONFIRMATION) {
                continue;
            }
            if (!empty($doc['is_draft'])) {
                continue;
            }

            return (int) ($doc['id'] ?? 0);
        }

        return 0;
    }

    public static function looksLikeRejection(string $subject, string $body): bool
    {
        $hay = mb_strtolower($subject . ' ' . $body);
        foreach (['ablehnung', 'lehne ab', 'nicht annehmen', 'stornieren', 'widerruf', 'kein interesse', 'absage'] as $needle) {
            if (str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeMessageId(string $raw): string
    {
        return MailLogRepository::normalizeMessageId($raw);
    }

    /**
     * @return list<string>
     */
    public static function normalizeMessageIdList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        preg_match_all('/<([^>]+)>/', $raw, $m);
        if (($m[1] ?? []) !== []) {
            return array_values(array_unique(array_map(
                static fn (string $id): string => MailLogRepository::normalizeMessageId($id),
                $m[1]
            )));
        }
        $parts = preg_split('/\s+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $n = MailLogRepository::normalizeMessageId($p);
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    private static function extractInReplyToFromArchive(string $storagePath): string
    {
        if ($storagePath === '' || !is_readable($storagePath)) {
            // relative under DG_ROOT?
            $full = defined('DG_ROOT') ? DG_ROOT . '/' . ltrim($storagePath, '/') : $storagePath;
            if (!is_readable($full)) {
                return '';
            }
            $storagePath = $full;
        }
        $raw = (string) file_get_contents($storagePath);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^In-Reply-To:\s*(.+)$/mi', $raw, $m)) {
            return MailLogRepository::normalizeMessageId(trim($m[1]));
        }

        return '';
    }
}
