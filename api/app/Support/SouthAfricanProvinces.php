<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The nine provinces.
 *
 * A closed list rather than free text: a province typed by hand produces
 * "KZN", "Kwazulu Natal" and "KwaZulu-Natal" in the same column, and no
 * regional report can be run over it afterwards.
 */
final class SouthAfricanProvinces
{
    /** The lender operates in one country; there is no second option to offer. */
    public const COUNTRY = 'South Africa';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'Eastern Cape',
            'Free State',
            'Gauteng',
            'KwaZulu-Natal',
            'Limpopo',
            'Mpumalanga',
            'Northern Cape',
            'North West',
            'Western Cape',
        ];
    }
}
