<?php

declare(strict_types=1);

namespace App\Services\Reference;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issues the reference numbers people quote on the phone: CHC000001,
 * CHR000001, CHL000001.
 *
 * Every reference carries the house prefix CH, so a number is recognisable as
 * CreditHub's wherever it is quoted, followed by one letter for what it
 * identifies - Client, Recruiter, Loan - and a zero-padded sequence. Each
 * sequence counts independently.
 *
 * Every call must run inside a transaction the caller owns, so that a number
 * is not consumed when the row it was issued for fails to save.
 */
final class ReferenceNumberService
{
    private const CLIENT = 'client';

    private const RECRUITER = 'recruiter';

    private const APPLICATION = 'application';

    /** Shared house prefix, taken from the CreditHub name. */
    private const HOUSE = 'CH';

    private const PREFIXES = [
        self::CLIENT => self::HOUSE.'C',
        self::RECRUITER => self::HOUSE.'R',
        self::APPLICATION => self::HOUSE.'L',
    ];

    private const WIDTH = 6;

    public function nextClientNumber(): string
    {
        return $this->next(self::CLIENT);
    }

    public function nextRecruiterNumber(): string
    {
        return $this->next(self::RECRUITER);
    }

    public function nextApplicationNumber(): string
    {
        return $this->next(self::APPLICATION);
    }

    private function next(string $key): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                "A reference number for [{$key}] was requested outside a transaction; "
                .'it would be consumed even if the record failed to save.'
            );
        }

        // Locks the counter row until the caller's transaction commits, so two
        // concurrent registrations cannot be handed the same number.
        $current = DB::table('reference_counters')
            ->where('key', $key)
            ->lockForUpdate()
            ->value('next_value');

        if ($current === null) {
            DB::table('reference_counters')->insert([
                'key' => $key,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = DB::table('reference_counters')
                ->where('key', $key)
                ->lockForUpdate()
                ->value('next_value');
        }

        $value = (int) $current;

        DB::table('reference_counters')
            ->where('key', $key)
            ->update(['next_value' => $value + 1, 'updated_at' => now()]);

        return self::PREFIXES[$key].str_pad((string) $value, self::WIDTH, '0', STR_PAD_LEFT);
    }
}
