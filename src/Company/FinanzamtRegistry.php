<?php
declare(strict_types=1);

/**
 * Finanzamt Registry.
 */
final class FinanzamtRegistry {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function load() {
        if (null !== self::$data) {
            return self::$data;
        }

        $path = DG_ROOT . '/assets/data/finanzaemter-gemfa.json';
        if (!is_readable($path)) {
            self::$data = array('offices' => array(), 'plz_index' => array());
            return self::$data;
        }

        $raw = json_decode(file_get_contents($path), true);
        self::$data = is_array($raw) ? $raw : array('offices' => array(), 'plz_index' => array());

        return self::$data;
    }

    /**
     * @return int
     */
    public static function count() {
        $data = self::load();
        return count($data['offices'] ?? array());
    }

    /**
     * @param string $bufo_nr
     * @return array<string, mixed>|null
     */
    public static function get_by_bufo($bufo_nr) {
        $bufo_nr = preg_replace('/\D/', '', (string) $bufo_nr);
        if (strlen($bufo_nr) !== 4) {
            return null;
        }

        $data = self::load();
        $office = $data['offices'][$bufo_nr] ?? null;

        return is_array($office) ? self::normalize_office($office) : null;
    }

    /**
     * @param string $query Name, Ort oder BuFa-Nr.
     * @return array<int, array<string, mixed>>
     */
    public static function search($query) {
        $query = trim($query);
        if ($query === '') {
            return array();
        }

        $data = self::load();
        $results = array();
        $q_lower = mb_strtolower($query);
        $q_digits = preg_replace('/\D/', '', $query);

        if (strlen($q_digits) === 4) {
            $office = self::get_by_bufo($q_digits);
            if ($office) {
                return array($office);
            }
        }

        foreach ($data['offices'] ?? array() as $office) {
            $name = mb_strtolower($office['name'] ?? '');
            $city = mb_strtolower($office['city'] ?? '');
            $bufo = (string) ($office['bufo_nr'] ?? '');

            if (
                strpos($name, $q_lower) !== false ||
                strpos($city, $q_lower) !== false ||
                ($q_digits !== '' && strpos($bufo, $q_digits) !== false)
            ) {
                $results[] = self::normalize_office($office);
            }
            if (count($results) >= 10) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param string $plz
     * @param string $city
     * @return array<int, array<string, mixed>>
     */
    public static function find_by_location($plz, $city = '') {
        $plz = preg_replace('/\D/', '', (string) $plz);
        if (strlen($plz) < 5) {
            return array();
        }

        $plz = substr($plz, 0, 5);
        $data = self::load();
        $bufo_list = $data['plz_index'][$plz] ?? array();
        $offices = array();

        foreach ($bufo_list as $bufo) {
            $office = self::get_by_bufo($bufo);
            if (!$office) {
                continue;
            }
            if ($city !== '') {
                $city_lower = mb_strtolower($city);
                if (
                    strpos(mb_strtolower($office['city'] ?? ''), $city_lower) === false &&
                    strpos(mb_strtolower($office['name'] ?? ''), $city_lower) === false
                ) {
                    continue;
                }
            }
            $offices[] = $office;
        }

        if (empty($offices) && $city !== '') {
            return self::search($city);
        }

        return $offices;
    }

    /**
     * @param array<string, mixed> $office
     * @return array<string, mixed>
     */
    private static function normalize_office($office) {
        return array(
            'bufo_nr'       => (string) ($office['bufo_nr'] ?? ''),
            'name'          => (string) ($office['name'] ?? ''),
            'street'        => (string) ($office['street'] ?? ''),
            'postal_code'   => (string) ($office['postal_code'] ?? ''),
            'city'          => (string) ($office['city'] ?? ''),
            'phone'         => (string) ($office['phone'] ?? ''),
            'fax'           => (string) ($office['fax'] ?? ''),
            'email'         => (string) ($office['email'] ?? ''),
            'website'       => (string) ($office['website'] ?? ''),
            'opening_hours' => (string) ($office['opening_hours'] ?? ''),
            'bank_iban'     => (string) ($office['bank_iban'] ?? ''),
            'bank_bic'      => (string) ($office['bank_bic'] ?? ''),
            'bank_name'     => (string) ($office['bank_name'] ?? ''),
            'creditor_id'   => '',
        );
    }
}

/**
 * Legacy wrapper
 *
 * @param string $bufo_nr
 * @return array<string, string>|null
 */

