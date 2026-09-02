<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Reads what a South African ID number already tells you.
 *
 * The 13 digits are YYMMDD | SSSS | C | A | Z: date of birth, a gender
 * sequence where 0000-4999 is female and 5000-9999 is male, a citizenship
 * digit, and a Luhn check digit.
 *
 * Date of birth and gender are therefore facts about the number, not separate
 * fields to be captured - so they are derived here and never accepted from the
 * request. A typo in a keyed date of birth would otherwise contradict the ID
 * on the same record.
 */
final class IdentityNumber
{
    public static function isWellFormed(string $id): bool
    {
        return preg_match('/^\d{13}$/', $id) === 1 && self::dateOfBirth($id) !== null;
    }

    public static function dateOfBirth(string $id): ?DateTimeImmutable
    {
        if (preg_match('/^\d{13}$/', $id) !== 1) {
            return null;
        }

        $yy = (int) substr($id, 0, 2);
        $mmdd = substr($id, 2, 4);

        // Two digits cannot say which century. Anything that would place the
        // birth in the future belongs to the previous one.
        $century = ((int) date('Y') % 100) >= $yy ? 2000 : 1900;

        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%d-%s-%s 00:00:00', $century + $yy, substr($mmdd, 0, 2), substr($mmdd, 2, 2)),
        );

        if ($date === false) {
            return null;
        }

        // createFromFormat rolls invalid dates forward (32 January becomes
        // 1 February), so the round trip is what actually rejects them.
        return $date->format('ymd') === substr($id, 0, 6) ? $date : null;
    }

    /**
     * @return 'female'|'male'|null
     */
    public static function gender(string $id): ?string
    {
        if (preg_match('/^\d{13}$/', $id) !== 1) {
            return null;
        }

        return (int) substr($id, 6, 4) < 5000 ? 'female' : 'male';
    }

    public static function age(string $id, ?DateTimeImmutable $on = null): ?int
    {
        $birth = self::dateOfBirth($id);

        if ($birth === null) {
            return null;
        }

        return $birth->diff($on ?? new DateTimeImmutable('today'))->y;
    }
}
