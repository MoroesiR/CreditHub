<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CommissionStatus;
use App\Enums\DisbursementStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\Commission;
use App\Models\Disbursement;
use App\Models\LoanApplication;
use App\Services\Repayments\LoanAccount;
use Illuminate\Support\Carbon;

/**
 * What the book looks like, in money rather than counts alone.
 *
 * Two figures are deliberately kept apart. Approved is what the lender has
 * committed to; disbursed is what has actually left the bank account. A loan
 * can be approved and never paid, so reporting the two as one number would
 * overstate what has been spent.
 */
final class PortfolioReport
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'pipeline' => $this->pipeline(),
            'cash_out' => $this->cashOut(),
            'commission' => $this->commission(),
            'repayments' => $this->repayments(),
            'monthly' => $this->monthly(),
        ];
    }

    /**
     * Every stage a file can sit at, with the money attached to it.
     *
     * @return array<int, array{status: string, label: string, count: int, total: float}>
     */
    private function pipeline(): array
    {
        $rows = LoanApplication::query()
            ->selectRaw('status, COUNT(*) as file_count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return array_map(
            static function (LoanApplicationStatus $status) use ($rows): array {
                $row = $rows->get($status->value);

                return [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'count' => (int) ($row->file_count ?? 0),
                    'total' => (float) ($row->total ?? 0),
                ];
            },
            LoanApplicationStatus::cases(),
        );
    }

    /**
     * Money that has actually left the business.
     *
     * @return array<string, mixed>
     */
    private function cashOut(): array
    {
        $loansPaid = Disbursement::where('status', DisbursementStatus::Paid);
        $commissionPaid = Commission::where('status', CommissionStatus::Paid);

        $loanTotal = (float) $loansPaid->clone()->sum('amount');
        $commissionTotal = (float) $commissionPaid->clone()->sum('amount');

        return [
            'loans_paid_count' => $loansPaid->clone()->count(),
            'loans_paid_total' => $loanTotal,
            'commission_paid_total' => $commissionTotal,
            'total' => round($loanTotal + $commissionTotal, 2),
            'awaiting_payout_count' => Disbursement::whereIn('status', [
                DisbursementStatus::Pending,
                DisbursementStatus::Verified,
            ])->count(),
            'awaiting_payout_total' => (float) Disbursement::whereIn('status', [
                DisbursementStatus::Pending,
                DisbursementStatus::Verified,
            ])->sum('amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commission(): array
    {
        return [
            'owing_count' => Commission::where('status', CommissionStatus::Pending)->count(),
            'owing_total' => (float) Commission::where('status', CommissionStatus::Pending)->sum('amount'),
            'paid_count' => Commission::where('status', CommissionStatus::Paid)->count(),
            'paid_total' => (float) Commission::where('status', CommissionStatus::Paid)->sum('amount'),
        ];
    }

    /**
     * How the book is being repaid.
     *
     * Collected is money in. Outstanding is what those loans still owe, and
     * arrears is the part of it that should already have been paid, which is
     * the only figure that says whether the book is healthy.
     *
     * @return array<string, mixed>
     */
    private function repayments(): array
    {
        $loans = LoanApplication::query()
            ->where('status', LoanApplicationStatus::Disbursed)
            ->with('disbursement')
            ->get();

        $accounts = new LoanAccount();

        $collected = 0.0;
        $outstanding = 0.0;
        $arrears = 0.0;
        $behind = 0;
        $settled = 0;

        foreach ($loans as $loan) {
            $account = $accounts->summarise($loan);

            $collected += $account['paid'];
            $outstanding += $account['balance'];
            $arrears += $account['arrears'];

            if ($account['is_in_arrears']) {
                $behind++;
            }

            if ($account['is_settled']) {
                $settled++;
            }
        }

        return [
            'live_loans' => $loans->count(),
            'collected' => round($collected, 2),
            'outstanding' => round($outstanding, 2),
            'arrears' => round($arrears, 2),
            'accounts_in_arrears' => $behind,
            'accounts_settled' => $settled,
        ];
    }

    /**
     * The last six months of payouts, oldest first.
     *
     * Months with no activity are filled in rather than skipped, so a quiet
     * month reads as a zero instead of vanishing from the series.
     *
     * @return array<int, array{month: string, label: string, count: int, total: float}>
     */
    private function monthly(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        $rows = Disbursement::query()
            ->where('status', DisbursementStatus::Paid)
            ->where('paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, COUNT(*) as payout_count, COALESCE(SUM(amount), 0) as total")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $cursor = $start->copy()->addMonths($offset);
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);

            $months[] = [
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'count' => (int) ($row->payout_count ?? 0),
                'total' => (float) ($row->total ?? 0),
            ];
        }

        return $months;
    }
}
