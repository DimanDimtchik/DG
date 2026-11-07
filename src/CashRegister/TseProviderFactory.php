<?php
declare(strict_types=1);

/**
 * Wählt den TSE-Provider (K3c).
 * mock → MockTseProvider; fiskaly → K3b (noch nicht).
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
     * @param array<string, mixed>|null $context Assignment- oder Config-Kontext (für spätere Adapter)
     */
    public static function forProvider(string $name, ?array $context = null): TseProviderInterface
    {
        $name = strtolower(trim($name));
        return match ($name) {
            self::PROVIDER_MOCK, '' => new MockTseProvider(),
            self::PROVIDER_FISKALY => throw new RuntimeException(
                'TSE-Provider fiskaly folgt in Schritt K3b (FiskalyTseProvider). Bis dahin provider=mock nutzen.'
            ),
            default => throw new RuntimeException('Unbekannter TSE-Provider: ' . $name),
        };
    }

    /**
     * Default ohne Assignment: Mock, solange kein Fiskaly-Adapter (K3b) existiert.
     * Später: aus TseSettings / Feature-Flag ableiten.
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
