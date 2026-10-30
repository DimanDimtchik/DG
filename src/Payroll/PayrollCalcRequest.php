<?php
declare(strict_types=1);

/**
 * Lohn L4c: Eingabe für Brutto→Netto (pure; keine DB-Pflicht).
 */
final class PayrollCalcRequest
{
    /**
     * @param array<string, mixed> $employerLohn PayrollEmployerSettings-ähnliches Array (Umlage-Flags)
     * @param array<string, mixed> $employee employee_data / payroll-Felder
     * @param list<array<string, mixed>> $lines Lohnpositionen:
     *        code, amount, direction(earning|deduction), taxable(0|1), subject_to_sv(0|1),
     *        unit(month|hour|amount|…), hours (bei unit=hour)
     * @param array<string, string|float|int> $rateParams param_key => value (Fixture oder Import)
     */
    public function __construct(
        public readonly int $contactId,
        public readonly string $periodYmd,
        public readonly array $employerLohn,
        public readonly array $employee,
        public readonly array $lines,
        public readonly array $rateParams,
        public readonly ?int $rateSetId = null,
        public readonly bool $minijobFlatPath = true,
    ) {
    }
}
