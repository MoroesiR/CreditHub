<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Idempotent: safe to re-run after adding a permission to the catalogue.
 * Existing role assignments are replaced with the catalogue's definition, so
 * the catalogue always wins over drift in the database.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::catalogue() as $slug => $definition) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'group' => $definition['group']],
            );
        }

        $permissionIds = Permission::query()->pluck('id', 'slug');

        foreach (Roles::catalogue() as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'description' => $definition['description']],
            );

            $role->permissions()->sync(
                collect($definition['permissions'])
                    ->map(fn (string $permission) => $permissionIds[$permission])
                    ->all(),
            );
        }
    }
}
