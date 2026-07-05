<?php
declare(strict_types=1);

final class BookingRepository
{
    private const PER_PAGE = 20;

    /**
     * @return array{items: list<Booking>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginate(string $search = '', int $page = 1): array
    {
        $page = max(1, $page);
        $pdo = Database::pdo();
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = 'WHERE customer_name LIKE :q OR customer_email LIKE :q OR customer_phone LIKE :q OR status LIKE :q';
            $params['q'] = '%' . $search . '%';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dg_bookings ' . $where);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = 'SELECT * FROM dg_bookings ' . $where . ' ORDER BY slot_datetime DESC LIMIT :limit OFFSET :offset';
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

    public static function findById(int $id): ?Booking
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_bookings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::map($row) : null;
    }

    /** @param array<string, mixed> $data */
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
            'customer_email' => trim((string) ($data['customer_email'] ?? '')),
            'customer_phone' => trim((string) ($data['customer_phone'] ?? '')),
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
            'INSERT INTO dg_bookings (article_id, employee_id, slot_datetime, customer_name, customer_email, customer_phone, status, admin_notes)
             VALUES (:article_id, :employee_id, :slot_datetime, :customer_name, :customer_email, :customer_phone, :status, :admin_notes)'
        );
        $stmt->execute($fields);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, string> */
    public static function emptyForm(): array
    {
        return [
            'slot_date' => '',
            'slot_time' => '',
            'slot_datetime' => '',
            'customer_name' => '', 'customer_email' => '',
            'customer_phone' => '', 'status' => 'gebucht', 'admin_notes' => '',
            'article_id' => '0', 'employee_id' => '0',
        ];
    }

    /** @return array<string, string> */
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
        ];
    }

    private static function normalizeDatetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : '';
    }

    /** @param array<string, mixed> $row */
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
        );
    }
}
