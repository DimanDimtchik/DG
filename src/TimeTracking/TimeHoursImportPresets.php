<?php
declare(strict_types=1);

/**
 * Quellsysteme für Arbeitsstunden-Import (Excel/CSV aus Shiftbase, Crewmeister, …).
 */
final class TimeHoursImportPresets
{
    public const SOURCE_EXCEL = 'excel';
    public const SOURCE_SHIFTBASE = 'shiftbase';
    public const SOURCE_CREWMEISTER = 'crewmeister';
    public const SOURCE_OTHER = 'other';

    /** @return array<string, array{label: string, hint: string, formats: string}> */
    public static function all(): array
    {
        return [
            self::SOURCE_EXCEL => [
                'label' => 'Excel / CSV (eigene Tabelle)',
                'hint' => 'Spalten z. B. E-Mail oder Login, Datum, Beginn, Ende, optional Pause/Stunden. Vorlage herunterladen.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_SHIFTBASE => [
                'label' => 'Shiftbase (Timesheet / Bericht)',
                'hint' => 'Shiftbase → Berichte → Timesheet detail bzw. Stundenbericht → Excel/CSV exportieren und hier hochladen.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_CREWMEISTER => [
                'label' => 'Crewmeister (Export)',
                'hint' => 'In Crewmeister Zeiten als Excel/CSV exportieren (Kommen/Gehen oder Stunden). Auch Schreibweise „Crewmaster“.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_OTHER => [
                'label' => 'Anderes Programm / unsicher',
                'hint' => 'Datei einfach hochladen — Spalten werden über gängige Aliasse erkannt (Beginn/Ende, Pause, Arbeitszeit).',
                'formats' => 'Excel (.xlsx), CSV',
            ],
        ];
    }

    public static function normalize(string $source): string
    {
        $source = strtolower(trim($source));
        if ($source === 'crewmaster') {
            return self::SOURCE_CREWMEISTER;
        }

        return array_key_exists($source, self::all()) ? $source : self::SOURCE_OTHER;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function aliases(string $source): array
    {
        $source = self::normalize($source);
        $base = [
            'email' => ['email', 'e_mail', 'mail', 'email_address', 'e_mail_address', 'emailadres'],
            'login' => [
                'login', 'benutzername', 'username', 'personalnummer', 'personal_number',
                'employee_number', 'employee_id', 'medewerker_id', 'personeelsnummer',
                'datev_personnel_number', 'mitarbeiternummer', 'pnr',
            ],
            'full_name' => [
                'name', 'mitarbeiter', 'mitarbeitername', 'employee', 'employee_name',
                'medewerker', 'naam', 'full_name', 'vollname', 'display_name',
            ],
            'first_name' => ['first_name', 'vorname', 'voornaam', 'given_name'],
            'last_name' => ['last_name', 'nachname', 'achternaam', 'surname', 'family_name'],
            'work_date' => ['datum', 'date', 'work_date', 'arbeitstag', 'tag', 'day', 'werksdatum'],
            'clock_in' => [
                'beginn', 'start', 'start_time', 'clock_in', 'clocked_in', 'kommen',
                'arbeitsbeginn', 'stempel_ein', 'von', 'begintijd', 'starttijd', 'in',
            ],
            'clock_out' => [
                'ende', 'end', 'end_time', 'clock_out', 'clocked_out', 'gehen',
                'arbeitsende', 'stempel_aus', 'bis', 'eindtijd', 'stoptijd', 'out',
            ],
            'break_minutes' => [
                'pause', 'pausenzeit', 'pause_minuten', 'break', 'break_minutes',
                'break_duration', 'pauze', 'pauze_minuten', 'break_time',
            ],
            'worked_hours' => [
                'stunden', 'arbeitsstunden', 'arbeitszeit', 'ist_stunden', 'hours',
                'worked_hours', 'duration', 'gewerkte_uren', 'uren', 'net_hours',
                'soll_ist', 'worked',
            ],
            'worked_minutes' => ['worked_minutes', 'arbeitsminuten', 'minuten', 'minutes'],
        ];

        $extra = match ($source) {
            self::SOURCE_SHIFTBASE => [
                'full_name' => ['user', 'user_name', 'team_member'],
                'work_date' => ['shift_date', 'roster_date'],
                'clock_in' => ['clocked_in_at', 'start_at', 'time_from'],
                'clock_out' => ['clocked_out_at', 'end_at', 'time_until'],
                'worked_hours' => ['total_hours', 'hours_worked', 'productive_hours'],
            ],
            self::SOURCE_CREWMEISTER => [
                'full_name' => ['mitarbeiter_name', 'worker', 'crew'],
                'work_date' => ['arbeitstag', 'buchungstag'],
                'clock_in' => ['kommenzeit', 'einstempeln', 'startzeit'],
                'clock_out' => ['gehenzeit', 'ausstempeln', 'endzeit'],
                'break_minutes' => ['pausenminuten', 'pause_dauer'],
                'worked_hours' => ['nettozeit', 'bruttozeit', 'anwesenheit'],
            ],
            default => [],
        };

        return InstallCsvHelper::mergeAliases($base, $extra);
    }
}
