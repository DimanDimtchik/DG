<?php
declare(strict_types=1);

/**
 * HTTP API and streaming endpoints for the media library (`/api/media`, `/app/media`, favicon).
 */
final class MediaApi
{
    /**
     * JSON API router (POST actions + GET download). Requires admin session.
     */
    public static function handle(): void
    {
        if (!Database::isConfigured()) {
            self::error('Datenbank nicht konfiguriert.');
        }

        MigrationRunner::runPending();
        MediaRepository::ensureTables();

        $user = AuthService::user();
        if (!$user) {
            self::error('Keine Berechtigung.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string) ($_GET['action'] ?? '') === 'list') {
            if (!RoleResolver::canEdit($user) || !MenuRegistry::canAccessWebsite($user)) {
                self::error('Keine Berechtigung.', 403);
            }
            header('Content-Type: application/json; charset=utf-8');
            self::listForPicker();
        }

        if (!RoleResolver::isAdmin($user)) {
            self::error('Keine Berechtigung.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string) ($_GET['action'] ?? '') === 'download') {
            self::download((string) ($_GET['id'] ?? ''));
        }

        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::error('Nur POST erlaubt.', 405);
        }

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            self::error('Ungültiges Formular (CSRF).', 403);
        }

        $action = (string) ($_POST['action'] ?? '');

        try {
            match ($action) {
                'upload' => self::upload($user),
                'save_meta' => self::saveMeta(),
                'transform' => self::transform(),
                'crop' => self::crop(),
                'scan' => self::scan(),
                'delete' => self::delete(),
                'set_logo' => self::setLogo(),
                'set_favicon' => self::setFavicon(),
                'svg_save' => self::svgSave(),
                default => self::error('Unbekannte Aktion.'),
            };
        } catch (Throwable $e) {
            self::error($e->getMessage());
        }
    }

    /**
     * Stream a media file for public website delivery (and CRM editors).
     */
    public static function serve(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            exit;
        }

        if (!Database::isConfigured()) {
            http_response_code(503);
            echo 'Datenbank nicht konfiguriert.';
            exit;
        }

        MediaRepository::ensureTables();

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            exit;
        }

        // Active library images are public (website CMS, logos, embeds). IDs are unguessable.
        $publicCache = true;
        self::streamFile($item, $publicCache);
    }

    /**
     * JSON list for the website builder / media picker (admin only).
     *
     * @return never
     */
    private static function listForPicker(): never
    {
        $items = [];
        foreach (MediaRepository::listWithUsage() as $row) {
            $mediaId = (string) $row['media_id'];
            $items[] = [
                'media_id' => $mediaId,
                'title' => (string) ($row['title'] ?? ''),
                'original_name' => (string) ($row['original_name'] ?? ''),
                'alt_text' => (string) ($row['alt_text'] ?? ''),
                'mime_type' => (string) ($row['mime_type'] ?? ''),
                'extension' => (string) ($row['extension'] ?? ''),
                'width' => isset($row['width']) ? (int) $row['width'] : null,
                'height' => isset($row['height']) ? (int) $row['height'] : null,
                'url' => MediaStorage::publicUrl($mediaId),
                'preview_url' => MediaStorage::publicUrl($mediaId),
            ];
        }

        self::ok(['items' => $items]);
    }

    /**
     * Stream a media file for the Bilder editor preview (admin only).
     */
    public static function streamForEditor(User $user, string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            exit;
        }

        if (!RoleResolver::isAdmin($user)) {
            http_response_code(403);
            echo 'Keine Berechtigung.';
            exit;
        }

        if (!Database::isConfigured()) {
            http_response_code(503);
            echo 'Datenbank nicht konfiguriert.';
            exit;
        }

        MediaRepository::ensureTables();

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            exit;
        }

        self::streamFile($item, false);
    }

    /**
     * @param array<string, mixed> $item Media DB row
     */
    private static function streamFile(array $item, bool $publicCache): void
    {
        $mediaId = (string) $item['media_id'];
        $path = MediaStorage::absolutePath($mediaId, (string) $item['stored_name']);
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Datei fehlt.';
            exit;
        }

        $mime = (string) $item['mime_type'];
        self::sendMediaHeaders($mime, $publicCache, (int) filesize($path));
        readfile($path);
        exit;
    }

    /**
     * Media responses must not inherit the app CSP: browsers apply it to SVG-as-<img>
     * and then block internal paint servers like fill="url(#id)".
     */
    private static function sendMediaHeaders(string $mime, bool $publicCache, int $length): void
    {
        header_remove('Content-Security-Policy');
        // PHP session_start() may have set no-cache; favicons/media need stable caching.
        if ($publicCache) {
            header_remove('Pragma');
            header_remove('Expires');
        }
        header('Content-Type: ' . $mime);
        if ($length > 0) {
            header('Content-Length: ' . (string) $length);
        }
        header('Cache-Control: ' . ($publicCache ? 'public, max-age=86400' : 'private, max-age=3600'));
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Serve the configured site favicon (SVG or PNG by size).
     */
    public static function serveFavicon(int $size = 32): void
    {
        if (!Database::isConfigured()) {
            http_response_code(404);
            exit;
        }

        MediaRepository::ensureTables();

        $mediaId = AppearanceSettings::faviconMediaId();
        if ($mediaId === '' || !MediaId::isValid($mediaId)) {
            http_response_code(404);
            exit;
        }

        $file = MediaStorage::resolveFaviconFile($mediaId, $size);
        if ($file === null) {
            http_response_code(404);
            exit;
        }

        self::sendMediaHeaders($file['mime'], true, (int) filesize($file['path']));
        readfile($file['path']);
        exit;
    }

    /**
     * Speichert einen Datei-Upload als neues Medium.
     *
     * @return never
     */
    private static function upload(User $user): never
    {
        if (empty($_FILES['file']['tmp_name'])) {
            self::error('Keine Datei erhalten.');
        }

        $mediaId = MediaId::generate();

        try {
            $stored = MediaStorage::storeUpload($mediaId, $_FILES['file']);
            $stored['source_note'] = trim((string) ($_POST['source_note'] ?? ''));
            $stored['title'] = trim((string) ($_POST['title'] ?? ''));
            $stored['alt_text'] = trim((string) ($_POST['alt_text'] ?? ''));
            if ($stored['title'] === '' && $stored['original_name'] !== '') {
                $stored['title'] = pathinfo($stored['original_name'], PATHINFO_FILENAME);
            }

            MediaRepository::insert($mediaId, $stored, $user->id);
        } catch (Throwable $e) {
            MediaStorage::deleteMediaFiles($mediaId);
            throw $e;
        }

        self::ok([
            'media_id' => $mediaId,
            'url' => MediaStorage::publicUrl($mediaId),
            'redirect' => '/app?page=bilder&action=edit&id=' . rawurlencode($mediaId),
        ]);
    }

    /**
     * Aktualisiert Metadaten (Titel, Alt-Text, Quellhinweis) eines Mediums.
     *
     * @return never
     */
    private static function saveMeta(): never
    {
        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        if (!MediaId::isValid($mediaId)) {
            self::error('Ungültige Medien-ID.');
        }

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            self::error('Bild nicht gefunden. Bitte zuerst hochladen — die Medien-Tabelle wurde ggf. gerade erst angelegt.');
        }

        MediaRepository::updateMetadata(
            $mediaId,
            trim((string) ($_POST['source_note'] ?? '')),
            trim((string) ($_POST['title'] ?? '')),
            trim((string) ($_POST['alt_text'] ?? ''))
        );

        self::ok(['reload' => true]);
    }

    /**
     * Skaliert/konvertiert ein Bild und legt ein neues abgeleitetes Medium an.
     *
     * @return never
     */
    private static function transform(): never
    {
        $user = AuthService::user();
        if ($user === null) {
            self::error('Keine Berechtigung.', 403);
        }

        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        if (!MediaId::isValid($mediaId)) {
            self::error('Ungültige Medien-ID.');
        }

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            self::error('Bild nicht gefunden.');
        }

        $path = MediaStorage::absolutePath($mediaId, (string) $item['stored_name']);
        $maxWidth = self::optionalInt($_POST['max_width'] ?? null);
        $maxHeight = self::optionalInt($_POST['max_height'] ?? null);
        $format = trim((string) ($_POST['target_format'] ?? 'keep'));

        if ($format === 'keep' && $maxWidth === null && $maxHeight === null) {
            self::error('Bitte Zielgröße oder Zielformat angeben.');
        }

        if ((string) $item['mime_type'] === 'image/svg+xml') {
            self::error('SVG kann nicht per GD umgewandelt werden.');
        }

        $targetFormat = $format === 'keep' ? (string) $item['extension'] : $format;
        $keepAspect = !isset($_POST['keep_aspect']) || (string) $_POST['keep_aspect'] !== '0';
        $meta = MediaImageProcessor::transform(
            $path,
            (string) $item['mime_type'],
            $targetFormat,
            $maxWidth,
            $maxHeight,
            $keepAspect
        );

        $editedPath = MediaStorage::absolutePath($mediaId, (string) $meta['stored_name']);
        $binary = (string) file_get_contents($editedPath);
        if ($binary === '') {
            self::error('Bearbeitetes Bild konnte nicht gelesen werden.');
        }
        @unlink($editedPath);

        $newId = self::createDerivedMedia($item, $binary, (string) $meta['mime_type'], 'Größe/Format', $user);

        self::ok([
            'media_id' => $newId,
            'redirect' => '/app?page=bilder&action=edit&id=' . rawurlencode($newId),
            'message' => 'Neues Bild angelegt. Das Original bleibt unverändert.',
        ]);
    }

    /**
     * Speichert einen Zuschnitt/Freisteller als neues Medium (Original bleibt).
     *
     * @return never
     */
    private static function crop(): never
    {
        $user = AuthService::user();
        if ($user === null) {
            self::error('Keine Berechtigung.', 403);
        }

        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        if (!MediaId::isValid($mediaId)) {
            self::error('Ungültige Medien-ID.');
        }

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            self::error('Bild nicht gefunden.');
        }

        if (empty($_FILES['file']['tmp_name'])) {
            self::error('Kein Bild erhalten.');
        }

        $binary = (string) file_get_contents((string) $_FILES['file']['tmp_name']);
        if ($binary === '') {
            self::error('Leere Datei.');
        }

        $mime = (string) ($_FILES['file']['type'] ?? 'image/png');
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            $mime = 'image/png';
        }

        $variant = trim((string) ($_POST['variant'] ?? 'crop'));
        $variantLabel = $variant === 'freigestellt' ? 'Freigestellt' : 'Zuschnitt';

        $newId = self::createDerivedMedia($item, $binary, $mime, $variantLabel, $user);

        self::ok([
            'media_id' => $newId,
            'redirect' => '/app?page=bilder&action=edit&id=' . rawurlencode($newId),
            'message' => 'Neues Bild angelegt. Das Original bleibt unverändert.',
        ]);
    }

    /**
     * Scannt die Installation nach Medien-Referenzen und aktualisiert die Nutzung.
     *
     * @return never
     */
    private static function scan(): never
    {
        $result = MediaScanner::scanAll();
        self::ok($result);
    }

    /**
     * Setzt oder entfernt das CRM-Logo (AppearanceSettings).
     *
     * @return never
     */
    private static function setLogo(): never
    {
        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        $enabled = !empty($_POST['enabled']) && (string) $_POST['enabled'] !== '0';

        if ($enabled) {
            if (!MediaId::isValid($mediaId)) {
                self::error('Ungültige Medien-ID.');
            }

            $item = MediaRepository::find($mediaId);
            if (!$item) {
                self::error('Bild nicht gefunden.');
            }

            AppearanceSettings::setLogoMediaId($mediaId);
            MediaScanner::scanAll();

            self::ok(['reload' => true, 'message' => 'CRM-Logo in Kopfzeile und Login aktiv.']);
        }

        if (MediaId::isValid($mediaId) && AppearanceSettings::logoMediaId() === $mediaId) {
            AppearanceSettings::clearLogoMediaId();
            MediaScanner::scanAll();
        }

        self::ok(['reload' => true, 'message' => 'CRM-Logo deaktiviert.']);
    }

    /**
     * Setzt oder entfernt das Browser-Favicon und generiert Varianten.
     *
     * @return never
     */
    private static function setFavicon(): never
    {
        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        $enabled = !empty($_POST['enabled']) && (string) $_POST['enabled'] !== '0';

        if ($enabled) {
            if (!MediaId::isValid($mediaId)) {
                self::error('Ungültige Medien-ID.');
            }

            $item = MediaRepository::find($mediaId);
            if (!$item) {
                self::error('Bild nicht gefunden.');
            }

            MediaFaviconGenerator::generateForMedia($mediaId);
            AppearanceSettings::setFaviconMediaId($mediaId);
            MediaScanner::scanAll();

            self::ok(['reload' => true, 'message' => 'Favicon wurde erzeugt und ist im Browser-Tab aktiv.']);
        }

        if (MediaId::isValid($mediaId) && AppearanceSettings::faviconMediaId() === $mediaId) {
            AppearanceSettings::clearFaviconMediaId();
            MediaScanner::scanAll();
        }

        self::ok(['reload' => true, 'message' => 'Favicon deaktiviert.']);
    }

    /**
     * Persist SVG color / stroke-width edits in place.
     */
    private static function svgSave(): never
    {
        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        if (!MediaId::isValid($mediaId)) {
            self::error('Ungültige Medien-ID.');
        }

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            self::error('Bild nicht gefunden.');
        }
        if ((string) $item['mime_type'] !== 'image/svg+xml') {
            self::error('Nur SVG-Dateien können so bearbeitet werden.');
        }

        $path = MediaStorage::absolutePath($mediaId, (string) $item['stored_name']);
        if (!is_file($path)) {
            self::error('Datei fehlt.');
        }

        $colorMap = self::decodeJsonMap($_POST['colors'] ?? '{}');
        $strokeWidthMap = self::decodeJsonMap($_POST['stroke_widths'] ?? '{}');
        if ($colorMap === [] && $strokeWidthMap === []) {
            self::error('Keine Änderungen übermittelt.');
        }

        $original = MediaSvgEditor::readFile($path);
        try {
            $updated = MediaSvgEditor::apply($original, $colorMap, $strokeWidthMap);
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage());
        }

        if ($updated === $original) {
            self::error('Keine wirksame Änderung erkannt.');
        }

        if (file_put_contents($path, $updated) === false) {
            self::error('SVG konnte nicht gespeichert werden.');
        }
        MediaStorage::sanitizeSvg($path);
        MediaStorage::applyReadablePermissions($path);

        $sanitized = MediaSvgEditor::readFile($path);
        [$width, $height] = MediaImageProcessor::readDimensions($path, 'image/svg+xml');
        MediaRepository::updateFileMeta($mediaId, [
            'stored_name' => (string) $item['stored_name'],
            'mime_type' => 'image/svg+xml',
            'extension' => 'svg',
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int) filesize($path),
        ]);

        if (AppearanceSettings::faviconMediaId() === $mediaId) {
            MediaFaviconGenerator::generateForMedia($mediaId);
        }

        $analysis = MediaSvgEditor::analyze($sanitized);
        self::ok([
            'message' => 'SVG gespeichert.',
            'reload' => true,
            'analysis' => $analysis,
            'preview_url' => MediaStorage::adminPreviewUrl($mediaId, time()),
        ]);
    }

    /**
     * Decode a JSON object or array map of string => string.
     *
     * @return array<string, string>
     */
    private static function decodeJsonMap(mixed $raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                return [];
            }
        }

        $map = [];
        foreach ($decoded as $from => $to) {
            $from = trim((string) $from);
            $to = trim((string) $to);
            if ($from === '' || $to === '') {
                continue;
            }
            $map[$from] = $to;
        }

        return $map;
    }

    /**
     * Löscht ein Medium, sofern es nicht als Logo/Favicon oder Referenzen genutzt wird.
     *
     * @return never
     */
    private static function delete(): never
    {
        $mediaId = trim((string) ($_POST['media_id'] ?? ''));
        if (!MediaId::isValid($mediaId)) {
            self::error('Ungültige Medien-ID.');
        }

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            self::error('Bild nicht gefunden.');
        }

        if (AppearanceSettings::logoMediaId() === $mediaId) {
            self::error('Dieses Bild ist das CRM-Logo. Bitte zuerst ein anderes Logo setzen.');
        }

        if (AppearanceSettings::faviconMediaId() === $mediaId) {
            self::error('Dieses Bild ist das Browser-Favicon. Bitte zuerst die Favicon-Checkbox deaktivieren.');
        }

        if (MediaRepository::activeUsageCount($mediaId) > 0) {
            self::error('Bild wird noch verwendet. Bitte zuerst Referenzen entfernen oder Scanner nach Änderungen erneut ausführen.');
        }

        if (MediaRepository::hasAnyUsage($mediaId)) {
            self::error('Bild wurde früher genutzt. Archiv-Löschung folgt in einer späteren Ausbaustufe — vorerst nicht löschbar.');
        }

        MediaRepository::delete($mediaId);
        self::ok(['redirect' => '/app?page=bilder']);
    }

    /**
     * Sendet die Mediendatei als Download-Attachment.
     *
     * @return never
     */
    private static function download(string $mediaId): never
    {
        if (!MediaId::isValid($mediaId)) {
            self::error('Ungültige Medien-ID.', 404);
        }

        $item = MediaRepository::find($mediaId);
        if (!$item) {
            self::error('Bild nicht gefunden.', 404);
        }

        $path = MediaStorage::absolutePath($mediaId, (string) $item['stored_name']);
        if (!is_file($path)) {
            self::error('Datei fehlt.', 404);
        }

        $name = (string) ($item['original_name'] ?: $item['stored_name']);
        header('Content-Type: ' . (string) $item['mime_type']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    /**
     * Parst einen positiven Integer aus Request-Werten; sonst null.
     */
    private static function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @param array<string, mixed> $sourceItem
     */
    private static function createDerivedMedia(array $sourceItem, string $binary, string $mime, string $variantLabel, User $user): string
    {
        $ext = MediaStorage::extensionFromMime($mime);
        if ($ext === null) {
            throw new InvalidArgumentException('MIME-Typ nicht unterstützt.');
        }

        $newId = MediaId::generate();

        try {
            $stored = MediaStorage::storeBinary($newId, 'original.' . $ext, $binary, $mime);
            $stored = self::enrichDerivedMetadata($stored, $sourceItem, $variantLabel);
            MediaRepository::insert($newId, $stored, $user->id);
        } catch (Throwable $e) {
            MediaStorage::deleteMediaFiles($newId);
            throw $e;
        }

        return $newId;
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function enrichDerivedMetadata(array $stored, array $source, string $variantLabel): array
    {
        $sourceId = (string) ($source['media_id'] ?? '');
        $baseTitle = trim((string) ($source['title'] ?? ''));
        if ($baseTitle === '') {
            $baseTitle = pathinfo((string) ($source['original_name'] ?: 'Bild'), PATHINFO_FILENAME);
        }

        $stored['title'] = $baseTitle . ' (' . $variantLabel . ')';
        $stored['alt_text'] = (string) ($source['alt_text'] ?? '');

        $note = trim((string) ($source['source_note'] ?? ''));
        $derivedLine = match ($variantLabel) {
            'Größe/Format' => 'Abgeleitet bei Größe & Format Änderung von ' . $sourceId . '.',
            'Zuschnitt' => 'Abgeleitet beim Zuschneiden von ' . $sourceId . '.',
            'Freigestellt' => 'Abgeleitet bei Hintergrund entfernen / Freistellen von ' . $sourceId . '.',
            default => 'Abgeleitet von ' . $sourceId . '.',
        };
        $stored['source_note'] = $note === '' ? $derivedLine : $note . "\n" . $derivedLine;

        $origBase = pathinfo((string) ($source['original_name'] ?: 'bild'), PATHINFO_FILENAME);
        $slug = match ($variantLabel) {
            'Größe/Format' => 'groesse-format',
            'Zuschnitt' => 'zuschnitt',
            'Freigestellt' => 'freigestellt',
            default => 'bearbeitet',
        };
        $stored['original_name'] = $origBase . '-' . $slug . '.' . (string) ($stored['extension'] ?? 'png');

        return $stored;
    }

    /**
     * Regeneriert Favicon-Varianten, wenn dieses Medium aktuell als Favicon gesetzt ist.
     */
    private static function maybeRegenerateFavicon(string $mediaId): void
    {
        if (AppearanceSettings::faviconMediaId() !== $mediaId) {
            return;
        }

        try {
            MediaFaviconGenerator::generateForMedia($mediaId);
        } catch (Throwable) {
            // Favicon-Regenerierung darf Bearbeitung nicht blockieren
        }
    }

    /**
     * JSON success response and exit.
     *
     * @param array<string, mixed> $data
     * @return never
     */
    private static function ok(array $data = []): never
    {
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * JSON error response and exit.
     */
    private static function error(string $message, int $code = 400): never
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
