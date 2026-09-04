<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The role catalogue and the permissions each role carries.
 *
 * The split between CREDIT_MANAGER and DISBURSEMENT_OFFICER is deliberate and
 * load-bearing: the person who approves a loan must not also be the person who
 * releases the money. Neither role appears in the other's permission list.
 */
final class Roles
{
    public const ADMIN = 'admin';

    public const LOAN_OFFICER = 'loan-officer';

    public const CREDIT_MANAGER = 'credit-manager';

    public const DISBURSEMENT_OFFICER = 'disbursement-officer';

    public const COLLECTIONS_OFFICER = 'collections-officer';

    public const AUDITOR = 'auditor';

    /**
     * @return array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    public static function catalogue(): array
    {
        return [
            self::ADMIN => [
                'name' => 'System Administrator',
                'description' => 'Oversight of the whole book, plus staff accounts and role assignment. Does not move money.',
                // Everything except releasing funds. An administrator can see
                // every payout and every commission, and can hand the job to
                // somebody by assigning the role, but cannot pay one out
                // themselves. Holding both the ability to grant permissions and
                // the ability to release money is the one combination that
                // leaves nothing for anyone else to check.
                'permissions' => array_values(array_diff(Permissions::all(), [
                    Permissions::DISBURSEMENTS_VERIFY,
                    Permissions::DISBURSEMENTS_PAY,
                    Permissions::COMMISSIONS_PAY,
                ])),
            ],

            self::LOAN_OFFICER => [
                'name' => 'Loan Officer',
                'description' => 'Origination: registers recruiters and clients, assesses affordability, captures and submits applications.',
                'permissions' => [
                    Permissions::RECRUITERS_VIEW,
                    Permissions::RECRUITERS_CREATE,
                    Permissions::RECRUITERS_UPDATE,
                    Permissions::CLIENTS_VIEW,
                    Permissions::CLIENTS_CREATE,
                    Permissions::CLIENTS_UPDATE,
                    Permissions::AFFORDABILITY_VIEW,
                    Permissions::AFFORDABILITY_RECORD,
                    Permissions::APPLICATIONS_VIEW,
                    Permissions::APPLICATIONS_CREATE,
                    Permissions::APPLICATIONS_SUBMIT,
                    Permissions::AGREEMENTS_VIEW,
                    Permissions::AGREEMENTS_GENERATE,
                    Permissions::AGREEMENTS_SIGN,
                    Permissions::COMMISSIONS_VIEW,
                    Permissions::REPAYMENTS_VIEW,
                    // May ask for a client or recruiter to be corrected, but
                    // not carry out the correction.
                    Permissions::CHANGE_REQUESTS_VIEW,
                    Permissions::CHANGE_REQUESTS_CREATE,
                ],
            ],

            self::CREDIT_MANAGER => [
                'name' => 'Credit Manager',
                'description' => 'Decides applications. Cannot release funds.',
                'permissions' => [
                    Permissions::RECRUITERS_VIEW,
                    Permissions::CLIENTS_VIEW,
                    Permissions::AFFORDABILITY_VIEW,
                    Permissions::APPLICATIONS_VIEW,
                    Permissions::APPLICATIONS_DECIDE,
                    Permissions::AGREEMENTS_VIEW,
                    Permissions::COMMISSIONS_VIEW,
                    Permissions::REPAYMENTS_VIEW,
                    Permissions::REPORTS_VIEW,
                ],
            ],

            self::DISBURSEMENT_OFFICER => [
                'name' => 'Disbursement Officer',
                'description' => 'Verifies approved, signed loans and releases the loan and commission payments. Cannot approve.',
                'permissions' => [
                    Permissions::CLIENTS_VIEW,
                    Permissions::RECRUITERS_VIEW,
                    Permissions::APPLICATIONS_VIEW,
                    Permissions::AGREEMENTS_VIEW,
                    Permissions::DISBURSEMENTS_VIEW,
                    Permissions::DISBURSEMENTS_VERIFY,
                    Permissions::DISBURSEMENTS_PAY,
                    Permissions::COMMISSIONS_VIEW,
                    Permissions::COMMISSIONS_PAY,
                    Permissions::REPAYMENTS_VIEW,
                ],
            ],

            self::COLLECTIONS_OFFICER => [
                'name' => 'Collections Officer',
                'description' => 'Receipts repayments against disbursed loans. Cannot originate, approve or pay anything out.',
                'permissions' => [
                    Permissions::CLIENTS_VIEW,
                    Permissions::APPLICATIONS_VIEW,
                    Permissions::REPAYMENTS_VIEW,
                    Permissions::REPAYMENTS_RECORD,
                ],
            ],

            self::AUDITOR => [
                'name' => 'Auditor',
                'description' => 'Read-only across the whole pipeline.',
                'permissions' => [...Permissions::readOnly(), Permissions::REPORTS_VIEW],
            ],
        ];
    }
}
