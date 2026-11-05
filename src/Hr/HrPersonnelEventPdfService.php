<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF für Abmahnung / Kündigung / Aufhebung (Briefpapier).
 */
final class HrPersonnelEventPdfService
{
    /**
     * @param array<string, string> $placeholders aus EmploymentContractContext
     * @param array<string, mixed> $payload
     */
    public static function render(array $placeholders, array $payload): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'PDF-Bibliothek (Dompdf) nicht geladen. Bitte vendor/ auf dem Server deployen.'
            );
        }

        $kind = (string) ($payload['event_type'] ?? HrPersonnelEventRepository::TYPE_WARNING);
        $bodyHtml = self::bodyHtml($placeholders, $payload, $kind);
        $subject = self::subjectFor($kind, $placeholders);

        $doc = [
            'recipient_lines' => array_values(array_filter([
                (string) ($placeholders['employee_name'] ?? ''),
                (string) ($placeholders['employee_address'] ?? ''),
            ], static fn(string $l): bool => trim($l) !== '')),
            'info' => [
                'our_ref' => 'HR-' . date('Ymd'),
                'our_handler' => '',
                'date' => (string) ($payload['event_date_de'] ?? $placeholders['today'] ?? date('d.m.Y')),
            ],
            'subject' => $subject,
            'greeting' => self::greeting($placeholders),
            'body_html' => $bodyHtml,
            'paragraphs' => [],
            'closing' => 'Mit freundlichen Grüßen',
            'signature' => (string) ($placeholders['employer_name'] ?? ''),
        ];

        $letterCfg = LetterheadSettings::config();
        $html = LetterheadRenderer::renderFullHtml(
            $doc,
            $letterCfg,
            ['show_guides' => false, 'title' => $subject, 'for_pdf' => true]
        );
        $html = self::absolutizeResourceUrls($html);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        LetterheadRenderer::attachPdfPageDecorations($dompdf, $letterCfg);
        $dompdf->render();
        $pdf = $dompdf->output();
        if (!is_string($pdf) || $pdf === '') {
            throw new RuntimeException('PDF-Erzeugung fehlgeschlagen.');
        }

        return $pdf;
    }

    /**
     * @param array<string, string> $p
     * @param array<string, mixed> $payload
     */
    private static function bodyHtml(array $p, array $payload, string $kind): string
    {
        $e = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $parts = [];
        $parts[] = '<p>' . $e(
            'Dieses Schreiben ist ein Entwurfs-/Dokumentationshilfsmittel. '
            . 'Rechtswirkungen hängen vom Einzelfall und dem Zugang ab. Kein Ersatz für Rechtsberatung.'
        ) . '</p>';

        if ($kind === HrPersonnelEventRepository::TYPE_WARNING) {
            $parts[] = '<p>' . $e(
                'hiermit sprechen wir eine Abmahnung wegen des nachfolgenden Sachverhalts aus.'
            ) . '</p>';
            $parts[] = '<p><strong>' . $e('Sachverhalt') . '</strong></p>';
            $parts[] = '<p>' . nl2br($e(trim((string) ($payload['facts_text'] ?? ''))), false) . '</p>';
            $expectation = trim((string) ($payload['expectation_text'] ?? ''));
            if ($expectation === '') {
                $expectation = 'Wir erwarten, dass Sie das beanstandete Verhalten sofort und dauerhaft unterlassen '
                    . 'und die vertraglichen Pflichten vollständig einhalten.';
            }
            $parts[] = '<p><strong>' . $e('Erwartung') . '</strong></p>';
            $parts[] = '<p>' . nl2br($e($expectation), false) . '</p>';
            $threat = trim((string) ($payload['threat_text'] ?? ''));
            if ($threat === '') {
                $threat = 'Für den Wiederholungs- oder einen gleichartigen Fall behalten wir uns arbeitsrechtliche '
                    . 'Maßnahmen bis hin zur Kündigung des Arbeitsverhältnisses vor.';
            }
            $parts[] = '<p><strong>' . $e('Androhung') . '</strong></p>';
            $parts[] = '<p>' . nl2br($e($threat), false) . '</p>';
        } elseif ($kind === HrPersonnelEventRepository::TYPE_TERM_AGREEMENT) {
            $parts[] = '<p>' . $e(
                'hiermit bestätigen wir die einvernehmliche Beendigung des Arbeitsverhältnisses '
                . '(Aufhebungsvertrag) zu den nachfolgenden Bedingungen.'
            ) . '</p>';
            $parts[] = self::termCommon($e, $p, $payload);
            $parts[] = '<p>' . $e(
                'Beide Seiten erklären, dass mit Erfüllung dieses Aufhebungsvertrags wechselseitige Ansprüche '
                . 'aus dem Arbeitsverhältnis erledigt sind, soweit nicht ausdrücklich etwas anderes vereinbart wird.'
            ) . '</p>';
            $parts[] = '<p>' . $e('Unterschrift Arbeitgeber: ______________________') . '</p>';
            $parts[] = '<p>' . $e('Unterschrift Arbeitnehmer/in: ______________________') . '</p>';
        } else {
            $fristlos = $kind === HrPersonnelEventRepository::TYPE_TERM_SUMMARY;
            $parts[] = '<p>' . $e(
                $fristlos
                    ? 'hiermit kündigen wir das mit Ihnen bestehende Arbeitsverhältnis außerordentlich und fristlos.'
                    : 'hiermit kündigen wir das mit Ihnen bestehende Arbeitsverhältnis ordentlich.'
            ) . '</p>';
            $parts[] = self::termCommon($e, $p, $payload);
            if ($fristlos) {
                $parts[] = '<p><strong>' . $e('Begründung (kurz)') . '</strong></p>';
                $parts[] = '<p>' . nl2br($e(trim((string) ($payload['facts_text'] ?? '…'))), false) . '</p>';
            } elseif (trim((string) ($payload['facts_text'] ?? '')) !== '') {
                $parts[] = '<p><strong>' . $e('Weitere Hinweise') . '</strong></p>';
                $parts[] = '<p>' . nl2br($e(trim((string) $payload['facts_text'])), false) . '</p>';
            }
            $parts[] = '<p>' . $e(
                'Bitte beachten Sie ggf. gesetzliche Klagefristen und die Pflicht zur Meldung bei der Agentur für Arbeit.'
            ) . '</p>';
        }

        $parts[] = '<p>' . $e('Arbeitgeber: ' . ($p['employer_name'] ?? '')) . '<br>'
            . $e((string) ($p['employer_address'] ?? '')) . '</p>';

        return implode("\n", $parts);
    }

    /**
     * @param callable(string):string $e
     * @param array<string, string> $p
     * @param array<string, mixed> $payload
     */
    private static function termCommon(callable $e, array $p, array $payload): string
    {
        $end = trim((string) ($payload['end_date_de'] ?? ''));
        $html = '<p>' . $e(
            'Das Arbeitsverhältnis endet am ' . ($end !== '' ? $end : '…')
            . ($p['contract_start'] !== '' ? ' (Beginn war ' . $p['contract_start'] . ')' : '') . '.'
        ) . '</p>';
        $html .= '<p>' . $e(
            'Bitte geben Sie Firmeneigentum, Schlüssel und Zugangsdaten unverzüglich zurück. '
            . 'Resturlaub und offene Ansprüche werden nach den gesetzlichen bzw. vertraglichen Regelungen abgerechnet.'
        ) . '</p>';

        return $html;
    }

    /** @param array<string, string> $p */
    private static function subjectFor(string $kind, array $p): string
    {
        $labels = HrPersonnelEventRepository::typeLabels();
        $base = $labels[$kind] ?? 'Personalvorgang';
        $name = trim((string) ($p['employee_name'] ?? ''));

        return $name !== '' ? $base . ' — ' . $name : $base;
    }

    /** @param array<string, string> $p */
    private static function greeting(array $p): string
    {
        $name = trim((string) ($p['employee_name'] ?? ''));
        if ($name === '') {
            return 'Sehr geehrte Damen und Herren,';
        }

        return 'Sehr geehrte/r ' . $name . ',';
    }

    private static function absolutizeResourceUrls(string $html): string
    {
        if (!class_exists('EmailLayoutSettings')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/\s(src|href)=(["\'])([^"\']+)\2/i',
            static function (array $m): string {
                $url = trim($m[3]);
                if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, '#')) {
                    return $m[0];
                }
                if (preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
                    return $m[0];
                }

                return ' ' . $m[1] . '=' . $m[2] . EmailLayoutSettings::absoluteUrl($url) . $m[2];
            },
            $html
        );
    }
}
