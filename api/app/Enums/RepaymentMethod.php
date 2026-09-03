<?php

declare(strict_types=1);

namespace App\Enums;

enum RepaymentMethod: string
{
    case DebitOrder = 'debit_order';
    case Eft = 'eft';
    case PayrollDeduction = 'payroll_deduction';
    case Cash = 'cash';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::DebitOrder => 'Debit order',
            self::Eft => 'EFT',
            self::PayrollDeduction => 'Payroll deduction',
            self::Cash => 'Cash',
            self::Reversal => 'Reversal',
        };
    }

    /**
     * The methods an officer may choose. A reversal is produced by the system
     * when an earlier receipt is backed out, never picked from a list.
     *
     * @return array<int, string>
     */
    public static function selectable(): array
    {
        return [
            self::DebitOrder->value,
            self::Eft->value,
            self::PayrollDeduction->value,
            self::Cash->value,
        ];
    }
}
