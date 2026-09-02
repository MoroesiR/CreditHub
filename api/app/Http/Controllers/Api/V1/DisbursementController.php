<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DisbursementResource;
use App\Models\Disbursement;
use App\Services\Disbursements\DisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class DisbursementController extends Controller
{
    public function __construct(
        private readonly DisbursementService $disbursements,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $disbursements = Disbursement::query()
            ->with(['loanApplication.client', 'loanApplication.recruiter', 'verifiedBy', 'paidBy'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
            // Oldest first: a payout queue is worked in the order people have
            // been waiting, not newest-first like a list of records.
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return DisbursementResource::collection($disbursements);
    }

    public function show(Disbursement $disbursement): DisbursementResource
    {
        return new DisbursementResource(
            $disbursement->load([
                'loanApplication.client',
                'loanApplication.recruiter',
                'loanApplication.agreement',
                'loanApplication.documents',
                'verifiedBy',
                'paidBy',
            ]),
        );
    }

    public function verify(Request $request, Disbursement $disbursement): JsonResponse
    {
        return $this->attempt(
            fn () => $this->disbursements->verify($disbursement, $request->user(), $request->ip()),
        );
    }

    public function hold(Request $request, Disbursement $disbursement): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->attempt(
            fn () => $this->disbursements->hold(
                $disbursement,
                $validated['reason'],
                $request->user(),
                $request->ip(),
            ),
        );
    }

    public function pay(Request $request, Disbursement $disbursement): JsonResponse
    {
        $validated = $request->validate([
            // The bank's own reference for the transfer. Without it a payment
            // in this system cannot be tied to one on a bank statement.
            'payment_reference' => ['required', 'string', 'max:100'],
        ]);

        return $this->attempt(
            fn () => $this->disbursements->pay(
                $disbursement,
                $validated['payment_reference'],
                $request->user(),
                $request->ip(),
            ),
        );
    }

    /**
     * Business rules refuse with a conflict, not a server error.
     *
     * @param  callable(): Disbursement  $action
     */
    private function attempt(callable $action): JsonResponse
    {
        try {
            $disbursement = $action();
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new DisbursementResource(
                $disbursement->load([
                    'loanApplication.client',
                    // Loaded so the response can carry the commission the
                    // payment just earned, if the client was introduced.
                    'loanApplication.commission.recruiter',
                    'verifiedBy',
                    'paidBy',
                ]),
            ),
        ]);
    }
}
