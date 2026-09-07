<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CommissionStatus;
use App\Models\Client;
use App\Models\Commission;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Models\Recruiter;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Clients\ClientRegistrationService;
use App\Services\Disbursements\DisbursementService;
use App\Services\Loans\AgreementService;
use App\Services\Loans\LoanApplicationService;
use App\Services\Recruiters\RecruiterRegistrationService;
use App\Services\Repayments\RepaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * A working book, so a fresh install has something to look at.
 *
 * Everything here goes through the same services the application uses. Writing
 * rows straight into tables would be quicker and would produce a book with no
 * audit trail, no reference numbers, no repayment schedules and no commission,
 * which is a book that cannot demonstrate any of the things this system exists
 * to do.
 *
 * The clock is moved backwards while each loan is built, so a loan disbursed
 * five months ago really does have five instalments fallen due. Arrears are
 * then a fact about the data rather than a number written into a column.
 *
 * Development only. It is called from DatabaseSeeder behind an environment
 * check, and it does nothing at all if any client already exists, so it will
 * not touch a database that is already in use.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(
        private readonly ClientRegistrationService $clients,
        private readonly RecruiterRegistrationService $recruiters,
        private readonly LoanApplicationService $applications,
        private readonly AgreementService $agreements,
        private readonly DisbursementService $disbursements,
        private readonly RepaymentService $repayments,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(): void
    {
        if (Client::query()->exists()) {
            $this->command?->info('Demo data skipped: this database already has clients.');

            return;
        }

        $staff = $this->staff();
        $recruiters = $this->seedRecruiters($staff['officer']);
        $clients = $this->seedClients($staff['officer'], $recruiters);

        $this->awaitingDecision($staff, $clients[0]);
        $this->declined($staff, $clients[1]);
        $this->approvedNotSigned($staff, $clients[2]);
        $this->awaitingPayout($staff, $clients[3]);
        $this->payingOnTime($staff, $clients[4]);
        $this->inArrears($staff, $clients[5]);
        $this->settled($staff, $clients[6]);

        Carbon::setTestNow();

        $this->command?->info(sprintf(
            'Demo book: %d clients, %d recruiters, %d applications, %d repayments.',
            Client::count(),
            count($recruiters),
            LoanApplication::count(),
            LoanRepayment::count(),
        ));
    }

    /**
     * @return array<string, User>
     */
    private function staff(): array
    {
        return [
            'officer' => User::where('email', 'officer@credithub.test')->firstOrFail(),
            'credit' => User::where('email', 'credit@credithub.test')->firstOrFail(),
            'payouts' => User::where('email', 'payouts@credithub.test')->firstOrFail(),
            'collections' => User::where('email', 'collections@credithub.test')->firstOrFail(),
        ];
    }

    /**
     * @return array<int, Recruiter>
     */
    private function seedRecruiters(User $officer): array
    {
        $people = [
            ['Thabo', 'Mokoena', '900315', '5008', '0721114455', 'Standard Bank', '1002345678'],
            ['Lerato', 'Sithole', '870722', '0912', '0836667788', 'Capitec Bank', '1745009911'],
            ['Sipho', 'Radebe', '820104', '5233', '0714448899', 'Nedbank', '1188223344'],
            ['Nomsa', 'Baloyi', '950630', '0187', '0827773366', 'First National Bank', '6255112233'],
        ];

        $created = [];

        foreach ($people as [$first, $last, $dob, $sequence, $phone, $bank, $account]) {
            $created[] = $this->at('-14 months', fn () => $this->recruiters->register([
                'first_name' => $first,
                'last_name' => $last,
                'id_number' => $this->identityNumber($dob, $sequence),
                'phone' => $phone,
                'bank_name' => $bank,
                'bank_account_number' => $account,
            ], $officer));
        }

        return $created;
    }

    /**
     * @param  array<int, Recruiter>  $recruiters
     * @return array<int, Client>
     */
    private function seedClients(User $officer, array $recruiters): array
    {
        $people = [
            ['Nomsa', 'Zulu', '1992-02-20', '4720', '0731234567', 'Shoprite Holdings', 'Cashier', 'Johannesburg', 'Gauteng', 'Capitec Bank', '1900112233', 18500, 14500, 6200, 2100, 0],
            ['Pieter', 'van Wyk', '1988-01-23', '5111', '0842345678', 'Transnet', 'Technician', 'Durban', 'KwaZulu-Natal', 'First National Bank', '6244556677', 29200, 22800, 9400, 4600, 0],
            ['Ayanda', 'Nkosi', '1975-06-15', '0080', '0793456789', 'Department of Health', 'Nursing Sister', 'Polokwane', 'Limpopo', 'Nedbank', '1177889900', 23400, 18300, 8800, 3200, 1],
            ['Refilwe', 'Molefe', '1995-11-08', '1147', '0764567890', 'Woolworths', 'Team Leader', 'Cape Town', 'Western Cape', 'Absa Bank', '4066778899', 14300, 11200, 5600, 900, null],
            ['Sibusiso', 'Khumalo', '1991-04-12', '5390', '0781122334', 'Eskom', 'Artisan', 'Emalahleni', 'Mpumalanga', 'Standard Bank', '2011223344', 34500, 26100, 10200, 5400, 1],
            ['Thandeka', 'Mbeki', '1986-09-27', '0448', '0605566778', 'Pick n Pay', 'Store Manager', 'Gqeberha', 'Eastern Cape', 'Capitec Bank', '1755667788', 26800, 20400, 8100, 3900, 2],
            ['Johan', 'Pretorius', '1979-12-03', '5062', '0839988776', 'Sasol', 'Foreman', 'Secunda', 'Mpumalanga', 'Nedbank', '1199887766', 41000, 31200, 11800, 6100, 2],
            ['Bongiwe', 'Dlamini', '1998-07-19', '0913', '0723344556', 'Clicks', 'Pharmacy Assistant', 'Bloemfontein', 'Free State', 'TymeBank', '5511223344', 16200, 12900, 5900, 1400, 3],
        ];

        $created = [];

        foreach ($people as $row) {
            [$first, $last, $dob, $sequence, $phone, $employer, $title, $city, $province, $bank, $account, $gross, $net, $living, $debt, $recruiter] = $row;

            $created[] = $this->at('-12 months', fn () => $this->clients->register(
                client: [
                    'first_name' => $first,
                    'last_name' => $last,
                    'id_number' => $this->identityNumber(str_replace('-', '', substr($dob, 2)), $sequence),
                    'phone' => $phone,
                    'employer_name' => $employer,
                    'job_title' => $title,
                    'employment_status' => 'permanent',
                    'city' => $city,
                    'province' => $province,
                    'bank_name' => $bank,
                    'bank_account_number' => $account,
                    'recruiter_id' => $recruiter === null ? null : $recruiters[$recruiter]->id,
                ],
                affordability: [
                    'gross_monthly_income' => $gross,
                    'net_monthly_income' => $net,
                    'monthly_living_expenses' => $living,
                    'monthly_debt_repayments' => $debt,
                ],
                registeredBy: $officer,
            ));
        }

        return $created;
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function awaitingDecision(array $staff, Client $client): void
    {
        $this->at('-3 days', fn () => $this->capture($staff['officer'], $client, 9000.00, 12, 'School fees'));
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function declined(array $staff, Client $client): void
    {
        $application = $this->at('-9 days', fn () => $this->capture($staff['officer'], $client, 15000.00, 18, 'Vehicle repairs'));

        $this->at('-7 days', fn () => $this->applications->decide(
            $application,
            false,
            'Bank statement shows an existing debit order not declared on the affordability assessment.',
            $staff['credit'],
        ));
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function approvedNotSigned(array $staff, Client $client): void
    {
        $application = $this->at('-5 days', fn () => $this->capture($staff['officer'], $client, 12000.00, 18, 'Home improvements'));

        $this->at('-4 days', fn () => $this->applications->decide($application, true, null, $staff['credit']));
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function awaitingPayout(array $staff, Client $client): void
    {
        $application = $this->at('-6 days', fn () => $this->capture($staff['officer'], $client, 7500.00, 12, 'Medical costs'));

        $this->at('-5 days', fn () => $this->applications->decide($application, true, null, $staff['credit']));
        $this->at('-2 days', fn () => $this->sign($staff['officer'], $application, $client));
    }

    /**
     * Five months in, every instalment met on the day it fell due.
     *
     * @param  array<string, User>  $staff
     */
    private function payingOnTime(array $staff, Client $client): void
    {
        $application = $this->disburse($staff, $client, 20000.00, 24, 'Debt consolidation', '-6 months');

        foreach ($application->scheduleEntries()->get() as $entry) {
            if ($entry->due_on->isFuture()) {
                break;
            }

            $this->at($entry->due_on->toDateString(), fn () => $this->repayments->record(
                $application,
                [
                    'amount' => $entry->total_due,
                    'received_on' => $entry->due_on->toDateString(),
                    'method' => 'debit_order',
                    'reference' => 'DO'.$entry->due_on->format('ym'),
                    'note' => null,
                ],
                $staff['collections'],
            ));
        }
    }

    /**
     * Paid twice, then stopped. The arrears follow from the missed months
     * rather than being written anywhere.
     *
     * @param  array<string, User>  $staff
     */
    private function inArrears(array $staff, Client $client): void
    {
        $application = $this->disburse($staff, $client, 16000.00, 18, 'Funeral costs', '-5 months');

        foreach ($application->scheduleEntries()->take(2)->get() as $entry) {
            $this->at($entry->due_on->toDateString(), fn () => $this->repayments->record(
                $application,
                [
                    'amount' => $entry->total_due,
                    'received_on' => $entry->due_on->toDateString(),
                    'method' => 'debit_order',
                    'reference' => 'DO'.$entry->due_on->format('ym'),
                    'note' => null,
                ],
                $staff['collections'],
            ));
        }
    }

    /**
     * A short loan, run to the end and paid off.
     *
     * @param  array<string, User>  $staff
     */
    private function settled(array $staff, Client $client): void
    {
        $application = $this->disburse($staff, $client, 6000.00, 6, 'Study fees', '-8 months');

        foreach ($application->scheduleEntries()->get() as $entry) {
            $this->at($entry->due_on->toDateString(), fn () => $this->repayments->record(
                $application,
                [
                    'amount' => $entry->total_due,
                    'received_on' => $entry->due_on->toDateString(),
                    'method' => 'debit_order',
                    'reference' => 'DO'.$entry->due_on->format('ym'),
                    'note' => null,
                ],
                $staff['collections'],
            ));
        }

        // The introduction earned a commission and it has been settled, so the
        // commissions screen has both states to show.
        $commission = $application->commission()->first();

        if ($commission !== null && $commission->status === CommissionStatus::Pending) {
            $this->at('-7 months', function () use ($commission, $staff, $application): void {
                $commission->update([
                    'status' => CommissionStatus::Paid,
                    'paid_at' => now(),
                    'paid_by' => $staff['payouts']->id,
                ]);

                $this->audit->record(
                    action: 'commission.paid',
                    subject: $application,
                    summary: sprintf(
                        'Paid commission of R%s on %s.',
                        number_format($commission->amount, 2),
                        $application->application_number,
                    ),
                    actor: $staff['payouts'],
                );
            });
        }
    }

    /**
     * Captures, decides, signs, verifies and pays a loan, each step on its own
     * date so the file reads as though it moved through the departments.
     *
     * @param  array<string, User>  $staff
     */
    private function disburse(
        array $staff,
        Client $client,
        float $amount,
        int $term,
        string $purpose,
        string $disbursedAgo,
    ): LoanApplication {
        $start = Carbon::parse($disbursedAgo);

        $application = $this->at(
            $start->copy()->subDays(6)->toDateString(),
            fn () => $this->capture($staff['officer'], $client, $amount, $term, $purpose),
        );

        $this->at(
            $start->copy()->subDays(4)->toDateString(),
            fn () => $this->applications->decide($application, true, null, $staff['credit']),
        );

        $this->at(
            $start->copy()->subDays(2)->toDateString(),
            fn () => $this->sign($staff['officer'], $application, $client),
        );

        $disbursement = $application->disbursement()->firstOrFail();

        $this->at(
            $start->copy()->subDay()->toDateString(),
            fn () => $this->disbursements->verify($disbursement, $staff['payouts']),
        );

        $this->at($start->toDateString(), fn () => $this->disbursements->pay(
            $disbursement->fresh(),
            'EFT'.$start->format('Ymd').str_pad((string) $application->id, 3, '0', STR_PAD_LEFT),
            $staff['payouts'],
        ));

        return $application->fresh();
    }

    private function capture(
        User $officer,
        Client $client,
        float $amount,
        int $term,
        string $purpose,
    ): LoanApplication {
        return $this->applications->submit(
            client: $client->fresh()->load('latestAffordability'),
            data: ['amount' => $amount, 'term_months' => $term, 'purpose' => $purpose],
            submittedBy: $officer,
            documents: $this->documents(),
        );
    }

    private function sign(User $officer, LoanApplication $application, Client $client): void
    {
        $agreement = $this->agreements->generate($application->fresh(), $officer);

        $this->agreements->sign(
            agreement: $agreement,
            signedName: $client->fullName(),
            signature: $this->signatureImage(),
            photo: null,
            witnessedBy: $officer,
        );
    }

    /**
     * Stand-in documents so the file has something to open. They are real
     * files of the right type, just empty of anything meaningful.
     *
     * @return array<string, UploadedFile>
     */
    private function documents(): array
    {
        $documents = [];

        foreach (['id_copy', 'bank_statement', 'payslip'] as $type) {
            $path = tempnam(sys_get_temp_dir(), 'seed');
            file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

            $documents[$type] = new UploadedFile(
                $path,
                str_replace('_', '-', $type).'.pdf',
                'application/pdf',
                null,
                // The file was written here rather than uploaded, so the check
                // for an actual upload has to be waived.
                true,
            );
        }

        return $documents;
    }

    /** A single transparent pixel, which is all the signature needs to be here. */
    private function signatureImage(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    /**
     * Runs a step as though it were happening on a given date, so the record it
     * writes carries that date rather than today's.
     *
     * @template T
     *
     * @param  callable(): T  $step
     * @return T
     */
    private function at(string $when, callable $step): mixed
    {
        Carbon::setTestNow(Carbon::parse($when)->setTime(9, 30));

        try {
            return $step();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Builds a structurally valid ID number so the demo rows would survive the
     * same validation a registration goes through.
     */
    private function identityNumber(string $yymmdd, string $sequence): string
    {
        $first12 = $yymmdd.$sequence.'08';

        $sum = 0;
        // Doubling starts on the twelfth digit, matching SouthAfricanIdNumber.
        $double = true;

        for ($i = 11; $i >= 0; $i--) {
            $digit = (int) $first12[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $first12.((10 - ($sum % 10)) % 10);
    }
}
