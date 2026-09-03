<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting a decision',
            self::Approved => 'Approved and applied',
            self::Rejected => 'Rejected',
        };
    }
}
