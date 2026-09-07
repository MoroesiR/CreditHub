<?php

declare(strict_types=1);

use App\Enums\CommissionStatus;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\Recruiter;
use App\Models\User;
use App\Services\Clients\BorrowingEligibility;
use App\Services\Clients\ClientRegistrationService;
use App\Services\Disbursements\DisbursementService;
use App\Services\Loans\AgreementService;
use App\Services\Loans\LoanApplicationService;
use App\Services\Recruiters\RecruiterRegistrationService;
use App\Services\Repayments\LoanAccount;
use App\Services\Repayments\RepaymentService;
use App\Support\Roles;
use Database\Seeders\CommissionSchemeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

/**
 * The money paths, end to end, through the services the application uses.
 *
 * These are the parts that must not drift: who may borrow, what a payment is
 * applied to, what a recruiter earns, and the order in which a loan may be
 * released. All of it was previously verified only by hand.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CommissionSchemeSeeder::class);

    $this->officer = User::factory()->withRole(Roles::LOAN_OFFICER)->create();
    $this->credit = User::factory()->withRole(Roles::CREDIT_MANAGER)->create();
    $this->payouts = User::factory()->withRole(Roles::DISBURSEMENT_OFFICER)->create();
    $this->collections = User::factory()->withRole(Roles::COLLECTIONS_OFFICER)->create();
});

/**
 * Registers a client with enough disposable income to borrow comfortably.
 */
function makeClient(?Recruiter $recruiter = null, float $net = 30000): Client
{
    return app(ClientRegistrationService::class)->register(
        client: [
            'first_name' => 'Test',
            'last_name' => 'Borrower'.random_int(1000, 9999),
            'id_number' => validIdNumber(),
            'phone' => '0721234567',
            'employment_status' => 'permanent',
            'recruiter_id' => $recruiter?->id,
        ],
        affordability: [
            'gross_monthly_income' => $net * 1.3,
            'net_monthly_income' => $net,
            'monthly_living_expenses' => 2000,
            'monthly_debt_repayments' => 1000,
        ],
        registeredBy: test()->officer,
    );
}

function makeRecruiter(): Recruiter
{
    return app(RecruiterRegistrationService::class)->register([
        'first_name' => 'Test',
        'last_name' => 'Introducer'.random_int(1000, 9999),
        'id_number' => validIdNumber(),
        'phone' => '0729876543',
        'bank_name' => 'Nedbank',
        'bank_account_number' => '1234567890',
    ], test()->officer);
}

/** A structurally valid South African ID number, unique per call. */
function validIdNumber(): string
{
    static $sequence = 5000;
    $first12 = '900101'.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT).'08';

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

/**
 * Takes a loan all the way to paid out.
 */
function disburse(Client $client, float $amount = 20000, int $term = 12): LoanApplication
{
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

it('refuses a second loan while the first is still being repaid', function (): void {
    $client = makeClient();
    disburse($client);

    expect(app(BorrowingEligibility::class)->check($client->fresh())['eligible'])->toBeFalse();

    app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => 5000, 'term_months' => 6, 'purpose' => 'Second loan'],
        submittedBy: $this->officer,
    );
})->throws(RuntimeException::class, 'still has');

it('refuses a second loan while the first is only part way through the pipeline', function (): void {
    $client = makeClient();

    app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => 8000, 'term_months' => 12, 'purpose' => 'First'],
        submittedBy: $this->officer,
    );

    // Captured but nowhere near paid out, and still a reason to refuse.
    app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => 3000, 'term_months' => 6, 'purpose' => 'Second'],
        submittedBy: $this->officer,
    );
})->throws(RuntimeException::class, 'one loan running at a time');

it('lets a client borrow again once the loan is settled', function (): void {
    $client = makeClient();
    $loan = disburse($client, 6000, 6);

    foreach ($loan->scheduleEntries()->get() as $entry) {
        app(RepaymentService::class)->record($loan, [
            'amount' => $entry->total_due,
            'received_on' => now()->toDateString(),
            'method' => 'eft',
            'reference' => null,
            'note' => null,
        ], $this->collections);
    }

    expect(app(LoanAccount::class)->summarise($loan->fresh())['is_settled'])->toBeTrue()
        ->and(app(BorrowingEligibility::class)->check($client->fresh())['eligible'])->toBeTrue();
});

it('applies a payment to fees, then interest, then capital', function (): void {
    $client = makeClient();
    $loan = disburse($client, 20000, 12);

    // Far enough on that several instalments have fallen due.
    Carbon::setTestNow(now()->addMonths(3));

    $due = $loan->scheduleEntries()->whereDate('due_on', '<=', now())->get();
    $feesDue = round((float) $due->sum('service_fee_due'), 2);
    $interestDue = round((float) $due->sum('interest_due'), 2);

    expect($due)->not->toBeEmpty();

    $pay = fn (float $amount) => app(RepaymentService::class)->record($loan, [
        'amount' => $amount,
        'received_on' => now()->toDateString(),
        'method' => 'cash',
        'reference' => null,
        'note' => null,
    ], $this->collections);

    // Short of the fees outstanding, so every cent goes to fees and none of it
    // touches the balance.
    $first = $pay(round($feesDue - 10, 2));

    expect($first->fee_portion)->toBe(round($feesDue - 10, 2))
        ->and($first->interest_portion)->toBe(0.0)
        ->and($first->capital_portion)->toBe(0.0);

    // Enough to finish the fees and start on interest, still nothing to capital.
    $second = $pay(60.00);

    expect($second->fee_portion)->toBe(10.0)
        ->and($second->interest_portion)->toBe(50.0)
        ->and($second->capital_portion)->toBe(0.0);

    // Past the interest outstanding, so the remainder finally reduces capital.
    $third = $pay(round($interestDue - 50 + 400, 2));

    expect($third->fee_portion)->toBe(0.0)
        ->and($third->interest_portion)->toBe(round($interestDue - 50, 2))
        ->and($third->capital_portion)->toBe(400.0);

    Carbon::setTestNow();
});

it('leaves a reversal with no net effect on the account', function (): void {
    $client = makeClient();
    $loan = disburse($client, 10000, 12);

    $before = app(LoanAccount::class)->summarise($loan)['balance'];

    $receipt = app(RepaymentService::class)->record($loan, [
        'amount' => 1000,
        'received_on' => now()->toDateString(),
        'method' => 'eft',
        'reference' => null,
        'note' => null,
    ], $this->collections);

    app(RepaymentService::class)->reverse($receipt, 'Debit order returned', $this->collections);

    expect(app(LoanAccount::class)->summarise($loan->fresh())['balance'])->toBe($before);
});

it('earns the recruiter commission only when the loan is paid out', function (): void {
    $recruiter = makeRecruiter();
    $client = makeClient($recruiter);

    $application = app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => 20000, 'term_months' => 12, 'purpose' => 'Testing'],
        submittedBy: $this->officer,
    );

    app(LoanApplicationService::class)->decide($application, true, null, $this->credit);

    // Approved is not paid, so nothing is earned yet.
    expect($application->fresh()->commission)->toBeNull();

    disburseFrom($application, $client);

    $commission = $application->fresh()->commission;

    // R20 000 falls in the 5% band, and the band is capped above the fee.
    expect($commission)->not->toBeNull()
        ->and($commission->rate_applied)->toBe(5.0)
        ->and($commission->amount)->toBe(1000.0)
        ->and($commission->status)->toBe(CommissionStatus::Pending);
});

it('pays no commission on a walk-in client', function (): void {
    $loan = disburse(makeClient(), 20000, 12);

    expect($loan->recruiter_id)->toBeNull()
        ->and($loan->commission)->toBeNull();
});

it('will not release a payout that has not been verified', function (): void {
    $client = makeClient();

    $application = app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => 9000, 'term_months' => 12, 'purpose' => 'Testing'],
        submittedBy: $this->officer,
    );

    app(LoanApplicationService::class)->decide($application, true, null, $this->credit);
    $agreement = app(AgreementService::class)->generate($application->fresh(), $this->officer);
    app(AgreementService::class)->sign(
        agreement: $agreement,
        signedName: 'Test Borrower',
        signature: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        photo: null,
        witnessedBy: $this->officer,
    );

    app(DisbursementService::class)->pay(
        $application->fresh()->disbursement,
        'EFT-TEST',
        $this->payouts,
    );
})->throws(RuntimeException::class, 'only released once it has been verified');

it('refuses an instalment the client cannot afford', function (): void {
    // Disposable income of R1 000 will not carry a R50 000 loan.
    $client = makeClient(null, 4000);

    app(LoanApplicationService::class)->submit(
        client: $client->fresh()->load('latestAffordability'),
        data: ['amount' => 50000, 'term_months' => 12, 'purpose' => 'Testing'],
        submittedBy: $this->officer,
    );
})->throws(RuntimeException::class, 'exceeds the disposable income');

/**
 * Signs, verifies and pays an application that has already been decided.
 */
function disburseFrom(LoanApplication $application, Client $client): void
{
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
}
