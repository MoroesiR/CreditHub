<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Services\Clients\ClientRegistrationService;
use App\Services\Recruiters\RecruiterRegistrationService;
use Illuminate\Database\Seeder;

/**
 * A handful of recruiters and clients so the dashboard and the client search
 * have something to show on a fresh install. Development only.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(
        private readonly ClientRegistrationService $registration,
        private readonly RecruiterRegistrationService $recruiters,
    ) {}

    public function run(): void
    {
        if (Client::query()->exists()) {
            return;
        }

        $officer = User::where('email', 'officer@credithub.test')->firstOrFail();

        $recruiters = [
            ['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => $this->id('900315', '5008'), 'phone' => '0721114455'],
            ['first_name' => 'Lerato', 'last_name' => 'Sithole', 'id_number' => $this->id('870722', '0912'), 'phone' => '0836667788'],
        ];

        $created = [];

        foreach ($recruiters as $recruiter) {
            $created[] = $this->recruiters->register([
                ...$recruiter,
                'bank_name' => 'Standard Bank',
                'bank_account_number' => '10'.substr($recruiter['id_number'], 0, 8),
            ], $officer);
        }

        $clients = [
            ['first_name' => 'Nomsa', 'last_name' => 'Zulu', 'dob' => '1992-02-20', 'seq' => '4720', 'phone' => '0731234567', 'employer' => 'Shoprite Holdings', 'net' => 14500, 'living' => 6200, 'debt' => 2100, 'recruiter' => 0, 'bank' => 'Capitec Bank'],
            ['first_name' => 'Pieter', 'last_name' => 'van Wyk', 'dob' => '1988-01-23', 'seq' => '5111', 'phone' => '0842345678', 'employer' => 'Transnet', 'net' => 22800, 'living' => 9400, 'debt' => 4600, 'recruiter' => 0, 'bank' => 'First National Bank'],
            ['first_name' => 'Ayanda', 'last_name' => 'Nkosi', 'dob' => '1975-06-15', 'seq' => '0080', 'phone' => '0793456789', 'employer' => 'Department of Health', 'net' => 18300, 'living' => 8800, 'debt' => 3200, 'recruiter' => 1, 'bank' => 'Nedbank'],
            ['first_name' => 'Refilwe', 'last_name' => 'Molefe', 'dob' => '1995-11-08', 'seq' => '1147', 'phone' => '0764567890', 'employer' => 'Woolworths', 'net' => 11200, 'living' => 5600, 'debt' => 900, 'recruiter' => null, 'bank' => 'Absa Bank'],
        ];

        foreach ($clients as $client) {
            $this->registration->register(
                client: [
                    'first_name' => $client['first_name'],
                    'last_name' => $client['last_name'],
                    'id_number' => $this->id(str_replace('-', '', substr($client['dob'], 2)), $client['seq']),
                    'phone' => $client['phone'],
                    'employer_name' => $client['employer'],
                    'employment_status' => 'permanent',
                    'city' => 'Johannesburg',
                    'province' => 'Gauteng',
                    'bank_name' => $client['bank'],
                    'bank_account_number' => '62'.substr($client['phone'], 2),
                    'recruiter_id' => $client['recruiter'] === null ? null : $created[$client['recruiter']]->id,
                ],
                affordability: [
                    'gross_monthly_income' => $client['net'] * 1.28,
                    'net_monthly_income' => $client['net'],
                    'monthly_living_expenses' => $client['living'],
                    'monthly_debt_repayments' => $client['debt'],
                ],
                registeredBy: $officer,
            );
        }
    }

    /**
     * Builds a structurally valid ID number so the demo rows would survive the
     * same validation a registration goes through.
     */
    private function id(string $yymmdd, string $sequence): string
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
