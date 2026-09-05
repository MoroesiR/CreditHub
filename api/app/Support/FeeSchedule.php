<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The fees a credit agreement may carry, and the caps on them.
 *
 * South African lenders charge two fees besides interest: a once-off
 * initiation fee, and a monthly service fee. Both are capped by regulation
 * under the National Credit Act, and a lender that exceeds either has written
 * an unlawful agreement, so the caps are enforced here rather than trusted to
 * whoever configures a product.
 *
 * The figures below are the caps as encoded when this was written. They are
 * amended by gazette from time to time, so they live together in one class
 * with the VAT rate: changing them is a change to this file and a new fee
 * version on the agreements written afterwards, never a silent edit that
 * repriced loans already on the book.
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
