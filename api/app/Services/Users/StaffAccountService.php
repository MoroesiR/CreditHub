<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creating and maintaining staff accounts.
 *
 * Two rules exist purely to stop an administrator locking the business out of
 * its own system: nobody may deactivate their own account, and the last active
 * administrator may not have that role taken away. Both are easy to do by
 * accident and impossible to undo from inside the application.
 */
final class StaffAccountService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $roleSlugs
     */
    public function create(
        array $data,
        array $roleSlugs,
        User $createdBy,
        ?string $ipAddress = null,
    ): User {
        return DB::transaction(function () use ($data, $roleSlugs, $createdBy, $ipAddress): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                // Hashed by the model cast, never stored as given.
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->roles()->sync($this->roleIds($roleSlugs));

            $this->audit->record(
                action: 'user.created',
                subject: $user,
                summary: sprintf(
                    'Created the account %s for %s as %s.',
                    $user->email,
                    $user->fullName(),
                    implode(', ', $roleSlugs),
                ),
                actor: $createdBy,
                metadata: ['roles' => $roleSlugs],
                ipAddress: $ipAddress,
            );

            return $user->load('roles');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $roleSlugs
     */
    public function update(
        User $user,
        array $data,
        ?array $roleSlugs,
        User $updatedBy,
        ?string $ipAddress = null,
    ): User {
        $deactivating = array_key_exists('is_active', $data) && $data['is_active'] === false;

        if ($deactivating && $user->is($updatedBy)) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        if ($roleSlugs !== null || $deactivating) {
            $this->guardLastAdministrator($user, $roleSlugs, $deactivating);
        }

        return DB::transaction(function () use ($user, $data, $roleSlugs, $updatedBy, $ipAddress): User {
            $before = $user->roles->pluck('slug')->sort()->values()->all();

            $user->update(array_filter(
                [
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'password' => $data['password'] ?? null,
                ],
                static fn (mixed $value): bool => $value !== null,
            ));

            if (array_key_exists('is_active', $data)) {
                $user->update(['is_active' => $data['is_active']]);
            }

            if ($roleSlugs !== null) {
                $user->roles()->sync($this->roleIds($roleSlugs));
            }

            $user->refresh()->load('roles');
            $after = $user->roles->pluck('slug')->sort()->values()->all();

            $this->audit->record(
                action: 'user.updated',
                subject: $user,
                summary: $this->describe($user, $data, $before, $after),
                actor: $updatedBy,
                metadata: ['roles_before' => $before, 'roles_after' => $after],
                ipAddress: $ipAddress,
            );

            return $user;
        });
    }

    /**
     * @param  array<int, string>|null  $roleSlugs
     */
    private function guardLastAdministrator(User $user, ?array $roleSlugs, bool $deactivating): void
    {
        $losesAdmin = $roleSlugs !== null && ! in_array('admin', $roleSlugs, strict: true);

        if (! $losesAdmin && ! $deactivating) {
            return;
        }

        if (! $user->hasRole('admin')) {
            return;
        }

        $remaining = User::query()
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
            ->count();

        if ($remaining === 0) {
            throw new RuntimeException(
                'This is the last active administrator. Give another account the role first.'
            );
        }
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, int>
     */
    private function roleIds(array $slugs): array
    {
        $ids = Role::whereIn('slug', $slugs)->pluck('id')->all();

        if (count($ids) !== count($slugs)) {
            throw new RuntimeException('One of those roles does not exist.');
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    private function describe(User $user, array $data, array $before, array $after): string
    {
        $changes = [];

        if ($before !== $after) {
            $changes[] = 'roles now '.(implode(', ', $after) ?: 'none');
        }

        if (array_key_exists('is_active', $data)) {
            $changes[] = $data['is_active'] ? 'reactivated' : 'deactivated';
        }

        if (! empty($data['password'])) {
            $changes[] = 'password reset';
        }

        if ($changes === []) {
            $changes[] = 'details amended';
        }

        return sprintf('Updated %s: %s.', $user->email, implode('; ', $changes));
    }
}
