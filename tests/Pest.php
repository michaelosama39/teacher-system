<?php

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function teacher(array $attributes = []): User
{
    return User::factory()->teacher()->create($attributes);
}

/**
 * @param  list<Permission>  $permissions
 */
function assistant(array $permissions = [], array $attributes = []): User
{
    return User::factory()->assistant($permissions)->create($attributes);
}

/**
 * Create a real Sanctum token and return the Authorization header.
 *
 * @return array{Authorization: string}
 */
function authHeaders(User $user, string $device = 'tests'): array
{
    return ['Authorization' => 'Bearer '.$user->createToken($device)->plainTextToken];
}

/**
 * The auth manager caches the resolved user between requests in a single test;
 * forget it so the next request re-authenticates from its token.
 */
function forgetAuth(): void
{
    app('auth')->forgetGuards();
}
