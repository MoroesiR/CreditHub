<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The banks a client or recruiter may be paid into, with their universal
 * branch codes.
 *
 * South African banks each publish one universal branch code that routes to
 * any branch, so capturing a per-branch code is obsolete - the officer picks
 * the bank and the code follows. Held server-side rather than in the SPA so
 * the dropdown and the validation cannot drift apart.
 */
final class SouthAfricanBanks
{
    /**
     * @return array<string, string> Bank name => universal branch code.
     */
    public static function all(): array
    {
        return [
            'Absa Bank' => '632005',
            'African Bank' => '430000',
            'Bank Zero' => '888000',
            'Bidvest Bank' => '462005',
            'Capitec Bank' => '470010',
            'Discovery Bank' => '679000',
            'First National Bank' => '250655',
            'Investec Bank' => '580105',
            'Nedbank' => '198765',
            'Sasfin Bank' => '683000',
            'Standard Bank' => '051001',
            'TymeBank' => '678910',
            'Ubank' => '431010',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function branchCodeFor(string $bank): ?string
    {
        return self::all()[$bank] ?? null;
    }

    /**
     * Shaped for the SPA's dropdown.
     *
     * @return array<int, array{name: string, branch_code: string}>
     */
    public static function forSelect(): array
    {
        return array_map(
            static fn (string $name, string $code): array => ['name' => $name, 'branch_code' => $code],
            array_keys(self::all()),
            array_values(self::all()),
        );
    }
}
