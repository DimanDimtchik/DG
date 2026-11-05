<?php
declare(strict_types=1);

/**
 * Modulare Vertragsklauseln (HTML-Fragmente) — tenant-neutral, ohne Kundenfirma im Kern.
 */
final class EmploymentContractClauses
{
    /**
     * @param array<string, string> $p Placeholders
     * @param array{
     *   contract_type: string,
     *   is_minor: bool,
     *   annex_training?: bool,
     *   annex_pension?: bool,
     *   annex_ifsg?: bool,
     *   annex_video?: bool
     * } $opts
     */
    public static function bodyHtml(array $p, array $opts): string
    {
        $type = (string) ($opts['contract_type'] ?? EmploymentContractContext::TYPE_FULLTIME);
        $parts = [];
        $parts[] = self::p(
            'Dieser Entwurf dient der Nachweisführung wesentlicher Vertragsbedingungen '
            . '(Nachweisgesetz). Vor Unterzeichnung rechtlich prüfen. Kein Ersatz für anwaltliche Beratung.'
        );
        $parts[] = self::parties($p);
        $parts[] = self::section('1', 'Beginn und Art des Arbeitsverhältnisses', self::startClauses($p, $type));
        $parts[] = self::section('2', 'Tätigkeit und Arbeitsort', self::jobClauses($p));
        $parts[] = self::section('3', 'Arbeitszeit', self::hoursClauses($p, $type, !empty($opts['is_minor'])));
        $parts[] = self::section('4', 'Vergütung', self::payClauses($p, $type));
        $parts[] = self::section('5', 'Urlaub', self::vacationClauses($p, !empty($opts['is_minor'])));
        $parts[] = self::section('6', 'Krankheit / Arbeitsunfähigkeit', self::sicknessClauses($p));
        $parts[] = self::section('7', 'Datenschutz und Verschwiegenheit', self::privacyClauses());
        if (!empty($opts['is_minor'])) {
            $parts[] = self::section('8', 'Besonderheiten bei Minderjährigen (JArbSchG)', self::minorClauses($p));
        }
        $parts[] = self::signatures($p, !empty($opts['is_minor']));

        if (!empty($opts['annex_training'])) {
            $parts[] = self::annexTraining($p, !empty($opts['annex_video']));
        }
        if ($type === EmploymentContractContext::TYPE_MINIJOB && !empty($opts['annex_pension'])) {
            $parts[] = self::annexPension($p, !empty($opts['is_minor']));
        }
        if (!empty($opts['annex_ifsg'])) {
            $parts[] = self::annexIfsg($p);
        }

        return implode("\n", array_filter($parts));
    }

    /** @param list<string> $paras */
    private static function section(string $nr, string $title, array $paras): string
    {
        $html = '<p><strong>§ ' . self::e($nr) . ' ' . self::e($title) . '</strong></p>';
        foreach ($paras as $para) {
            $html .= self::p($para);
        }

        return $html;
    }

    private static function p(string $text): string
    {
        return '<p>' . self::e($text) . '</p>';
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function eMultiline(string $text): string
    {
        $parts = [];
        foreach (EmploymentContractContext::addressLines($text) as $line) {
            $parts[] = self::e($line);
        }

        return implode('<br>', $parts);
    }

    /** @param array<string, string> $p */
    private static function parties(array $p): string
    {
        return '<p><strong>Zwischen</strong><br>'
            . self::e($p['employer_name']) . '<br>'
            . self::eMultiline($p['employer_address'])
            . '<br><br><strong>und</strong><br>'
            . self::e($p['employee_name'])
            . ($p['employee_birth_date'] !== '' ? '<br>geb. am ' . self::e($p['employee_birth_date']) : '')
            . '<br>' . self::eMultiline($p['employee_address'])
            . '<br><br>wird folgender Arbeitsvertrag geschlossen:</p>';
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function startClauses(array $p, string $type): array
    {
        $lines = [
            'Das Arbeitsverhältnis beginnt am ' . ($p['contract_start'] !== '' ? $p['contract_start'] : '…') . '.',
        ];
        if ($p['contract_end'] !== '') {
            $lines[] = 'Es ist befristet bis zum ' . $p['contract_end'] . '.';
        } else {
            $lines[] = 'Das Arbeitsverhältnis wird auf unbestimmte Zeit geschlossen.';
        }
        if ($p['probation_period'] !== '') {
            $lines[] = 'Probezeit: ' . $p['probation_period'] . '.';
        } else {
            $lines[] = 'Soweit vereinbart, beträgt die Probezeit höchstens sechs Monate.';
        }
        $lines[] = 'Vertragsart: ' . ($p['contract_type_label'] !== '' ? $p['contract_type_label'] : $type) . '.';
        if ($type === EmploymentContractContext::TYPE_MINIJOB) {
            $lines[] = 'Es handelt sich um eine geringfügige Beschäftigung im Sinne von SGB IV § 8. '
                . 'Die monatliche Entgeltgrenze beträgt ab ' . $p['rates_from'] . ' im Jahresdurchschnitt '
                . $p['geringfuegig_month'] . ' (sofern amtlich bekannt).';
        }

        return $lines;
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function jobClauses(array $p): array
    {
        $activity = $p['job_type'] !== '' ? $p['job_type'] : $p['employment_relationship'];
        $lines = [
            'Die Arbeitnehmerin / der Arbeitnehmer wird beschäftigt als / mit folgender Tätigkeit: ' . $activity . '.',
            'Arbeitsort: ' . ($p['work_location'] !== '' ? $p['work_location'] : '…') . '.',
        ];
        if ($p['employer_site'] !== '') {
            $lines[] = 'Betriebliche Stelle: ' . $p['employer_site'] . '.';
        }
        $lines[] = 'Der Arbeitgeber kann im Rahmen des Direktionsrechts gleichwertige Tätigkeiten zuweisen, '
            . 'soweit zumutbar und nicht mit einer wesentlichen Verschlechterung verbunden.';

        return $lines;
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function hoursClauses(array $p, string $type, bool $minor): array
    {
        $lines = [
            'Die regelmäßige Arbeitszeit beträgt: ' . ($p['working_hours'] !== '' ? $p['working_hours'] : '…') . '.',
        ];
        if ($p['daily_work_minutes'] !== '' && (int) $p['daily_work_minutes'] > 0) {
            $mins = (int) $p['daily_work_minutes'];
            $lines[] = 'Soll-Arbeitszeit pro Tag: ' . $mins . ' Minuten ('
                . number_format($mins / 60, 2, ',', '') . ' Stunden).';
        }
        if ($type === EmploymentContractContext::TYPE_MINIJOB) {
            $lines[] = 'Die Arbeitszeit ist so zu bemessen, dass bei dem vereinbarten Stundenlohn die '
                . 'Geringfügigkeitsgrenze im Jahresdurchschnitt nicht überschritten wird.';
        }
        if ($minor) {
            $lines[] = 'Für Minderjährige gelten die Höchstarbeitszeiten, Ruhepausen und Beschäftigungsverbote '
                . 'des Jugendarbeitsschutzgesetzes (JArbSchG). Sonn- und Feiertagsarbeit nur in den gesetzlich '
                . 'zulässigen Ausnahmen.';
        } else {
            $lines[] = 'Es gelten die Vorgaben des Arbeitszeitgesetzes (ArbZG) zu Höchstarbeitszeit, Ruhepausen '
                . 'und Ruhezeit.';
        }

        return $lines;
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function payClauses(array $p, string $type): array
    {
        $lines = [
            'Die Vergütung beträgt: ' . ($p['salary'] !== '' ? $p['salary'] : '…') . '.',
            'Es gilt mindestens der gesetzliche Mindestlohn (' . $p['min_wage'] . ', Stand ab ' . $p['rates_from']
                . ', soweit amtlich bekannt). Unbezahlte Probearbeit ist unzulässig.',
            'Die Vergütung ist jeweils zum vereinbarten Fälligkeitstermin, spätestens zum Ende des Folgemonats, '
                . 'auf das angegebene Konto zu zahlen.',
        ];
        if ($type === EmploymentContractContext::TYPE_MINIJOB) {
            $lines[] = 'Hinweise zur Sozialversicherung (Minijob) und ggf. Rentenversicherungsbefreiung siehe Anlage.';
        }

        return $lines;
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function vacationClauses(array $p, bool $minor): array
    {
        $lines = [
            'Der jährliche Erholungsurlaub beträgt: ' . ($p['vacation_days'] !== '' ? $p['vacation_days'] : '…') . '.',
        ];
        if ($minor) {
            $lines[] = 'Für Minderjährige gelten die erhöhten Urlaubsansprüche nach JArbSchG § 19 in Abhängigkeit vom Alter.';
        } else {
            $lines[] = 'Es gelten mindestens die Vorschriften des Bundesurlaubsgesetzes (BUrlG).';
        }

        return $lines;
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function sicknessClauses(array $p): array
    {
        return [
            'Arbeitsunfähigkeit ist unverzüglich mitzuteilen. Spätestens ab dem dritten Kalendertag der '
                . 'Arbeitsunfähigkeit ist eine ärztliche Bescheinigung vorzulegen, sofern nichts Abweichendes vereinbart ist.',
            'Ansprechpartnerin / Ansprechpartner bei Krankheit: '
                . ($p['employer_name'] !== '' ? $p['employer_name'] : 'Arbeitgeber')
                . ($p['employer_phone'] !== '' ? ' (Tel. ' . $p['employer_phone'] . ')' : '')
                . ($p['employer_email'] !== '' ? ', E-Mail ' . $p['employer_email'] : '') . '.',
            'Entgeltfortzahlung richtet sich nach dem Entgeltfortzahlungsgesetz (EFZG).',
        ];
    }

    /** @return list<string> */
    private static function privacyClauses(): array
    {
        return [
            'Personenbezogene Daten werden nur zur Durchführung des Arbeitsverhältnisses verarbeitet '
                . '(Art. 6 Abs. 1 lit. b DSGVO und einschlägige nationale Vorschriften).',
            'Geschäfts- und Kundengeheimnisse sowie interne Informationen sind vertraulich zu behandeln. '
                . 'Fotos, Videos und Social-Media-Veröffentlichungen mit Bezug zu Betrieb oder Kundschaft '
                . 'nur mit vorheriger Freigabe.',
        ];
    }

    /**
     * @param array<string, string> $p
     * @return list<string>
     */
    private static function minorClauses(array $p): array
    {
        return [
            'Beschäftigung nur im Rahmen des JArbSchG; verbotene Arbeiten und Alleinarbeit ohne Freigabe sind untersagt.',
            'Erforderlichenfalls ist die ärztliche Untersuchung nach JArbSchG nachzuweisen.',
            'Gesetzliche Vertretung: ' . ($p['legal_guardian_name'] !== '' ? $p['legal_guardian_name'] : '…')
                . ($p['legal_guardian_address'] !== '' ? ', ' . $p['legal_guardian_address'] : '') . '.',
            'Dieser Vertrag bedarf der Zustimmung der gesetzlichen Vertretung (siehe Unterschriften).',
        ];
    }

    /** @param array<string, string> $p */
    private static function signatures(array $p, bool $minor): string
    {
        $html = '<p><strong>Unterschriften</strong></p>';
        $html .= self::p('Ort, Datum: ______________________');
        $html .= self::p('Arbeitgeberin / Arbeitgeber: ______________________');
        $html .= self::p('Arbeitnehmerin / Arbeitnehmer: ______________________');
        if ($minor) {
            $html .= self::p(
                'Gesetzliche Vertretung ('
                . ($p['legal_guardian_name'] !== '' ? $p['legal_guardian_name'] : 'Name')
                . '): ______________________'
            );
        }

        return $html;
    }

    /** @param array<string, string> $p */
    private static function annexTraining(array $p, bool $withVideo): string
    {
        $html = '<p><strong>Anlage: Kurzschulung / Unterweisung</strong></p>';
        $html .= self::p(
            'Vor Aufnahme der Tätigkeit wird eine Kurzunterweisung durchgeführt. Inhalte mindestens:'
        );
        $items = [
            'Persönliche Hygiene (Arbeitskleidung, Hände, Arbeitsplatz)',
            'Händewaschen/-desinfektion vor Beginn, nach Toilette, Husten/Niesen, Abfall, Reinigung und Tätigkeitswechsel',
            'Wunden, Hautveränderungen und Krankheit unverzüglich melden; Kundeneinsatz nur nach Freigabe',
            'Arbeitsplatz- und Betriebshygiene (Flächen, Geräte, Wäsche, Abfall)',
            'Reinigung und Desinfektion nur nach Einweisung (Dosierung, Einwirkzeit, nicht mischen)',
            'Grenzen der Tätigkeit: nur die vereinbarte Hilfs-/Facharbeit; keine unerlaubten Behandlungen',
            'Kundenschutz: bei Infektionsrisiko, Blutkontakt, Belästigung oder Überforderung unterbrechen und melden',
            'Datenschutz und Social Media: keine unbefugte Weitergabe von Kundendaten, Fotos oder Videos',
        ];
        $html .= '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . self::e($item) . '</li>';
        }
        $html .= '</ul>';
        if ($withVideo && $p['training_video_url'] !== '') {
            $html .= self::p('Schulungsvideo / Online-Link: ' . $p['training_video_url']);
        }
        $html .= self::p('Unterweisung durchgeführt am: __________ durch: __________');
        $html .= self::p('Bestätigung Arbeitnehmerin / Arbeitnehmer: ______________________');
        if ($p['legal_guardian_name'] !== '') {
            $html .= self::p('Bestätigung gesetzliche Vertretung: ______________________');
        }

        return $html;
    }

    /** @param array<string, string> $p */
    private static function annexPension(array $p, bool $minor): string
    {
        $html = '<p><strong>Anlage: Rentenversicherungsbefreiung (Minijob)</strong></p>';
        $html .= self::p(
            'Antrag auf Befreiung von der Rentenversicherungspflicht nach SGB VI § 6 Abs. 1b '
            . 'für die Dauer der geringfügigen Beschäftigung. Die Befreiung gilt für die Dauer '
            . 'dieser Beschäftigung und kann nicht widerrufen werden.'
        );
        $html .= self::p('Arbeitnehmerin / Arbeitnehmer: ' . ($p['employee_name'] !== '' ? $p['employee_name'] : '…'));
        $html .= self::p('Eingangsstempel / Datum Arbeitgeber: __________');
        $html .= self::p('Unterschrift Arbeitnehmerin / Arbeitnehmer: ______________________');
        if ($minor) {
            $html .= self::p('Unterschrift gesetzliche Vertretung: ______________________');
        }

        return $html;
    }

    /** @param array<string, string> $p */
    private static function annexIfsg(array $p): string
    {
        $html = '<p><strong>Anlage: Infektionsschutz / Lebensmittelkontakt (nur bei tatsächlichem Kontakt)</strong></p>';
        $html .= self::p(
            'Soweit die Tätigkeit tatsächlichen Umgang mit Lebensmitteln umfasst, gelten IfSG §§ 42, 43: '
            . 'Erstbelehrung, Folgebelehrung mindestens alle zwei Jahre sowie jährliche Hygieneschulung. '
            . 'LMHV § 4 nur bei leicht verderblichen Lebensmitteln.'
        );
        $html .= self::p(
            'Ohne Lebensmittelkontakt entfällt diese Anlage inhaltlich; sie wird nur aufgenommen, '
            . 'wenn die entsprechende Pflicht im Mitarbeiterdatensatz gesetzt ist.'
        );
        $html .= self::p('Belehrung am: __________ Unterschrift: ______________________');

        return $html;
    }
}
