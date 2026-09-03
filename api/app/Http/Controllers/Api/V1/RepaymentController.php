<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanApplicationStatus;
use App\Enums\RepaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Repayments\StoreRepaymentRequest;
use App\Http\Resources\LoanRepaymentResource;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Services\Repayments\LoanAccount;
use App\Services\Repayments\RepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class RepaymentController extends Controller
{
    public function __construct(
        private readonly RepaymentService $repayments,
        private readonly LoanAccount $accounts,
    ) {}

    /**
     * Every loan that has money out against it, with where each account stands.
     */
    public function loans(Request $request): JsonResponse
    {
        $loans = LoanApplication::query()
            ->where('status', LoanApplicationStatus::Disbursed)
            ->with(['client', 'disbursement'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->value().'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('application_number', 'like', $term)
                        ->orWhereHas('client', function ($query) use ($term): void {
                            $query->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('client_number', 'like', $term)
                                ->orWhere('id_number', 'like', $term);
                        });
                });
            })
            ->orderBy('application_number')
            ->get();

        $rows = $loans->map(function (LoanApplication $loan): array {
            $account = $this->accounts->summarise($loan);

            return [
                'id' => $loan->id,
                'application_number' => $loan->application_number,
                'client_name' => $loan->client?->fullName(),
                'client_number' => $loan->client?->client_number,
                'amount' => $loan->amount,
                'monthly_instalment' => $loan->monthly_instalment,
                'term_months' => $loan->term_months,
                'disbursed_on' => $loan->disbursement?->paid_at?->toDateString(),
                'account' => $account,
            ];
        });

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'total_outstanding' => round($rows->sum(fn (array $row): float => $row['account']['balance']), 2),
                'total_arrears' => round($rows->sum(fn (array $row): float => $row['account']['arrears']), 2),
                'accounts_in_arrears' => $rows->filter(fn (array $row): bool => $row['account']['is_in_arrears'])->count(),
                'methods' => array_map(
                    static fn (string $value): array => [
                        'value' => $value,
                        'label' => RepaymentMethod::from($value)->label(),
                    ],
                    RepaymentMethod::selectable(),
                ),
            ],
        ]);
    }

    public function index(LoanApplication $application): JsonResponse
    {
        return response()->json([
            'data' => LoanRepaymentResource::collection(
                $application->repayments()->with('recordedBy')->get(),
            ),
            'meta' => ['account' => $this->accounts->summarise($application)],
        ]);
    }

    public function store(StoreRepaymentRequest $request, LoanApplication $application): JsonResponse
    {
        try {
            $repayment = $this->repayments->record(
                application: $application,
                data: [
                    'amount' => (float) $request->validated('amount'),
                    'received_on' => $request->validated('received_on'),
                    'method' => $request->validated('method'),
                    'reference' => $request->validated('reference'),
                    'note' => $request->validated('note'),
                ],
                recordedBy: $request->user(),
                ipAddress: $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new LoanRepaymentResource($repayment->load('recordedBy')),
            'meta' => ['account' => $this->accounts->summarise($application->fresh())],
        ], Response::HTTP_CREATED);
    }

    public function reverse(
        Request $request,
        LoanApplication $application,
        LoanRepayment $repayment,
    ): JsonResponse {
        abort_unless($repayment->loan_application_id === $application->id, Response::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reversal = $this->repayments->reverse(
                $repayment,
                $validated['reason'],
                $request->user(),
                $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new LoanRepaymentResource($reversal->load('recordedBy')),
            'meta' => ['account' => $this->accounts->summarise($application->fresh())],
        ]);
    }
}
