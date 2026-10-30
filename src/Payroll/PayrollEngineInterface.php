<?php
declare(strict_types=1);

/**
 * Lohn L4c: Strategy-Interface — Stufe A nur StandardPayrollEngine.
 */
interface PayrollEngineInterface
{
    public function calculate(PayrollCalcRequest $request): PayrollCalcResult;
}
