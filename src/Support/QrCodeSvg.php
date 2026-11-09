<?php
declare(strict_types=1);

/** QR-Code als inline-SVG (Belegdruck, ohne GD). */
final class QrCodeSvg
{
    public static function render(string $payload, int $moduleSize = 3, int $margin = 2): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }
        $file = DG_ROOT . '/vendor/phpqrcode/phpqrcode.php';
        if (!is_readable($file)) {
            throw new RuntimeException('QR-Bibliothek fehlt.');
        }
        require_once $file;
        if (!class_exists('QRcode')) {
            throw new RuntimeException('QR-Bibliothek unvollständig.');
        }

        $level = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 1;
        $tab = QRcode::text($payload, false, $level, max(1, $moduleSize), max(0, $margin));
        if (!is_array($tab) || $tab === []) {
            throw new RuntimeException('QR-Code konnte nicht erzeugt werden.');
        }

        $size = count($tab);
        $ms = max(1, $moduleSize);
        $dim = $size * $ms;
        $rects = [];
        foreach ($tab as $y => $row) {
            $row = (string) $row;
            $len = strlen($row);
            $x = 0;
            $py = ((int) $y) * $ms;
            while ($x < $len) {
                if ($row[$x] !== '1') {
                    $x++;
                    continue;
                }
                $run = 1;
                while ($x + $run < $len && $row[$x + $run] === '1') {
                    $run++;
                }
                $rects[] = '<rect x="' . ($x * $ms) . '" y="' . $py . '" width="' . ($run * $ms) . '" height="' . $ms . '"/>';
                $x += $run;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $dim . '" height="' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="TSE-Prüfcode">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<g fill="#000">' . implode('', $rects) . '</g></svg>';
    }
}
