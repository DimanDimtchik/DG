<?php
declare(strict_types=1);

/**
 * Arbeitsvertrags-HTML — V3: freigegebene Vorlage aus DB, sonst Katalog-Fallback.
 */
final class EmploymentContractClauses
{
    /**
     * @param array<string, string> $p Placeholders
     * @param array{
     *   contract_type: string,
     *   is_minor: bool,
     *   annex_training?: bool,
     *   annex_pension?: bool,
     *   annex_ifsg?: bool,
     *   annex_video?: bool
     * } $opts
     */
    public static function bodyHtml(array $p, array $opts): string
    {
        $type = (string) ($opts['contract_type'] ?? EmploymentContractContext::TYPE_FULLTIME);
        $resolved = self::resolveForType($type);

        return ContractTemplateRenderer::render($resolved['blocks'], $p, $opts);
    }

    /**
     * Bausteine für Vertragstyp: nur Status published aus DB, sonst Katalog.
     *
     * @return array{
     *   code: string,
     *   source: 'published'|'catalog',
     *   version: string,
     *   template_id: int,
     *   blocks: list<array<string, mixed>>
     * }
     */
    public static function resolveForType(string $contractType): array
    {
        $code = ContractTemplateCatalog::codeForContractType($contractType);
        if (
            class_exists('ContractTemplateRepository')
            && ContractTemplateRepository::tableReady()
        ) {
            try {
                $pub = ContractTemplateRepository::publishedWithBlocks($code);
                if ($pub !== null && $pub['blocks'] !== []) {
                    $tpl = $pub['template'];

                    return [
                        'code' => $code,
                        'source' => 'published',
                        'version' => (string) ($tpl['version'] ?? ''),
                        'template_id' => (int) ($tpl['id'] ?? 0),
                        'blocks' => $pub['blocks'],
                    ];
                }
            } catch (Throwable) {
                // Katalog-Fallback
            }
        }

        return [
            'code' => $code,
            'source' => 'catalog',
            'version' => '',
            'template_id' => 0,
            'blocks' => ContractTemplateCatalog::blocksForCode($code),
        ];
    }
}
