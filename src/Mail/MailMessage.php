<?php
declare(strict_types=1);

final class MailMessage
{
    /** @param list<string> $to */
    /** @param list<string> $cc */
    /** @param list<string> $bcc */
    public function __construct(
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly array $to,
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly string $textBody = '',
        public readonly ?string $fromEmail = null,
        public readonly ?string $fromName = null,
        public readonly ?string $replyTo = null,
        public readonly ?int $contactId = null,
        public readonly ?string $inReplyTo = null,
        public readonly ?string $references = null,
    ) {
    }

    /** @return list<string> */
    public function allRecipients(): array
    {
        return array_values(array_unique(array_merge($this->to, $this->cc, $this->bcc)));
    }

    public function toMime(string $fromEmail, string $fromName, ?string $replyTo, string $messageId): string
    {
        $to = self::formatAddressList($this->to);
        if ($to === '') {
            throw new InvalidArgumentException('Mindestens ein Empfänger erforderlich.');
        }

        $text = $this->textBody !== '' ? $this->textBody : self::htmlToText($this->htmlBody);
        $boundary = 'dg_' . bin2hex(random_bytes(12));
        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $encodedSubject = self::encodeHeader($this->subject);
        $encodedFrom = self::encodeAddress($fromEmail, $fromName);

        $headers = [
            'Date: ' . $date,
            'Message-ID: <' . $messageId . '>',
            'From: ' . $encodedFrom,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: DG-CRM',
        ];

        if ($this->cc !== []) {
            $headers[] = 'Cc: ' . self::formatAddressList($this->cc);
        }
        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
            $headers[] = 'In-Reply-To: ' . $this->inReplyTo;
        }
        if ($this->references !== null && $this->references !== '') {
            $headers[] = 'References: ' . $this->references;
        }

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->htmlBody)) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** @param list<string> $addresses */
    private static function formatAddressList(array $addresses): string
    {
        $valid = [];
        foreach ($addresses as $address) {
            $address = trim($address);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $address;
            }
        }

        return implode(', ', $valid);
    }

    private static function encodeAddress(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        if ($value === '' || preg_match('/^[\x20-\x7E]+$/', $value)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public static function bodyPreview(string $html): string
    {
        return self::htmlToText($html);
    }

    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html)));
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
