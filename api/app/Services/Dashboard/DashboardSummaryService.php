<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CommissionStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\Client;
use App\Models\Commission;
use App\Models\LoanApplication;
use App\Models\Recruiter;
use App\Models\User;
use App\Support\Permissions;

/**
 * The dashboard tiles.
 *
 * A tile is only built when the signed-in user holds the permission for the
 * module it summarises - a Credit Manager has no business seeing a payout
 * total, so the figure is never sent rather than merely hidden in the browser.
 */
final class DashboardSummaryService
{
    /**
     * @return array<int, array{key: string, label: string, value: int|float, caption: string, format: string, href: string}>
     */
    public function forUser(User $user): array
    {
        $tiles = [];

        if ($user->hasPermission(Permissions::CLIENTS_VIEW)) {
            $tiles[] = [
                'key' => 'clients',
                'label' => 'Clients',
                'value' => Client::count(),
                'caption' => 'Registered borrowers',
                'format' => 'number',
                'href' => '/clients',
            ];
        }

        if ($user->hasPermission(Permissions::RECRUITERS_VIEW)) {
            $tiles[] = [
                'key' => 'recruiters',
                'label' => 'Recruiters',
                'value' => Recruiter::where('is_active', true)->count(),
                'caption' => 'Active introducers',
                'format' => 'number',
                'href' => '/recruiters',
            ];
        }

        if ($user->hasPermission(Permissions::APPLICATIONS_VIEW)) {
            $tiles[] = [
                'key' => 'applications',
                'label' => 'Applications',
                'value' => LoanApplication::count(),
                'caption' => 'Captured to date',
                'format' => 'number',
                'href' => '/applications',
            ];

            $tiles[] = [
                'key' => 'applications_pending',
                'label' => 'Awaiting decision',
                'value' => LoanApplication::where('status', LoanApplicationStatus::Submitted)->count(),
                'caption' => 'Submitted, not yet decided',
                'format' => 'number',
                'href' => '/applications',
            ];
        }

        if ($user->hasPermission(Permissions::DISBURSEMENTS_VIEW)) {
            $tiles[] = [
                'key' => 'awaiting_payout',
                'label' => 'Awaiting payout',
                'value' => LoanApplication::where('status', LoanApplicationStatus::AgreementSigned)->count(),
                'caption' => 'Signed, ready to verify',
                'format' => 'number',
                'href' => '/disbursements',
            ];
        }

        if ($user->hasPermission(Permissions::COMMISSIONS_VIEW)) {
            $tiles[] = [
                'key' => 'commissions_pending',
                'label' => 'Commission owing',
                'value' => (float) Commission::where('status', CommissionStatus::Pending)->sum('amount'),
                'caption' => 'Calculated, not yet paid',
                'format' => 'money',
                'href' => '/commissions',
            ];
        }

        return $tiles;
    }
}
