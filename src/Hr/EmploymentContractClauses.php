<?php
declare(strict_types=1);

/**
 * Arbeitsvertrags-HTML — nutzt ContractTemplateCatalog + Renderer (V0).
 * Fallback bleibt API-kompatibel für EmploymentContractPdfService.
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
        $code = ContractTemplateCatalog::codeForContractType($type);
        $blocks = ContractTemplateCatalog::blocksForCode($code);

        return ContractTemplateRenderer::render($blocks, $p, $opts);
    }
}
