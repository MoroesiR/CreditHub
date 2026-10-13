<?php

declare(strict_types=1);

use App\Models\LoanApplication;
use App\Models\User;
use App\Services\Clients\ClientRegistrationService;
use App\Services\Disbursements\DisbursementService;
use App\Services\Loans\AgreementService;
use App\Services\Loans\LoanApplicationService;
use App\Services\Repayments\ArrearsAge;
use App\Services\Repayments\RepaymentService;
use App\Support\Roles;
use Database\Seeders\CommissionSchemeSeeder;
use Database\Seeders\RolePermissionSeeder;

// How long an account has been behind is what collections sorts on, so the
// date the age is taken from has to survive a part payment.
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CommissionSchemeSeeder::class);

    $this->officer = User::factory()->withRole(Roles::LOAN_OFFICER)->create();
    $this->credit = User::factory()->withRole(Roles::CREDIT_MANAGER)->create();
    $this->payouts = User::factory()->withRole(Roles::DISBURSEMENT_OFFICER)->create();
    $this->collections = User::factory()->withRole(Roles::COLLECTIONS_OFFICER)->create();
});

/** A structurally valid South African ID number, unique per call. */
function ageingIdNumber(): string
{
    static $sequence = 3000;
    $first12 = '870303'.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT).'08';

    $sum = 0;
    $double = true;

    for ($i = 11; $i >= 0; $i--) {
        $digit = (int) $first12[$i];

        if ($double) {
            $digit *= 2;
            $digit = $digit > 9 ? $digit - 9 : $digit;
        }

        $sum += $digit;
        $double = ! $double;
    }

    return $first12.((10 - ($sum % 10)) % 10);
}

function ageingLoan(float $amount = 20000, int $term = 12): LoanApplication
{
    $client = app(ClientRegistrationService::class)->register(
        client: [
            'first_name' => 'Ageing',
            'last_name' => 'Borrower'.random_int(1000, 9999),
            'id_number' => ageingIdNumber(),
            'phone' => '0721234567',
            'employment_status' => 'permanent',
        ],
        affordability: [
            'gross_monthly_income' => 39000,
            'net_monthly_income' => 30000,
            'monthly_living_expenses' => 2000,
            'monthly_debt_repayments' => 1000,
        ],
        registeredBy: test()->officer,
    );

    $application = app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => $amount, 'term_months' => $term, 'purpose' => 'Testing'],
        submittedBy: test()->officer,
    );

    app(LoanApplicationService::class)->decide($application, true, null, test()->credit);

    $agreement = app(AgreementService::class)->generate($application->fresh(), test()->officer);
    app(AgreementService::class)->sign(
        agreement: $agreement,
        signedName: $client->fullName(),
        signature: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        photo: null,
        witnessedBy: test()->officer,
    );

    $disbursement = $application->fresh()->disbursement;
    app(DisbursementService::class)->verify($disbursement, test()->payouts);
    app(DisbursementService::class)->pay($disbursement->fresh(), 'EFT-TEST', test()->payouts);

    return $application->fresh();
}

/** The date a given instalment falls due, plus however many days. */
function dueDatePlus(LoanApplication $loan, int $instalment, int $days): DateTimeImmutable
{
    $entry = $loan->scheduleEntries()->where('instalment_number', $instalment)->firstOrFail();

    return new DateTimeImmutable($entry->due_on->addDays($days)->toDateString());
}

it('reads as current while nothing has fallen due', function (): void {
    $loan = ageingLoan();

    $age = app(ArrearsAge::class)->for($loan, dueDatePlus($loan, 1, -5));

    expect($age['bucket'])->toBe(ArrearsAge::CURRENT)
        ->and($age['days'])->toBe(0)
        ->and($age['oldest_unpaid_due_on'])->toBeNull();
});

it('ages from the due date of the instalment that was missed', function (): void {
    $loan = ageingLoan();
    $on = dueDatePlus($loan, 1, 12);

    $age = app(ArrearsAge::class)->for($loan, $on);

    expect($age['days'])->toBe(12)
        ->and($age['bucket'])->toBe('1-30')
        ->and($age['oldest_unpaid_due_on'])
        ->toBe($loan->scheduleEntries()->where('instalment_number', 1)->value('due_on')->toDateString());
});

it('moves the age forward when a payment covers the oldest instalment', function (): void {
    $loan = ageingLoan();
    $first = $loan->scheduleEntries()->where('instalment_number', 1)->firstOrFail();

    app(RepaymentService::class)->record($loan, [
        'amount' => $first->total_due,
        'received_on' => $first->due_on->toDateString(),
        'method' => 'debit_order',
        'reference' => null,
        'note' => null,
    ], $this->collections);

    // Far enough on that the second instalment is a week overdue.
    $age = app(ArrearsAge::class)->for($loan->fresh(), dueDatePlus($loan, 2, 7));

    expect($age['days'])->toBe(7)
        ->and($age['oldest_unpaid_due_on'])
        ->toBe($loan->scheduleEntries()->where('instalment_number', 2)->value('due_on')->toDateString());
});

it('still ages from the oldest instalment when a payment falls short of it', function (): void {
    $loan = ageingLoan();
    $first = $loan->scheduleEntries()->where('instalment_number', 1)->firstOrFail();

    app(RepaymentService::class)->record($loan, [
        'amount' => round($first->total_due - 50, 2),
        'received_on' => $first->due_on->toDateString(),
        'method' => 'cash',
        'reference' => null,
        'note' => null,
    ], $this->collections);

    $age = app(ArrearsAge::class)->for($loan->fresh(), dueDatePlus($loan, 1, 20));

    expect($age['days'])->toBe(20)
        ->and($age['oldest_unpaid_due_on'])->toBe($first->due_on->toDateString());
});

it('sorts a long-standing default into the oldest bucket', function (): void {
    $loan = ageingLoan();

    $age = app(ArrearsAge::class)->for($loan, dueDatePlus($loan, 1, 95));

    expect($age['bucket'])->toBe('90+')
        ->and($age['days'])->toBe(95);
});
