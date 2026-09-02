<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Applications\DecideLoanApplicationRequest;
use App\Http\Requests\Api\V1\Applications\StoreLoanApplicationRequest;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\LoanApplicationResource;
use App\Models\ApplicationDocument;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Services\Loans\ApplicationDocumentStore;
use App\Services\Loans\InstalmentCalculator;
use App\Services\Loans\LoanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanApplicationService $applications,
        private readonly InstalmentCalculator $pricing,
        private readonly ApplicationDocumentStore $documents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = LoanApplication::query()
            ->with(['client', 'recruiter'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
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
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return LoanApplicationResource::collection($applications);
    }

    public function store(StoreLoanApplicationRequest $request): JsonResponse
    {
        $client = Client::with('latestAffordability')->findOrFail($request->integer('client_id'));

        try {
            $application = $this->applications->submit(
                client: $client,
                data: [
                    'amount' => (float) $request->validated('amount'),
                    'term_months' => (int) $request->validated('term_months'),
                    'purpose' => $request->validated('purpose'),
                ],
                submittedBy: $request->user(),
                ipAddress: $request->ip(),
                documents: $request->file('documents', []),
            );
        } catch (RuntimeException $exception) {
            // Well-formed, but refused by a business rule - unaffordable, or
            // no assessment on file.
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return (new LoanApplicationResource($application))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(LoanApplication $application): LoanApplicationResource
    {
        return new LoanApplicationResource(
            $application->load([
                'client.recruiter', 'recruiter', 'submittedBy', 'decidedBy', 'documents',
            ]),
        );
    }

    /**
     * Streams a supporting document.
     *
     * The file is never reachable by URL: it lives on the private disk and is
     * only handed over here, after the route's permission check, and only when
     * it belongs to the application named in the path.
     */
    public function downloadDocument(
        LoanApplication $application,
        ApplicationDocument $document,
    ): StreamedResponse {
        abort_unless($document->loan_application_id === $application->id, Response::HTTP_NOT_FOUND);
        abort_unless($this->documents->exists($document), Response::HTTP_NOT_FOUND);

        return Storage::disk($this->documents->disk())->download(
            $document->path,
            $document->original_name,
        );
    }

    public function decide(
        DecideLoanApplicationRequest $request,
        LoanApplication $application,
    ): JsonResponse {
        try {
            $decided = $this->applications->decide(
                application: $application,
                approved: $request->boolean('approved'),
                declineReason: $request->validated('decline_reason'),
                decidedBy: $request->user(),
                ipAddress: $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new LoanApplicationResource(
                $decided->load(['submittedBy', 'decidedBy']),
            ),
        ]);
    }

    /**
     * Prices a loan without capturing one, so the officer can see the
     * instalment before committing the client to anything.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:'.InstalmentCalculator::MAX_TERM_MONTHS],
        ]);

        return response()->json([
            'data' => $this->pricing->quote(
                (float) $validated['amount'],
                (int) $validated['term_months'],
            ),
        ]);
    }

    public function auditTrail(LoanApplication $application): AnonymousResourceCollection
    {
        $events = AuditEvent::query()
            ->where('auditable_type', LoanApplication::class)
            ->where('auditable_id', $application->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return AuditEventResource::collection($events);
    }
}
