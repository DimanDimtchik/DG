<?php
declare(strict_types=1);

/** Modul-Sperren (hard) und Hinweise (soft/compare) für Pflichtschulungen. */
final class AcademyGateService
{
    /** @return array{blocked: bool, mode: string, course_slug: string, course_title: string, message: string}|null */
    public static function gateStatus(User $user, string $moduleKey): ?array
    {
        if (RoleResolver::isCustomer($user)) {
            return null;
        }

        $gate = AcademyRepository::gateForModule($moduleKey);
        if ($gate === null) {
            return null;
        }

        $courseId = (int) ($gate['course_id'] ?? 0);
        $assignment = AcademyRepository::findAssignment((int) $user->id, $courseId);
        if ($assignment === null) {
            return null;
        }

        $mode = AcademyAccessMode::sanitize((string) ($assignment['access_mode'] ?? AcademyAccessMode::COMPARE));
        $status = (string) ($assignment['status'] ?? 'open');
        $cleared = self::isCleared($assignment);

        if ($cleared) {
            return null;
        }

        $title = (string) ($gate['course_title'] ?? 'Pflichtschulung');
        $slug = (string) ($gate['course_slug'] ?? '');

        return [
            'blocked' => $mode === AcademyAccessMode::HARD,
            'mode' => $mode,
            'course_slug' => $slug,
            'course_title' => $title,
            'message' => $mode === AcademyAccessMode::HARD
                ? 'Pflichtschulung „' . $title . '“ absolvieren, bevor dieses Modul genutzt werden kann.'
                : 'Bitte Schulung „' . $title . '“ in der Akademie absolvieren (Modus: ' . AcademyAccessMode::labels()[$mode] . ').',
        ];
    }

    public static function isModuleAccessible(User $user, string $moduleKey): bool
    {
        $gate = self::gateStatus($user, $moduleKey);

        return $gate === null || !($gate['blocked'] ?? false);
    }

    /** @param array<string, mixed> $assignment */
    public static function isCleared(array $assignment): bool
    {
        $status = (string) ($assignment['status'] ?? '');

        if (!empty($assignment['certificate_enabled'])) {
            return $status === 'completed';
        }

        return in_array($status, ['completed', 'waived'], true);
    }

    /** @return list<array<string, mixed>> */
    public static function activeBanners(User $user): array
    {
        $banners = [];
        if (!Database::isConfigured()) {
            return $banners;
        }

        foreach (AcademyRepository::assignmentsForUser((int) $user->id) as $assignment) {
            if (self::isCleared($assignment)) {
                continue;
            }
            $mode = AcademyAccessMode::sanitize((string) ($assignment['access_mode'] ?? AcademyAccessMode::COMPARE));
            if ($mode === AcademyAccessMode::HARD) {
                continue;
            }
            $banners[] = [
                'course_title' => (string) ($assignment['course_title'] ?? ''),
                'course_slug' => (string) ($assignment['course_slug'] ?? ''),
                'mode' => $mode,
                'status' => (string) ($assignment['status'] ?? ''),
            ];
        }

        return $banners;
    }
}
