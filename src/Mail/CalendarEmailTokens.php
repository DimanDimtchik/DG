<?php
declare(strict_types=1);

/** Platzhalter für Kalender-E-Mail-Vorlagen. */
final class CalendarEmailTokens
{
    /**
     * Methode labels.
     * @return array<string, mixed>
     */
    public static function labels(): array
    {
        return [
            '{termin_datum}' => 'Datum (z. B. Freitag, 10.07.2026)',
            '{termin_zeit}' => 'Uhrzeit (z. B. 10:00 Uhr)',
            '{leistung}' => 'Gebuchte Leistung',
            '{kunde_name}' => 'Name des Kunden',
            '{kunde_email}' => 'E-Mail des Kunden',
            '{kunde_telefon}' => 'Telefon des Kunden',
            '{mitarbeiter}' => 'Zugewiesener Mitarbeiter',
            '{firma}' => 'Firmenname',
            '{firma_adresse}' => 'Firmenadresse (mehrzeilig)',
            '{firma_website}' => 'Website der Firma',
            '{buchung_id}' => 'Buchungsnummer (z. B. DG-7K2M9P4Q)',
            '{buchungsnummer}' => 'Buchungsnummer (Alias)',
        ];
    }

    /**
     * Methode context for booking.
     * @param Booking $booking
     * @return array<string, mixed>
     */
    public static function contextForBooking(Booking $booking): array
    {
        $company = CompanySettings::config();
        $address = trim(implode("\n", array_filter([
            trim($company['street'] ?? ''),
            trim(($company['postal'] ?? '') . ' ' . ($company['city'] ?? '')),
        ])));
        $formatted = self::formatGermanSlot($booking->slotDatetime);
        $employee = $booking->employeeId > 0 ? CalendarStaffRepository::getEmployeeById($booking->employeeId) : null;
        $employeeName = $employee ? trim((string) ($employee['name'] ?? '')) : '';
        $articleTitle = $booking->articleId > 0 ? CalendarArticleRepository::title($booking->articleId) : '';
        $code = $booking->publicCode();

        return [
            'termin_datum' => $formatted['datum'],
            'termin_zeit' => $formatted['zeit'],
            'leistung' => $articleTitle,
            'kunde_name' => $booking->customerName,
            'kunde_email' => $booking->customerEmail,
            'kunde_telefon' => $booking->customerPhone,
            'mitarbeiter' => $employeeName,
            'firma' => $company['name'] !== '' ? $company['name'] : CompanySettings::displayName(),
            'firma_adresse' => $address,
            'firma_website' => trim($company['website'] ?? ''),
            'buchung_id' => $code,
            'buchungsnummer' => $code,
        ];
    }

    /**
     * Methode format german slot.
     * @param string $slotDatetime
     * @return array<string, mixed>
     */
    private static function formatGermanSlot(string $slotDatetime): array
    {
        $ts = strtotime($slotDatetime);
        if ($ts === false) {
            return ['datum' => '', 'zeit' => ''];
        }

        $weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

        return [
            'datum' => $weekdays[(int) date('w', $ts)] . ', ' . date('d.m.Y', $ts),
            'zeit' => date('H:i', $ts) . ' Uhr',
        ];
    }

    /**
     * Methode demo context.
     * @return array<string, mixed>
     */
    public static function demoContext(): array
    {
        $company = CompanySettings::config();
        $address = trim(implode("\n", array_filter([
            trim($company['street'] ?? ''),
            trim(($company['postal'] ?? '') . ' ' . ($company['city'] ?? '')),
        ])));

        return [
            'termin_datum' => 'Freitag, 10.07.2026',
            'termin_zeit' => '10:00 Uhr',
            'leistung' => 'Gesichtsbehandlung Classic',
            'kunde_name' => 'Maria Beispiel',
            'kunde_email' => 'maria.beispiel@example.de',
            'kunde_telefon' => '+49 170 1234567',
            'mitarbeiter' => 'Anna Muster',
            'firma' => $company['name'] !== '' ? $company['name'] : 'Musterfirma GmbH',
            'firma_adresse' => $address !== '' ? $address : "Musterstraße 1\n12345 Musterstadt",
            'firma_website' => trim($company['website'] ?? '') !== '' ? trim($company['website']) : 'https://www.beispiel.de',
            'buchung_id' => 'DG-7K2M9P4Q',
            'buchungsnummer' => 'DG-7K2M9P4Q',
        ];
    }

    /**
     * Methode replace.
     * @param string $text
     * @param array|null $context
     * @return string
     */
    public static function replace(string $text, ?array $context = null): string
    {
        $context = $context ?? self::demoContext();
        $map = [];
        foreach ($context as $key => $value) {
            $map['{' . $key . '}'] = (string) $value;
        }

        return strtr($text, $map);
    }

    /**
     * Methode reference groups.
     * @return array<string, mixed>
     */
    public static function referenceGroups(): array
    {
        $items = [];
        foreach (self::labels() as $code => $label) {
            $items[] = ['label' => $label, 'codes' => [$code]];
        }

        return [
            ['title' => 'Kalender & Kunde', 'items' => $items],
            ['title' => 'Firma', 'items' => [
                ['label' => 'Firmenname', 'codes' => ['{firma}']],
                ['label' => 'Firmenadresse', 'codes' => ['{firma_adresse}']],
                ['label' => 'Website', 'codes' => ['{firma_website}']],
            ]],
        ];
    }
}
