<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionResource;
use App\Models\Commission;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CommissionController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $commissions = Commission::query()
            ->with(['recruiter', 'loanApplication.client'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('recruiter_id'),
                fn ($query) => $query->where('recruiter_id', $request->integer('recruiter_id')),
            )
            ->orderBy('calculated_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return CommissionResource::collection($commissions);
    }

    /**
     * Releases a recruiter's commission.
     *
     * Separate from the loan payment on purpose: the client's money and the
     * recruiter's fee are two transfers, and a failure to pay one must never
     * silently mean the other was paid.
     */
    public function pay(Request $request, Commission $commission): JsonResponse
    {
        if ($commission->status !== CommissionStatus::Pending) {
            return response()->json(
                ['message' => "This commission is {$commission->status->label()} and cannot be paid again."],
                Response::HTTP_CONFLICT,
            );
        }

        $commission->load('recruiter', 'loanApplication');

        if ($commission->recruiter?->bank_account_number === null) {
            return response()->json(
                ['message' => 'This recruiter has no bank account on file, so the commission cannot be paid.'],
                Response::HTTP_CONFLICT,
            );
        }

        DB::transaction(function () use ($commission, $request): void {
            $commission->update([
                'status' => CommissionStatus::Paid,
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
            ]);

            $this->audit->record(
                action: 'commission.paid',
                subject: $commission->loanApplication,
                summary: sprintf(
                    'Paid commission of R%s to %s (%s) on %s.',
                    number_format($commission->amount, 2),
                    $commission->recruiter?->fullName() ?? 'the recruiter',
                    $commission->recruiter?->recruiter_number ?? '',
                    $commission->loanApplication->application_number,
                ),
                actor: $request->user(),
                metadata: ['commission_amount' => $commission->amount],
                ipAddress: $request->ip(),
            );
        });

        return response()->json([
            'data' => new CommissionResource($commission->fresh()->load('recruiter', 'loanApplication.client')),
        ]);
    }
}
