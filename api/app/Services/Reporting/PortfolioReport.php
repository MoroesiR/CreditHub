<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CommissionStatus;
use App\Enums\DisbursementStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\Commission;
use App\Models\Disbursement;
use App\Models\LoanApplication;
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
