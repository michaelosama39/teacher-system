<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('requires authentication', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('returns the current user with children for a parent', function () {
    $parent = User::factory()->parent()->create();
    $children = Student::factory()->count(2)->create();
    $parent->children()->attach($children->pluck('id'), ['relation' => 'mother']);

    $this->getJson('/api/auth/me', authHeaders($parent))
        ->assertOk()
        ->assertJsonPath('roles', ['parent'])
        ->assertJsonCount(2, 'user.children')
        ->assertJsonPath('user.children.0.relation', 'mother');
});

it('logs out by revoking only the current token', function () {
    $user = User::factory()->create();
    $other = $user->createToken('tablet');

    $this->postJson('/api/auth/logout', [], authHeaders($user))->assertOk();

    expect($user->tokens()->pluck('id')->all())->toBe([$other->accessToken->id]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'logout', 'causer_id' => $user->id]);
});

it('cannot use a token after logout', function () {
    $user = User::factory()->create();
    $headers = authHeaders($user);

    $this->postJson('/api/auth/logout', [], $headers)->assertOk();
    forgetAuth();

    $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
});

it('blocks a deactivated user and revokes all tokens', function () {
    $user = User::factory()->create();
    $headers = authHeaders($user);
    $user->createToken('other');

    $user->update(['is_active' => false]);

    $this->getJson('/api/auth/me', $headers)
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_INACTIVE');

    expect($user->tokens()->count())->toBe(0);
});

it('blocks every route except me, logout and change-password while the password is temporary', function () {
    $teacher = User::factory()->teacher()->mustChangePassword()->create();
    $headers = authHeaders($teacher);

    $this->getJson('/api/assistants', $headers)
        ->assertForbidden()
        ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    $this->getJson('/api/auth/me', $headers)
        ->assertOk()
        ->assertJsonPath('must_change_password', true);
});

it('changes the password, clears the flag and revokes other tokens', function () {
    $user = User::factory()->teacher()->mustChangePassword()->create();
    $headers = authHeaders($user, 'phone');
    $otherHeaders = authHeaders($user, 'laptop');

    $this->postJson('/api/auth/change-password', [
        'current_password' => 'password',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('must_change_password', false);

    $user->refresh();
    expect(Hash::check('new-secret-123', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->tokens()->pluck('name')->all())->toBe(['phone']);

    forgetAuth();
    $this->getJson('/api/assistants', $headers)->assertOk();

    forgetAuth();
    $this->getJson('/api/auth/me', $otherHeaders)->assertUnauthorized();
});

it('validates the password change', function (array $payload, string $field) {
    $user = User::factory()->create();

    $this->postJson('/api/auth/change-password', $payload, authHeaders($user))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors($field);
})->with([
    'wrong current' => [['current_password' => 'nope', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'], 'current_password'],
    'too short' => [['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'not confirmed' => [['current_password' => 'password', 'password' => 'new-secret-123', 'password_confirmation' => 'other-123'], 'password'],
    'same as current' => [['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'], 'password'],
]);
