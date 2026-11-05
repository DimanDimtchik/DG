<?php
declare(strict_types=1);

/**
 * Platzhalter-Ersetzung und Baustein-Filter für Vertragsvorlagen.
 */
final class ContractTemplateRenderer
{
    /**
     * @param list<array{block_key?: string, body_html?: string, is_annex?: int|bool, enabled?: int|bool}> $blocks
     * @param array<string, string> $placeholders
     * @param array<string, mixed> $opts contract_type, is_minor, annex_*
     * @param array<string, string> $extraBag zusätzliche Platzhalter (z. B. Abmahnung facts_html)
     * @param list<string>|null $missingKeys unbekannte Schlüssel (Ausgabe)
     */
    public static function render(
        array $blocks,
        array $placeholders,
        array $opts,
        array $extraBag = [],
        ?array &$missingKeys = null
    ): string {
        $type = (string) ($opts['contract_type'] ?? EmploymentContractContext::TYPE_FULLTIME);
        $bag = self::expandPlaceholders($placeholders, $type, $opts);
        foreach ($extraBag as $k => $v) {
            $bag[(string) $k] = (string) $v;
        }
        $foundMissing = [];
        $parts = [];
        foreach ($blocks as $block) {
            if (!self::includeBlock($block, $opts, $type)) {
                continue;
            }
            $html = self::replacePlaceholders((string) ($block['body_html'] ?? ''), $bag, $foundMissing);
            $html = self::stripEmptyParagraphs($html);
            if (trim(strip_tags($html)) === '') {
                continue;
            }
            $parts[] = $html;
        }
        if ($missingKeys !== null) {
            $missingKeys = array_values(array_unique($foundMissing));
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $opts
     */
    public static function includeBlock(array $block, array $opts, string $type): bool
    {
        if (isset($block['enabled']) && !(int) $block['enabled']) {
            return false;
        }
        $key = (string) ($block['block_key'] ?? '');
        if ($key === 's8_minor' && empty($opts['is_minor'])) {
            return false;
        }
        if ($key === 'annex_training' && empty($opts['annex_training'])) {
            return false;
        }
        if ($key === 'annex_pension') {
            if ($type !== EmploymentContractContext::TYPE_MINIJOB || empty($opts['annex_pension'])) {
                return false;
            }
        }
        if ($key === 'annex_ifsg' && empty($opts['annex_ifsg'])) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, string> $p
     * @param array<string, mixed> $opts
     * @return array<string, string>
     */
    public static function expandPlaceholders(array $p, string $type, array $opts): array
    {
        $minor = !empty($opts['is_minor']);
        $e = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $employerAddressHtml = implode('<br>', array_map(
            $e,
            EmploymentContractContext::addressLines((string) ($p['employer_address'] ?? ''))
        ));
        $employeeAddressHtml = implode('<br>', array_map(
            $e,
            EmploymentContractContext::addressLines((string) ($p['employee_address'] ?? ''))
        ));

        $birth = trim((string) ($p['employee_birth_date'] ?? ''));
        $activity = trim((string) ($p['job_type'] ?? ''));
        if ($activity === '') {
            $activity = trim((string) ($p['employment_relationship'] ?? ''));
        }

        $bag = [
            'disclaimer' => $e(
                'Dieser Entwurf dient der Nachweisführung wesentlicher Vertragsbedingungen '
                . '(Nachweisgesetz). Vor Unterzeichnung rechtlich prüfen. Kein Ersatz für anwaltliche Beratung.'
            ),
            'employer_name' => $e((string) ($p['employer_name'] ?? '')),
            'employer_address_html' => $employerAddressHtml,
            'employee_name' => $e((string) ($p['employee_name'] ?? '')),
            'employee_birth_line' => $birth !== '' ? '<br>geb. am ' . $e($birth) : '',
            'employee_address_html' => $employeeAddressHtml,
            'clause_contract_start' => $e(
                'Das Arbeitsverhältnis beginnt am '
                . (trim((string) ($p['contract_start'] ?? '')) !== '' ? (string) $p['contract_start'] : '…') . '.'
            ),
            'clause_duration' => $e(
                trim((string) ($p['contract_end'] ?? '')) !== ''
                    ? 'Es ist befristet bis zum ' . (string) $p['contract_end'] . '.'
                    : 'Das Arbeitsverhältnis wird auf unbestimmte Zeit geschlossen.'
            ),
            'clause_probation' => $e(
                trim((string) ($p['probation_period'] ?? '')) !== ''
                    ? 'Probezeit: ' . (string) $p['probation_period'] . '.'
                    : 'Soweit vereinbart, beträgt die Probezeit höchstens sechs Monate.'
            ),
            'clause_contract_type' => $e(
                'Vertragsart: '
                . (trim((string) ($p['contract_type_label'] ?? '')) !== ''
                    ? (string) $p['contract_type_label']
                    : $type) . '.'
            ),
            'clause_minijob_sgb' => $e(
                'Es handelt sich um eine geringfügige Beschäftigung im Sinne von SGB IV § 8. '
                . 'Die monatliche Entgeltgrenze beträgt ab ' . (string) ($p['rates_from'] ?? '')
                . ' im Jahresdurchschnitt ' . (string) ($p['geringfuegig_month'] ?? '')
                . ' (sofern amtlich bekannt).'
            ),
            'clause_activity' => $e(
                'Die Arbeitnehmerin / der Arbeitnehmer wird beschäftigt als / mit folgender Tätigkeit: '
                . $activity . '.'
            ),
            'clause_work_location' => $e(
                'Arbeitsort: '
                . (trim((string) ($p['work_location'] ?? '')) !== '' ? (string) $p['work_location'] : '…') . '.'
            ),
            'clause_employer_site' => trim((string) ($p['employer_site'] ?? '')) !== ''
                ? $e('Betriebliche Stelle: ' . (string) $p['employer_site'] . '.')
                : '',
            'clause_directives' => $e(
                'Der Arbeitgeber kann im Rahmen des Direktionsrechts gleichwertige Tätigkeiten zuweisen, '
                . 'soweit zumutbar und nicht mit einer wesentlichen Verschlechterung verbunden.'
            ),
            'clause_working_hours' => $e(
                'Die regelmäßige Arbeitszeit beträgt: '
                . (trim((string) ($p['working_hours'] ?? '')) !== '' ? (string) $p['working_hours'] : '…') . '.'
            ),
            'clause_daily_minutes' => '',
            'clause_minijob_hours' => $e(
                'Die Arbeitszeit ist so zu bemessen, dass bei dem vereinbarten Stundenlohn die '
                . 'Geringfügigkeitsgrenze im Jahresdurchschnitt nicht überschritten wird.'
            ),
            'clause_hours_law' => $e(
                $minor
                    ? 'Für Minderjährige gelten die Höchstarbeitszeiten, Ruhepausen und Beschäftigungsverbote '
                    . 'des Jugendarbeitsschutzgesetzes (JArbSchG). Sonn- und Feiertagsarbeit nur in den gesetzlich '
                    . 'zulässigen Ausnahmen.'
                    : 'Es gelten die Vorgaben des Arbeitszeitgesetzes (ArbZG) zu Höchstarbeitszeit, Ruhepausen '
                    . 'und Ruhezeit.'
            ),
            'clause_salary' => $e(
                'Die Vergütung beträgt: '
                . (trim((string) ($p['salary'] ?? '')) !== '' ? (string) $p['salary'] : '…') . '.'
            ),
            'clause_min_wage' => $e(
                'Es gilt mindestens der gesetzliche Mindestlohn (' . (string) ($p['min_wage'] ?? '')
                . ', Stand ab ' . (string) ($p['rates_from'] ?? '')
                . ', soweit amtlich bekannt). Unbezahlte Probearbeit ist unzulässig.'
            ),
            'clause_pay_due' => $e(
                'Die Vergütung ist jeweils zum vereinbarten Fälligkeitstermin, spätestens zum Ende des Folgemonats, '
                . 'auf das angegebene Konto zu zahlen.'
            ),
            'clause_minijob_pay_hint' => $e(
                'Hinweise zur Sozialversicherung (Minijob) und ggf. Rentenversicherungsbefreiung siehe Anlage.'
            ),
            'clause_vacation' => $e(
                'Der jährliche Erholungsurlaub beträgt: '
                . (trim((string) ($p['vacation_days'] ?? '')) !== '' ? (string) $p['vacation_days'] : '…') . '.'
            ),
            'clause_vacation_law' => $e(
                $minor
                    ? 'Für Minderjährige gelten die erhöhten Urlaubsansprüche nach JArbSchG § 19 in Abhängigkeit vom Alter.'
                    : 'Es gelten mindestens die Vorschriften des Bundesurlaubsgesetzes (BUrlG).'
            ),
            'clause_sickness_notify' => $e(
                'Arbeitsunfähigkeit ist unverzüglich mitzuteilen. Spätestens ab dem dritten Kalendertag der '
                . 'Arbeitsunfähigkeit ist eine ärztliche Bescheinigung vorzulegen, sofern nichts Abweichendes vereinbart ist.'
            ),
            'clause_sickness_contact' => $e(
                'Ansprechpartnerin / Ansprechpartner bei Krankheit: '
                . (trim((string) ($p['employer_name'] ?? '')) !== '' ? (string) $p['employer_name'] : 'Arbeitgeber')
                . (trim((string) ($p['employer_phone'] ?? '')) !== '' ? ' (Tel. ' . (string) $p['employer_phone'] . ')' : '')
                . (trim((string) ($p['employer_email'] ?? '')) !== '' ? ', E-Mail ' . (string) $p['employer_email'] : '')
                . '.'
            ),
            'clause_sickness_efzg' => $e('Entgeltfortzahlung richtet sich nach dem Entgeltfortzahlungsgesetz (EFZG).'),
            'clause_privacy_dsgvo' => $e(
                'Personenbezogene Daten werden nur zur Durchführung des Arbeitsverhältnisses verarbeitet '
                . '(Art. 6 Abs. 1 lit. b DSGVO und einschlägige nationale Vorschriften).'
            ),
            'clause_privacy_confidential' => $e(
                'Geschäfts- und Kundengeheimnisse sowie interne Informationen sind vertraulich zu behandeln. '
                . 'Fotos, Videos und Social-Media-Veröffentlichungen mit Bezug zu Betrieb oder Kundschaft '
                . 'nur mit vorheriger Freigabe.'
            ),
            'clause_minor_jarbschg' => $e(
                'Beschäftigung nur im Rahmen des JArbSchG; verbotene Arbeiten und Alleinarbeit ohne Freigabe sind untersagt.'
            ),
            'clause_minor_medical' => $e(
                'Erforderlichenfalls ist die ärztliche Untersuchung nach JArbSchG nachzuweisen.'
            ),
            'clause_minor_guardian' => $e(
                'Gesetzliche Vertretung: '
                . (trim((string) ($p['legal_guardian_name'] ?? '')) !== '' ? (string) $p['legal_guardian_name'] : '…')
                . (trim((string) ($p['legal_guardian_address'] ?? '')) !== ''
                    ? ', ' . (string) $p['legal_guardian_address']
                    : '') . '.'
            ),
            'clause_minor_consent' => $e(
                'Dieser Vertrag bedarf der Zustimmung der gesetzlichen Vertretung (siehe Unterschriften).'
            ),
            'clause_sig_place' => $e('Ort, Datum: ______________________'),
            'clause_sig_employer' => $e('Arbeitgeberin / Arbeitgeber: ______________________'),
            'clause_sig_employee' => $e('Arbeitnehmerin / Arbeitnehmer: ______________________'),
            'clause_sig_guardian' => $minor
                ? $e(
                    'Gesetzliche Vertretung ('
                    . (trim((string) ($p['legal_guardian_name'] ?? '')) !== ''
                        ? (string) $p['legal_guardian_name']
                        : 'Name')
                    . '): ______________________'
                )
                : '',
            'clause_annex_training_intro' => $e(
                'Vor Aufnahme der Tätigkeit wird eine Kurzunterweisung durchgeführt. Inhalte mindestens:'
            ),
            'clause_annex_training_list_html' => self::trainingListHtml($e),
            'clause_annex_training_video' => '',
            'clause_annex_training_confirm' => $e('Unterweisung durchgeführt am: __________ durch: __________'),
            'clause_annex_training_employee' => $e(
                'Bestätigung Arbeitnehmerin / Arbeitnehmer: ______________________'
            ),
            'clause_annex_training_guardian' => trim((string) ($p['legal_guardian_name'] ?? '')) !== ''
                ? $e('Bestätigung gesetzliche Vertretung: ______________________')
                : '',
            'clause_annex_pension_text' => $e(
                'Antrag auf Befreiung von der Rentenversicherungspflicht nach SGB VI § 6 Abs. 1b '
                . 'für die Dauer der geringfügigen Beschäftigung. Die Befreiung gilt für die Dauer '
                . 'dieser Beschäftigung und kann nicht widerrufen werden.'
            ),
            'clause_annex_pension_employee' => $e(
                'Arbeitnehmerin / Arbeitnehmer: '
                . (trim((string) ($p['employee_name'] ?? '')) !== '' ? (string) $p['employee_name'] : '…')
            ),
            'clause_annex_pension_stamp' => $e('Eingangsstempel / Datum Arbeitgeber: __________'),
            'clause_annex_pension_sig' => $e('Unterschrift Arbeitnehmerin / Arbeitnehmer: ______________________'),
            'clause_annex_pension_guardian' => $minor
                ? $e('Unterschrift gesetzliche Vertretung: ______________________')
                : '',
            'clause_annex_ifsg_text' => $e(
                'Soweit die Tätigkeit tatsächlichen Umgang mit Lebensmitteln umfasst, gelten IfSG §§ 42, 43: '
                . 'Erstbelehrung, Folgebelehrung mindestens alle zwei Jahre sowie jährliche Hygieneschulung. '
                . 'LMHV § 4 nur bei leicht verderblichen Lebensmitteln.'
            ),
            'clause_annex_ifsg_note' => $e(
                'Ohne Lebensmittelkontakt entfällt diese Anlage inhaltlich; sie wird nur aufgenommen, '
                . 'wenn die entsprechende Pflicht im Mitarbeiterdatensatz gesetzt ist.'
            ),
            'clause_annex_ifsg_confirm' => $e('Belehrung am: __________ Unterschrift: ______________________'),
        ];

        $mins = (int) ($p['daily_work_minutes'] ?? 0);
        if ($mins > 0) {
            $bag['clause_daily_minutes'] = $e(
                'Soll-Arbeitszeit pro Tag: ' . $mins . ' Minuten ('
                . number_format($mins / 60, 2, ',', '') . ' Stunden).'
            );
        }

        if (!empty($opts['annex_video']) && trim((string) ($p['training_video_url'] ?? '')) !== '') {
            $bag['clause_annex_training_video'] = $e(
                'Schulungsvideo / Online-Link: ' . (string) $p['training_video_url']
            );
        }

        return $bag;
    }

    /** @param callable(string):string $e */
    private static function trainingListHtml(callable $e): string
    {
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
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $e($item) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @param array<string, string> $bag
     * @param list<string> $missingKeys
     */
    private static function replacePlaceholders(string $html, array $bag, array &$missingKeys = []): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function (array $m) use ($bag, &$missingKeys): string {
                $key = $m[1];
                if (!array_key_exists($key, $bag)) {
                    $missingKeys[] = $key;

                    return '';
                }

                return $bag[$key];
            },
            $html
        );
    }

    private static function stripEmptyParagraphs(string $html): string
    {
        $html = (string) preg_replace('/<p>\s*<\/p>/i', '', $html);

        return trim($html);
    }
}
