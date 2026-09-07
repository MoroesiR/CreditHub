<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clients\IndexClientRequest;
use App\Http\Requests\Api\V1\Clients\StoreClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\Clients\BorrowingEligibility;
use App\Services\Clients\ClientProfile;
use App\Services\Clients\ClientRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ClientController extends Controller
{
    public function __construct(
        private readonly ClientRegistrationService $registration,
        private readonly ClientProfile $profile,
    ) {}

    public function index(IndexClientRequest $request): AnonymousResourceCollection
    {
        $clients = Client::query()
            ->with(['recruiter', 'latestAffordability'])
            ->when(
                $request->filled('search'),
                fn ($query) => $query->search($request->string('search')->value()),
            )
            ->when(
                $request->filled('recruiter_id'),
                fn ($query) => $query->where('recruiter_id', $request->integer('recruiter_id')),
            )
            ->orderBy(
                $request->string('sort', 'created_at')->value(),
                $request->string('direction', 'desc')->value(),
            )
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->registration->register(
            client: $request->safe()->except(['affordability', 'new_recruiter']),
            affordability: $request->validated('affordability'),
            registeredBy: $request->user(),
            newRecruiter: $request->validated('new_recruiter'),
            ipAddress: $request->ip(),
        );

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Whether this client may take a loan, and if not, what is in the way.
     *
     * Its own route rather than a field on the client, because answering it
     * means reading every loan's schedule and receipts. Doing that for each
     * row of a search would cost far more than the answer is worth on a list
     * nobody is borrowing from.
     */
    public function borrowing(Client $client, BorrowingEligibility $eligibility): JsonResponse
    {
        return response()->json(['data' => $eligibility->check($client)]);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource(
            $client->load(['recruiter', 'latestAffordability', 'registeredBy']),
        );
    }

    /**
     * The whole file on one borrower: who they are, every application, every
     * receipt, and every document held against them.
     */
    public function profile(Client $client): JsonResponse
    {
        return response()->json([
            'data' => [
                'client' => new ClientResource(
                    $client->load(['recruiter', 'latestAffordability', 'registeredBy']),
                ),
                ...$this->profile->build($client),
            ],
        ]);
    }
}
