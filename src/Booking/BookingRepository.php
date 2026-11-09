<?php
declare(strict_types=1);

/**
 * CRUD und Pagination für Kalender-Buchungen.
 */
final class BookingRepository
{
    private const PER_PAGE = 20;

    /** Erlaubte Listen-Perioden (GET period=…). */
    public const PERIOD_FILTERS = ['heute', 'woche', 'monat', 'zukunft', 'vergangen'];

    /**
     * Methode paginate.
     * @param string $search
     * @param int $page
     * @param string $periodFilter heute|woche|monat|zukunft|vergangen|''
     * @return array<string, mixed>
     */
    public static function paginate(string $search = '', int $page = 1, string $periodFilter = ''): array
    {
        $page = max(1, $page);
        $pdo = Database::pdo();
        [$where, $params] = self::buildListWhere($search, $periodFilter);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dg_bookings ' . $where);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $order = $periodFilter === 'zukunft' || $periodFilter === 'heute'
            ? 'ORDER BY slot_datetime ASC'
            : 'ORDER BY slot_datetime DESC';

        $sql = 'SELECT * FROM dg_bookings ' . $where . ' ' . $order . ' LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = self::map($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function buildListWhere(string $search, string $periodFilter): array
    {
        $parts = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $parts[] = '(customer_name LIKE :q0 OR customer_email LIKE :q1 OR customer_phone LIKE :q2 OR status LIKE :q3)';
            $params['q0'] = $like;
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $periodFilter = strtolower(trim($periodFilter));
        if (in_array($periodFilter, self::PERIOD_FILTERS, true)) {
            $tz = new DateTimeZone((string) App::config('timezone', 'Europe/Berlin'));
            $now = new DateTimeImmutable('now', $tz);
            $todayStart = $now->setTime(0, 0, 0);

            if ($periodFilter === 'heute') {
                $parts[] = 'slot_datetime >= :p_from AND slot_datetime < :p_to';
                $params['p_from'] = $todayStart->format('Y-m-d H:i:s');
                $params['p_to'] = $todayStart->modify('+1 day')->format('Y-m-d H:i:s');
            } elseif ($periodFilter === 'woche') {
                // ISO-Woche Mo–So (format N: 1=Mo … 7=So)
                $dow = (int) $todayStart->format('N');
                $weekStart = $todayStart->modify('-' . ($dow - 1) . ' days');
                $parts[] = 'slot_datetime >= :p_from AND slot_datetime < :p_to';
                $params['p_from'] = $weekStart->format('Y-m-d H:i:s');
                $params['p_to'] = $weekStart->modify('+7 days')->format('Y-m-d H:i:s');
            } elseif ($periodFilter === 'monat') {
                $monthStart = $todayStart->modify('first day of this month');
                $parts[] = 'slot_datetime >= :p_from AND slot_datetime < :p_to';
                $params['p_from'] = $monthStart->format('Y-m-d H:i:s');
                $params['p_to'] = $monthStart->modify('first day of next month')->format('Y-m-d H:i:s');
            } elseif ($periodFilter === 'zukunft') {
                $parts[] = 'slot_datetime >= :p_from';
                $params['p_from'] = $now->format('Y-m-d H:i:s');
            } elseif ($periodFilter === 'vergangen') {
                $parts[] = 'slot_datetime < :p_to';
                $params['p_to'] = $now->format('Y-m-d H:i:s');
            }
        }

        $where = $parts === [] ? '' : 'WHERE ' . implode(' AND ', $parts);

        return [$where, $params];
    }

    /**
     * Findet einen Datensatz anhand der ID.
     * @param int $id
     * @return Booking|null
     */
    public static function findById(int $id): ?Booking
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }
        self::backfillMissingCodes();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_bookings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::map($row) : null;
    }

    /**
     * Lookup by public booking code (e.g. DG-7K2M9P4Q).
     */
    public static function findByCode(string $code): ?Booking
    {
        $code = BookingCode::normalize($code);
        if ($code === '' || !Database::isConfigured()) {
            return null;
        }
        self::backfillMissingCodes();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_bookings WHERE booking_code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row ? self::map($row) : null;
    }

    /**
     * Assign codes to legacy rows without booking_code.
     */
    public static function backfillMissingCodes(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $pdo = Database::pdo();
            $rows = $pdo->query(
                "SELECT id FROM dg_bookings WHERE booking_code IS NULL OR booking_code = '' LIMIT 200"
            )->fetchAll(PDO::FETCH_COLUMN);
            if ($rows === []) {
                return;
            }
            $upd = $pdo->prepare('UPDATE dg_bookings SET booking_code = :code WHERE id = :id AND (booking_code IS NULL OR booking_code = \'\')');
            foreach ($rows as $id) {
                for ($attempt = 0; $attempt < 8; $attempt++) {
                    try {
                        $upd->execute(['code' => BookingCode::generate(), 'id' => (int) $id]);
                        break;
                    } catch (Throwable) {
                        // unique collision — retry
                    }
                }
            }
        } catch (Throwable) {
            // column may not exist until migration runs
        }
    }

    /**
     * Upcoming bookings for public form suggestions (e-mail + name must match).
     *
     * @return list<Booking>
     * @deprecated Prefer findByCode for public forms
     */
    public static function findUpcomingByCustomerIdentity(string $email, string $name, int $limit = 5): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || self::normalizePersonName($name) === '' || !Database::isConfigured()) {
            return [];
        }
        $limit = max(1, min(10, $limit));
        $driver = (string) Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeSql = $driver === 'sqlite' ? "datetime('now', 'localtime')" : 'NOW()';
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_bookings
             WHERE LOWER(customer_email) = :email
               AND status NOT IN (\'storniert\')
               AND slot_datetime >= ' . $timeSql . '
             ORDER BY slot_datetime ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['email' => $email]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $booking = self::map($row);
            if (!self::customerNamesMatch($booking->customerName, $name)) {
                continue;
            }
            $out[] = $booking;
        }

        return $out;
    }

    private static function customerNamesMatch(string $bookingName, string $inputName): bool
    {
        $a = self::normalizePersonName($bookingName);
        $b = self::normalizePersonName($inputName);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $bParts = preg_split('/\s+/u', $b) ?: [];
        if (count($bParts) !== 1) {
            return false;
        }
        $aParts = preg_split('/\s+/u', $a) ?: [];
        $last = (string) end($aParts);

        return $last !== '' && $last === $bParts[0];
    }

    private static function normalizePersonName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
        $name = (string) preg_replace('/[^a-z0-9\s\-]/u', '', $name);
        $name = (string) preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    /**
     * Upcoming / recent bookings for a customer e-mail (legacy helper).
     *
     * @return list<Booking>
     */
    public static function findByCustomerEmail(string $email, int $limit = 10): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !Database::isConfigured()) {
            return [];
        }
        $limit = max(1, min(20, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_bookings
             WHERE LOWER(customer_email) = :email
               AND status NOT IN (\'storniert\')
             ORDER BY slot_datetime DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['email' => $email]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[] = self::map($row);
        }

        return $out;
    }

    /**
     * Methode save.
     * @param array $data
     * @param int|null $id
     * @return int
     * @throws InvalidArgumentException
     */
    public static function save(array $data, ?int $id = null): int
    {
        $slotDatetime = trim((string) ($data['slot_datetime'] ?? ''));
        if ($slotDatetime === '') {
            $slotDate = trim((string) ($data['slot_date'] ?? ''));
            $slotTime = trim((string) ($data['slot_time'] ?? ''));
            if ($slotDate !== '' && $slotTime !== '') {
                $slotDatetime = $slotDate . ' ' . $slotTime;
            }
        }

        $fields = [
            'article_id' => max(0, (int) ($data['article_id'] ?? 0)),
            'employee_id' => max(0, (int) ($data['employee_id'] ?? 0)),
            'slot_datetime' => self::normalizeDatetime($slotDatetime),
            'customer_name' => trim((string) ($data['customer_name'] ?? '')),
            'customer_email' => EmailNormalizer::normalize((string) ($data['customer_email'] ?? '')),
            'customer_phone' => PhoneNormalizer::normalize((string) ($data['customer_phone'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'gebucht')) ?: 'gebucht',
            'admin_notes' => trim((string) ($data['admin_notes'] ?? '')),
        ];

        if ($fields['customer_name'] === '' || $fields['slot_datetime'] === '') {
            throw new InvalidArgumentException('Kundenname und Termin sind Pflichtfelder.');
        }
        if ($fields['article_id'] > 0 && CalendarArticleRepository::findById($fields['article_id']) === null) {
            throw new InvalidArgumentException('Die gewählte Leistung existiert nicht mehr.');
        }
        if ($fields['employee_id'] > 0 && CalendarStaffRepository::getEmployeeById($fields['employee_id']) === null) {
            throw new InvalidArgumentException('Der gewählte Mitarbeiter existiert nicht mehr.');
        }

        $fields['slot_datetime'] = BookingSlotService::normalizeSlotDatetime($fields['slot_datetime']);
        if ($fields['slot_datetime'] === '') {
            throw new InvalidArgumentException('Ungültiges Termin-Datum.');
        }

        $skipAvailability = in_array(strtolower($fields['status']), ['storniert', 'cancelled', 'canceled', 'cancel'], true);
        if (!$skipAvailability) {
            BookingSlotService::assertBookable(
                $fields['slot_datetime'],
                $fields['article_id'],
                $fields['employee_id'],
                $id
            );
        }

        $pdo = Database::pdo();

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE dg_bookings SET article_id=:article_id, employee_id=:employee_id, slot_datetime=:slot_datetime,
                 customer_name=:customer_name, customer_email=:customer_email, customer_phone=:customer_phone,
                 status=:status, admin_notes=:admin_notes WHERE id=:id'
            );
            $fields['id'] = $id;
            $stmt->execute($fields);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_bookings (booking_code, article_id, employee_id, slot_datetime, customer_name, customer_email, customer_phone, status, admin_notes)
             VALUES (:booking_code, :article_id, :employee_id, :slot_datetime, :customer_name, :customer_email, :customer_phone, :status, :admin_notes)'
        );
        $fields['booking_code'] = self::allocateUniqueCode();
        $stmt->execute($fields);

        return (int) $pdo->lastInsertId();
    }

    private static function allocateUniqueCode(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $code = BookingCode::generate();
            $stmt = Database::pdo()->prepare('SELECT 1 FROM dg_bookings WHERE booking_code = :c LIMIT 1');
            $stmt->execute(['c' => $code]);
            if (!$stmt->fetchColumn()) {
                return $code;
            }
        }

        return BookingCode::generate() . substr((string) time(), -2);
    }

    /**
     * Methode empty form.
     * @return array<string, mixed>
     */
    public static function emptyForm(): array
    {
        return [
            'slot_date' => '',
            'slot_time' => '',
            'slot_datetime' => '',
            'customer_name' => '', 'customer_email' => '',
            'customer_phone' => '', 'status' => 'gebucht', 'admin_notes' => '',
            'article_id' => '0', 'employee_id' => '0',
            'booking_code' => '',
        ];
    }

    /**
     * Methode to form.
     * @param Booking $b
     * @return array<string, mixed>
     */
    public static function toForm(Booking $b): array
    {
        $ts = strtotime($b->slotDatetime);
        $slotDate = '';
        $slotTime = '';
        if ($ts) {
            $slotDate = date('Y-m-d', $ts);
            $slotTime = date('H:i', $ts);
        }

        return [
            'slot_date' => $slotDate,
            'slot_time' => $slotTime,
            'slot_datetime' => $ts ? date('Y-m-d\TH:i', $ts) : '',
            'customer_name' => $b->customerName,
            'customer_email' => $b->customerEmail,
            'customer_phone' => $b->customerPhone,
            'status' => $b->status,
            'admin_notes' => $b->adminNotes,
            'article_id' => (string) $b->articleId,
            'employee_id' => (string) $b->employeeId,
            'booking_code' => $b->bookingCode,
        ];
    }

    /**
     * Führt aus: normalize datetime.
     * @param string $value
     * @return string
     */
    private static function normalizeDatetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : '';
    }

    /**
     * Methode map.
     * @param array $row
     * @return Booking
     */
    private static function map(array $row): Booking
    {
        return new Booking(
            (int) $row['id'],
            (int) ($row['article_id'] ?? 0),
            (int) ($row['employee_id'] ?? 0),
            (string) $row['slot_datetime'],
            (string) $row['customer_name'],
            (string) ($row['customer_email'] ?? ''),
            (string) ($row['customer_phone'] ?? ''),
            (string) ($row['status'] ?? 'gebucht'),
            (string) ($row['admin_notes'] ?? ''),
            (string) ($row['created_at'] ?? ''),
            (string) ($row['booking_code'] ?? ''),
        );
    }
}
