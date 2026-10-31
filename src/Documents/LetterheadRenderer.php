<?php

declare(strict_types=1);

/**
 * A4-Briefpapier-Renderer (B4) — HTML/CSS in mm.
 * Gleiche Basis später für PDF-Export (D5).
 */
final class LetterheadRenderer
{
    public const PAGE_WIDTH_MM = 210.0;
    public const PAGE_HEIGHT_MM = 297.0;

    /**
     * Beispieldokument für Einstellungs-Vorschau.
     *
     * @return array{
     *   recipient_lines: list<string>,
     *   info: array<string, string>,
     *   subject: string,
     *   greeting: string,
     *   paragraphs: list<string>,
     *   closing: string,
     *   signature: string
     * }
     */
    public static function sampleDocument(): array
    {
        $today = date('d.m.Y');

        return [
            'recipient_lines' => [
                'Musterkunde GmbH',
                'z. Hd. Frau Erika Beispiel',
                'Musterstraße 12',
                '12345 Musterstadt',
            ],
            'info' => [
                'their_ref' => 'KD-8842',
                'their_date' => '01.10.2026',
                'their_handler' => 'E. Beispiel',
                'their_case_no' => 'A-2026-11',
                'our_ref' => 'BR-2026-0042',
                'our_handler' => 'Max Mustermann',
                'our_department' => 'Vertrieb',
                'our_case_no' => 'V-7781',
                'our_phone' => '',
                'our_email' => '',
                'date' => $today,
            ],
            'subject' => 'Ihr Auftrag vom 1. Oktober 2026 — Bestätigung',
            'greeting' => self::sampleGreeting(),
            'paragraphs' => [
                'vielen Dank für Ihre Anfrage. Gerne bestätigen wir Ihnen die besprochenen Leistungen wie folgt.',
                'Die Umsetzung erfolgt nach Freigabe innerhalb von zehn Werktagen. Bei Rückfragen erreichen Sie uns unter den angegebenen Kontaktdaten.',
            ],
            'closing' => self::sampleClosing(),
            'signature' => '',
        ];
    }

    private static function sampleGreeting(): string
    {
        if (!class_exists('LetterheadSettings')) {
            return 'Sehr geehrte/r Frau Beispiel,';
        }
        $g = rtrim(LetterheadSettings::resolvedPhrases()['greeting']);
        if ($g === '') {
            return 'Sehr geehrte/r Frau Beispiel,';
        }
        if (preg_match('/,$/u', $g)) {
            return $g;
        }

        return $g . ' Frau Beispiel,';
    }

    private static function sampleClosing(): string
    {
        if (!class_exists('LetterheadSettings')) {
            return "Mit freundlichen Grüßen,\nIhr Team";
        }
        $c = LetterheadSettings::resolvedPhrases()['closing'];

        return $c !== '' ? $c : "Mit freundlichen Grüßen,\nIhr Team";
    }

    /**
     * Vollständiges HTML-Dokument (iframe / Druck / später PDF).
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed>|null $cfg
     * @param array{show_guides?: bool, title?: string, for_pdf?: bool} $options
     */
    public static function renderFullHtml(array $document = [], ?array $cfg = null, array $options = []): string
    {
        $cfg = $cfg ?? LetterheadSettings::config();
        $showGuides = array_key_exists('show_guides', $options)
            ? !empty($options['show_guides'])
            : !empty($cfg['page']['show_din_guides']);
        $forPdf = !empty($options['for_pdf']);
        if ($forPdf) {
            $showGuides = false;
        }
        $title = trim((string) ($options['title'] ?? 'Briefvorschau'));
        if ($title === '') {
            $title = 'Briefvorschau';
        }
        $page = self::renderPage($document, $cfg, ['show_guides' => $showGuides, 'for_pdf' => $forPdf]);
        $css = self::stylesheet($cfg, $showGuides, $forPdf);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bodyClass = 'dg-lh-body' . ($forPdf ? ' dg-lh-body--pdf' : '');

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $safeTitle . '</title>'
            . '<style>' . $css . '</style></head><body class="' . $bodyClass . '">'
            . $page
            . '</body></html>';
    }

    /**
     * Eine A4-Seite als HTML-Fragment.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed>|null $cfg
     * @param array{show_guides?: bool, for_pdf?: bool} $options
     */
    public static function renderPage(array $document = [], ?array $cfg = null, array $options = []): string
    {
        $cfg = $cfg ?? LetterheadSettings::config();
        $doc = array_merge(self::sampleDocument(), $document);
        $party = LetterheadSettings::resolvedCompany($cfg);
        $showGuides = !empty($options['show_guides']);
        $forPdf = !empty($options['for_pdf']);

        if (($doc['signature'] ?? '') === '') {
            $doc['signature'] = $party['legal_name'] !== '' ? $party['legal_name'] : 'Ihre Firma';
        }
        if (($doc['info']['our_phone'] ?? '') === '') {
            $doc['info']['our_phone'] = $party['phone'];
        }
        if (($doc['info']['our_email'] ?? '') === '') {
            $doc['info']['our_email'] = $party['email'];
        }

        $headerHtml = self::renderHeader($cfg, $party);
        // Adresse und Infoblock in einer Tabelle: die höhere Spalte schiebt den
        // Brieftext nach unten. Der Text darunter hat die volle Satzbreite.
        $metaHtml = self::renderMetaFlow($cfg, $doc);
        $bodyHtml = self::renderBody($cfg, $doc, $forPdf);
        $footerHtml = self::renderFooter($cfg, $party);
        $bgHtml = self::renderBackground($cfg);
        $wmHtml = self::renderWatermark($cfg);
        $guidesHtml = $showGuides ? self::renderGuides($cfg) : '';
        $foldHtml = self::renderFoldMarks($cfg);
        $pageNumHtml = self::renderPageNumber($cfg);
        // In der Bildschirmvorschau: Linien bei 297 mm / 594 mm … als Seitengrenzen
        $seamsHtml = $forPdf ? '' : '<div class="dg-lh-page-seams" aria-hidden="true"></div>';

        $pageInner = $bgHtml
            . $wmHtml
            . $guidesHtml
            . ($forPdf ? '' : $foldHtml)
            . $seamsHtml
            . $headerHtml
            . $metaHtml
            . $bodyHtml
            . ($forPdf ? '' : $footerHtml)
            . $pageNumHtml;

        $article = '<article class="dg-lh-page' . ($forPdf ? ' dg-lh-page--pdf' : '') . '">'
            . $pageInner
            . '</article>';

        // Dompdf: fixierte Falz-/Fußzeile außerhalb der fließenden Seite → auf jeder Seite
        if ($forPdf) {
            return $foldHtml . $footerHtml . $article;
        }

        return $article;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $party
     */
    private static function renderHeader(array $cfg, array $party): string
    {
        $header = is_array($cfg['header'] ?? null) ? $cfg['header'] : [];
        if (empty($header['show'])) {
            return '';
        }
        $form = strtoupper((string) ($header['height_form'] ?? 'B')) === 'A' ? 'A' : 'B';
        $logoPos = (string) ($header['logo_position'] ?? 'left');
        if (!in_array($logoPos, ['left', 'center', 'right'], true)) {
            $logoPos = 'left';
        }
        $namePos = (string) ($header['company_name_position'] ?? $logoPos);
        if (!in_array($namePos, ['left', 'center', 'right'], true)) {
            $namePos = 'left';
        }
        $logoH = (float) ($header['logo_max_height_mm'] ?? 18);

        /** @var array{left: list<string>, center: list<string>, right: list<string>} $slots */
        $slots = ['left' => [], 'center' => [], 'right' => []];
        if (!empty($header['show_logo'])) {
            $url = LetterheadSettings::logoUrl($cfg);
            $slots[$logoPos][] = '<img class="dg-lh-logo" src="' . self::e($url) . '" alt="" style="max-height:' . self::mm($logoH) . '">';
        }
        if (!empty($header['show_company_name']) && $party['legal_name'] !== '') {
            $slots[$namePos][] = '<div class="dg-lh-company-name">' . self::e($party['legal_name']) . '</div>';
        }
        if ($slots['left'] === [] && $slots['center'] === [] && $slots['right'] === []) {
            return '';
        }

        $html = '<header class="dg-lh-header dg-lh-header--form-' . $form . '">';
        foreach (['left', 'center', 'right'] as $slot) {
            $html .= '<div class="dg-lh-header__slot dg-lh-header__slot--' . $slot . '">';
            if ($slots[$slot] !== []) {
                $html .= '<div class="dg-lh-header__stack">' . implode('', $slots[$slot]) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</header>';

        return $html;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     */
    private static function addressWindowInnerHtml(array $cfg, array $doc): string
    {
        $sender = is_array($cfg['sender_line'] ?? null) ? $cfg['sender_line'] : [];
        $inner = '';
        if (!empty($sender['show'])) {
            $font = (float) ($sender['font_size_pt'] ?? 8);
            $inner .= '<div class="dg-lh-sender" style="font-size:' . self::pt($font) . '">'
                . self::e(LetterheadSettings::senderLineText($cfg))
                . '</div>';
        }
        $lines = is_array($doc['recipient_lines'] ?? null) ? $doc['recipient_lines'] : [];
        $inner .= '<div class="dg-lh-recipient">';
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $inner .= '<div>' . self::e($line) . '</div>';
        }
        $inner .= '</div>';

        return $inner;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     * @return list<string>
     */
    private static function infoBlockRowsHtml(array $cfg, array $doc): array
    {
        $block = is_array($cfg['info_block'] ?? null) ? $cfg['info_block'] : [];
        if (empty($block['show'])) {
            return [];
        }
        $infoValues = is_array($doc['info'] ?? null) ? $doc['info'] : [];
        $rows = [];
        foreach (LetterheadSettings::infoFieldsSorted($cfg, true) as $field) {
            $key = $field['key'];
            $value = trim((string) ($infoValues[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $rows[] = '<div class="dg-lh-info__row">'
                . '<span class="dg-lh-info__label">' . self::e($field['label']) . '</span>'
                . '<span class="dg-lh-info__value">' . self::e($value) . '</span>'
                . '</div>';
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     */
    private static function renderAddressWindow(array $cfg, array $doc): string
    {
        $window = is_array($cfg['address_window'] ?? null) ? $cfg['address_window'] : [];
        if (empty($window['show'])) {
            return '';
        }
        $left = (float) ($window['left_mm'] ?? LetterheadSettings::ADDRESS_WINDOW_LEFT_MM);
        $top = (float) ($window['top_mm'] ?? 50);
        $width = (float) ($window['width_mm'] ?? LetterheadSettings::ADDRESS_WINDOW_WIDTH_MM);
        $height = (float) ($window['height_mm'] ?? LetterheadSettings::ADDRESS_WINDOW_HEIGHT_MM);

        return '<section class="dg-lh-window" style="left:' . self::mm($left) . ';top:' . self::mm($top)
            . ';width:' . self::mm($width) . ';height:' . self::mm($height) . '">'
            . self::addressWindowInnerHtml($cfg, $doc)
            . '</section>';
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     */
    private static function renderInfoBlock(array $cfg, array $doc): string
    {
        $window = is_array($cfg['address_window'] ?? null) ? $cfg['address_window'] : [];
        $rows = self::infoBlockRowsHtml($cfg, $doc);
        if ($rows === []) {
            return '';
        }
        $top = (float) ($window['top_mm'] ?? 50);
        $left = (float) ($window['left_mm'] ?? 20) + (float) ($window['width_mm'] ?? 85) + 8.0;
        $right = (float) ($cfg['page']['margin_right_mm'] ?? 20);

        return '<aside class="dg-lh-info" style="top:' . self::mm($top) . ';left:' . self::mm($left)
            . ';right:' . self::mm($right) . '">' . implode('', $rows) . '</aside>';
    }

    /**
     * Dompdf-tauglich: Adresse | Infoblock im Fluss (volle Textbreite darunter).
     *
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     */
    private static function renderMetaFlow(array $cfg, array $doc): string
    {
        $window = is_array($cfg['address_window'] ?? null) ? $cfg['address_window'] : [];
        $showWindow = !empty($window['show']);
        $rows = self::infoBlockRowsHtml($cfg, $doc);
        if (!$showWindow && $rows === []) {
            return '';
        }
        $winTop = (float) ($window['top_mm'] ?? 50);
        $marginTop = max(0.0, $winTop - self::headerFlowHeightFromPageMm($cfg));
        $addr = $showWindow
            ? self::addressWindowInnerHtml($cfg, $doc)
            : '';
        $pairs = self::infoPairs($cfg, $doc);
        $style = 'width:100%;table-layout:fixed;border-collapse:collapse;margin-top:' . self::mm($marginTop);
        $html = '<table class="dg-lh-meta" width="100%" cellspacing="0" cellpadding="0" style="' . $style . '">';
        if ($pairs === []) {
            $html .= '<tr><td class="dg-lh-meta__addr" width="100%" valign="top" colspan="3">' . $addr . '</td></tr>';
        } else {
            $first = true;
            foreach ($pairs as $pair) {
                $html .= '<tr>'
                    . '<td class="dg-lh-meta__addr" width="50%" valign="top">' . ($first ? $addr : '&#160;') . '</td>'
                    . '<td class="dg-lh-info__label" width="22%" valign="top">' . self::e($pair['label']) . '</td>'
                    . '<td class="dg-lh-info__value" width="28%" valign="top">' . self::e($pair['value']) . '</td>'
                    . '</tr>';
                $first = false;
            }
        }

        return $html . '</table>';
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     * @return list<array{label: string, value: string}>
     */
    private static function infoPairs(array $cfg, array $doc): array
    {
        $block = is_array($cfg['info_block'] ?? null) ? $cfg['info_block'] : [];
        if (empty($block['show'])) {
            return [];
        }
        $infoValues = is_array($doc['info'] ?? null) ? $doc['info'] : [];
        $pairs = [];
        foreach (LetterheadSettings::infoFieldsSorted($cfg, true) as $field) {
            $value = trim((string) ($infoValues[$field['key']] ?? ''));
            if ($value === '') {
                continue;
            }
            $pairs[] = [
                'label' => (string) $field['label'],
                'value' => $value,
            ];
        }

        return $pairs;
    }

    /**
     * Vertikaler Start des Brieftexts ab Blattoberkante (unter Fenster und Infoblock).
     *
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     */
    private static function bodyContentTopFromPageMm(array $cfg, array $doc = []): float
    {
        $window = is_array($cfg['address_window'] ?? null) ? $cfg['address_window'] : [];
        $winTop = (float) ($window['top_mm'] ?? 50);
        $winBottom = $winTop + (float) ($window['height_mm'] ?? 45);
        $infoRows = count(self::infoBlockRowsHtml($cfg, $doc));
        // ~5,2 mm je Infozeile (8,5 pt + Abstand) — Dompdf
        $infoBottom = $infoRows > 0 ? $winTop + ($infoRows * 5.2) : $winTop;

        return max($winBottom, $infoBottom) + 5.0;
    }

    /**
     * Platz, den der Briefkopf im normalen Dokumentfluss ab Blattoberkante schon belegt
     * (Header hat margin-top: −margin_top und sitzt damit am Blattrand).
     *
     * @param array<string, mixed> $cfg
     */
    private static function headerFlowHeightFromPageMm(array $cfg): float
    {
        $header = is_array($cfg['header'] ?? null) ? $cfg['header'] : [];
        if (empty($header['show'])) {
            return (float) ($cfg['page']['margin_top_mm'] ?? 17);
        }
        $form = strtoupper((string) ($header['height_form'] ?? 'B')) === 'A' ? 27.0 : 45.0;
        // Dompdf: min-height oft überschritten (Logo + 2 Namenszeilen) — Puffer
        $logoH = (float) ($header['logo_max_height_mm'] ?? 18);

        return max($form, $logoH + 16.0) + 4.0;
    }

    /**
     * TipTap-/Editor-HTML: feste Breiten und Block-Schrumpfung entfernen.
     */
    private static function sanitizeBodyHtml(string $bodyHtml): string
    {
        $safe = class_exists('MailHtmlSanitizer')
            ? MailHtmlSanitizer::clean($bodyHtml)
            : strip_tags($bodyHtml, '<p><br><strong><b><em><i><u><s><del><mark><ul><ol><li><table><thead><tbody><tr><th><td><img><span><a>');
        // TipTap/Editor-Breiten nicht in den Brief übernehmen
        $safe = (string) preg_replace('/(?:max-|min-)?width\s*:\s*[^;"]+;?/i', '', $safe);
        $safe = (string) preg_replace('/\s(?:width|max-width|min-width)\s*=\s*["\'][^"\']*["\']/i', '', $safe);
        $safe = (string) preg_replace('/\sstyle\s*=\s*["\']\s*["\']/i', '', $safe);

        return $safe;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $doc
     */
    private static function renderBody(array $cfg, array $doc, bool $forPdf = false): string
    {
        $subjectCfg = is_array($cfg['subject'] ?? null) ? $cfg['subject'] : [];
        // Meta-Tabelle liegt im Fluss: Text beginnt darunter, über die volle Satzbreite.
        $padStyle = 'padding-top:4mm';
        $subject = trim((string) ($doc['subject'] ?? ''));
        $html = '<section class="dg-lh-body-content" style="' . $padStyle . '">';
        if ($subject !== '') {
            $label = !empty($subjectCfg['show_label']) ? '<span class="dg-lh-subject__label">Betreff: </span>' : '';
            $weight = !empty($subjectCfg['bold']) ? ' dg-lh-subject--bold' : '';
            $html .= '<div class="dg-lh-subject' . $weight . '">' . $label . self::e($subject) . '</div>';
        }
        $greeting = trim((string) ($doc['greeting'] ?? ''));
        if ($greeting !== '') {
            $html .= '<p class="dg-lh-greeting">' . self::e($greeting) . '</p>';
        }
        $bodyHtml = trim((string) ($doc['body_html'] ?? ''));
        if ($bodyHtml !== '') {
            $html .= '<div class="dg-lh-rich">' . self::sanitizeBodyHtml($bodyHtml) . '</div>';
        } else {
            $paragraphs = is_array($doc['paragraphs'] ?? null) ? $doc['paragraphs'] : [];
            foreach ($paragraphs as $p) {
                $p = trim((string) $p);
                if ($p === '') {
                    continue;
                }
                $html .= '<p class="dg-lh-p">' . nl2br(self::e($p)) . '</p>';
            }
        }
        $closing = trim((string) ($doc['closing'] ?? ''));
        if ($closing !== '') {
            $html .= '<p class="dg-lh-closing">' . nl2br(self::e($closing), false) . '</p>';
        }
        $signature = trim((string) ($doc['signature'] ?? ''));
        // DIN: nach Gruß zwei Leerzeilen Platz für Unterschrift, dann Name
        if ($closing !== '' || $signature !== '') {
            $html .= '<div class="dg-lh-sign-gap" aria-hidden="true">&nbsp;<br>&nbsp;</div>';
        }
        if ($signature !== '') {
            $html .= '<p class="dg-lh-signature">' . nl2br(self::e($signature), false) . '</p>';
        }
        $html .= '</section>';

        return $html;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $party
     */
    private static function renderFooter(array $cfg, array $party): string
    {
        $footer = is_array($cfg['footer'] ?? null) ? $cfg['footer'] : [];
        if (empty($footer['show'])) {
            return '';
        }
        $cols = max(1, min(4, (int) ($footer['columns'] ?? 3)));
        $font = (float) ($footer['font_size_pt'] ?? 7);
        /** @var list<list<array{type: string, value: string}>> $columns */
        $columns = array_fill(0, $cols, []);
        $i = 0;
        $push = static function (string $text) use (&$columns, &$i, $cols): void {
            $text = trim($text);
            if ($text === '') {
                return;
            }
            $columns[$i % $cols][] = ['type' => 'text', 'value' => $text];
            $i++;
        };
        $pushHtml = static function (string $html) use (&$columns, &$i, $cols): void {
            if (trim(strip_tags($html)) === '') {
                return;
            }
            $columns[$i % $cols][] = ['type' => 'html', 'value' => $html];
            $i++;
        };

        if (!empty($footer['show_legal_mandatory'])) {
            foreach (self::legalMandatoryLines($party) as $line) {
                $push($line);
            }
        }
        if (!empty($footer['show_address'])) {
            $addr = trim(implode(', ', array_filter([
                $party['street'],
                trim($party['postal'] . ' ' . $party['city']),
            ])));
            $push($addr);
        }
        if (!empty($footer['show_phone']) && $party['phone'] !== '') {
            $telHref = self::telHref($party['phone']);
            if ($telHref !== '') {
                $pushHtml('Tel. ' . self::footerAnchor($telHref, $party['phone']));
            } else {
                $push('Tel. ' . $party['phone']);
            }
        }
        if (!empty($footer['show_email']) && $party['email'] !== '') {
            $email = trim((string) $party['email']);
            $pushHtml(self::footerAnchor('mailto:' . $email, $email));
        }
        if (!empty($footer['show_website']) && $party['website'] !== '') {
            $web = trim((string) $party['website']);
            $webHref = preg_match('#^https?://#i', $web) ? $web : 'https://' . ltrim($web, '/');
            $pushHtml(self::footerAnchor($webHref, $web, true));
        }
        if (!empty($footer['show_tax_number']) && $party['tax_number'] !== '') {
            $push('Steuernr. ' . $party['tax_number']);
        }
        if (!empty($footer['show_vat_id']) && $party['vat_id'] !== '') {
            $push('USt-IdNr. ' . $party['vat_id']);
        }
        if (!empty($footer['show_bank'])) {
            foreach ($party['bank_lines'] as $bankLine) {
                $push((string) $bankLine);
            }
        }
        $linkLabels = [
            'impressum' => 'Impressum',
            'datenschutz' => 'Datenschutz',
            'agb' => 'AGB',
            'widerruf' => 'Widerruf',
        ];
        $linkBits = [];
        foreach ($linkLabels as $key => $label) {
            $flagKey = 'show_link_' . $key;
            $enabled = array_key_exists($flagKey, $footer)
                ? !empty($footer[$flagKey])
                : !empty($footer['show_links']);
            if (!$enabled) {
                continue;
            }
            $url = trim((string) ($party['links'][$key] ?? ''));
            if ($url === '') {
                continue;
            }
            $linkBits[] = self::footerAnchor($url, $label, true);
        }
        if ($linkBits !== []) {
            $pushHtml(implode(' · ', $linkBits));
        }

        $hasContent = false;
        foreach ($columns as $col) {
            if ($col !== []) {
                $hasContent = true;
                break;
            }
        }
        if (!$hasContent) {
            return '';
        }

        $rule = !empty($footer['show_rule']) ? ' dg-lh-footer--rule' : '';
        $html = '<footer class="dg-lh-footer' . $rule . '" style="font-size:' . self::pt($font) . '">';
        $html .= '<div class="dg-lh-footer__grid" style="grid-template-columns:repeat(' . $cols . ',1fr)">';
        foreach ($columns as $col) {
            $html .= '<div class="dg-lh-footer__col">';
            foreach ($col as $line) {
                if (($line['type'] ?? '') === 'html') {
                    $html .= '<div>' . $line['value'] . '</div>';
                } else {
                    $html .= '<div>' . self::e((string) ($line['value'] ?? '')) . '</div>';
                }
            }
            $html .= '</div>';
        }
        $html .= '</div></footer>';

        return $html;
    }

    /**
     * @param array<string, mixed> $party
     * @return list<string>
     */
    public static function legalMandatoryLines(array $party): array
    {
        $lines = [];
        // Nur Firmenname: Rechtsform steckt meist im Namen; Sitz/Ort steht bereits in der Anschrift.
        $head = trim((string) ($party['legal_name'] ?? ''));
        if ($head !== '') {
            $lines[] = $head;
        }
        $reg = trim(implode(' ', array_filter([
            $party['register_court'],
            $party['register_number'],
        ])));
        if ($reg !== '') {
            $lines[] = $reg;
        }
        if ($party['managing_directors'] !== '') {
            $label = in_array($party['company_type'], ['gmbh', 'gmbh_igr', 'ug', 'ug_igr'], true)
                ? 'Geschäftsführer'
                : 'Vertretung';
            $lines[] = $label . ': ' . $party['managing_directors'];
        }
        if ($party['supervisory_chair'] !== '') {
            $lines[] = 'Aufsichtsratsvorsitz: ' . $party['supervisory_chair'];
        }
        if ($party['share_capital'] !== '') {
            $cap = 'Stammkapital: ' . $party['share_capital'];
            if ($party['outstanding_contributions'] !== '') {
                $cap .= ' · Ausstehend: ' . $party['outstanding_contributions'];
            }
            $lines[] = $cap;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function renderBackground(array $cfg): string
    {
        $bg = is_array($cfg['background'] ?? null) ? $cfg['background'] : [];
        $mode = (string) ($bg['mode'] ?? 'none');
        if ($mode === 'none' || $mode === 'watermark') {
            return '';
        }
        $opacity = (float) ($bg['opacity'] ?? 0.08);
        $style = 'opacity:' . max(0.02, min(0.5, $opacity)) . ';';
        if ($mode === 'color') {
            $color = trim((string) ($bg['color'] ?? ''));
            if ($color === '') {
                return '';
            }

            return '<div class="dg-lh-bg dg-lh-bg--color" style="' . $style . 'background:' . self::e($color) . '"></div>';
        }
        if ($mode !== 'image') {
            return '';
        }

        return self::renderMediaLayer(
            'dg-lh-bg dg-lh-bg--image',
            trim((string) ($bg['media_id'] ?? '')),
            (float) ($bg['opacity'] ?? 0.08),
            (string) ($bg['fit'] ?? 'contain')
        );
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function renderWatermark(array $cfg): string
    {
        $wm = is_array($cfg['watermark'] ?? null) ? $cfg['watermark'] : [];
        // Legacy: Hintergrund-Modus „watermark“ ohne eigenen Block
        $legacyBg = is_array($cfg['background'] ?? null) ? $cfg['background'] : [];
        if (empty($wm['enabled']) && (string) ($legacyBg['mode'] ?? '') === 'watermark') {
            $wm = [
                'enabled' => true,
                'media_id' => $legacyBg['media_id'] ?? '',
                'opacity' => $legacyBg['opacity'] ?? 0.12,
                'fit' => $legacyBg['fit'] ?? 'contain',
            ];
        }
        if (empty($wm['enabled'])) {
            return '';
        }

        return self::renderMediaLayer(
            'dg-lh-bg dg-lh-bg--watermark',
            trim((string) ($wm['media_id'] ?? '')),
            (float) ($wm['opacity'] ?? 0.12),
            (string) ($wm['fit'] ?? 'contain')
        );
    }

    private static function renderMediaLayer(string $class, string $mediaId, float $opacity, string $fit): string
    {
        if ($mediaId === '' || !class_exists('MediaRepository')) {
            return '';
        }
        MediaRepository::ensureTables();
        $item = MediaRepository::find($mediaId);
        $url = is_array($item) ? (string) ($item['url'] ?? '') : '';
        if ($url === '') {
            return '';
        }
        $size = $fit === 'cover' ? 'cover' : ($fit === 'tile' ? 'auto' : 'contain');
        $repeat = $fit === 'tile' ? 'repeat' : 'no-repeat';
        $style = 'opacity:' . max(0.02, min(0.5, $opacity)) . ';'
            . 'background-image:url(' . self::e($url) . ');background-size:' . $size
            . ';background-repeat:' . $repeat . ';background-position:center';

        return '<div class="' . self::e($class) . '" style="' . $style . '"></div>';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function renderGuides(array $cfg): string
    {
        $window = is_array($cfg['address_window'] ?? null) ? $cfg['address_window'] : [];
        $left = (float) ($window['left_mm'] ?? 20);
        $top = (float) ($window['top_mm'] ?? 50);
        $width = (float) ($window['width_mm'] ?? 85);
        $height = (float) ($window['height_mm'] ?? 45);
        $ml = (float) ($cfg['page']['margin_left_mm'] ?? 24.1);
        $mr = (float) ($cfg['page']['margin_right_mm'] ?? 20);
        $mt = (float) ($cfg['page']['margin_top_mm'] ?? 17);
        $mb = (float) ($cfg['page']['margin_bottom_mm'] ?? 16.7);

        return '<div class="dg-lh-guides" aria-hidden="true">'
            . '<div class="dg-lh-guide dg-lh-guide--margin" style="left:' . self::mm($ml) . ';right:' . self::mm($mr)
            . ';top:' . self::mm($mt) . ';bottom:' . self::mm($mb) . '"></div>'
            . '<div class="dg-lh-guide dg-lh-guide--window" style="left:' . self::mm($left) . ';top:' . self::mm($top)
            . ';width:' . self::mm($width) . ';height:' . self::mm($height) . '"></div>'
            . '<div class="dg-lh-guide-label" style="left:' . self::mm($left) . ';top:' . self::mm(max(2, $top - 5)) . '">DIN-Fenster</div>'
            . '</div>';
    }

    /**
     * DIN-Falzmarken (Knicklinien) und optionale Lochmarke — linker Blattrand.
     * Positionen ab oberem Blattrand: 105 mm / 210 mm (DIN 5008), Loch 148,5 mm.
     *
     * @param array<string, mixed> $cfg
     */
    private static function renderFoldMarks(array $cfg): string
    {
        $page = is_array($cfg['page'] ?? null) ? $cfg['page'] : [];
        $showFold = array_key_exists('show_fold_marks', $page)
            ? !empty($page['show_fold_marks'])
            : true;
        $showPunch = !empty($page['show_punch_mark']);
        if (!$showFold && !$showPunch) {
            return '';
        }

        // Absolute zum Blattrand (nicht Content-Box): Dompdf + overflow-sicher als Balken
        $html = '<div class="dg-lh-fold-marks" aria-hidden="true">';
        if ($showFold) {
            $html .= '<div class="dg-lh-fold" style="top:105mm" title="Falzmarke 105 mm"></div>';
            $html .= '<div class="dg-lh-fold" style="top:210mm" title="Falzmarke 210 mm"></div>';
        }
        if ($showPunch) {
            $html .= '<div class="dg-lh-fold dg-lh-fold--punch" style="top:148.5mm" title="Lochmarke"></div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Seitenzahl — CSS counter(page[/pages]) für Druck; Vorschau zeigt Beispieltext.
     *
     * @param array<string, mixed> $cfg
     */
    private static function renderPageNumber(array $cfg): string
    {
        $page = is_array($cfg['page'] ?? null) ? $cfg['page'] : [];
        $pn = is_array($page['page_numbers'] ?? null) ? $page['page_numbers'] : [];
        if (empty($pn['show'])) {
            return '';
        }
        $format = (string) ($pn['format'] ?? 'seite_n_von_N');
        $position = (string) ($pn['position'] ?? 'center');
        if (!isset(LetterheadSettings::pageNumberPositionOptions()[$position])) {
            $position = 'center';
        }
        $sample = match ($format) {
            'n' => '1',
            'n_slash_N' => '1 / 1',
            'n_von_N' => '1 von 1',
            'seite_n' => 'Seite 1',
            default => 'Seite 1 von 1',
        };
        $mod = match ($format) {
            'n' => 'n',
            'n_slash_N' => 'n-slash-N',
            'n_von_N' => 'n-von-N',
            'seite_n' => 'seite-n',
            default => 'seite-n-von-N',
        };

        return '<div class="dg-lh-pagenum dg-lh-pagenum--' . self::e($position)
            . ' dg-lh-pagenum--fmt-' . self::e($mod) . '" aria-hidden="true">'
            . '<span class="dg-lh-pagenum__sample">' . self::e($sample) . '</span>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function stylesheet(array $cfg, bool $showGuides, bool $forPdf = false): string
    {
        $ml = (float) ($cfg['page']['margin_left_mm'] ?? 24.1);
        $mr = (float) ($cfg['page']['margin_right_mm'] ?? 20);
        $mt = (float) ($cfg['page']['margin_top_mm'] ?? 17);
        $mb = (float) ($cfg['page']['margin_bottom_mm'] ?? 16.7);
        $headerForm = strtoupper((string) ($cfg['header']['height_form'] ?? 'B')) === 'A' ? 27.0 : 45.0;
        $contentW = max(80.0, self::PAGE_WIDTH_MM - $ml - $mr);
        // Fußzeile ist im PDF fixiert → Platz am unteren Rand jeder Seite freilassen
        $footerBandMm = 24.0;
        $pdfPadBottom = $mb + $footerBandMm;

        $pdfCss = '';
        if ($forPdf) {
            $pdfCss = <<<PDFCSS

/* Dompdf: kein Browser-Preview-Rahmen, Fußzeile pro Seite, kein CSS-Grid/Flex */
body.dg-lh-body--pdf,
html body.dg-lh-body--pdf { background: #fff !important; }
body.dg-lh-body--pdf .dg-lh-page {
  display: block !important;
  margin: 0 !important;
  box-shadow: none !important;
  min-height: 0 !important;
  height: auto !important;
  overflow: visible !important;
  width: auto !important;
  max-width: 210mm !important;
  /* Satzspiegel über Padding — rechter Rand bleibt 20 mm (kein Überlauf) */
  padding-top: {$mt}mm !important;
  padding-right: {$mr}mm !important;
  padding-bottom: {$pdfPadBottom}mm !important;
  padding-left: {$ml}mm !important;
  box-sizing: border-box !important;
}
/* Falzmarken am linken Blattrand (DIN 5008), nicht am Content-Rand */
body.dg-lh-body--pdf .dg-lh-fold-marks {
  display: block !important;
  position: fixed !important;
  left: 0 !important;
  top: 0 !important;
  width: 10mm !important;
  height: 297mm !important;
  z-index: 6 !important;
}
body.dg-lh-body--pdf .dg-lh-fold {
  position: absolute !important;
  left: 0 !important;
  width: 7mm !important;
  height: 0.45mm !important;
  background: #111 !important;
}
body.dg-lh-body--pdf .dg-lh-fold--punch {
  width: 4mm !important;
  height: 0.4mm !important;
  background: #333 !important;
}
body.dg-lh-body--pdf .dg-lh-header {
  display: table !important;
  width: 100% !important;
  table-layout: fixed !important;
  /* Kein negativer Rand — sonst treibt Dompdf die Satzbreite über den rechten Rand */
  margin: 0 0 4mm 0 !important;
  padding: 0 !important;
}
body.dg-lh-body--pdf .dg-lh-header__slot {
  display: table-cell !important;
  vertical-align: middle !important;
  width: 33.33% !important;
}
body.dg-lh-body--pdf .dg-lh-header__stack {
  display: block !important;
}
body.dg-lh-body--pdf .dg-lh-meta {
  width: 100% !important;
  border-collapse: collapse !important;
  table-layout: fixed !important;
  margin-bottom: 2mm !important;
}
body.dg-lh-body--pdf .dg-lh-meta__addr {
  width: 50% !important;
  vertical-align: top !important;
  box-sizing: border-box !important;
  padding-right: 3mm !important;
  font-size: 10pt !important;
  line-height: 1.35 !important;
}
body.dg-lh-body--pdf .dg-lh-info__label,
body.dg-lh-body--pdf .dg-lh-info__value {
  display: table-cell !important;
  vertical-align: top !important;
  font-size: 8.5pt !important;
  line-height: 1.35 !important;
  padding: 0 0 1.2mm 0 !important;
  word-wrap: break-word !important;
}
body.dg-lh-body--pdf .dg-lh-info__label { width: 22% !important; color: #555 !important; padding-right: 2mm !important; }
body.dg-lh-body--pdf .dg-lh-info__value { width: 28% !important; font-weight: 600 !important; }
body.dg-lh-body--pdf .dg-lh-footer {
  position: fixed !important;
  bottom: {$mb}mm !important;
  left: {$ml}mm !important;
  right: {$mr}mm !important;
}
body.dg-lh-body--pdf .dg-lh-footer__grid {
  display: table !important;
  width: 100% !important;
  table-layout: fixed !important;
}
body.dg-lh-body--pdf .dg-lh-footer__col {
  display: table-cell !important;
  vertical-align: top !important;
  padding-right: 2mm !important;
}
body.dg-lh-body--pdf .dg-lh-body-content,
body.dg-lh-body--pdf .dg-lh-meta,
body.dg-lh-body--pdf .dg-lh-subject,
body.dg-lh-body--pdf .dg-lh-greeting,
body.dg-lh-body--pdf .dg-lh-p,
body.dg-lh-body--pdf .dg-lh-closing,
body.dg-lh-body--pdf .dg-lh-signature,
body.dg-lh-body--pdf .dg-lh-rich,
body.dg-lh-body--pdf .dg-lh-rich p {
  max-width: {$contentW}mm !important;
}
body.dg-lh-body--pdf .dg-lh-body-content {
  position: relative !important;
  page-break-inside: auto;
  display: block !important;
  width: 100% !important;
  box-sizing: border-box !important;
  clear: both !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}
body.dg-lh-body--pdf .dg-lh-subject,
body.dg-lh-body--pdf .dg-lh-greeting,
body.dg-lh-body--pdf .dg-lh-closing,
body.dg-lh-body--pdf .dg-lh-signature,
body.dg-lh-body--pdf .dg-lh-sign-gap {
  page-break-inside: avoid;
}
body.dg-lh-body--pdf .dg-lh-p,
body.dg-lh-body--pdf .dg-lh-rich p {
  orphans: 2;
  widows: 2;
}
body.dg-lh-body--pdf .dg-lh-subject,
body.dg-lh-body--pdf .dg-lh-greeting,
body.dg-lh-body--pdf .dg-lh-p,
body.dg-lh-body--pdf .dg-lh-closing,
body.dg-lh-body--pdf .dg-lh-signature,
body.dg-lh-body--pdf .dg-lh-rich,
body.dg-lh-body--pdf .dg-lh-rich p,
body.dg-lh-body--pdf .dg-lh-rich ul,
body.dg-lh-body--pdf .dg-lh-rich ol,
body.dg-lh-body--pdf .dg-lh-rich table {
  width: 100% !important;
  max-width: {$contentW}mm !important;
  box-sizing: border-box !important;
}
PDFCSS;
        }

        return <<<CSS
@page { size: 210mm 297mm; margin: 0; }
html, body.dg-lh-body { margin: 0; padding: 0; background: #e8eaef; }
body.dg-lh-body { font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: #1a1a1a; }
.dg-lh-page {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 210mm;
  min-height: 297mm;
  height: auto;
  margin: 8mm auto;
  background: #fff;
  box-sizing: border-box;
  padding: {$mt}mm {$mr}mm {$mb}mm {$ml}mm;
  box-shadow: 0 2px 12px rgba(0,0,0,.12);
  overflow: visible;
}
.dg-lh-bg { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
.dg-lh-bg--watermark { z-index: 0; }
.dg-lh-header, .dg-lh-window, .dg-lh-info, .dg-lh-body-content, .dg-lh-footer, .dg-lh-guides {
  position: relative; z-index: 1;
}
/* Falzmarken: linker Blattrand (DIN 5008), absolute zum Padding-Rand = Blattkante */
.dg-lh-fold-marks {
  position: absolute;
  left: 0;
  top: 0;
  width: 10mm;
  height: 297mm;
  z-index: 6;
  pointer-events: none;
}
.dg-lh-fold {
  position: absolute;
  left: 0;
  width: 7mm;
  height: 0.45mm;
  background: #111;
  border: none;
  font-size: 0;
  line-height: 0;
}
.dg-lh-fold--punch {
  width: 4mm;
  height: 0.4mm;
  background: #333;
}
.dg-lh-pagenum {
  position: absolute;
  bottom: 6mm;
  left: {$ml}mm;
  right: {$mr}mm;
  z-index: 3;
  font-size: 8pt;
  line-height: 1;
  color: #333;
  pointer-events: none;
}
.dg-lh-pagenum--left { text-align: left; }
.dg-lh-pagenum--center { text-align: center; }
.dg-lh-pagenum--right { text-align: right; }
.dg-lh-pagenum__sample { display: inline; }
.dg-lh-header {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  align-items: center;
  column-gap: 3mm;
  min-height: {$headerForm}mm;
  margin: -{$mt}mm -{$mr}mm 4mm -{$ml}mm;
  padding: 3mm {$mr}mm 3mm {$ml}mm;
  box-sizing: border-box;
}
.dg-lh-header__slot { min-width: 0; }
.dg-lh-header__slot--left { justify-self: start; text-align: left; }
.dg-lh-header__slot--center { justify-self: center; text-align: center; }
.dg-lh-header__slot--right { justify-self: end; text-align: right; }
.dg-lh-header__stack {
  display: flex;
  flex-direction: column;
  align-items: inherit;
  gap: 2mm;
}
.dg-lh-header__slot--left .dg-lh-header__stack { align-items: flex-start; }
.dg-lh-header__slot--center .dg-lh-header__stack { align-items: center; }
.dg-lh-header__slot--right .dg-lh-header__stack { align-items: flex-end; }
.dg-lh-logo { max-width: 55mm; width: auto; height: auto; display: block; }
.dg-lh-company-name { font-size: 14pt; font-weight: 700; line-height: 1.2; }
.dg-lh-window {
  position: absolute;
  box-sizing: border-box;
  padding: 1.5mm 2mm;
  overflow: hidden;
  font-size: 10pt;
  line-height: 1.35;
}
.dg-lh-sender {
  border-bottom: 0.2mm solid #333;
  margin-bottom: 1.5mm;
  padding-bottom: 0.5mm;
  color: #333;
  white-space: normal;
  overflow: visible;
}
.dg-lh-info__label,
.dg-lh-info__value { vertical-align: top; padding: 0 0 1.2mm 0; overflow-wrap: anywhere; word-wrap: break-word; }
.dg-lh-info__label { width: 22%; color: #555; padding-right: 2mm; font-size: 8.5pt; line-height: 1.35; }
.dg-lh-info__value { width: 28%; font-weight: 600; font-size: 8.5pt; line-height: 1.35; }
.dg-lh-meta {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  margin: 0 0 2mm 0;
}
.dg-lh-meta__addr,
.dg-lh-info__label,
.dg-lh-info__value {
  vertical-align: top;
  box-sizing: border-box;
}
.dg-lh-meta__addr { width: 50%; padding-right: 3mm; font-size: 10pt; line-height: 1.35; }
.dg-lh-body-content {
  display: block;
  width: 100%;
  max-width: none;
  box-sizing: border-box;
  clear: both;
  font-size: 11pt;
  line-height: 1.45;
}
.dg-lh-subject { margin: 0 0 5mm; width: 100%; max-width: none; }
.dg-lh-subject--bold { font-weight: 700; }
.dg-lh-subject__label { font-weight: 400; color: #555; }
.dg-lh-greeting, .dg-lh-p, .dg-lh-closing, .dg-lh-signature { margin: 0 0 3.5mm; width: 100%; max-width: none; }
.dg-lh-closing { margin-bottom: 0; }
.dg-lh-sign-gap {
  display: block;
  height: 2.9em;
  line-height: 1.45;
  font-size: 11pt;
  margin: 0 0 0;
  width: 100%;
}
.dg-lh-signature { margin-top: 0; }
.dg-lh-rich {
  display: block;
  width: 100%;
  max-width: none;
  box-sizing: border-box;
  font-size: 11pt;
  line-height: 1.45;
  margin: 0 0 3.5mm;
}
.dg-lh-rich p { margin: 0 0 3mm; width: 100%; max-width: none; }
.dg-lh-rich ul, .dg-lh-rich ol { margin: 0 0 3mm; padding-left: 6mm; width: 100%; max-width: 100%; box-sizing: border-box; }
.dg-lh-rich table, .dg-writer-table { border-collapse: collapse; width: 100%; max-width: 100%; margin: 0 0 3mm; font-size: 10pt; }
.dg-lh-rich th, .dg-lh-rich td, .dg-writer-table th, .dg-writer-table td { border: 0.2mm solid #666; padding: 1.5mm 2mm; vertical-align: top; }
.dg-lh-rich img, .dg-writer-img { max-width: 100%; height: auto; vertical-align: middle; }
.dg-lh-footer {
  position: relative;
  margin-top: auto;
  width: 100%;
  flex-shrink: 0;
  color: #333;
  line-height: 1.3;
}
.dg-lh-footer--rule { border-top: 0.25mm solid #999; padding-top: 2mm; }
.dg-lh-footer__grid { display: grid; gap: 3mm; }
.dg-lh-footer a,
.dg-lh-footer__link {
  color: inherit;
  text-decoration: underline;
  cursor: pointer;
  pointer-events: auto;
}
.dg-lh-page-seams {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  z-index: 4;
  pointer-events: none;
  background-image: repeating-linear-gradient(
    to bottom,
    transparent 0,
    transparent calc(297mm - 0.35mm),
    #94a3b8 calc(297mm - 0.35mm),
    #94a3b8 297mm
  );
}
.dg-lh-guides { position: absolute; inset: 0; z-index: 2; pointer-events: none; }
.dg-lh-guide--margin {
  position: absolute;
  border: 0.3mm dashed #3b82f6;
  opacity: .55;
}
.dg-lh-guide--window {
  position: absolute;
  border: 0.35mm solid #ef4444;
  opacity: .7;
  background: rgba(239,68,68,.04);
}
.dg-lh-guide-label {
  position: absolute;
  font-size: 7pt;
  color: #b91c1c;
  background: rgba(255,255,255,.85);
  padding: 0.3mm 1mm;
}
@media print {
  html, body.dg-lh-body { background: #fff; }
  .dg-lh-page {
    display: block;
    margin: 0;
    box-shadow: none;
    overflow: visible;
    height: auto;
    min-height: 297mm;
    padding-bottom: {$pdfPadBottom}mm;
  }
  .dg-lh-guides,
  .dg-lh-page-seams { display: none !important; }
  .dg-lh-fold-marks { z-index: 6; display: block !important; }
  .dg-lh-footer {
    position: fixed;
    left: {$ml}mm;
    right: {$mr}mm;
    bottom: {$mb}mm;
    margin-top: 0;
    width: auto;
  }
  .dg-lh-pagenum {
    position: fixed;
    bottom: 8mm;
    left: {$ml}mm;
    right: {$mr}mm;
  }
  .dg-lh-pagenum__sample { display: none !important; }
  .dg-lh-pagenum--fmt-n::after { content: counter(page); }
  .dg-lh-pagenum--fmt-n-slash-N::after { content: counter(page) " / " counter(pages); }
  .dg-lh-pagenum--fmt-n-von-N::after { content: counter(page) " von " counter(pages); }
  .dg-lh-pagenum--fmt-seite-n::after { content: "Seite " counter(page); }
  .dg-lh-pagenum--fmt-seite-n-von-N::after { content: "Seite " counter(page) " von " counter(pages); }
}
CSS
            . $pdfCss;
    }

    private static function mm(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . 'mm';
    }

    private static function pt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . 'pt';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** tel:-URI für Softphone / native Anruf-Apps (ohne CRM-Telefonanlage). */
    private static function telHref(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($digits === '' || $digits === '+') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = '+' . substr($digits, 2);
        }

        return 'tel:' . $digits;
    }

    private static function footerAnchor(string $href, string $label, bool $newTab = false): string
    {
        $href = trim($href);
        $label = trim($label);
        if ($href === '' || $label === '') {
            return self::e($label);
        }
        $extra = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';

        return '<a class="dg-lh-footer__link" href="' . self::e($href) . '"' . $extra . '>'
            . self::e($label) . '</a>';
    }
}
