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
        $rawHours = (string) ($office['opening_hours'] ?? '');
        $channels = self::normalize_contact_channels(
            (string) ($office['email'] ?? ''),
            (string) ($office['website'] ?? '')
        );

        return array(
            'bufo_nr'       => (string) ($office['bufo_nr'] ?? ''),
            'name'          => (string) ($office['name'] ?? ''),
            'street'        => (string) ($office['street'] ?? ''),
            'postal_code'   => (string) ($office['postal_code'] ?? ''),
            'city'          => (string) ($office['city'] ?? ''),
            'phone'         => (string) ($office['phone'] ?? ''),
            'fax'           => (string) ($office['fax'] ?? ''),
            'email'         => $channels['email'],
            'website'       => $channels['website'],
            'opening_hours' => $rawHours,
            'opening_hours_lines' => FinanzamtOpeningHours::toLines($rawHours),
            'opening_hours_text' => FinanzamtOpeningHours::toPlainText($rawHours),
            'bank_iban'     => (string) ($office['bank_iban'] ?? ''),
            'bank_bic'      => (string) ($office['bank_bic'] ?? ''),
            'bank_name'     => (string) ($office['bank_name'] ?? ''),
            'creditor_id'   => '',
        );
    }

    /**
     * GemFA legt oft die Website im E-Mail-Feld ab — trennen.
     *
     * @return array{email: string, website: string}
     */
    public static function normalize_contact_channels(string $email, string $website = ''): array
    {
        $email = trim($email);
        $website = trim($website);

        if ($email !== '' && self::looks_like_url($email)) {
            if ($website === '') {
                $website = $email;
            }
            $email = '';
        }
        if ($website !== '' && !self::looks_like_url($website) && filter_var($website, FILTER_VALIDATE_EMAIL)) {
            if ($email === '') {
                $email = $website;
            }
            $website = '';
        }
        if ($website !== '' && !preg_match('#^https?://#i', $website) && self::looks_like_url($website)) {
            $website = 'https://' . preg_replace('#^//#', '', $website);
        }

        return [
            'email' => $email,
            'website' => $website,
        ];
    }

    private static function looks_like_url(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (str_contains($value, '@') && !preg_match('#^https?://#i', $value)) {
            return false;
        }

        return (bool) preg_match('#^(https?://|www\.)#i', $value)
            || (bool) preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $value);
    }
}

/**
 * Legacy wrapper
 *
 * @param string $bufo_nr
 * @return array<string, string>|null
 */

