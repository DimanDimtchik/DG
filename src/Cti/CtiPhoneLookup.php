<?php
declare(strict_types=1);

final class CtiPhoneLookup
{
    public static function contactIdForPhone(string $phone): int
    {
        $phone = trim($phone);
        if ($phone === '' || !Database::isConfigured()) {
            return 0;
        }
        $e164 = PhoneNormalizer::toE164($phone) ?? '';
        $digits = preg_replace('/\D/', '', $e164 !== '' ? $e164 : $phone) ?? '';
        if (strlen($digits) < 6) {
            return 0;
        }
        // Letzte 8–10 Ziffern reichen oft für DE-Match (Vorwahl-Varianten)
        $tail = substr($digits, -10);
        $stmt = Database::pdo()->prepare(
            'SELECT id, phone_1, phone_2 FROM dg_contacts
             WHERE phone_1 <> \'\' OR phone_2 <> \'\'
             ORDER BY id DESC
             LIMIT 2000'
        );
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach (['phone_1', 'phone_2'] as $col) {
                $raw = trim((string) ($row[$col] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $other = PhoneNormalizer::toE164($raw) ?? $raw;
                $od = preg_replace('/\D/', '', $other) ?? '';
                if ($od === '') {
                    continue;
                }
                if ($od === $digits || str_ends_with($od, $tail) || str_ends_with($digits, substr($od, -10))) {
                    return (int) $row['id'];
                }
            }
        }

        return 0;
    }
}
