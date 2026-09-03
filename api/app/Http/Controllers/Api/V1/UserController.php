<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\StoreUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\StaffAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    public function __construct(
        private readonly StaffAccountService $accounts,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles.permissions')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->value().'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('job_title', 'like', $term);
                });
            })
            ->orderBy('first_name')
            ->get();

        return UserResource::collection($users);
    }

    /**
     * The roles an administrator may assign, with what each one carries.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get()->map(
            static fn (Role $role): array => [
                'slug' => $role->slug,
                'name' => $role->name,
                'description' => $role->description,
                'permission_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('slug')->values(),
            ],
        );

        return response()->json(['data' => $roles]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->accounts->create(
                $request->safe()->except(['roles']),
                $request->validated('roles'),
                $request->user(),
                $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return (new UserResource($user->load('roles.permissions')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $updated = $this->accounts->update(
                $user,
                $request->safe()->except(['roles']),
                $request->has('roles') ? $request->validated('roles') : null,
                $request->user(),
                $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => new UserResource($updated->load('roles.permissions')),
        ]);
    }
}
