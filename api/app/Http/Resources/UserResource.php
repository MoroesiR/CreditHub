<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn (Role $role): array => [
                'slug' => $role->slug,
                'name' => $role->name,
            ])->values()),
            // Flattened so the SPA can gate UI without walking the role graph.
            'permissions' => $this->whenLoaded('roles', fn () => $this->permissionSlugs()),
        ];
    }
}
