<?php
declare(strict_types=1);

/** Öffentliche Bewerbungsseite /rekrut (pro Instanz). */
final class RecruitingPublicService
{
    public const PAGE_SLUG = 'rekrut';

    /** @deprecated Nutze retentionHintMonths() — folgt Instanz-Einstellung. */
    public const RETENTION_HINT_MONTHS = JobApplicationRepository::DEFAULT_RETENTION_MONTHS;

    /**
     * Entwurf in Website → Seiten (wie Stempeluhr), damit Karriere in der Liste und im Menü sichtbar wird.
     * Live-Inhalt bleibt die Route /rekrut — nicht der Builder.
     */
    public static function ensureDraftWebsitePage(?int $userId = null): void
    {
        if (!Database::isConfigured() || !class_exists('WebsitePageRepository')) {
            return;
        }
        $existing = WebsitePageRepository::findBySlugAnyStatus(self::PAGE_SLUG);
        if ($existing !== null) {
            return;
        }
        WebsitePageRepository::save([
            'title' => 'Karriere / Bewerbungen',
            'slug' => self::PAGE_SLUG,
            'status' => WebsitePageRepository::STATUS_DRAFT,
            'layout' => self::websitePageLayout(),
        ], null, $userId);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, page_kind: string}
     */
    public static function websitePageLayout(): array
    {
        return [
            'page_kind' => 'recruiting',
            'rows' => [[
                'id' => 'row-rekrut-intro',
                'columns' => [[
                    'id' => 'col-rekrut-intro',
                    'width' => 12,
                    'blocks' => [
                        [
                            'id' => 'blk-rekrut-h',
                            'type' => 'heading',
                            'text' => 'Karriere / Bewerbungen',
                            'level' => 'h1',
                        ],
                        [
                            'id' => 'blk-rekrut-t',
                            'type' => 'text',
                            'text' => 'Diese Seite ist als Entwurf in der Seitenliste angelegt. '
                                . 'Die Bewerbungsübersicht und die Formulare laufen fest unter /rekrut '
                                . '(auch im Wartungsmodus). Veröffentlichen Sie die Seite und setzen Sie '
                                . 'sie ins Website-Menü, wenn der Eintrag öffentlich verlinkt werden soll. '
                                . 'Stellen pflegen Sie unter HR → Stellen.',
                        ],
                        [
                            'id' => 'blk-rekrut-btn',
                            'type' => 'button',
                            'label' => 'Zu den Stellen',
                            'url' => '/rekrut',
                        ],
                    ],
                ]],
            ]],
        ];
    }

    public static function isRecruitingWebsitePage(array $pageOrForm): bool
    {
        $rawSlug = (string) ($pageOrForm['slug'] ?? '');
        $slug = class_exists('WebsitePageRepository')
            ? WebsitePageRepository::sanitizeSlug($rawSlug)
            : strtolower(trim($rawSlug));
        if ($slug === self::PAGE_SLUG) {
            return true;
        }
        $layout = $pageOrForm['layout'] ?? [];
        if (!is_array($layout)) {
            return false;
        }

        return (string) ($layout['page_kind'] ?? '') === 'recruiting';
    }

    public static function retentionHintMonths(): int
    {
        return JobApplicationRepository::retentionMonths();
    }

    public static function publicRetentionHint(): string
    {
        if (class_exists('RecruitingPrivacySettings')) {
            return RecruitingPrivacySettings::publicRetentionHint();
        }

        return 'Ihre Angaben speichern wir nur für dieses Bewerbungsverfahren. '
            . 'Nach Absage oder Verfahrensende löschen wir sie in der Regel nach '
            . self::retentionHintMonths() . ' Monaten (laufende Verfahren ausgenommen).';
    }

    public static function publicConsentLabel(): string
    {
        if (class_exists('RecruitingPrivacySettings')) {
            return RecruitingPrivacySettings::publicConsentLabel();
        }

        return 'Ich willige ein, dass meine Bewerbungsdaten für dieses Bewerbungsverfahren gespeichert und verarbeitet werden. *';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publishedJobs(): array
    {
        return JobPostingRepository::listPublished();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function publishedJobBySlug(string $slug): ?array
    {
        return JobPostingRepository::findPublishedBySlug($slug);
    }

    public static function privacyPageUrl(): string
    {
        if (Database::isConfigured() && class_exists('WebsitePageRepository')) {
            try {
                $page = WebsitePageRepository::findBySlug('datenschutz');
                if (is_array($page) && ($page['status'] ?? '') === WebsitePageRepository::STATUS_PUBLISHED) {
                    return rtrim(App::publicBaseUrl(), '/') . '/datenschutz';
                }
            } catch (Throwable) {
                // Fallback unten
            }
        }

        return rtrim(App::publicBaseUrl(), '/') . '/datenschutz';
    }

    /**
     * Öffentliche Bewerbung: Bewerber + Bewerbung (Quelle website), optional Dateien.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files $_FILES['recruiting_files'] o. Ä.
     */
    public static function submitApplication(array $post, array $files = []): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Bewerbung derzeit nicht möglich.');
        }
        MigrationRunner::runPending();

        $jobId = (int) ($post['job_posting_id'] ?? 0);
        $job = JobPostingRepository::find($jobId);
        if ($job === null || (string) ($job['status'] ?? '') !== JobPostingRepository::STATUS_PUBLISHED) {
            throw new InvalidArgumentException('Diese Stelle ist nicht (mehr) veröffentlicht.');
        }

        if (empty($post['privacy_consent'])) {
            throw new InvalidArgumentException('Bitte der Speicherung Ihrer Daten zustimmen.');
        }

        $payload = [
            'job_posting_id' => $jobId,
            'email' => (string) ($post['email'] ?? ''),
            'first_name' => (string) ($post['first_name'] ?? ''),
            'last_name' => (string) ($post['last_name'] ?? ''),
            'phone' => (string) ($post['phone'] ?? ''),
            'city' => (string) ($post['city'] ?? ''),
            'cover_letter' => (string) ($post['cover_letter'] ?? ''),
            'status' => JobApplicationRepository::STATUS_RECEIVED,
            'source' => JobApplicationRepository::SOURCE_WEBSITE,
            'privacy_consent' => '1',
            'notes' => '',
        ];

        $applicationId = JobApplicationRepository::save($payload, null, null);

        if ($files !== []) {
            $kind = RecruitingFileStorage::sanitizeKind((string) ($post['file_kind'] ?? RecruitingFileStorage::KIND_CV));
            RecruitingFileStorage::storeUploads($applicationId, $files, null, $kind);
        }

        if (class_exists('RecruitingMailService')) {
            RecruitingMailService::afterPublicApplication($applicationId);
        }

        return $applicationId;
    }

    public static function companyLabel(): string
    {
        try {
            if (class_exists('CompanySettings')) {
                $name = trim((string) CompanySettings::displayName());
                if ($name !== '') {
                    return $name;
                }
            }
        } catch (Throwable) {
            // fallback
        }

        return (string) (App::config('crm_name') ?: 'Karriere');
    }

    /**
     * Öffentliche /rekrut-Seiten im Website-Chrome (Kopf/Navi/Fuß) wie normale CMS-Seiten.
     *
     * @param array<string, mixed> $viewVars
     */
    public static function renderWithWebsiteChrome(string $view, array $viewVars, string $title, string $pathSlug = self::PAGE_SLUG): void
    {
        ob_start();
        View::render($view, $viewVars);
        $mainContentHtml = (string) ob_get_clean();

        $fontFamily = class_exists('AppearanceSettings')
            ? AppearanceSettings::uiFontFamily()
            : 'system-ui, sans-serif';
        $themeVars = class_exists('CrmFrontendTheme')
            ? CrmFrontendTheme::rootDeclarations()
            : '';

        $extraHeadHtml = '<link rel="stylesheet" href="' . htmlspecialchars(Asset::crmStylesheetUrl(), ENT_QUOTES, 'UTF-8') . '">'
            . '<style>:root { --dg-font: ' . htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8') . '; ' . $themeVars . ' }'
            . '.ws-main .ws-rekrut.dg-wrap { max-width: none; margin: 0; padding: 0; }</style>';

        // Menü-Aktivierung über Slug „rekrut“; Canonical behält Detail-Pfad /rekrut/{slug}.
        View::render('website-public', [
            'page' => [
                'title' => $title,
                'slug' => self::PAGE_SLUG,
                'canonical_path' => $pathSlug,
                'status' => WebsitePageRepository::STATUS_PUBLISHED,
                'description' => '',
                'layout' => ['version' => 1, 'rows' => []],
            ],
            'mainContentHtml' => $mainContentHtml,
            'extraHeadHtml' => $extraHeadHtml,
            'chrome' => WebsiteSettings::chrome(),
            'menu' => WebsiteSettings::publicMenu(),
            'design' => WebsiteSettings::design(),
        ]);
    }
}
