<?php
declare(strict_types=1);

/**
 * Formatiert GemFA-Öffnungszeiten in lesbare Zeilen / HTML.
 */
final class FinanzamtOpeningHours
{
    private const DAY_ORDER = ['Mo' => 1, 'Di' => 2, 'Mi' => 3, 'Do' => 4, 'Fr' => 5, 'Sa' => 6, 'So' => 7];

    /**
     * @return list<string>
     */
    public static function toLines(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($raw === '') {
            return [];
        }

        $lines = [];
        foreach (preg_split('/\s*\|\s*/u', $raw) ?: [] as $block) {
            $block = trim((string) $block);
            if ($block === '' || $block === 'Finanzamt:') {
                continue;
            }

            [$note, $days] = self::parseBlock($block);
            if ($note !== '') {
                $lines[] = $note;
            }
            foreach (self::collapseDays($days) as $label => $hours) {
                $lines[] = $label . ': ' . $hours;
            }
        }

        return $lines !== [] ? $lines : [$raw];
    }

    public static function toPlainText(string $raw): string
    {
        return implode("\n", self::toLines($raw));
    }

    public static function toHtml(string $raw): string
    {
        $lines = self::toLines($raw);
        if ($lines === []) {
            return '';
        }

        $html = '<div class="dg-opening-hours">';
        $html .= '<p class="dg-opening-hours__title">Öffnungszeiten</p>';
        $html .= '<ul class="dg-opening-hours__list">';
        foreach ($lines as $line) {
            $html .= self::lineToHtmlItem((string) $line);
        }
        $html .= '</ul></div>';

        return $html;
    }

    /**
     * Eine Anzeigezeile als Listeneintrag (Tag/Zeit getrennt, falls möglich).
     */
    public static function lineToHtmlItem(string $line): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }

        if (preg_match('/^((?:Mo|Di|Mi|Do|Fr|Sa|So)(?:\s*[–-]\s*(?:Mo|Di|Mi|Do|Fr|Sa|So))?)\s*:\s*(.+)$/u', $line, $m)) {
            return '<li class="dg-opening-hours__day"><span class="dg-opening-hours__label">'
                . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</span><span class="dg-opening-hours__value">'
                . htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</span></li>';
        }

        return '<li class="dg-opening-hours__note">'
            . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</li>';
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function parseBlock(string $block): array
    {
        $pattern = '/\b(Mo|Di|Mi|Do|Fr|Sa|So)(?:\s*[-–]\s*(Mo|Di|Mi|Do|Fr|Sa|So))?\s*:\s*([0-9]{1,2}[:.][0-9]{2}\s*[-–]\s*[0-9]{1,2}[:.][0-9]{2})/iu';
        if (!preg_match_all($pattern, $block, $matches, PREG_OFFSET_CAPTURE)) {
            return [rtrim($block, " :"), []];
        }

        $days = [];
        $firstOffset = (int) $matches[0][0][1];
        $note = trim(rtrim(substr($block, 0, $firstOffset), " :-"));

        foreach ($matches[0] as $i => $full) {
            $from = self::normalizeDay((string) $matches[1][$i][0]);
            $to = trim((string) ($matches[2][$i][0] ?? ''));
            $hours = self::normalizeHours((string) $matches[3][$i][0]);
            if ($to !== '') {
                $toDay = self::normalizeDay($to);
                foreach (self::dayRange($from, $toDay) as $day) {
                    $days[$day] = $hours;
                }
            } else {
                $days[$from] = $hours;
            }
        }

        return [$note, $days];
    }

    /**
     * @param array<string, string> $days
     * @return array<string, string>
     */
    private static function collapseDays(array $days): array
    {
        if ($days === []) {
            return [];
        }

        uksort($days, static function (string $a, string $b): int {
            return (self::DAY_ORDER[$a] ?? 99) <=> (self::DAY_ORDER[$b] ?? 99);
        });

        $collapsed = [];
        $ordered = array_keys($days);
        $i = 0;
        $n = count($ordered);
        while ($i < $n) {
            $start = $ordered[$i];
            $hours = $days[$start];
            $j = $i;
            while (
                $j + 1 < $n
                && $days[$ordered[$j + 1]] === $hours
                && (self::DAY_ORDER[$ordered[$j + 1]] ?? 0) === (self::DAY_ORDER[$ordered[$j]] ?? 0) + 1
            ) {
                ++$j;
            }
            $end = $ordered[$j];
            $label = $start === $end ? $start : $start . '–' . $end;
            $collapsed[$label] = $hours;
            $i = $j + 1;
        }

        return $collapsed;
    }

    /**
     * @return list<string>
     */
    private static function dayRange(string $from, string $to): array
    {
        $start = self::DAY_ORDER[$from] ?? 0;
        $end = self::DAY_ORDER[$to] ?? 0;
        if ($start < 1 || $end < 1 || $end < $start) {
            return [$from];
        }
        $out = [];
        foreach (self::DAY_ORDER as $day => $ord) {
            if ($ord >= $start && $ord <= $end) {
                $out[] = $day;
            }
        }

        return $out;
    }

    private static function normalizeDay(string $day): string
    {
        $day = ucfirst(mb_strtolower(trim($day)));
        return isset(self::DAY_ORDER[$day]) ? $day : $day;
    }

    private static function normalizeHours(string $hours): string
    {
        $hours = trim($hours);
        $hours = str_replace('.', ':', $hours);
        $hours = preg_replace('/\s*[-–]\s*/u', '–', $hours) ?? $hours;

        return $hours;
    }
}
