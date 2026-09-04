<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The permission catalogue.
 *
 * One source of truth: the seeder builds the `permissions` table from here and
 * route definitions reference the same constants, so a typo in a route is a
 * static error rather than a silently open endpoint.
 */
final class Permissions
{
    public const RECRUITERS_VIEW = 'recruiters.view';

    public const RECRUITERS_CREATE = 'recruiters.create';

    public const RECRUITERS_UPDATE = 'recruiters.update';

    public const CLIENTS_VIEW = 'clients.view';

    public const CLIENTS_CREATE = 'clients.create';

    public const CLIENTS_UPDATE = 'clients.update';

    public const AFFORDABILITY_VIEW = 'affordability.view';

    public const AFFORDABILITY_RECORD = 'affordability.record';

    public const APPLICATIONS_VIEW = 'applications.view';

    public const APPLICATIONS_CREATE = 'applications.create';

    public const APPLICATIONS_SUBMIT = 'applications.submit';

    public const APPLICATIONS_DECIDE = 'applications.decide';

    public const AGREEMENTS_VIEW = 'agreements.view';

    public const AGREEMENTS_GENERATE = 'agreements.generate';

    public const AGREEMENTS_SIGN = 'agreements.sign';

    public const DISBURSEMENTS_VIEW = 'disbursements.view';

    public const DISBURSEMENTS_VERIFY = 'disbursements.verify';

    public const DISBURSEMENTS_PAY = 'disbursements.pay';

    public const REPAYMENTS_VIEW = 'repayments.view';

    public const REPAYMENTS_RECORD = 'repayments.record';

    public const COMMISSIONS_VIEW = 'commissions.view';

    public const COMMISSIONS_PAY = 'commissions.pay';

    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const CHANGE_REQUESTS_VIEW = 'change-requests.view';

    public const CHANGE_REQUESTS_CREATE = 'change-requests.create';

    public const CHANGE_REQUESTS_REVIEW = 'change-requests.review';

    /**
     * Every permission, as slug => [name, group].
     *
     * @return array<string, array{name: string, group: string}>
     */
    public static function catalogue(): array
    {
        return [
            self::RECRUITERS_VIEW => ['name' => 'View recruiters', 'group' => 'recruiters'],
            self::RECRUITERS_CREATE => ['name' => 'Register recruiters', 'group' => 'recruiters'],
            self::RECRUITERS_UPDATE => ['name' => 'Update recruiters', 'group' => 'recruiters'],

            self::CLIENTS_VIEW => ['name' => 'View clients', 'group' => 'clients'],
            self::CLIENTS_CREATE => ['name' => 'Register clients', 'group' => 'clients'],
            self::CLIENTS_UPDATE => ['name' => 'Update clients', 'group' => 'clients'],

            self::AFFORDABILITY_VIEW => ['name' => 'View affordability assessments', 'group' => 'affordability'],
            self::AFFORDABILITY_RECORD => ['name' => 'Record affordability assessments', 'group' => 'affordability'],

            self::APPLICATIONS_VIEW => ['name' => 'View loan applications', 'group' => 'applications'],
            self::APPLICATIONS_CREATE => ['name' => 'Capture loan applications', 'group' => 'applications'],
            self::APPLICATIONS_SUBMIT => ['name' => 'Submit applications for decision', 'group' => 'applications'],
            self::APPLICATIONS_DECIDE => ['name' => 'Approve or decline applications', 'group' => 'applications'],

            self::AGREEMENTS_VIEW => ['name' => 'View agreements', 'group' => 'agreements'],
            self::AGREEMENTS_GENERATE => ['name' => 'Generate agreements', 'group' => 'agreements'],
            self::AGREEMENTS_SIGN => ['name' => 'Capture agreement signatures', 'group' => 'agreements'],

            self::DISBURSEMENTS_VIEW => ['name' => 'View the disbursement queue', 'group' => 'disbursements'],
            self::DISBURSEMENTS_VERIFY => ['name' => 'Verify payout instructions', 'group' => 'disbursements'],
            self::DISBURSEMENTS_PAY => ['name' => 'Release loan payments', 'group' => 'disbursements'],

            self::REPAYMENTS_VIEW => ['name' => 'View loan accounts and repayments', 'group' => 'repayments'],
            self::REPAYMENTS_RECORD => ['name' => 'Record repayments received', 'group' => 'repayments'],

            self::COMMISSIONS_VIEW => ['name' => 'View recruiter commissions', 'group' => 'commissions'],
            self::COMMISSIONS_PAY => ['name' => 'Release commission payments', 'group' => 'commissions'],

            self::USERS_VIEW => ['name' => 'View staff accounts', 'group' => 'users'],
            self::USERS_MANAGE => ['name' => 'Manage staff accounts and roles', 'group' => 'users'],

            self::REPORTS_VIEW => ['name' => 'View reports', 'group' => 'reports'],

            self::CHANGE_REQUESTS_VIEW => ['name' => 'View change requests', 'group' => 'change-requests'],
            self::CHANGE_REQUESTS_CREATE => ['name' => 'Request a change to a client or recruiter', 'group' => 'change-requests'],
            self::CHANGE_REQUESTS_REVIEW => ['name' => 'Approve or reject change requests', 'group' => 'change-requests'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::catalogue());
    }

    /**
     * Read-only subset, used to build the auditor role.
     *
     * @return array<int, string>
     */
    public static function readOnly(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $slug): bool => str_ends_with($slug, '.view'),
        ));
    }
}
