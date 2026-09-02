<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The application's position in the pipeline.
 *
 * APPROVED is not the end of origination: the agreement must be signed before
 * the file reaches the disbursement queue, and disbursement is a separate
 * department's decision.
 */
enum LoanApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';
    case AgreementSigned = 'agreement_signed';
    case Disbursed = 'disbursed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::AgreementSigned => 'Agreement signed',
            self::Disbursed => 'Disbursed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Awaiting a credit decision.
     */
    public function isPending(): bool
    {
        return $this === self::Submitted;
    }
}
