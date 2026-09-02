<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One demo account per role, so the whole pipeline can be walked end to end
 * locally. These are development credentials and are never seeded in
 * production - see DatabaseSeeder.
 */
class UserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $people = [
            ['Thandi', 'Naidoo', 'admin@credithub.test', 'System Administrator', Roles::ADMIN],
            ['Sipho', 'Dlamini', 'officer@credithub.test', 'Loan Officer', Roles::LOAN_OFFICER],
            ['Naledi', 'Khumalo', 'credit@credithub.test', 'Credit Manager', Roles::CREDIT_MANAGER],
            ['Kagiso', 'Pillay', 'payouts@credithub.test', 'Disbursement Officer', Roles::DISBURSEMENT_OFFICER],
            ['Fatima', 'Adams', 'auditor@credithub.test', 'Internal Auditor', Roles::AUDITOR],
        ];

        $roleIds = Role::query()->pluck('id', 'slug');

        foreach ($people as [$firstName, $lastName, $email, $jobTitle, $role]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'job_title' => $jobTitle,
                    'password' => Hash::make(self::PASSWORD),
                    'is_active' => true,
                ],
            );

            $user->roles()->sync([$roleIds[$role]]);
        }
    }
}
