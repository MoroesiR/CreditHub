<?php

declare(strict_types=1);

namespace App\Enums;

enum DisbursementStatus: string
{
    /** Signed and waiting to be checked. */
    case Pending = 'pending';
    /** Checked, and cleared to be paid. */
    case Verified = 'verified';
    case Paid = 'paid';
    /** Something is wrong with the file; it is not being paid until resolved. */
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting verification',
            self::Verified => 'Verified, ready to pay',
            self::Paid => 'Paid',
            self::OnHold => 'On hold',
        };
    }
}
