<?php

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('registration');
    config(['teacher.features.self_registration' => true]);
});

function registration(array $overrides = []): array
{
    return [
        'name' => 'Self Registered',
        'phone' => '01001111111',
        'parent_phone' => '01202222222',
        'parent_relation' => 'mother',
        ...$overrides,
    ];
}

it('is unavailable when the feature flag is off', function () {
    config(['teacher.features.self_registration' => false]);

    $this->postJson('/api/register', registration())
        ->assertNotFound()
        ->assertJsonPath('code', 'FEATURE_DISABLED');

    expect(Student::count())->toBe(0);
});

it('creates a pending student without any accounts', function () {
    $this->postJson('/api/register', registration())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissingPath('credentials');

    $student = Student::sole();

    expect($student->isPending())->toBeTrue()
        ->and($student->user_id)->toBeNull()
        ->and(User::count())->toBe(0)
        ->and($student->registration_data['parent_phone'])->toBe('01202222222');
});

it('validates registration', function () {
    $this->postJson('/api/register', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'phone', 'parent_phone']);
});

it('is rate limited', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/register', ['name' => ''])->assertUnprocessable();
    }

    $this->postJson('/api/register', registration())->assertTooManyRequests();
});

it('approves a pending student, creating accounts and linking an existing parent', function () {
    $parent = User::factory()->parent()->create(['phone' => '01202222222']);
    $sibling = Student::factory()->create();
    $parent->children()->attach($sibling);

    $this->postJson('/api/register', registration())->assertCreated();
    $student = Student::status(StudentStatus::Pending)->sole();

    $staff = assistant([Permission::StudentsApprove]);

    $this->postJson("/api/students/{$student->id}/approve", [], authHeaders($staff))
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(1, 'credentials')
        ->assertJsonPath('credentials.0.role', 'student')
        ->assertJsonPath('data.parents.0.relation', 'mother');

    $student->refresh();
    expect($student->registration_data)->toBeNull()
        ->and($student->user->phone)->toBe('01001111111')
        ->and($parent->children()->count())->toBe(2);

    $this->assertDatabaseHas('activity_log', ['subject_id' => $student->id, 'log_name' => 'students', 'causer_id' => $staff->id]);
});

it('rejects a pending student', function () {
    $student = Student::factory()->pending()->create();

    $this->postJson("/api/students/{$student->id}/reject", ['reason' => 'duplicate'], authHeaders(assistant([Permission::StudentsApprove])))
        ->assertOk();

    expect($student->fresh()->trashed())->toBeTrue();
    $this->assertDatabaseHas('activity_log', ['event' => 'rejected', 'subject_id' => $student->id]);
});

it('refuses to approve or reject a student that is not pending', function () {
    $student = Student::factory()->create();
    $headers = authHeaders(teacher());

    $this->postJson("/api/students/{$student->id}/approve", [], $headers)
        ->assertStatus(409)
        ->assertJsonPath('code', 'STUDENT_NOT_PENDING');

    $this->postJson("/api/students/{$student->id}/reject", [], $headers)->assertStatus(409);
});

it('requires students.approve', function () {
    $student = Student::factory()->pending()->create();
    $headers = authHeaders(assistant([Permission::StudentsCreate, Permission::StudentsEdit]));

    $this->postJson("/api/students/{$student->id}/approve", [], $headers)->assertForbidden();
    $this->postJson("/api/students/{$student->id}/reject", [], $headers)->assertForbidden();

    expect($student->fresh()->isPending())->toBeTrue();
});
