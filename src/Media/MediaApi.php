<?php
declare(strict_types=1);

final class MediaApi
{
    public static function handle(): void
    {
        if (!Database::isConfigured()) {
            self::error('Datenbank nicht konfiguriert.');
        }

        MigrationRunner::runPending();
        MediaRepository::ensureTables();

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string) ($_GET['action'] ?? '') === 'download') {
            $user = AuthService::user();
            if (!$user || !RoleResolver::isAdmin($user)) {
                self::error('Keine Berechtigung.', 403);
            }
            self::download((string) ($_GET['id'] ?? ''));
        }

        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !RoleResolver::isAdmin($user)) {
            self::error('Keine Berechtigung.', 403);
        }

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
                default => self::error('Unbekannte Aktion.'),
            };
        } catch (Throwable $e) {
            self::error($e->getMessage());
        }
    }

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

        $user = AuthService::user();
        $canPreview = $user !== null && RoleResolver::canEdit($user);
        $isPublicLogo = AppearanceSettings::logoMediaId() === $mediaId;

        if (!$isPublicLogo && !$canPreview) {
            http_response_code(403);
            echo 'Keine Berechtigung.';
            exit;
        }

        self::streamFile($item, $isPublicLogo);
    }

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

    /** @param array<string, mixed> $item */
    private static function streamFile(array $item, bool $publicCache): void
    {
        $mediaId = (string) $item['media_id'];
        $path = MediaStorage::absolutePath($mediaId, (string) $item['stored_name']);
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Datei fehlt.';
            exit;
        }

        header('Content-Type: ' . (string) $item['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: ' . ($publicCache ? 'public, max-age=86400' : 'private, max-age=3600'));
        readfile($path);
        exit;
    }

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

        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . (string) filesize($file['path']));
        header('Cache-Control: public, max-age=86400');
        readfile($file['path']);
        exit;
    }

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

    private static function scan(): never
    {
        $result = MediaScanner::scanAll();
        self::ok($result);
    }

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

    /** @param array<string, mixed> $data */
    private static function ok(array $data = []): never
    {
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function error(string $message, int $code = 400): never
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
