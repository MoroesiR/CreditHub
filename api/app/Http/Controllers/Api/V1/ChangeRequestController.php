<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeRequests\StoreChangeRequestRequest;
use App\Http\Resources\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Recruiter;
use App\Services\ChangeRequests\ChangeRequestService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class ChangeRequestController extends Controller
{
    public function __construct(
        private readonly ChangeRequestService $requests,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $requests = ChangeRequest::query()
            ->with(['subject', 'requestedBy', 'reviewedBy'])
            // Whoever cannot decide a request sees only their own. An officer
            // has no reason to read a colleague's correction to a file they
            // are not working on.
            ->unless(
                $user->hasPermission(Permissions::CHANGE_REQUESTS_REVIEW),
                fn ($query) => $query->where('requested_by', $user->id),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->value()),
            )
            ->latest()
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return ChangeRequestResource::collection($requests);
    }

    public function store(StoreChangeRequestRequest $request): JsonResponse
    {
        $subject = $request->validated('subject_kind') === 'client'
            ? Client::find($request->integer('subject_id'))
            : Recruiter::find($request->integer('subject_id'));

        if ($subject === null) {
            return response()->json(
                ['message' => 'That record could not be found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $created = $this->requests->submit(
                subject: $subject,
                changes: $request->validated('changes'),
                reason: $request->validated('reason'),
                requestedBy: $request->user(),
                ipAddress: $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return (new ChangeRequestResource(
            $created->load(['subject', 'requestedBy']),
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function approve(Request $request, ChangeRequest $changeRequest): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->decide(
            fn () => $this->requests->approve(
                $changeRequest,
                $request->user(),
                $validated['note'] ?? null,
                $request->ip(),
            ),
        );
    }

    public function reject(Request $request, ChangeRequest $changeRequest): JsonResponse
    {
        $validated = $request->validate([
            // A rejection has to say why: the officer who asked is entitled to
            // know what to correct before asking again.
            'note' => ['required', 'string', 'max:255'],
        ]);

        return $this->decide(
            fn () => $this->requests->reject(
                $changeRequest,
                $validated['note'],
                $request->user(),
                $request->ip(),
            ),
        );
    }

    /**
     * @param  callable(): ChangeRequest  $action
     */
    private function decide(callable $action): JsonResponse
    {
        try {
            $decided = $action();
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new ChangeRequestResource(
                $decided->load(['subject', 'requestedBy', 'reviewedBy']),
            ),
        ]);
    }
}
