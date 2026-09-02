<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Reference data: required in every environment, production included.
        $this->call(RolePermissionSeeder::class);
        $this->call(CommissionSchemeSeeder::class);

        // Demo staff accounts carry a known password, so they stay out of
        // production regardless of how the seeder is invoked.
        if (! app()->environment('production')) {
            $this->call(UserSeeder::class);
            $this->call(DemoDataSeeder::class);
        }
    }
}
