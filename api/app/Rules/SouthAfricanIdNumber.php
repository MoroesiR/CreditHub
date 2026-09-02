<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A South African ID number is 13 digits: YYMMDD, a sequence establishing
 * citizenship and gender, and a Luhn check digit. Validating the structure
 * here stops a mistyped digit becoming a client record that can never be
 * matched to a credit bureau.
 */
final class SouthAfricanIdNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{13}$/', $value) !== 1) {
            $fail('The :attribute must be 13 digits.');

            return;
        }

        if (! $this->hasValidDateOfBirth($value)) {
            $fail('The :attribute does not begin with a valid date of birth.');

            return;
        }

        if (! $this->hasValidCheckDigit($value)) {
            $fail('The :attribute is not a valid South African ID number.');
        }
    }

    private function hasValidDateOfBirth(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('ymd', substr($value, 0, 6));

        return $date !== false && $date->format('ymd') === substr($value, 0, 6);
    }

    /**
     * Luhn, computed right to left over the first twelve digits.
     *
     * The doubling starts ON the twelfth digit - the one immediately left of
     * the check digit - not on the one before it. Getting that parity wrong
     * still produces a self-consistent checksum, so generated numbers validate
     * against each other while every real ID is rejected.
     */
    private function hasValidCheckDigit(string $value): bool
    {
        $sum = 0;
        $double = true;

        for ($i = 11; $i >= 0; $i--) {
            $digit = (int) $value[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return (10 - ($sum % 10)) % 10 === (int) $value[12];
    }
}
