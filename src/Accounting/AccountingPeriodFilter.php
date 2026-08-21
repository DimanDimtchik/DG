<?php
declare(strict_types=1);

/** Zeitraum-Filter für Buchhaltung (Jahr, Monat, von–bis). */
final class AccountingPeriodFilter
{
    public int $year;
    public string $dateFrom;
    public string $dateTo;
    public ?int $month;
    public string $label;

    /**
     * @param array<string, mixed> $input
     */
    public static function fromRequest(array $input, ?int $defaultYear = null): self
    {
        $year = max(2000, (int) ($input['year'] ?? $defaultYear ?? (int) date('Y')));
        $monthRaw = (int) ($input['month'] ?? 0);
        $month = $monthRaw >= 1 && $monthRaw <= 12 ? $monthRaw : null;

        $from = trim((string) ($input['date_from'] ?? ''));
        $to = trim((string) ($input['date_to'] ?? ''));

        if ($from !== '' && strtotime($from) !== false) {
            $from = date('Y-m-d', strtotime($from));
        } else {
            $from = '';
        }
        if ($to !== '' && strtotime($to) !== false) {
            $to = date('Y-m-d', strtotime($to));
        } else {
            $to = '';
        }

        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($from === '' && $to === '' && $month !== null) {
            $from = sprintf('%04d-%02d-01', $year, $month);
            $to = date('Y-m-t', strtotime($from));
            $label = self::monthName($month) . ' ' . $year;
        } elseif ($from !== '' && $to !== '') {
            if ($from === sprintf('%04d-01-01', $year) && $to === sprintf('%04d-12-31', $year)) {
                $label = 'Jahr ' . $year;
            } else {
                $label = date('d.m.Y', strtotime($from)) . ' – ' . date('d.m.Y', strtotime($to));
            }
        } else {
            $from = sprintf('%04d-01-01', $year);
            $to = sprintf('%04d-12-31', $year);
            $label = 'Jahr ' . $year;
        }

        $self = new self();
        $self->year = $year;
        $self->dateFrom = $from;
        $self->dateTo = $to;
        $self->month = $month;
        $self->label = $label;

        return $self;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function sqlDateRange(string $column, array &$params, string $prefix = 'pd'): string
    {
        $params[$prefix . '_from'] = $this->dateFrom;
        $params[$prefix . '_to'] = $this->dateTo;

        return "{$column} BETWEEN :{$prefix}_from AND :{$prefix}_to";
    }

    /**
     * @return array<string, string|int>
     */
    public function queryParams(): array
    {
        $out = ['year' => $this->year];
        if ($this->month !== null) {
            $out['month'] = $this->month;
        }
        if ($this->dateFrom !== sprintf('%04d-01-01', $this->year)
            || $this->dateTo !== sprintf('%04d-12-31', $this->year)
        ) {
            $out['date_from'] = $this->dateFrom;
            $out['date_to'] = $this->dateTo;
        }

        return $out;
    }

    public function appendToUrl(string $baseUrl): string
    {
        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        $parts = [];
        foreach ($this->queryParams() as $key => $value) {
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return $baseUrl . ($parts !== [] ? $sep . implode('&', $parts) : '');
    }

    public function isFullYear(): bool
    {
        return $this->dateFrom === sprintf('%04d-01-01', $this->year)
            && $this->dateTo === sprintf('%04d-12-31', $this->year);
    }

    private static function monthName(int $month): string
    {
        $names = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $names[$month] ?? '';
    }
}
