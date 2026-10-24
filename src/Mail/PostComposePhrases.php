<?php
declare(strict_types=1);

/** Vorlagen für Post-Compose: Anrede, Schlussabsatz, Grußformel. */
final class PostComposePhrases
{
    /**
     * @return list<array{id: string, label: string, text: string}>
     */
    public static function greetings(?Contact $contact = null): array
    {
        $last = $contact !== null ? trim($contact->lastName) : '';
        $first = $contact !== null ? trim($contact->firstName) : '';
        $name = $last !== '' ? $last : ($first !== '' ? $first : '…');
        $sal = $contact !== null ? trim($contact->salutation) : '';

        return [
            ['id' => 'neutral', 'label' => 'Sehr geehrte Damen und Herren', 'text' => 'Sehr geehrte Damen und Herren,'],
            ['id' => 'frau', 'label' => 'Sehr geehrte Frau …', 'text' => 'Sehr geehrte Frau ' . $name . ','],
            ['id' => 'herr', 'label' => 'Sehr geehrter Herr …', 'text' => 'Sehr geehrter Herr ' . $name . ','],
            ['id' => 'guten_tag', 'label' => 'Guten Tag', 'text' => $name !== '…' ? ('Guten Tag ' . trim($first . ' ' . $last) . ',') : 'Guten Tag,'],
            ['id' => 'hallo', 'label' => 'Hallo (locker)', 'text' => $first !== '' ? ('Hallo ' . $first . ',') : 'Hallo,'],
            ['id' => 'team', 'label' => 'Sehr geehrtes Team', 'text' => 'Sehr geehrtes Team,'],
        ];
    }

    /**
     * @return list<array{id: string, label: string, text: string}>
     */
    public static function closingParagraphs(): array
    {
        return [
            [
                'id' => 'verfuegung',
                'label' => 'Zur Verfügung',
                'text' => 'Vielen Dank im Voraus. Wir stehen Ihnen gerne jederzeit zur Verfügung.',
            ],
            [
                'id' => 'rueckfragen',
                'label' => 'Rückfragen',
                'text' => 'Bei Rückfragen erreichen Sie uns gerne telefonisch oder per E-Mail.',
            ],
            [
                'id' => 'freude',
                'label' => 'Zusammenarbeit',
                'text' => 'Wir freuen uns auf die weitere Zusammenarbeit und melden uns bei Bedarf umgehend.',
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, text: string}>
     */
    public static function signatures(string $authorName): array
    {
        $authorName = trim($authorName) !== '' ? trim($authorName) : 'Ihr Team';

        return [
            ['id' => 'mfg', 'label' => 'Mit freundlichen Grüßen', 'text' => "Mit freundlichen Grüßen\n" . $authorName],
            ['id' => 'mbg', 'label' => 'Mit besten Grüßen', 'text' => "Mit besten Grüßen\n" . $authorName],
            ['id' => 'fg', 'label' => 'Freundliche Grüße', 'text' => "Freundliche Grüße\n" . $authorName],
            ['id' => 'herzliche', 'label' => 'Herzliche Grüße', 'text' => "Herzliche Grüße\n" . $authorName],
            ['id' => 'hochachtung', 'label' => 'Hochachtungsvoll', 'text' => "Hochachtungsvoll\n" . $authorName],
        ];
    }

    public static function defaultGreetingId(?Contact $contact): string
    {
        if ($contact === null) {
            return 'neutral';
        }
        $sal = mb_strtolower(trim($contact->salutation));
        if (str_contains($sal, 'frau')) {
            return 'frau';
        }
        if (str_contains($sal, 'herr')) {
            return 'herr';
        }
        if ($sal === 'firma' || $contact->companyName !== '') {
            return 'neutral';
        }

        return 'neutral';
    }

    public static function buildBody(string $greeting, string $closingPara, string $signature, string $middle = ''): string
    {
        $parts = [rtrim($greeting)];
        $middle = trim($middle);
        if ($middle !== '') {
            $parts[] = $middle;
        } else {
            $parts[] = '';
        }
        $parts[] = rtrim($closingPara);
        $parts[] = '';
        $parts[] = rtrim($signature);

        return implode("\n\n", $parts) . "\n";
    }
}
