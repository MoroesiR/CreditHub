<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Recruiters\LinkClientRequest;
use App\Http\Requests\Api\V1\Recruiters\StoreRecruiterRequest;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\RecruiterResource;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Recruiter;
use App\Services\Clients\ClientRecruiterLinkService;
use App\Services\Recruiters\RecruiterRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class RecruiterController extends Controller
{
    public function __construct(
        private readonly RecruiterRegistrationService $registration,
        private readonly ClientRecruiterLinkService $links,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $recruiters = Recruiter::query()
            ->withCount('clients')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->search($request->string('search')->value()),
            )
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('is_active', true),
            )
            ->orderBy('last_name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return RecruiterResource::collection($recruiters);
    }

    public function store(StoreRecruiterRequest $request): JsonResponse
    {
        $recruiter = $this->registration->register(
            $request->validated(),
            $request->user(),
            $request->ip(),
        );

        return (new RecruiterResource($recruiter))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Recruiter $recruiter): RecruiterResource
    {
        return new RecruiterResource($recruiter->loadCount('clients'));
    }

    /**
     * The clients this recruiter is credited with introducing.
     */
    public function clients(Recruiter $recruiter): AnonymousResourceCollection
    {
        return ClientResource::collection(
            $recruiter->clients()->with('latestAffordability')->latest()->get(),
        );
    }

    /**
     * Clients captured as walk-ins - which is what a client looks like when
     * the recruiter who introduced them was not yet on file. Offered so a
     * newly registered recruiter can be credited with introductions they made
     * before they existed in the system.
     */
    public function unlinkedClients(Request $request): AnonymousResourceCollection
    {
        $clients = Client::query()
            ->whereNull('recruiter_id')
            ->with('latestAffordability')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->search($request->string('search')->value()),
            )
            ->latest()
            ->limit(50)
            ->get();

        return ClientResource::collection($clients);
    }

    public function linkClient(LinkClientRequest $request, Recruiter $recruiter): JsonResponse
    {
        $client = Client::findOrFail($request->integer('client_id'));

        try {
            $this->links->link($client, $recruiter, $request->user(), $request->ip());
        } catch (RuntimeException $exception) {
            // A client already attached, or an inactive recruiter: the request
            // is well formed but conflicts with the current state.
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json(['data' => new ClientResource($client)]);
    }

    public function auditTrail(Recruiter $recruiter): AnonymousResourceCollection
    {
        $events = AuditEvent::query()
            ->where('auditable_type', Recruiter::class)
            ->where('auditable_id', $recruiter->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return AuditEventResource::collection($events);
    }
}
