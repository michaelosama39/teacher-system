<?php

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('login'));

function login(array $overrides = []): array
{
    return [
        'identifier' => '01001234567',
        'password' => 'password',
        'device_name' => 'android',
        ...$overrides,
    ];
}

it('logs in by phone and returns token, user, roles and permissions', function () {
    $assistant = assistant([Permission::StudentsView], ['phone' => '01001234567']);

    $response = $this->postJson('/api/auth/login', login())
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'phone'], 'roles', 'permissions', 'must_change_password'])
        ->assertJsonPath('roles', ['assistant'])
        ->assertJsonPath('permissions', ['students.view'])
        ->assertJsonPath('must_change_password', false);

    expect($assistant->tokens()->count())->toBe(1)
        ->and($assistant->fresh()->last_login_at)->not->toBeNull()
        ->and($response->json('token'))->toBeString();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'login', 'causer_id' => $assistant->id]);
});

it('accepts any phone format as identifier', function (string $identifier) {
    User::factory()->parent()->create(['phone' => '01001234567']);

    $this->postJson('/api/auth/login', login(['identifier' => $identifier]))->assertOk();
})->with(['+201001234567', '00201001234567', '1001234567', '010 0123 4567', '٠١٠٠١٢٣٤٥٦٧']);

it('logs in by student code (case insensitive)', function () {
    $student = Student::factory()->withAccount()->create();

    $this->postJson('/api/auth/login', login(['identifier' => strtolower($student->fresh()->code)]))
        ->assertOk()
        ->assertJsonPath('roles', ['student'])
        ->assertJsonPath('user.student.id', $student->id);
});

it('gives the teacher every permission', function () {
    teacher(['phone' => '01001234567']);

    $this->postJson('/api/auth/login', login())
        ->assertOk()
        ->assertJsonPath('permissions', Permission::values());
});

it('rejects wrong credentials with the standard error shape', function () {
    User::factory()->create(['phone' => '01001234567']);

    $this->postJson('/api/auth/login', login(['password' => 'wrong-password']))
        ->assertUnprocessable()
        ->assertExactJsonStructure(['message', 'code', 'errors' => ['identifier']])
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');

    $this->postJson('/api/auth/login', login(['identifier' => 'ST-9999']))
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('validates the request', function () {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors(['identifier', 'password', 'device_name']);
});

it('rejects inactive users', function () {
    $user = User::factory()->inactive()->create(['phone' => '01001234567']);

    $this->postJson('/api/auth/login', login())
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_INACTIVE');

    expect($user->tokens()->count())->toBe(0);
});

it('rate limits login to 5 per minute per identifier and ip', function () {
    User::factory()->create(['phone' => '01001234567']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', login(['password' => 'wrong']))->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', login())
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');

    // Same phone written differently is the same identifier.
    $this->postJson('/api/auth/login', login(['identifier' => '+201001234567']))->assertTooManyRequests();

    // A different identifier from the same IP is not blocked.
    User::factory()->create(['phone' => '01101234567']);
    $this->postJson('/api/auth/login', login(['identifier' => '01101234567']))->assertOk();
});
