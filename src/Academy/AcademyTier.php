<?php
declare(strict_types=1);

/** Lizenz-Tarife für Akademie-Inhalte (Starter / Business / Premium). */
final class AcademyTier
{
    public const STARTER = 'starter';
    public const BUSINESS = 'business';
    public const PREMIUM = 'premium';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::STARTER => 'Starter',
            self::BUSINESS => 'Business',
            self::PREMIUM => 'Premium',
        ];
    }

    public static function sanitize(string $tier): string
    {
        $tier = strtolower(trim($tier));

        return isset(self::labels()[$tier]) ? $tier : self::STARTER;
    }

    public static function rank(string $tier): int
    {
        return match (self::sanitize($tier)) {
            self::BUSINESS => 2,
            self::PREMIUM => 3,
            default => 1,
        };
    }

    public static function currentPlan(): string
    {
        if (!class_exists('LicenseGuard')) {
            return self::PREMIUM;
        }

        $status = LicenseGuard::status();
        $plan = strtolower(trim((string) ($status['plan'] ?? '')));

        if ($plan === '' || $plan === 'dev' || $plan === 'development') {
            return self::PREMIUM;
        }

        if (isset(self::labels()[$plan])) {
            return $plan;
        }

        if (str_contains($plan, 'premium') || str_contains($plan, 'enterprise')) {
            return self::PREMIUM;
        }
        if (str_contains($plan, 'business') || str_contains($plan, 'pro')) {
            return self::BUSINESS;
        }

        return self::STARTER;
    }

    public static function allows(string $minTier): bool
    {
        return self::rank(self::currentPlan()) >= self::rank($minTier);
    }
}
