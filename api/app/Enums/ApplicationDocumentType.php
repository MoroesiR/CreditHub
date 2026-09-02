<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The documents a loan application is assessed on.
 *
 * All three are required at submission: identity proves who is borrowing, the
 * payslip evidences the income the affordability was calculated from, and the
 * bank statement is the only one of the three the client cannot easily author.
 */
enum ApplicationDocumentType: string
{
    case IdCopy = 'id_copy';
    case BankStatement = 'bank_statement';
    case Payslip = 'payslip';

    public function label(): string
    {
        return match ($this) {
            self::IdCopy => 'ID copy',
            self::BankStatement => 'Bank statement',
            self::Payslip => 'Payslip',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
