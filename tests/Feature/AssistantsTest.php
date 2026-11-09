<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->teacher = teacher();
    $this->headers = authHeaders($this->teacher);
});

it('lists assistants with their permissions', function () {
    assistant([Permission::AttendanceScan], ['name' => 'Ahmed']);
    User::factory()->parent()->create();

    $this->getJson('/api/assistants', $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ahmed')
        ->assertJsonPath('data.0.permissions', ['attendance.scan']);
});

it('returns the permission catalogue with arabic labels', function () {
    $this->getJson('/api/assistants/permissions', $this->headers)
        ->assertOk()
        ->assertJsonCount(count(Permission::cases()), 'data')
        ->assertJsonPath('data.0', ['value' => 'attendance.scan', 'label' => Permission::AttendanceScan->label()]);
});

it('creates an assistant with a temporary password shown once', function () {
    $response = $this->postJson('/api/assistants', [
        'name' => 'Sara',
        'phone' => '+20 112 345 6789',
        'permissions' => ['attendance.scan', 'students.view'],
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.phone', '01123456789')
        ->assertJsonPath('data.must_change_password', true)
        ->assertJsonPath('data.permissions', ['attendance.scan', 'students.view'])
        ->assertJsonPath('credentials.phone', '01123456789');

    $assistant = User::where('phone', '01123456789')->firstOrFail();

    expect($assistant->hasRole('assistant'))->toBeTrue()
        ->and(Hash::check($response->json('credentials.password'), $assistant->password))->toBeTrue();

    $this->getJson('/api/assistants', $this->headers)->assertJsonMissingPath('data.0.credentials');
});

it('validates assistant creation', function () {
    User::factory()->create(['phone' => '01001234567']);

    $this->postJson('/api/assistants', [
        'name' => '',
        'phone' => '0100 123 4567',
        'permissions' => ['not.a.permission'],
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'phone', 'permissions.0']);
});

it('updates assistant permissions and logs the change', function () {
    $assistant = assistant([Permission::AttendanceScan]);

    $this->putJson("/api/assistants/{$assistant->id}/permissions", [
        'permissions' => ['payments.collect'],
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.permissions', ['payments.collect']);

    expect($assistant->fresh()->hasPermissionTo('attendance.scan'))->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'permissions_updated',
        'subject_id' => $assistant->id,
        'causer_id' => $this->teacher->id,
    ]);
});

it('resets an assistant password and revokes tokens', function () {
    $assistant = assistant();
    $assistant->createToken('phone');

    $response = $this->postJson("/api/assistants/{$assistant->id}/reset-password", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.must_change_password', true);

    $assistant->refresh();
    expect(Hash::check($response->json('credentials.password'), $assistant->password))->toBeTrue()
        ->and($assistant->tokens()->count())->toBe(0);
});

it('deactivates an assistant, revoking tokens and blocking login', function () {
    $assistant = assistant([], ['phone' => '01001234567']);
    $assistantHeaders = authHeaders($assistant);

    $this->postJson("/api/assistants/{$assistant->id}/deactivate", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($assistant->tokens()->count())->toBe(0);

    forgetAuth();
    $this->getJson('/api/auth/me', $assistantHeaders)->assertUnauthorized();

    RateLimiter::clear('login');
    $this->postJson('/api/auth/login', ['identifier' => '01001234567', 'password' => 'password', 'device_name' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_INACTIVE');

    forgetAuth();
    $this->postJson("/api/assistants/{$assistant->id}/activate", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('only targets assistants', function () {
    $parent = User::factory()->parent()->create();

    $this->postJson("/api/assistants/{$parent->id}/deactivate", [], $this->headers)
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('forbids assistants from managing assistants, whatever their permissions', function () {
    $assistant = assistant(Permission::cases());

    forgetAuth();
    $this->getJson('/api/assistants', authHeaders($assistant))
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
