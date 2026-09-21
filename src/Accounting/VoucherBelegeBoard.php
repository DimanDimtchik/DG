<?php
declare(strict_types=1);

/**
 * Accordion-Board für die Belegliste (Verkaufskette + Handlungsbedarf).
 */
final class VoucherBelegeBoard
{
    public const SECTION_ACTION = 'action';
    public const SECTION_OFFERS = 'offers';
    public const SECTION_ORDER_CONFIRMATIONS = 'order_confirmations';
    public const SECTION_DELIVERY_NOTES = 'delivery_notes';
    public const SECTION_INVOICES = 'invoices';
    public const SECTION_CREDITS = 'credits';
    public const SECTION_INCOMING = 'incoming';

    /**
     * @return array<string, array{label: string, meta: string}>
     */
    public static function sectionDefinitions(): array
    {
        return [
            self::SECTION_ACTION => [
                'label' => 'Handlungsbedarf',
                'meta' => 'Entwürfe, abgelaufene Angebote, angenommene ohne AB, überfällige Rechnungen',
            ],
            self::SECTION_OFFERS => [
                'label' => 'Angebote',
                'meta' => 'Verkaufskette — Ausgangspunkt',
            ],
            self::SECTION_ORDER_CONFIRMATIONS => [
                'label' => 'Auftragsbestätigungen',
                'meta' => 'Nach Angebotsannahme',
            ],
            self::SECTION_DELIVERY_NOTES => [
                'label' => 'Lieferscheine',
                'meta' => 'Warenausgang / Leistung',
            ],
            self::SECTION_INVOICES => [
                'label' => 'Rechnungen',
                'meta' => 'Abschlag, Rechnung, Schlussrechnung',
            ],
            self::SECTION_CREDITS => [
                'label' => 'Gutschriften',
                'meta' => 'Kundengutschriften',
            ],
            self::SECTION_INCOMING => [
                'label' => 'Eingangsbelege',
                'meta' => 'Ausgaben und Ausgabenminderungen',
            ],
        ];
    }

    public static function sanitizeSection(string $section): string
    {
        $section = strtolower(trim($section));

        return isset(self::sectionDefinitions()[$section]) ? $section : self::SECTION_ACTION;
    }

    /**
     * @param array{
     *   date_from?: string,
     *   date_to?: string,
     *   year?: int,
     *   search?: string,
     *   contact_id?: int,
     *   amount_min?: float|string|null,
     *   amount_max?: float|string|null,
     *   section?: string,
     *   status?: string,
     *   invoice_kind?: string,
     *   pay?: string,
     *   page?: int,
     *   actionable_only?: bool|string|int
     * } $input
     * @return array{
     *   section: string,
     *   status: string,
     *   invoice_kind: string,
     *   pay: string,
     *   actionable_only: bool,
     *   contact_id: int,
     *   contact_label: string,
     *   amount_min: string,
     *   amount_max: string,
     *   search: string,
     *   sections: list<array<string, mixed>>,
     *   chips: list<array<string, mixed>>,
     *   invoice_kind_chips: list<array<string, mixed>>,
     *   pay_chips: list<array<string, mixed>>,
     *   list: array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, gross_sum: float},
     *   contacts: list<array{id: int, label: string}>,
     *   action_total: int
     * }
     */
    public static function build(array $input): array
    {
        $baseFilters = self::baseFiltersFromInput($input);
        $section = self::sanitizeSection((string) ($input['section'] ?? self::SECTION_ACTION));
        $actionableOnly = self::truthy($input['actionable_only'] ?? false);
        if ($actionableOnly) {
            $section = self::SECTION_ACTION;
        }

        $status = trim((string) ($input['status'] ?? ''));
        $invoiceKind = VoucherDocumentKind::sanitize((string) ($input['invoice_kind'] ?? ''));
        if (!in_array($invoiceKind, [
            VoucherDocumentKind::PARTIAL_INVOICE,
            VoucherDocumentKind::INVOICE,
            VoucherDocumentKind::FINAL_INVOICE,
        ], true)) {
            $invoiceKind = '';
        }
        $pay = strtolower(trim((string) ($input['pay'] ?? '')));
        if (!in_array($pay, ['open', 'partial', 'paid', ''], true)) {
            $pay = '';
        }

        $contactId = max(0, (int) ($input['contact_id'] ?? 0));
        $contactLabel = '';
        if ($contactId > 0) {
            $contact = ContactRepository::findById($contactId);
            if ($contact !== null) {
                $contactLabel = $contact->listLabel();
                if ($contactLabel === '') {
                    $contactLabel = 'Kontakt #' . $contactId;
                }
            } else {
                $contactId = 0;
            }
        }

        $counts = self::aggregateCounts($baseFilters);
        $actionCounts = self::actionBucketCounts($baseFilters);
        $actionAll = VoucherRepository::list(array_merge($baseFilters, [
            'action_bucket' => 'all_action',
            'page' => 1,
            'per_page' => 1,
        ]));
        $actionTotal = (int) ($actionAll['total'] ?? 0);
        $actionGross = (float) ($actionAll['gross_sum'] ?? 0);

        $sections = [];
        foreach (self::sectionDefinitions() as $id => $def) {
            $count = match ($id) {
                self::SECTION_ACTION => $actionTotal,
                self::SECTION_OFFERS => (int) ($counts['by_kind'][VoucherDocumentKind::OFFER]['total'] ?? 0),
                self::SECTION_ORDER_CONFIRMATIONS => (int) ($counts['by_kind'][VoucherDocumentKind::ORDER_CONFIRMATION]['total'] ?? 0),
                self::SECTION_DELIVERY_NOTES => (int) ($counts['by_kind'][VoucherDocumentKind::DELIVERY_NOTE]['total'] ?? 0),
                self::SECTION_INVOICES => (int) ($counts['invoices']['total'] ?? 0),
                self::SECTION_CREDITS => (int) ($counts['by_type']['credit']['total'] ?? 0),
                self::SECTION_INCOMING => (int) (($counts['by_type']['expense']['total'] ?? 0)
                    + ($counts['by_type']['expense_reduction']['total'] ?? 0)),
                default => 0,
            };
            $gross = match ($id) {
                self::SECTION_ACTION => $actionGross,
                self::SECTION_OFFERS => (float) ($counts['by_kind'][VoucherDocumentKind::OFFER]['gross'] ?? 0),
                self::SECTION_ORDER_CONFIRMATIONS => (float) ($counts['by_kind'][VoucherDocumentKind::ORDER_CONFIRMATION]['gross'] ?? 0),
                self::SECTION_DELIVERY_NOTES => (float) ($counts['by_kind'][VoucherDocumentKind::DELIVERY_NOTE]['gross'] ?? 0),
                self::SECTION_INVOICES => (float) ($counts['invoices']['gross'] ?? 0),
                self::SECTION_CREDITS => (float) ($counts['by_type']['credit']['gross'] ?? 0),
                self::SECTION_INCOMING => (float) (($counts['by_type']['expense']['gross'] ?? 0)
                    + ($counts['by_type']['expense_reduction']['gross'] ?? 0)),
                default => 0.0,
            };
            $sections[] = [
                'id' => $id,
                'label' => $def['label'],
                'meta' => $def['meta'],
                'count' => $count,
                'gross_sum' => $gross,
                'is_open' => $id === $section,
                'url' => self::url($baseFilters, [
                    'section' => $id,
                    'status' => '',
                    'invoice_kind' => '',
                    'pay' => '',
                    'page' => 1,
                    'actionable_only' => $actionableOnly && $id === self::SECTION_ACTION ? '1' : '',
                ]),
            ];
        }

        $chips = self::chipsForSection(
            $section,
            $counts,
            $actionCounts,
            $actionTotal,
            $baseFilters,
            $status,
            $invoiceKind,
            $pay,
            $actionableOnly
        );
        $status = self::normalizeStatusForSection($section, $status, $chips);

        $invoiceKindChips = [];
        $payChips = [];
        if ($section === self::SECTION_INVOICES) {
            $invoiceKindChips = self::invoiceKindChips($counts, $baseFilters, $status, $invoiceKind, $pay, $actionableOnly);
            $payChips = self::payChips($counts, $baseFilters, $status, $invoiceKind, $pay, $actionableOnly);
        }

        $listFilters = array_merge($baseFilters, self::listFiltersForSection(
            $section,
            $status,
            $invoiceKind,
            $pay
        ));
        $listFilters['page'] = max(1, (int) ($input['page'] ?? 1));
        $list = VoucherRepository::list($listFilters);

        $amountMin = isset($input['amount_min']) && $input['amount_min'] !== '' && $input['amount_min'] !== null
            ? (string) $input['amount_min'] : '';
        $amountMax = isset($input['amount_max']) && $input['amount_max'] !== '' && $input['amount_max'] !== null
            ? (string) $input['amount_max'] : '';

        return [
            'section' => $section,
            'status' => $status,
            'invoice_kind' => $invoiceKind,
            'pay' => $pay,
            'actionable_only' => $actionableOnly,
            'contact_id' => $contactId,
            'contact_label' => $contactLabel,
            'amount_min' => $amountMin,
            'amount_max' => $amountMax,
            'search' => trim((string) ($input['search'] ?? '')),
            'sections' => $sections,
            'chips' => $chips,
            'invoice_kind_chips' => $invoiceKindChips,
            'pay_chips' => $payChips,
            'list' => $list,
            'contacts' => self::contactOptions($baseFilters),
            'action_total' => $actionTotal,
        ];
    }

    /**
     * @param array<string, mixed> $baseFilters
     * @param array<string, mixed> $overrides
     */
    public static function url(array $baseFilters, array $overrides = []): string
    {
        $params = [
            'page' => 'buchhaltung-belege',
            'year' => (int) ($baseFilters['_year'] ?? date('Y')),
        ];
        if (!empty($baseFilters['_month'])) {
            $params['month'] = (int) $baseFilters['_month'];
        }
        if (!empty($baseFilters['_date_from_raw']) && !empty($baseFilters['_date_to_raw'])) {
            $params['date_from'] = (string) $baseFilters['_date_from_raw'];
            $params['date_to'] = (string) $baseFilters['_date_to_raw'];
        }
        if (($baseFilters['search'] ?? '') !== '') {
            $params['s'] = (string) $baseFilters['search'];
        }
        if ((int) ($baseFilters['contact_id'] ?? 0) > 0) {
            $params['contact_id'] = (int) $baseFilters['contact_id'];
        }
        if (isset($baseFilters['amount_min']) && $baseFilters['amount_min'] !== '' && $baseFilters['amount_min'] !== null) {
            $params['amount_min'] = (string) $baseFilters['amount_min'];
        }
        if (isset($baseFilters['amount_max']) && $baseFilters['amount_max'] !== '' && $baseFilters['amount_max'] !== null) {
            $params['amount_max'] = (string) $baseFilters['amount_max'];
        }

        foreach ($overrides as $key => $value) {
            if ($value === '' || $value === null || $value === false) {
                unset($params[$key]);
                continue;
            }
            if ($key === 'page' && (int) $value === 1) {
                unset($params['paged']);
                continue;
            }
            if ($key === 'page' && (int) $value > 1) {
                $params['paged'] = (int) $value;
                continue;
            }
            $params[$key] = $value;
        }

        $parts = [];
        foreach ($params as $key => $value) {
            if ($key === 'page' && $value === 'buchhaltung-belege') {
                $parts[] = 'page=buchhaltung-belege';
                continue;
            }
            if ($key === 'page') {
                continue;
            }
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return '/app?' . implode('&', $parts);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function baseFiltersFromInput(array $input): array
    {
        $filters = [
            'date_from' => (string) ($input['date_from'] ?? ''),
            'date_to' => (string) ($input['date_to'] ?? ''),
            'search' => trim((string) ($input['search'] ?? '')),
            'contact_id' => max(0, (int) ($input['contact_id'] ?? 0)),
        ];
        if (isset($input['amount_min']) && $input['amount_min'] !== '' && $input['amount_min'] !== null) {
            $filters['amount_min'] = $input['amount_min'];
        }
        if (isset($input['amount_max']) && $input['amount_max'] !== '' && $input['amount_max'] !== null) {
            $filters['amount_max'] = $input['amount_max'];
        }
        $filters['_year'] = (int) ($input['year'] ?? date('Y'));
        $filters['_month'] = isset($input['month']) && (int) $input['month'] > 0 ? (int) $input['month'] : null;
        $filters['_date_from_raw'] = (string) ($input['date_from_raw'] ?? '');
        $filters['_date_to_raw'] = (string) ($input['date_to_raw'] ?? '');

        return $filters;
    }

    /**
     * @param array<string, mixed> $baseFilters
     * @return array{
     *   by_kind: array<string, array{total: int, gross: float, by_status: array<string, array{total: int, gross: float}>}>,
     *   by_type: array<string, array{total: int, gross: float}>,
     *   invoices: array{total: int, gross: float, by_kind: array<string, array{total: int, gross: float}>, by_status: array<string, array{total: int, gross: float}>, by_pay: array<string, array{total: int, gross: float}>}
     * }
     */
    private static function aggregateCounts(array $baseFilters): array
    {
        $empty = [
            'by_kind' => [],
            'by_type' => [],
            'invoices' => [
                'total' => 0,
                'gross' => 0.0,
                'by_kind' => [],
                'by_status' => [],
                'by_pay' => [
                    'open' => ['total' => 0, 'gross' => 0.0],
                    'partial' => ['total' => 0, 'gross' => 0.0],
                    'paid' => ['total' => 0, 'gross' => 0.0],
                ],
            ],
        ];
        if (!Database::isConfigured()) {
            return $empty;
        }

        MigrationRunner::runPending();

        [$whereSql, $params] = self::sqlWhereFromBase($baseFilters);
        $sql = 'SELECT v.document_kind, v.document_status, v.voucher_type, v.payment_status,
                       COUNT(*) AS cnt, COALESCE(SUM(v.gross_amount), 0) AS gross_sum
                FROM dg_vouchers v
                LEFT JOIN dg_contacts c ON c.id = v.contact_id
                ' . $whereSql . '
                GROUP BY v.document_kind, v.document_status, v.voucher_type, v.payment_status';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $result = $empty;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $kind = (string) ($row['document_kind'] ?? '');
            $status = (string) ($row['document_status'] ?? '');
            $type = (string) ($row['voucher_type'] ?? '');
            $pay = (string) ($row['payment_status'] ?? '');
            $cnt = (int) ($row['cnt'] ?? 0);
            $gross = round((float) ($row['gross_sum'] ?? 0), 2);

            if (!isset($result['by_type'][$type])) {
                $result['by_type'][$type] = ['total' => 0, 'gross' => 0.0];
            }
            $result['by_type'][$type]['total'] += $cnt;
            $result['by_type'][$type]['gross'] = round($result['by_type'][$type]['gross'] + $gross, 2);

            if ($kind !== '') {
                if (!isset($result['by_kind'][$kind])) {
                    $result['by_kind'][$kind] = ['total' => 0, 'gross' => 0.0, 'by_status' => []];
                }
                $result['by_kind'][$kind]['total'] += $cnt;
                $result['by_kind'][$kind]['gross'] = round($result['by_kind'][$kind]['gross'] + $gross, 2);
                if ($status !== '') {
                    if (!isset($result['by_kind'][$kind]['by_status'][$status])) {
                        $result['by_kind'][$kind]['by_status'][$status] = ['total' => 0, 'gross' => 0.0];
                    }
                    $result['by_kind'][$kind]['by_status'][$status]['total'] += $cnt;
                    $result['by_kind'][$kind]['by_status'][$status]['gross'] = round(
                        $result['by_kind'][$kind]['by_status'][$status]['gross'] + $gross,
                        2
                    );
                }
            }

            if (in_array($kind, [
                VoucherDocumentKind::PARTIAL_INVOICE,
                VoucherDocumentKind::INVOICE,
                VoucherDocumentKind::FINAL_INVOICE,
            ], true)) {
                $result['invoices']['total'] += $cnt;
                $result['invoices']['gross'] = round($result['invoices']['gross'] + $gross, 2);
                if (!isset($result['invoices']['by_kind'][$kind])) {
                    $result['invoices']['by_kind'][$kind] = ['total' => 0, 'gross' => 0.0];
                }
                $result['invoices']['by_kind'][$kind]['total'] += $cnt;
                $result['invoices']['by_kind'][$kind]['gross'] = round(
                    $result['invoices']['by_kind'][$kind]['gross'] + $gross,
                    2
                );
                if ($status !== '') {
                    if (!isset($result['invoices']['by_status'][$status])) {
                        $result['invoices']['by_status'][$status] = ['total' => 0, 'gross' => 0.0];
                    }
                    $result['invoices']['by_status'][$status]['total'] += $cnt;
                    $result['invoices']['by_status'][$status]['gross'] = round(
                        $result['invoices']['by_status'][$status]['gross'] + $gross,
                        2
                    );
                }
                $payBucket = match (true) {
                    $pay === VoucherPaymentStatus::PARTIAL => 'partial',
                    $pay === VoucherPaymentStatus::OPEN || VoucherPaymentStatus::isOpen($pay) => 'open',
                    default => 'paid',
                };
                $result['invoices']['by_pay'][$payBucket]['total'] += $cnt;
                $result['invoices']['by_pay'][$payBucket]['gross'] = round(
                    $result['invoices']['by_pay'][$payBucket]['gross'] + $gross,
                    2
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $baseFilters
     * @return array<string, int|float>
     */
    private static function actionBucketCounts(array $baseFilters): array
    {
        $out = [
            'drafts' => 0,
            'expired_offers' => 0,
            'accepted_without_ab' => 0,
            'overdue_invoices' => 0,
            '_gross' => 0.0,
        ];
        if (!Database::isConfigured()) {
            return $out;
        }

        foreach (['drafts', 'expired_offers', 'accepted_without_ab', 'overdue_invoices'] as $bucket) {
            $list = VoucherRepository::list(array_merge($baseFilters, [
                'action_bucket' => $bucket,
                'page' => 1,
                'per_page' => 1,
            ]));
            $out[$bucket] = (int) ($list['total'] ?? 0);
            $out['_gross'] = round((float) $out['_gross'] + (float) ($list['gross_sum'] ?? 0), 2);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $counts
     * @param array<string, int|float> $actionCounts
     * @param array<string, mixed> $baseFilters
     * @return list<array{id: string, label: string, count: int, active: bool, url: string}>
     */
    private static function chipsForSection(
        string $section,
        array $counts,
        array $actionCounts,
        int $actionTotal,
        array $baseFilters,
        string $activeStatus,
        string $invoiceKind,
        string $pay,
        bool $actionableOnly
    ): array {
        $chips = [];
        $allUrl = self::url($baseFilters, [
            'section' => $section,
            'status' => '',
            'invoice_kind' => $section === self::SECTION_INVOICES ? $invoiceKind : '',
            'pay' => $section === self::SECTION_INVOICES ? $pay : '',
            'actionable_only' => $actionableOnly ? '1' : '',
            'page' => 1,
        ]);

        if ($section === self::SECTION_ACTION) {
            $defs = [
                '' => [
                    'label' => 'Alle',
                    'count' => $actionTotal,
                ],
                'drafts' => ['label' => 'Entwürfe', 'count' => (int) ($actionCounts['drafts'] ?? 0)],
                'expired_offers' => ['label' => 'Abgelaufene Angebote', 'count' => (int) ($actionCounts['expired_offers'] ?? 0)],
                'accepted_without_ab' => ['label' => 'Angenommen ohne AB', 'count' => (int) ($actionCounts['accepted_without_ab'] ?? 0)],
                'overdue_invoices' => ['label' => 'Überfällige Rechnungen', 'count' => (int) ($actionCounts['overdue_invoices'] ?? 0)],
            ];
        } elseif ($section === self::SECTION_OFFERS) {
            $byStatus = $counts['by_kind'][VoucherDocumentKind::OFFER]['by_status'] ?? [];
            $defs = self::statusChipDefs(VoucherDocumentStatus::allowedForKind(VoucherDocumentKind::OFFER), $byStatus);
            $defs = ['' => ['label' => 'Alle', 'count' => (int) ($counts['by_kind'][VoucherDocumentKind::OFFER]['total'] ?? 0)]] + $defs;
        } elseif ($section === self::SECTION_ORDER_CONFIRMATIONS) {
            $byStatus = $counts['by_kind'][VoucherDocumentKind::ORDER_CONFIRMATION]['by_status'] ?? [];
            $defs = self::statusChipDefs(VoucherDocumentStatus::allowedForKind(VoucherDocumentKind::ORDER_CONFIRMATION), $byStatus);
            $defs = ['' => ['label' => 'Alle', 'count' => (int) ($counts['by_kind'][VoucherDocumentKind::ORDER_CONFIRMATION]['total'] ?? 0)]] + $defs;
        } elseif ($section === self::SECTION_DELIVERY_NOTES) {
            $byStatus = $counts['by_kind'][VoucherDocumentKind::DELIVERY_NOTE]['by_status'] ?? [];
            $defs = self::statusChipDefs(VoucherDocumentStatus::allowedForKind(VoucherDocumentKind::DELIVERY_NOTE), $byStatus);
            $defs = ['' => ['label' => 'Alle', 'count' => (int) ($counts['by_kind'][VoucherDocumentKind::DELIVERY_NOTE]['total'] ?? 0)]] + $defs;
        } elseif ($section === self::SECTION_INVOICES) {
            $byStatus = $counts['invoices']['by_status'] ?? [];
            $defs = self::statusChipDefs(
                VoucherDocumentStatus::allowedForKind(VoucherDocumentKind::INVOICE),
                $byStatus
            );
            $defs = ['' => ['label' => 'Alle', 'count' => (int) ($counts['invoices']['total'] ?? 0)]] + $defs;
        } elseif ($section === self::SECTION_CREDITS || $section === self::SECTION_INCOMING) {
            $defs = ['' => [
                'label' => 'Alle',
                'count' => $section === self::SECTION_CREDITS
                    ? (int) ($counts['by_type']['credit']['total'] ?? 0)
                    : (int) (($counts['by_type']['expense']['total'] ?? 0) + ($counts['by_type']['expense_reduction']['total'] ?? 0)),
            ]];
        } else {
            $defs = ['' => ['label' => 'Alle', 'count' => 0]];
        }

        foreach ($defs as $id => $def) {
            $chips[] = [
                'id' => (string) $id,
                'label' => $def['label'],
                'count' => (int) $def['count'],
                'active' => $activeStatus === (string) $id,
                'url' => self::url($baseFilters, [
                    'section' => $section,
                    'status' => (string) $id,
                    'invoice_kind' => $section === self::SECTION_INVOICES ? $invoiceKind : '',
                    'pay' => $section === self::SECTION_INVOICES ? $pay : '',
                    'actionable_only' => $actionableOnly ? '1' : '',
                    'page' => 1,
                ]),
            ];
        }

        if ($chips === []) {
            $chips[] = [
                'id' => '',
                'label' => 'Alle',
                'count' => 0,
                'active' => true,
                'url' => $allUrl,
            ];
        }

        return $chips;
    }

    /**
     * @param list<string> $statuses
     * @param array<string, array{total: int, gross: float}> $byStatus
     * @return array<string, array{label: string, count: int}>
     */
    private static function statusChipDefs(array $statuses, array $byStatus): array
    {
        $defs = [];
        foreach ($statuses as $status) {
            $defs[$status] = [
                'label' => VoucherDocumentStatus::label($status),
                'count' => (int) ($byStatus[$status]['total'] ?? 0),
            ];
        }

        return $defs;
    }

    /**
     * @param array<string, mixed> $counts
     * @param array<string, mixed> $baseFilters
     * @return list<array{id: string, label: string, count: int, active: bool, url: string}>
     */
    private static function invoiceKindChips(
        array $counts,
        array $baseFilters,
        string $status,
        string $activeKind,
        string $pay,
        bool $actionableOnly
    ): array {
        $defs = [
            '' => ['label' => 'Alle Arten', 'count' => (int) ($counts['invoices']['total'] ?? 0)],
            VoucherDocumentKind::PARTIAL_INVOICE => [
                'label' => 'Abschlag',
                'count' => (int) ($counts['invoices']['by_kind'][VoucherDocumentKind::PARTIAL_INVOICE]['total'] ?? 0),
            ],
            VoucherDocumentKind::INVOICE => [
                'label' => 'Rechnung',
                'count' => (int) ($counts['invoices']['by_kind'][VoucherDocumentKind::INVOICE]['total'] ?? 0),
            ],
            VoucherDocumentKind::FINAL_INVOICE => [
                'label' => 'Schlussrechnung',
                'count' => (int) ($counts['invoices']['by_kind'][VoucherDocumentKind::FINAL_INVOICE]['total'] ?? 0),
            ],
        ];
        $chips = [];
        foreach ($defs as $id => $def) {
            $chips[] = [
                'id' => (string) $id,
                'label' => $def['label'],
                'count' => (int) $def['count'],
                'active' => $activeKind === (string) $id,
                'url' => self::url($baseFilters, [
                    'section' => self::SECTION_INVOICES,
                    'status' => $status,
                    'invoice_kind' => (string) $id,
                    'pay' => $pay,
                    'actionable_only' => $actionableOnly ? '1' : '',
                    'page' => 1,
                ]),
            ];
        }

        return $chips;
    }

    /**
     * @param array<string, mixed> $counts
     * @param array<string, mixed> $baseFilters
     * @return list<array{id: string, label: string, count: int, active: bool, url: string}>
     */
    private static function payChips(
        array $counts,
        array $baseFilters,
        string $status,
        string $invoiceKind,
        string $activePay,
        bool $actionableOnly
    ): array {
        $byPay = $counts['invoices']['by_pay'] ?? [];
        $defs = [
            '' => ['label' => 'Zahlung: alle', 'count' => (int) ($counts['invoices']['total'] ?? 0)],
            'open' => ['label' => 'Offen', 'count' => (int) ($byPay['open']['total'] ?? 0)],
            'partial' => ['label' => 'Teilweise', 'count' => (int) ($byPay['partial']['total'] ?? 0)],
            'paid' => ['label' => 'Bezahlt', 'count' => (int) ($byPay['paid']['total'] ?? 0)],
        ];
        $chips = [];
        foreach ($defs as $id => $def) {
            $chips[] = [
                'id' => (string) $id,
                'label' => $def['label'],
                'count' => (int) $def['count'],
                'active' => $activePay === (string) $id,
                'url' => self::url($baseFilters, [
                    'section' => self::SECTION_INVOICES,
                    'status' => $status,
                    'invoice_kind' => $invoiceKind,
                    'pay' => (string) $id,
                    'actionable_only' => $actionableOnly ? '1' : '',
                    'page' => 1,
                ]),
            ];
        }

        return $chips;
    }

    /**
     * @param list<array{id: string, label: string, count: int, active: bool, url: string}> $chips
     */
    private static function normalizeStatusForSection(string $section, string $status, array $chips): string
    {
        $valid = [];
        foreach ($chips as $chip) {
            $valid[(string) $chip['id']] = true;
        }
        if (!isset($valid[$status])) {
            return '';
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private static function listFiltersForSection(
        string $section,
        string $status,
        string $invoiceKind,
        string $pay
    ): array {
        $filters = [];
        if ($section === self::SECTION_ACTION) {
            if ($status === '') {
                // Alle Handlungsbedarf: OR über Buckets — lade als Union über drafts+… via action_bucket leer und custom
                // Einfachster Weg: keine document_kind-Einschränkung, sondern action_bucket nur bei Chip.
                // Für „Alle“: verwende einen speziellen Filter über IDs — besser: action_bucket = 'all_action'
                $filters['action_bucket'] = 'all_action';
            } else {
                $filters['action_bucket'] = $status;
            }

            return $filters;
        }

        if ($section === self::SECTION_OFFERS) {
            $filters['document_kind'] = VoucherDocumentKind::OFFER;
            if ($status !== '') {
                $filters['document_status'] = $status;
            }
        } elseif ($section === self::SECTION_ORDER_CONFIRMATIONS) {
            $filters['document_kind'] = VoucherDocumentKind::ORDER_CONFIRMATION;
            if ($status !== '') {
                $filters['document_status'] = $status;
            }
        } elseif ($section === self::SECTION_DELIVERY_NOTES) {
            $filters['document_kind'] = VoucherDocumentKind::DELIVERY_NOTE;
            if ($status !== '') {
                $filters['document_status'] = $status;
            }
        } elseif ($section === self::SECTION_INVOICES) {
            if ($invoiceKind !== '') {
                $filters['document_kind'] = $invoiceKind;
            } else {
                $filters['document_kinds'] = [
                    VoucherDocumentKind::PARTIAL_INVOICE,
                    VoucherDocumentKind::INVOICE,
                    VoucherDocumentKind::FINAL_INVOICE,
                ];
            }
            if ($status !== '') {
                $filters['document_status'] = $status;
            }
            if ($pay === 'open') {
                $filters['payment_statuses'] = [VoucherPaymentStatus::OPEN];
            } elseif ($pay === 'partial') {
                $filters['payment_statuses'] = [VoucherPaymentStatus::PARTIAL];
            } elseif ($pay === 'paid') {
                $filters['payment_statuses'] = [
                    VoucherPaymentStatus::CASH,
                    VoucherPaymentStatus::PRIVATE,
                    VoucherPaymentStatus::DIRECT_DEBIT,
                    VoucherPaymentStatus::BANK,
                    VoucherPaymentStatus::TIP,
                ];
            }
        } elseif ($section === self::SECTION_CREDITS) {
            $filters['type'] = 'credit';
        } elseif ($section === self::SECTION_INCOMING) {
            $filters['voucher_types'] = ['expense', 'expense_reduction'];
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $baseFilters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function sqlWhereFromBase(array $baseFilters): array
    {
        $where = [];
        $params = [];
        $dateFrom = trim((string) ($baseFilters['date_from'] ?? ''));
        $dateTo = trim((string) ($baseFilters['date_to'] ?? ''));
        if ($dateFrom !== '' && $dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1
        ) {
            $where[] = 'v.voucher_date BETWEEN :date_from AND :date_to';
            $params['date_from'] = $dateFrom;
            $params['date_to'] = $dateTo;
        }
        $contactId = max(0, (int) ($baseFilters['contact_id'] ?? 0));
        if ($contactId > 0) {
            $where[] = 'v.contact_id = :contact_id';
            $params['contact_id'] = $contactId;
        }
        if (isset($baseFilters['amount_min']) && $baseFilters['amount_min'] !== '' && $baseFilters['amount_min'] !== null) {
            $where[] = 'v.gross_amount >= :amount_min';
            $params['amount_min'] = (float) $baseFilters['amount_min'];
        }
        if (isset($baseFilters['amount_max']) && $baseFilters['amount_max'] !== '' && $baseFilters['amount_max'] !== null) {
            $where[] = 'v.gross_amount <= :amount_max';
            $params['amount_max'] = (float) $baseFilters['amount_max'];
        }
        $search = trim((string) ($baseFilters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(
                v.supplier_name LIKE :q1 OR v.invoice_number LIKE :q2
                OR v.description LIKE :q3 OR v.account_number LIKE :q4
                OR c.display_name LIKE :q5 OR c.company_name LIKE :q6
            )';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $params];
    }

    /**
     * @param array<string, mixed> $baseFilters
     * @return list<array{id: int, label: string}>
     */
    private static function contactOptions(array $baseFilters): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $filtersForContacts = $baseFilters;
        $filtersForContacts['contact_id'] = 0;
        $filtersForContacts['search'] = '';
        [$whereSql, $params] = self::sqlWhereFromBase($filtersForContacts);

        $sql = 'SELECT DISTINCT c.id,
                       COALESCE(NULLIF(TRIM(c.display_name), \'\'), NULLIF(TRIM(c.company_name), \'\'), CONCAT(\'Kontakt #\', c.id)) AS label
                FROM dg_vouchers v
                INNER JOIN dg_contacts c ON c.id = v.contact_id
                ' . $whereSql . '
                ORDER BY label ASC
                LIMIT 400';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? ('Kontakt #' . $id)),
            ];
        }

        return $out;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
