<?php
declare(strict_types=1);

/**
 * Pre-Close-Checkliste vor dem Jahresabschluss — auch für Kunden ohne Steuerberater (DIY).
 */
final class FiscalCloseService
{
    public const NA_STORE_KEY = 'fiscal_close_na';

    /**
     * @return list<array{id: string, label: string, status: string, detail: string, href: string, allow_na?: bool, na_marked?: bool}>
     */
    public static function checklist(int $year): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $items = [];
        $items[] = self::itemDrafts($year);
        $items[] = self::itemOpenItems($year);
        $items[] = self::itemJournalBalance($year);
        $items[] = self::itemBalanceSheet($year);
        $items[] = self::itemUnbalancedVouchers($year);
        $items[] = self::itemBankOpen($year);
        $items[] = self::itemVacationProvision($year);

        return $items;
    }

    public static function canClose(int $year): bool
    {
        foreach (self::checklist($year) as $item) {
            if (($item['status'] ?? '') === 'error') {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws RuntimeException
     */
    public static function assertCanClose(int $year): void
    {
        $blocking = [];
        foreach (self::checklist($year) as $item) {
            if (($item['status'] ?? '') === 'error') {
                $blocking[] = (string) ($item['label'] ?? '');
            }
        }
        if ($blocking !== []) {
            throw new RuntimeException(
                'Jahresabschluss blockiert: ' . implode('; ', $blocking)
            );
        }
    }

    /**
     * @return array{done: int, total: int, errors: int, warnings: int}
     */
    public static function summary(int $year): array
    {
        $done = 0;
        $errors = 0;
        $warnings = 0;
        foreach (self::checklist($year) as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'ok') {
                $done++;
            } elseif ($status === 'error') {
                $errors++;
            } elseif ($status === 'warn') {
                $warnings++;
            }
        }

        return [
            'done' => $done,
            'total' => count(self::checklist($year)),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemDrafts(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_vouchers WHERE is_draft = 1 AND YEAR(voucher_date) = :y'
        );
        $stmt->execute(['y' => $year]);
        $count = (int) $stmt->fetchColumn();

        return [
            'id' => 'drafts',
            'label' => 'Keine Beleg-Entwürfe',
            'status' => $count === 0 ? 'ok' : 'error',
            'detail' => $count === 0
                ? 'Alle Belege sind final gebucht.'
                : sprintf('%d Entwurf/Entwürfe im Jahr %d — bitte veröffentlichen oder löschen.', $count, $year),
            'href' => '/app?page=buchhaltung-belege&year=' . $year . '&draft=1',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemOpenItems(int $year): array
    {
        $opos = OpenItemsRepository::list();
        $yearItems = array_filter(
            $opos['items'],
            static function (array $row) use ($year): bool {
                if ((int) substr((string) ($row['voucher_date'] ?? ''), 0, 4) !== $year) {
                    return false;
                }
                // Jahresabschluss: nur buchbare Rechnungen, keine reinen Angebots-/AB-Anzahlungen.
                return empty($row['is_advance']);
            }
        );
        $count = count($yearItems);
        $total = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['open_amount'] ?? 0),
            $yearItems
        )), 2);

        return [
            'id' => 'opos',
            'label' => 'Offene Posten (OPOS)',
            'status' => $count === 0 ? 'ok' : 'warn',
            'detail' => $count === 0
                ? 'Keine offenen Forderungen/Verbindlichkeiten aus ' . $year . '.'
                : sprintf(
                    '%d offene Posten (%.2f €) aus %d — vor Abschluss prüfen oder als bewusst offen dokumentieren.',
                    $count,
                    $total,
                    $year
                ),
            'href' => '/app?page=buchhaltung-opos',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemJournalBalance(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT ROUND(SUM(CASE WHEN side='debit' THEN amount ELSE 0 END), 2) AS d,
                    ROUND(SUM(CASE WHEN side='credit' THEN amount ELSE 0 END), 2) AS c
             FROM dg_ledger_postings
             WHERE fiscal_year = :y"
        );
        $stmt->execute(['y' => $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['d' => 0, 'c' => 0];
        $diff = round(abs((float) $row['d'] - (float) $row['c']), 2);

        return [
            'id' => 'journal',
            'label' => 'Buchungsjournal ausgeglichen',
            'status' => $diff <= 0.01 ? 'ok' : 'error',
            'detail' => $diff <= 0.01
                ? sprintf('Soll = Haben (%.2f €).', (float) $row['d'])
                : sprintf('Differenz Soll/Haben: %.2f € — Journal prüfen.', $diff),
            'href' => '/app?page=buchhaltung-kontenuebersicht&year=' . $year,
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemBalanceSheet(int $year): array
    {
        $bs = FinancialReportsService::balanceSheet($year);
        $aktiva = round((float) ($bs['totals']['aktiva'] ?? 0), 2);
        $passiva = round((float) ($bs['totals']['passiva'] ?? 0), 2);
        $diff = round(abs($aktiva - $passiva), 2);

        return [
            'id' => 'bilanz',
            'label' => 'Bilanz stimmig',
            'status' => $diff <= 0.05 ? 'ok' : 'warn',
            'detail' => $diff <= 0.05
                ? sprintf('Aktiva %.2f € · Passiva inkl. Ergebnis %.2f €.', $aktiva, $passiva)
                : sprintf(
                    'Aktiva %.2f € vs. Passiva %.2f € (Diff. %.2f €) — Bilanz prüfen.',
                    $aktiva,
                    $passiva,
                    $diff
                ),
            'href' => '/app?page=buchhaltung-auswertungen&year=' . $year . '&type=bilanz',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemUnbalancedVouchers(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM (
                SELECT voucher_id,
                       ROUND(SUM(CASE WHEN side='debit' THEN amount ELSE 0 END), 2) AS d,
                       ROUND(SUM(CASE WHEN side='credit' THEN amount ELSE 0 END), 2) AS c
                FROM dg_ledger_postings
                WHERE fiscal_year = :y AND source = 'voucher' AND voucher_id IS NOT NULL
                GROUP BY voucher_id
                HAVING ABS(d - c) > 0.01
             ) AS t"
        );
        $stmt->execute(['y' => $year]);
        $count = (int) $stmt->fetchColumn();

        return [
            'id' => 'voucher_balance',
            'label' => 'Belegbuchungen ausgeglichen',
            'status' => $count === 0 ? 'ok' : 'error',
            'detail' => $count === 0
                ? 'Jeder Beleg ist im Journal ausgeglichen (Soll = Haben).'
                : sprintf('%d Beleg(e) mit unbalancierten Journalzeilen.', $count),
            'href' => '/app?page=buchhaltung-belege&year=' . $year,
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemBankOpen(int $year): array
    {
        $open = BankTransactionRepository::list('open');
        $yearOpen = array_filter(
            $open,
            static fn (array $row): bool => (int) substr((string) ($row['transaction_date'] ?? ''), 0, 4) === $year
        );
        $count = count($yearOpen);

        return [
            'id' => 'bank',
            'label' => 'Bankabgleich',
            'status' => $count === 0 ? 'ok' : 'warn',
            'detail' => $count === 0
                ? 'Keine offenen Bankumsätze aus ' . $year . '.'
                : sprintf('%d Bankumsätze aus %d noch nicht zugeordnet.', $count, $year),
            'href' => '/app?page=buchhaltung-bankabgleich',
        ];
    }

    /**
     * Z5d: Urlaubsrückstellung — ok bei Batch oder n. a.; sonst warn (blockiert Abschluss nicht).
     *
     * @return array{id: string, label: string, status: string, detail: string, href: string, allow_na?: bool, na_marked?: bool}
     */
    private static function itemVacationProvision(int $year): array
    {
        $href = '/app?page=zeiterfassung-rueckstellung&year=' . $year;
        $label = 'Urlaubsrückstellung ' . $year;

        $batchId = null;
        if (class_exists('TimeProvisionService')) {
            $batchId = TimeProvisionService::existingBatchId($year);
        }

        if ($batchId !== null && $batchId > 0) {
            return [
                'id' => 'time_provision',
                'label' => $label,
                'status' => 'ok',
                'detail' => sprintf('Rückstellung gebucht (Journal-Batch #%d).', $batchId),
                'href' => $href,
            ];
        }

        if (self::isNaMarked('time_provision', $year)) {
            $mark = self::naMark('time_provision', $year);
            $note = trim((string) ($mark['note'] ?? ''));

            return [
                'id' => 'time_provision',
                'label' => $label,
                'status' => 'ok',
                'detail' => $note !== ''
                    ? 'Als n. a. markiert: ' . $note
                    : 'Als n. a. markiert (keine CRM-Rückstellungsbuchung erforderlich).',
                'href' => $href,
                'na_marked' => true,
            ];
        }

        return [
            'id' => 'time_provision',
            'label' => $label,
            'status' => 'warn',
            'detail' => 'Noch keine Rückstellungsbuchung für '
                . $year
                . ' — unter Zeiterfassung buchen oder hier als n. a. markieren (blockiert den Abschluss nicht).',
            'href' => $href,
            'allow_na' => true,
        ];
    }

    public static function isNaMarked(string $itemId, int $year): bool
    {
        return self::naMark($itemId, $year) !== null;
    }

    /**
     * @return array{note: string, at: string, by: int|null}|null
     */
    public static function naMark(string $itemId, int $year): ?array
    {
        $all = self::naStore();
        $key = self::naKey($itemId, $year);
        $row = $all[$key] ?? null;
        if (!is_array($row)) {
            return null;
        }

        return [
            'note' => (string) ($row['note'] ?? ''),
            'at' => (string) ($row['at'] ?? ''),
            'by' => isset($row['by']) && $row['by'] !== null && $row['by'] !== ''
                ? (int) $row['by']
                : null,
        ];
    }

    public static function markNa(string $itemId, int $year, string $note, ?int $userId): void
    {
        $itemId = preg_replace('/[^a-z0-9_]/', '', $itemId) ?? '';
        if ($itemId === '' || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Checklisten-Punkt oder Jahr ungültig.');
        }
        if ($itemId !== 'time_provision') {
            throw new InvalidArgumentException('Nur Urlaubsrückstellung kann als n. a. markiert werden.');
        }
        $note = trim($note);
        if (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 255);
        } else {
            $note = substr($note, 0, 255);
        }
        $all = self::naStore();
        $all[self::naKey($itemId, $year)] = [
            'note' => $note,
            'at' => date('c'),
            'by' => $userId !== null && $userId > 0 ? $userId : null,
        ];
        SettingsStore::set(self::NA_STORE_KEY, $all);
    }

    public static function clearNa(string $itemId, int $year): void
    {
        $all = self::naStore();
        $key = self::naKey($itemId, $year);
        if (!isset($all[$key])) {
            return;
        }
        unset($all[$key]);
        SettingsStore::set(self::NA_STORE_KEY, $all);
    }

    /**
     * @return array<string, mixed>
     */
    private static function naStore(): array
    {
        if (!class_exists('SettingsStore')) {
            return [];
        }
        $raw = SettingsStore::get(self::NA_STORE_KEY, []);

        return is_array($raw) ? $raw : [];
    }

    private static function naKey(string $itemId, int $year): string
    {
        return $itemId . ':' . $year;
    }
}
