<?php

declare(strict_types=1);

namespace App\Services\Clients;

/**
 * What the client has left each month once living costs and existing debt are
 * met. It is the ceiling on any instalment the lender may grant, so it is
 * computed in one place and stored with the assessment rather than recomputed
 * wherever it is displayed.
 */
final class AffordabilityCalculator
{
    /**
     * @param  array<string, mixed>  $figures
     */
    public function disposableIncome(array $figures): float
    {
        $net = (float) $figures['net_monthly_income'];
        $living = (float) $figures['monthly_living_expenses'];
        $debt = (float) $figures['monthly_debt_repayments'];

        return round($net - $living - $debt, 2);
    }
}
