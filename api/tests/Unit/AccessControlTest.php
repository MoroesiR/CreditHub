<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;

it('seeds every catalogued permission', function (): void {
    expect(Permission::query()->pluck('slug')->sort()->values()->all())
        ->toBe(collect(Permissions::all())->sort()->values()->all());
});

/**
 * An administrator oversees the book and hands out access, so the one thing
 * they must not also do is move money. Holding both the power to grant a
 * permission and the power to release funds leaves nothing for anyone else to
 * check, which is the whole point of splitting the roles.
 */
it('gives an administrator oversight of everything except releasing money', function (): void {
    $admin = User::factory()->withRole(Roles::ADMIN)->create();

    $withheld = [
        Permissions::DISBURSEMENTS_VERIFY,
        Permissions::DISBURSEMENTS_PAY,
        Permissions::COMMISSIONS_PAY,
    ];

    expect($admin->permissionSlugs())
        ->toEqualCanonicalizing(array_values(array_diff(Permissions::all(), $withheld)));

    foreach ($withheld as $permission) {
        expect($admin->hasPermission($permission))->toBeFalse();
    }
});

/**
 * Separation of duties. These two assertions are the reason the roles are
 * split at all: the person who says yes to a loan must not be the person who
 * moves the money.
 */
it('does not let a credit manager release funds', function (): void {
    $manager = User::factory()->withRole(Roles::CREDIT_MANAGER)->create();

    expect($manager->hasPermission(Permissions::APPLICATIONS_DECIDE))->toBeTrue()
        ->and($manager->hasPermission(Permissions::DISBURSEMENTS_PAY))->toBeFalse()
        ->and($manager->hasPermission(Permissions::COMMISSIONS_PAY))->toBeFalse();
});

it('does not let a disbursement officer approve a loan', function (): void {
    $officer = User::factory()->withRole(Roles::DISBURSEMENT_OFFICER)->create();

    expect($officer->hasPermission(Permissions::DISBURSEMENTS_PAY))->toBeTrue()
        ->and($officer->hasPermission(Permissions::APPLICATIONS_DECIDE))->toBeFalse();
});

it('gives an auditor sight of everything and control of nothing', function (): void {
    $auditor = User::factory()->withRole(Roles::AUDITOR)->create();

    foreach ($auditor->permissionSlugs() as $permission) {
        expect($permission)->toMatch('/(\.view$|^reports\.)/');
    }
});

it('unions the permissions of every role a person holds', function (): void {
    $user = User::factory()
        ->withRole(Roles::LOAN_OFFICER)
        ->withRole(Roles::DISBURSEMENT_OFFICER)
        ->create();

    expect($user->hasPermission(Permissions::CLIENTS_CREATE))->toBeTrue()
        ->and($user->hasPermission(Permissions::DISBURSEMENTS_PAY))->toBeTrue();
});

it('keeps the role catalogue and the seeded roles in step', function (): void {
    expect(Role::query()->pluck('slug')->sort()->values()->all())
        ->toBe(collect(array_keys(Roles::catalogue()))->sort()->values()->all());
});
