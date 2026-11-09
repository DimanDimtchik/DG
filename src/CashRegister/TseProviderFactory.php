<?php
declare(strict_types=1);

/**
 * Wählt den TSE-Provider (K3c/K3b).
 * mock → MockTseProvider; fiskaly → FiskalyTseProvider.
 * Nachsignatur nur bei provider.supportsDeferredSignature() (K14c); Fiskaly bis Freigabe false.
 */
final class TseProviderFactory
{
    public const PROVIDER_MOCK = 'mock';
    public const PROVIDER_FISKALY = 'fiskaly';

    /**
     * Aus dg_cash_tse_assignments-Zeile (oder null → Instanz-Default).
     *
     * @param array<string, mixed>|null $assignment
     */
    public static function fromAssignment(?array $assignment): TseProviderInterface
    {
        if ($assignment === null || $assignment === []) {
            return self::forProvider(self::defaultProviderName());
        }
        $name = strtolower(trim((string) ($assignment['provider'] ?? '')));
        if ($name === '') {
            $name = self::defaultProviderName();
        }

        return self::forProvider($name, $assignment);
    }

    /**
     * @param array<string, mixed>|null $context Assignment- oder Config-Kontext
     */
    public static function forProvider(string $name, ?array $context = null): TseProviderInterface
    {
        $name = strtolower(trim($name));
        return match ($name) {
            self::PROVIDER_MOCK, '' => new MockTseProvider(),
            self::PROVIDER_FISKALY => new FiskalyTseProvider(
                is_array($context) ? FiskalyTseProvider::configFromAssignment($context) : null
            ),
            default => throw new RuntimeException('Unbekannter TSE-Provider: ' . $name),
        };
    }

    /**
     * Default ohne Assignment: Mock (Dev/Selftest ohne Netz).
     * Produktiv: Assignment provider=fiskaly setzen.
     */
    public static function defaultProviderName(): string
    {
        return self::PROVIDER_MOCK;
    }

    /**
     * @return list<string>
     */
    public static function knownProviders(): array
    {
        return [self::PROVIDER_MOCK, self::PROVIDER_FISKALY];
    }
}
