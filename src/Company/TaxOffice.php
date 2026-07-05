<?php
declare(strict_types=1);

/** Steuernummer parsen und Finanzamt ermitteln. */
final class TaxOffice {

    /**
     * @param string $input
     * @return string
     */
    public static function digits_only($input) {
        return preg_replace('/\D/', '', (string) $input);
    }

    /**
     * @param string $input
     * @return bool
     */
    public static function is_steuer_id($input) {
        return strlen(self::digits_only($input)) === 11;
    }

    /**
     * @param string $input
     * @return string|null
     */
    public static function to_elster_format($input) {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        $digits = self::digits_only($raw);

        if (strlen($digits) === 13 && $digits[4] === '0') {
            return $digits;
        }

        if (strlen($digits) === 12) {
            return substr($digits, 0, 4) . '0' . substr($digits, 4);
        }

        $parts = preg_split('#[/\s.-]+#', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) === 3) {
            return self::convert_local_parts_to_elster($parts[0], $parts[1], $parts[2]);
        }

        return null;
    }

    /**
     * @param string $f
     * @param string $b
     * @param string $uup
     * @return string|null
     */
    private static function convert_local_parts_to_elster($f, $b, $uup) {
        $f   = self::digits_only($f);
        $b   = self::digits_only($b);
        $uup = self::digits_only($uup);

        if (strlen($uup) < 5) {
            return null;
        }

        $p    = substr($uup, -1);
        $uuuu = substr($uup, 0, -1);

        if (strlen($f) === 3) {
            return '9' . str_pad($f, 3, '0', STR_PAD_LEFT) . '0' . str_pad($b, 3, '0', STR_PAD_LEFT) . str_pad($uuuu, 4, '0', STR_PAD_LEFT) . $p;
        }

        if (strlen($f) === 2) {
            $prefix = self::guess_state_prefix($f);
            return $prefix . str_pad($f, 2, '0', STR_PAD_LEFT) . '0' . str_pad($b, 3, '0', STR_PAD_LEFT) . str_pad($uuuu, 4, '0', STR_PAD_LEFT) . $p;
        }

        return null;
    }

    /**
     * @param string $f
     * @return string
     */
    private static function guess_state_prefix($f) {
        $f_int = (int) self::digits_only($f);
        if ($f_int >= 21 && $f_int <= 29) {
            return '21';
        }
        if ($f_int >= 11 && $f_int <= 19) {
            return '11';
        }
        return '28';
    }

    /**
     * @param string $input
     * @return string|null
     */
    public static function extract_bufo_number($input) {
        if (self::is_steuer_id($input)) {
            return null;
        }

        $elster = self::to_elster_format($input);
        if ($elster && strlen($elster) === 13) {
            return substr($elster, 0, 4);
        }

        return null;
    }

    /**
     * Finanzamt anhand Steuernummer
     *
     * @param string $tax_number
     * @param array  $options
     * @return array<string, mixed>
     */
    public static function resolve($tax_number, $options = array()) {
        if (trim($tax_number) === '') {
            return array(
                'found'  => false,
                'error'  => 'Keine Steuernummer angegeben.',
                'founder_guidance' => self::get_founder_guidance($options),
            );
        }

        if (self::is_steuer_id($tax_number)) {
            return array(
                'found'  => false,
                'error'  => 'Die 11-stellige Steuer-ID enthält keine Finanzamtsnummer. Bitte die ESt-Steuernummer (lokales oder ELSTER-Format) eingeben.',
                'founder_guidance' => self::get_founder_guidance($options),
            );
        }

        $elster  = self::to_elster_format($tax_number);
        $bufo_nr = self::extract_bufo_number($tax_number);

        if (!$bufo_nr) {
            return array(
                'found'  => false,
                'error'  => 'Steuernummer konnte nicht erkannt werden. Bitte Format z. B. 127/219/40770 oder 13-stelliges ELSTER-Format verwenden – oder Standortsuche nutzen.',
                'founder_guidance' => self::get_founder_guidance($options),
            );
        }

        $office = FinanzamtRegistry::get_by_bufo($bufo_nr);

        return self::build_result($bufo_nr, $elster, $office, $options);
    }

    /**
     * Finanzamt für Gründer ohne Steuernummer (PLZ / Ort)
     *
     * @param string $plz
     * @param string $city
     * @param array  $options
     * @return array<string, mixed>
     */
    public static function resolve_by_location($plz, $city = '', $options = array()) {
        $offices = FinanzamtRegistry::find_by_location($plz, $city);

        if (empty($offices)) {
            return array(
                'found'            => false,
                'error'            => 'Für diese PLZ/Ort wurde kein Finanzamt gefunden. Bitte PLZ prüfen oder Finanzamtsname suchen.',
                'founder_guidance' => self::get_founder_guidance($options),
            );
        }

        $primary = $offices[0];
        $result  = self::build_result($primary['bufo_nr'], null, $primary, $options);
        $result['location_search'] = true;
        $result['alternatives']    = count($offices) > 1 ? array_slice($offices, 1, 4) : array();

        return $result;
    }

    /**
     * Freitextsuche Finanzamt (Name / BuFa)
     *
     * @param string $query
     * @param array  $options
     * @return array<string, mixed>
     */
    public static function resolve_by_search($query, $options = array()) {
        $offices = FinanzamtRegistry::search($query);
        if (empty($offices)) {
            return array(
                'found'            => false,
                'error'            => 'Kein Finanzamt gefunden.',
                'founder_guidance' => self::get_founder_guidance($options),
            );
        }

        $primary = $offices[0];
        $result  = self::build_result($primary['bufo_nr'], null, $primary, $options);
        $result['search_query'] = $query;
        $result['alternatives'] = count($offices) > 1 ? array_slice($offices, 1, 9) : array();

        return $result;
    }

    /**
     * @param string $bufo_nr
     * @param string|null $elster
     * @param array|null $office
     * @param array $options
     * @return array<string, mixed>
     */
    private static function build_result($bufo_nr, $elster, $office, $options) {
        $result = array(
            'found'            => (bool) $office,
            'bufo_nr'          => $bufo_nr,
            'elster_number'    => $elster,
            'office'           => $office,
            'deadlines'        => self::get_vat_advance_deadlines($options),
            'founder_guidance' => self::get_founder_guidance($options),
            'registry_count'   => FinanzamtRegistry::count(),
        );

        if (!$office) {
            $result['error'] = sprintf(
                'BuFa-Nr. %s erkannt, aber kein Eintrag in der GemFA-Datenbank.',
                $bufo_nr
            );
            $result['office'] = array(
                'name'          => sprintf('Finanzamt (BuFa-Nr. %s)', $bufo_nr),
                'bufo_nr'       => $bufo_nr,
                'street'        => '',
                'postal_code'   => '',
                'city'          => '',
                'phone'         => '',
                'email'         => '',
                'opening_hours' => '',
            );
        }

        return $result;
    }

    /**
     * Hinweise für Gründer ohne Steuernummer
     *
     * @param array $options
     * @return array<string, mixed>
     */
    public static function get_founder_guidance($options = array()) {
        $company_type = $options['company_type'] ?? '';

        $steps = array(
            array(
                'title' => 'Gewerbe anmelden oder Freiberuf prüfen',
                'text'  => 'Gewerbetreibende melden das Gewerbe unverzüglich bei der Gemeinde (Gewerbeamt). Freiberufler (Katalogberufe, §18 EStG) benötigen in der Regel keine Gewerbeanmeldung.',
            ),
            array(
                'title' => 'Fragebogen zur steuerlichen Erfassung',
                'text'  => 'Das Finanzamt stellt den „Fragebogen zur steuerlichen Erfassung“ bereit – oft zusammen mit der Gewerbeanmeldung (E-One-Stop-Shop) oder separat über ELSTER / Finanzamt.',
            ),
            array(
                'title' => 'Frist: unverzüglich nach Tätigkeitsbeginn',
                'text'  => 'Der Fragebogen ist spätestens unverzüglich nach Aufnahme der Tätigkeit abzugeben (§138 AO). Verzögerungen können Bußgelder nach sich ziehen.',
            ),
            array(
                'title' => 'Steuer-ID und Steuernummer',
                'text'  => 'Die 11-stellige Steuer-ID erhalten Sie automatisch als Person. Die steuerliche Steuernummer fürs Unternehmen wird nach Bearbeitung des Fragebogens vom Finanzamt mitgeteilt.',
            ),
        );

        $links = array(
            array(
                'label' => 'ELSTER – Fragebogen zur steuerlichen Erfassung',
                'url'   => 'https://www.elster.de/eportal/start',
            ),
            array(
                'label' => 'BZSt Finanzamtsuche (GemFA)',
                'url'   => 'https://www.bzst.de/DE/Service/Behoerdenwegweiser/Finanzamtsuche/GemFa/finanzamtsuche_node.html',
            ),
        );

        if (in_array($company_type, array('freiberufler', ''), true)) {
            $links[] = array(
                'label' => 'Finanzamt.de – Informationen',
                'url'   => 'https://www.finanzamt.de',
            );
        }

        return array(
            'headline'    => 'Noch keine Steuernummer?',
            'summary'     => 'Als Gründer können Sie das zuständige Finanzamt über PLZ und Ort ermitteln. Die Steuernummer erhalten Sie nach Abgabe des Fragebogens zur steuerlichen Erfassung.',
            'steps'       => $steps,
            'links'       => $links,
            'deadlines'   => self::get_founder_deadlines($options),
        );
    }

    /**
     * @param array $options
     * @return array<string, mixed>
     */
    public static function get_founder_deadlines($options = array()) {
        $items = array(
            'Gewerbeanmeldung: unverzüglich vor Tätigkeitsbeginn (GewO)',
            'Fragebogen zur steuerlichen Erfassung: unverzüglich nach Beginn (§138 AO)',
            'USt-Vorauszahlung: nach Vergabe der Steuernummer gemäß Meldezeitraum (10. des Folgemonats/-quartals)',
            'Erste EÜR/Bilanz: nach Ablauf des Wirtschaftsjahres (Frist siehe Bescheid / Steuerberater)',
        );

        $vat = self::get_vat_advance_deadlines($options);
        if (!empty($vat['summary'])) {
            $items[] = $vat['summary'];
        }

        return array(
            'title' => 'Wichtige Fristen für Gründer',
            'items' => $items,
        );
    }

    /**
     * @param array $options
     * @return array<string, string|array>
     */
    public static function get_vat_advance_deadlines($options) {
        $period    = $options['reporting_period'] ?? 'quarterly';
        $extension = !empty($options['permanent_extension']);
        $year      = (int) gmdate('Y');

        $labels = array(
            'monthly'   => 'Monatlich: Vorauszahlung bis zum 10. des Folgemonats',
            'quarterly' => 'Vierteljährlich: Vorauszahlung bis zum 10. des Monats nach Quartalsende',
            'yearly'    => 'Jährlich: siehe jährliche Festsetzung / Anmeldung',
        );

        $base = $labels[$period] ?? $labels['quarterly'];
        if ($extension) {
            $base .= '. ' . 'Mit Dauerfristverlängerung: Frist um einen Monat verlängert (separater Antrag beim Finanzamt).';
        }

        $quarters = array();
        if ($period === 'quarterly') {
            $month_offset = $extension ? 1 : 0;
            foreach (array(1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4') as $q => $label) {
                $due_month = ($q * 3) + 1 + $month_offset;
                $due_year  = $year;
                if ($due_month > 12) {
                    $due_month -= 12;
                    $due_year++;
                }
                $quarters[] = sprintf('%s %d: %02d/%d', $label, $year, $due_month, $due_year);
            }
        }

        return array(
            'summary'  => $base,
            'year'     => (string) $year,
            'quarters' => $quarters,
        );
    }
}
