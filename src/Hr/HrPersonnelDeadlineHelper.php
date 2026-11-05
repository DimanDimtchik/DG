<?php
declare(strict_types=1);

/**
 * Fristenhilfe (Hinweisrechner) — keine Rechtsverbindlichkeit.
 */
final class HrPersonnelDeadlineHelper
{
    /**
     * @return array{ok: bool, lines: list<string>, disclaimer: string}
     */
    public static function hintsForContact(Contact $contact, ?string $asOf = null): array
    {
        $disclaimer = 'Nur Orientierungshilfe (u. a. BGB § 622). Maßgeblich sind Vertrag, Tarif, '
            . 'Sonderkündigungsschutz und Einzelfall. Keine Rechtsberatung.';
        $ed = is_array($contact->employeeData) ? $contact->employeeData : [];
        $startRaw = trim((string) ($ed['contract_start'] ?? ''));
        if ($startRaw === '') {
            $startRaw = trim((string) ($ed['entry_date'] ?? ''));
        }
        $asOfDt = date_create(trim((string) $asOf) !== '' ? trim((string) $asOf) : 'today');
        if (!$asOfDt instanceof DateTimeInterface) {
            return ['ok' => false, 'lines' => ['Stichtag ungültig.'], 'disclaimer' => $disclaimer];
        }
        $lines = [];
        $probation = trim((string) ($ed['probation_period'] ?? ''));
        if ($probation !== '') {
            $lines[] = 'Hinterlegte Probezeit (Freitext): ' . $probation . '.';
        } else {
            $lines[] = 'Keine Probezeit im Mitarbeiterdatensatz hinterlegt.';
        }

        if ($startRaw === '') {
            $lines[] = 'Kein Vertragsbeginn/Eintritt — Betriebszugehörigkeit und gesetzliche Grundfristen nicht berechenbar.';

            return ['ok' => true, 'lines' => $lines, 'disclaimer' => $disclaimer];
        }

        $start = date_create($startRaw);
        if (!$start instanceof DateTimeInterface) {
            $lines[] = 'Vertragsbeginn/Eintritt ungültig.';

            return ['ok' => true, 'lines' => $lines, 'disclaimer' => $disclaimer];
        }

        $diff = $start->diff($asOfDt);
        $months = ($diff->y * 12) + $diff->m;
        if ($diff->invert === 1) {
            $lines[] = 'Stichtag liegt vor Vertragsbeginn.';

            return ['ok' => true, 'lines' => $lines, 'disclaimer' => $disclaimer];
        }

        $lines[] = 'Betriebszugehörigkeit zum '
            . $asOfDt->format('d.m.Y') . ': ' . $diff->y . ' Jahr(e), ' . $diff->m . ' Monat(e)'
            . ($diff->d > 0 ? ', ' . $diff->d . ' Tag(e)' : '') . '.';

        // Grobe gesetzliche Grundkündigungsfrist Arbeitgeber nach BGB § 622 Abs. 2 (Kalendermonatsende),
        // ohne Wartezeit-/Tarif-/Sonderfälle — nur Hinweis.
        if ($months < 2) {
            $notice = 'in der Regel 2 Wochen (Probezeit/Wartezeit prüfen)';
        } elseif ($diff->y < 5) {
            $notice = 'in der Regel 1 Monat zum Monatsende';
        } elseif ($diff->y < 8) {
            $notice = 'in der Regel 2 Monate zum Monatsende';
        } elseif ($diff->y < 10) {
            $notice = 'in der Regel 3 Monate zum Monatsende';
        } elseif ($diff->y < 12) {
            $notice = 'in der Regel 4 Monate zum Monatsende';
        } elseif ($diff->y < 15) {
            $notice = 'in der Regel 5 Monate zum Monatsende';
        } else {
            $notice = 'in der Regel 6 Monate zum Monatsende';
        }
        $lines[] = 'Gesetzliche Orientierung Arbeitgeberkündigung (BGB § 622): ' . $notice . '.';
        $lines[] = 'Arbeitnehmerkündigung gesetzlich oft 4 Wochen zum 15. oder Monatsende — Vertrag kann abweichen.';
        $lines[] = 'Zugang der Erklärung entscheidet über den Fristenlauf — bitte Zugang dokumentieren.';

        return ['ok' => true, 'lines' => $lines, 'disclaimer' => $disclaimer];
    }
}
