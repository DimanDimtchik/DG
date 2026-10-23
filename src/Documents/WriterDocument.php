<?php

declare(strict_types=1);

/**
 * Dokumentenverfasser D1 — Inhaltsmodell (Betreff, Blöcke, Empfänger, Infoblock, Status).
 * Persistenz: WriterDocumentRepository. Rendering: LetterheadRenderer::toRenderDocument().
 */
final class WriterDocument
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINAL = 'final';
    public const STATUS_ARCHIVED = 'archived';

    public const BODY_FORMAT_BLOCKS = 'blocks';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Entwurf',
            self::STATUS_FINAL => 'Final',
            self::STATUS_ARCHIVED => 'Archiviert',
        ];
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!isset(self::statusOptions()[$status])) {
            return self::STATUS_DRAFT;
        }

        return $status;
    }

    /**
     * Leeres Formular / neues Dokument.
     *
     * @return array{
     *   id: int,
     *   status: string,
     *   version: int,
     *   title: string,
     *   subject: string,
     *   greeting: string,
     *   closing: string,
     *   signature: string,
     *   recipient_lines: list<string>,
     *   info: array<string, string>,
     *   body: array{format: string, blocks: list<array<string, mixed>>, tiptap: mixed},
     *   body_html_cache: string,
     *   contact_id: int,
     *   voucher_id: int
     * }
     */
    public static function empty(): array
    {
        return [
            'id' => 0,
            'status' => self::STATUS_DRAFT,
            'version' => 1,
            'title' => '',
            'subject' => '',
            'greeting' => 'Sehr geehrte Damen und Herren,',
            'closing' => 'Mit freundlichen Grüßen',
            'signature' => '',
            'recipient_lines' => [''],
            'info' => self::emptyInfo(),
            'body' => self::emptyBody(),
            'body_html_cache' => '',
            'contact_id' => 0,
            'voucher_id' => 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function emptyInfo(): array
    {
        $info = [];
        foreach (array_keys(LetterheadSettings::defaultInfoFields()) as $key) {
            $info[$key] = $key === 'date' ? date('d.m.Y') : '';
        }

        return $info;
    }

    /**
     * Strukturierte Absätze (D3: TipTap-JSON zusätzlich in tiptap).
     *
     * @return array{format: string, blocks: list<array<string, mixed>>, tiptap: null}
     */
    public static function emptyBody(): array
    {
        return [
            'format' => self::BODY_FORMAT_BLOCKS,
            'blocks' => [
                self::paragraphBlock(''),
            ],
            'tiptap' => null,
        ];
    }

    /**
     * @return array{type: string, text: string, html: string}
     */
    public static function paragraphBlock(string $text = '', string $html = ''): array
    {
        return [
            'type' => 'paragraph',
            'text' => $text,
            'html' => $html,
        ];
    }

    /**
     * @return array<string, string> Vorlage-Text => Kurzlabel
     */
    public static function greetingPresets(): array
    {
        return [
            'Sehr geehrte Damen und Herren,' => 'Neutral',
            'Sehr geehrte Frau …,' => 'Frau',
            'Sehr geehrter Herr …,' => 'Herr',
            'Guten Tag,' => 'Kurz',
            'Liebe …,' => 'Persönlich',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function closingPresets(): array
    {
        return [
            'Mit freundlichen Grüßen' => 'Standard',
            'Freundliche Grüße' => 'Kurz',
            'Mit besten Grüßen' => 'Warm',
            'Hochachtungsvoll' => 'Formell',
        ];
    }

    /**
     * Absätze für Textareas aus Dokumentstruktur.
     *
     * @param array<string, mixed> $doc
     * @return list<string>
     */
    public static function paragraphTexts(array $doc): array
    {
        $body = is_array($doc['body'] ?? null) ? $doc['body'] : [];
        $blocks = is_array($body['blocks'] ?? null) ? $body['blocks'] : [];
        $texts = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? 'paragraph') !== 'paragraph') {
                continue;
            }
            $texts[] = (string) ($block['text'] ?? '');
        }

        return $texts !== [] ? $texts : [''];
    }

    /**
     * TipTap-JSON → strukturierte Blöcke (Absätze + Tabellen/HTML-Snippets).
     *
     * @param array<string, mixed> $tiptap
     * @return list<array<string, mixed>>
     */
    public static function blocksFromTiptap(array $tiptap): array
    {
        $content = is_array($tiptap['content'] ?? null) ? $tiptap['content'] : [];
        $blocks = [];
        foreach ($content as $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = (string) ($node['type'] ?? '');
            if ($type === 'paragraph') {
                $blocks[] = self::paragraphBlock(self::plainTextFromTipTapNode($node));
            } elseif ($type === 'bulletList' || $type === 'orderedList') {
                $blocks[] = [
                    'type' => $type === 'orderedList' ? 'ordered_list' : 'bullet_list',
                    'text' => self::plainTextFromTipTapNode($node),
                    'html' => '',
                ];
            } elseif ($type === 'table') {
                $blocks[] = [
                    'type' => 'table',
                    'text' => self::plainTextFromTipTapNode($node),
                    'html' => '',
                ];
            } elseif ($type === 'image') {
                $blocks[] = [
                    'type' => 'image',
                    'text' => trim((string) ($node['attrs']['alt'] ?? '')),
                    'html' => '',
                ];
            }
            if (count($blocks) >= 100) {
                break;
            }
        }

        return $blocks;
    }

    /**
     * Initiales TipTap-JSON aus gespeichertem Dokument (oder Absätze → Doc).
     *
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    public static function tipTapDocument(array $doc): array
    {
        $body = is_array($doc['body'] ?? null) ? $doc['body'] : [];
        if (is_array($body['tiptap'] ?? null) && ($body['tiptap']['type'] ?? '') === 'doc') {
            return $body['tiptap'];
        }

        $paras = self::paragraphTexts($doc);
        $content = [];
        foreach ($paras as $text) {
            $node = ['type' => 'paragraph'];
            if ($text !== '') {
                $node['content'] = [['type' => 'text', 'text' => $text]];
            }
            $content[] = $node;
        }
        if ($content === []) {
            $content[] = ['type' => 'paragraph'];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function plainTextFromTipTapNode(array $node): string
    {
        $parts = [];
        if (($node['type'] ?? '') === 'text') {
            return (string) ($node['text'] ?? '');
        }
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $chunk = self::plainTextFromTipTapNode($child);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function blocksAreOnlyParagraphs(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? 'paragraph') !== 'paragraph') {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalisiert Eingabe (POST/API/JSON) für Speichern.
     *
     * @param array<string, mixed> $input
     * @return array{
     *   title: string,
     *   subject: string,
     *   greeting: string,
     *   closing: string,
     *   signature: string,
     *   recipient_lines: list<string>,
     *   info: array<string, string>,
     *   body: array{format: string, blocks: list<array<string, mixed>>, tiptap: mixed},
     *   body_html_cache: string,
     *   contact_id: int,
     *   voucher_id: int
     * }
     */
    public static function sanitize(array $input): array
    {
        $subject = self::clip(trim((string) ($input['subject'] ?? '')), 500);
        $title = self::clip(trim((string) ($input['title'] ?? '')), 191);
        if ($title === '' && $subject !== '') {
            $title = self::clip($subject, 191);
        }

        $recipient = self::sanitizeRecipientLines($input['recipient_lines'] ?? ($input['recipient'] ?? []));
        $info = self::sanitizeInfo(is_array($input['info'] ?? null) ? $input['info'] : []);
        $body = self::sanitizeBody(is_array($input['body'] ?? null) ? $input['body'] : []);

        // TipTap-JSON aus Formular (hidden field body_tiptap)
        if (array_key_exists('body_tiptap', $input)) {
            $tiptapRaw = $input['body_tiptap'];
            if (is_string($tiptapRaw) && $tiptapRaw !== '') {
                $decoded = json_decode($tiptapRaw, true);
                $body['tiptap'] = is_array($decoded) ? $decoded : null;
            } elseif (is_array($tiptapRaw)) {
                $body['tiptap'] = $tiptapRaw;
            } else {
                $body['tiptap'] = null;
            }
            if (is_array($body['tiptap'])) {
                $fromTip = self::blocksFromTiptap($body['tiptap']);
                if ($fromTip !== []) {
                    $body['blocks'] = $fromTip;
                }
            }
        }

        // Formular: paragraphs[] (Fallback / Sync aus Editor)
        if (isset($input['paragraphs']) && is_array($input['paragraphs'])) {
            $blocks = [];
            foreach ($input['paragraphs'] as $p) {
                $blocks[] = self::paragraphBlock(trim((string) $p));
                if (count($blocks) >= 100) {
                    break;
                }
            }
            if ($blocks === []) {
                $blocks[] = self::paragraphBlock('');
            }
            // TipTap-Blöcke (Tabellen etc.) nicht überschreiben, wenn nur Absätze als Sync kommen
            if ($body['tiptap'] === null || self::blocksAreOnlyParagraphs($body['blocks'])) {
                $body['blocks'] = $blocks;
            }
        }

        $htmlCache = trim((string) ($input['body_html_cache'] ?? ''));
        if (mb_strlen($htmlCache) > 65000) {
            $htmlCache = mb_substr($htmlCache, 0, 65000);
        }

        return [
            'title' => $title,
            'subject' => $subject,
            'greeting' => self::clip(trim((string) ($input['greeting'] ?? '')), 255),
            'closing' => self::clip(trim((string) ($input['closing'] ?? '')), 255),
            'signature' => self::clip(trim((string) ($input['signature'] ?? '')), 255),
            'recipient_lines' => $recipient,
            'info' => $info,
            'body' => $body,
            'body_html_cache' => $htmlCache,
            'contact_id' => max(0, (int) ($input['contact_id'] ?? 0)),
            'voucher_id' => max(0, (int) ($input['voucher_id'] ?? 0)),
        ];
    }

    /**
     * DB-Zeile → Formular-/API-Struktur.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function fromRow(array $row): array
    {
        $empty = self::empty();
        $recipient = self::decodeJsonList($row['recipient_json'] ?? '[]');
        $infoRaw = self::decodeJsonObject($row['info_json'] ?? '{}');
        $bodyRaw = self::decodeJsonObject($row['body_json'] ?? '{}');

        $payload = self::sanitize([
            'title' => (string) ($row['title'] ?? ''),
            'subject' => (string) ($row['subject'] ?? ''),
            'greeting' => (string) ($row['greeting'] ?? ''),
            'closing' => (string) ($row['closing'] ?? ''),
            'signature' => (string) ($row['signature'] ?? ''),
            'recipient_lines' => $recipient !== [] ? $recipient : $empty['recipient_lines'],
            'info' => $infoRaw,
            'body' => $bodyRaw !== [] ? $bodyRaw : $empty['body'],
            'body_html_cache' => (string) ($row['body_html_cache'] ?? ''),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'voucher_id' => (int) ($row['voucher_id'] ?? 0),
        ]);

        return array_merge($payload, [
            'id' => (int) ($row['id'] ?? 0),
            'status' => self::normalizeStatus((string) ($row['status'] ?? self::STATUS_DRAFT)),
            'version' => max(1, (int) ($row['version'] ?? 1)),
            'created_by' => (int) ($row['created_by'] ?? 0) ?: null,
            'updated_by' => (int) ($row['updated_by'] ?? 0) ?: null,
            'finalized_by' => (int) ($row['finalized_by'] ?? 0) ?: null,
            'finalized_at' => $row['finalized_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
    }

    /**
     * Für LetterheadRenderer (sampleDocument-Form).
     *
     * @param array<string, mixed> $doc sanitize()/fromRow()-Struktur
     * @return array{
     *   recipient_lines: list<string>,
     *   info: array<string, string>,
     *   subject: string,
     *   greeting: string,
     *   paragraphs: list<string>,
     *   closing: string,
     *   signature: string
     * }
     */
    public static function toRenderDocument(array $doc): array
    {
        $clean = self::sanitize($doc);

        $paragraphs = [];
        foreach ($clean['body']['blocks'] as $block) {
            if (($block['type'] ?? '') !== 'paragraph') {
                continue;
            }
            $text = trim((string) ($block['text'] ?? ''));
            if ($text === '' && trim((string) ($block['html'] ?? '')) !== '') {
                $text = trim(html_entity_decode(strip_tags((string) $block['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        $bodyHtml = trim((string) ($clean['body_html_cache'] ?? ''));

        return [
            'recipient_lines' => $clean['recipient_lines'],
            'info' => $clean['info'],
            'subject' => $clean['subject'],
            'greeting' => $clean['greeting'],
            'paragraphs' => $paragraphs,
            'body_html' => $bodyHtml,
            'closing' => $clean['closing'],
            'signature' => $clean['signature'],
        ];
    }

    /**
     * Vollständiger Snapshot für Versionshistorie (GoBD).
     *
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    public static function snapshotPayload(array $doc): array
    {
        $clean = self::sanitize($doc);

        return [
            'status' => self::normalizeStatus((string) ($doc['status'] ?? self::STATUS_DRAFT)),
            'version' => max(1, (int) ($doc['version'] ?? 1)),
            'title' => $clean['title'],
            'subject' => $clean['subject'],
            'greeting' => $clean['greeting'],
            'closing' => $clean['closing'],
            'signature' => $clean['signature'],
            'recipient_lines' => $clean['recipient_lines'],
            'info' => $clean['info'],
            'body' => $clean['body'],
            'body_html_cache' => $clean['body_html_cache'],
            'contact_id' => $clean['contact_id'],
            'voucher_id' => $clean['voucher_id'],
            'snapshotted_at' => date('c'),
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function sanitizeRecipientLines(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [''];
        }
        $lines = [];
        foreach ($raw as $line) {
            $line = self::clip(trim((string) $line), 200);
            if ($line === '') {
                continue;
            }
            $lines[] = $line;
            if (count($lines) >= 12) {
                break;
            }
        }

        return $lines !== [] ? $lines : [''];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, string>
     */
    private static function sanitizeInfo(array $raw): array
    {
        $info = self::emptyInfo();
        foreach (array_keys($info) as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            $info[$key] = self::clip(trim((string) $raw[$key]), 200);
        }

        return $info;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{format: string, blocks: list<array<string, mixed>>, tiptap: mixed}
     */
    private static function sanitizeBody(array $raw): array
    {
        $blocksIn = is_array($raw['blocks'] ?? null) ? $raw['blocks'] : [];
        $blocks = [];
        foreach ($blocksIn as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = strtolower(trim((string) ($block['type'] ?? 'paragraph')));
            if ($type === '') {
                $type = 'paragraph';
            }
            // v1: nur Absätze; andere Typen als Platzhalter behalten (D3)
            $text = self::clip(trim((string) ($block['text'] ?? '')), 20000);
            $html = trim((string) ($block['html'] ?? ''));
            if (mb_strlen($html) > 50000) {
                $html = mb_substr($html, 0, 50000);
            }
            $blocks[] = [
                'type' => self::clip($type, 40),
                'text' => $text,
                'html' => $html,
            ];
            if (count($blocks) >= 100) {
                break;
            }
        }
        if ($blocks === []) {
            $blocks[] = self::paragraphBlock('');
        }

        $tiptap = $raw['tiptap'] ?? null;
        if ($tiptap !== null && !is_array($tiptap) && !is_string($tiptap)) {
            $tiptap = null;
        }

        return [
            'format' => self::BODY_FORMAT_BLOCKS,
            'blocks' => $blocks,
            'tiptap' => $tiptap,
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function blocksAreEmpty(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (trim((string) ($block['text'] ?? '')) !== '' || trim((string) ($block['html'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function decodeJsonList(mixed $json): array
    {
        if (is_array($json)) {
            $out = [];
            foreach ($json as $v) {
                $out[] = (string) $v;
            }

            return $out;
        }
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            $out[] = (string) $v;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJsonObject(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function clip(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }
}
