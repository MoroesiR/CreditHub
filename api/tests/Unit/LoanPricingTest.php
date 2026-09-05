<?php

declare(strict_types=1);

use App\Services\Loans\InstalmentCalculator;
use App\Support\FeeSchedule;

it('charges the initiation fee only on the advance above the threshold', function (): void {
    // Nothing is chargeable below the threshold, so both give the flat part.
    expect(FeeSchedule::initiationFee(500.00))->toBe(FeeSchedule::initiationFee(1000.00));

    // R2 000 is R1 000 above the threshold, so R165 + R100, plus VAT.
    expect(FeeSchedule::initiationFee(2000.00))->toBe(304.75);
});

it('caps the initiation fee however large the loan', function (): void {
    $cap = round(FeeSchedule::INITIATION_CAP * (1 + FeeSchedule::VAT_RATE), 2);

    expect(FeeSchedule::initiationFee(50_000.00))->toBe($cap)
        ->and(FeeSchedule::initiationFee(250_000.00))->toBe($cap);
});

it('finances the initiation fee rather than deducting it from the advance', function (): void {
    $quote = (new InstalmentCalculator)->quote(20_000.00, 24);

    // The client receives the full advance; the fee is added to what they owe.
    expect($quote['advance'])->toBe(20_000.00)
        ->and($quote['amount_financed'])->toBe(round(20_000.00 + $quote['initiation_fee'], 2));
});

it('keeps the service fee out of the amortisation', function (): void {
    $quote = (new InstalmentCalculator)->quote(20_000.00, 24);

    // The instalment is the amortising part plus a flat fee, and the fee earns
    // no interest, so it multiplies out exactly over the term.
    expect($quote['monthly_instalment'])
        ->toBe(round($quote['capital_instalment'] + $quote['monthly_service_fee'], 2))
        ->and($quote['total_service_fees'])
        ->toBe(round($quote['monthly_service_fee'] * 24, 2));
});

it('prices on reducing balance, so a longer term costs more in total', function (): void {
    $calculator = new InstalmentCalculator;

    $short = $calculator->quote(20_000.00, 12);
    $long = $calculator->quote(20_000.00, 36);

    expect($long['monthly_instalment'])->toBeLessThan($short['monthly_instalment'])
        ->and($long['cost_of_credit'])->toBeGreaterThan($short['cost_of_credit']);
});

it('handles an interest free loan without dividing by zero', function (): void {
    $quote = (new InstalmentCalculator)->quote(12_000.00, 12, 0.0);

    expect($quote['total_interest'])->toBe(0.0)
        ->and($quote['capital_instalment'])->toBe(round($quote['amount_financed'] / 12, 2));
});

it('refuses an instalment larger than what the client has left', function (): void {
    $calculator = new InstalmentCalculator;

    expect($calculator->isAffordable(1_200.00, 1_500.00))->toBeTrue()
        ->and($calculator->isAffordable(1_200.00, 1_200.00))->toBeTrue()
        ->and($calculator->isAffordable(1_200.01, 1_200.00))->toBeFalse();
});
