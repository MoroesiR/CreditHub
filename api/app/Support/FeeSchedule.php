<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The two fees a credit agreement may carry besides interest, and the caps the
 * National Credit Act puts on them. Exceed either and the agreement is
 * unlawful, so they are enforced here rather than configured per product.
 *
 * These are the caps as encoded when this was written. They are amended by
 * gazette, so they sit in one file with the VAT rate.
 */
final class FeeSchedule
{
    /** Charged on the portion of the advance above this. */
    public const INITIATION_THRESHOLD = 1000.00;

    /** Flat part of the initiation fee, before VAT. */
    public const INITIATION_BASE = 165.00;

    /** Charged on the advance above the threshold, before VAT. */
    public const INITIATION_RATE = 0.10;

    /** Ceiling on the initiation fee before VAT, whatever the formula gives. */
    public const INITIATION_CAP = 1050.00;

    /** Monthly service fee before VAT. */
    public const SERVICE_FEE = 60.00;

    public const VAT_RATE = 0.15;

    /**
     * The once-off fee for putting the agreement in place.
     *
     * It is charged on the advance rather than on the total repayable: the fee
     * is for originating the loan, not for the interest the lender will earn
     * on it.
     */
    public static function initiationFee(float $advance): float
    {
        $chargeable = max($advance - self::INITIATION_THRESHOLD, 0);
        $fee = min(self::INITIATION_BASE + ($chargeable * self::INITIATION_RATE), self::INITIATION_CAP);

        return self::withVat($fee);
    }

    public static function monthlyServiceFee(): float
    {
        return self::withVat(self::SERVICE_FEE);
    }

    private static function withVat(float $amount): float
    {
        return round($amount * (1 + self::VAT_RATE), 2);
    }
}
