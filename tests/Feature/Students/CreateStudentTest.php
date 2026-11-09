<?php

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->staff = assistant([Permission::StudentsCreate]);
    $this->headers = authHeaders($this->staff);
});

it('creates a student with own account and a new parent account', function () {
    $response = $this->postJson('/api/students', [
        'name' => 'Omar Ali',
        'phone' => '+201001111111',
        'parent_phone' => '01202222222',
        'parent_name' => 'Ali Hassan',
        'parent_relation' => 'father',
    ], $this->headers)->assertCreated();

    $student = Student::firstOrFail();

    $response
        ->assertJsonPath('data.code', $student->code)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.account.phone', '01001111111')
        ->assertJsonPath('data.parents.0.phone', '01202222222')
        ->assertJsonPath('data.parents.0.relation', 'father')
        ->assertJsonCount(2, 'credentials')
        ->assertJsonPath('credentials.0.role', 'student')
        ->assertJsonPath('credentials.0.code', $student->code)
        ->assertJsonPath('credentials.1.role', 'parent')
        ->assertJsonMissingPath('data.qr_token');

    $studentUser = User::where('phone', '01001111111')->firstOrFail();
    $parent = User::where('phone', '01202222222')->firstOrFail();

    expect($studentUser->hasRole('student'))->toBeTrue()
        ->and($studentUser->must_change_password)->toBeTrue()
        ->and($student->user_id)->toBe($studentUser->id)
        ->and($parent->hasRole('parent'))->toBeTrue()
        ->and($parent->name)->toBe('Ali Hassan')
        ->and(Hash::check($response->json('credentials.0.password'), $studentUser->password))->toBeTrue()
        ->and(Hash::check($response->json('credentials.1.password'), $parent->password))->toBeTrue();
});

it('creates no student account when the student has no phone', function () {
    $this->postJson('/api/students', [
        'name' => 'Little Kid',
        'parent_phone' => '01202222222',
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.account', null)
        ->assertJsonCount(1, 'credentials')
        ->assertJsonPath('credentials.0.role', 'parent');

    expect(Student::first()->user_id)->toBeNull()
        ->and(User::role('student')->count())->toBe(0);
});

it('links an existing parent with multiple children without new credentials', function () {
    $this->postJson('/api/students', ['name' => 'First Child', 'parent_phone' => '01202222222'], $this->headers)
        ->assertCreated()
        ->assertJsonCount(1, 'credentials');

    $this->postJson('/api/students', ['name' => 'Second Child', 'parent_phone' => '+20 120 222 2222'], $this->headers)
        ->assertCreated()
        ->assertJsonCount(0, 'credentials');

    $parent = User::where('phone', '01202222222')->sole();

    expect($parent->children()->pluck('name')->sort()->values()->all())->toBe(['First Child', 'Second Child'])
        ->and(User::role('parent')->count())->toBe(1);
});

it('creates only the parent account when student phone equals parent phone', function () {
    $this->postJson('/api/students', [
        'name' => 'Shared Phone',
        'phone' => '01202222222',
        'parent_phone' => '+201202222222',
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.account', null)
        ->assertJsonCount(1, 'credentials')
        ->assertJsonPath('credentials.0.role', 'parent');

    expect(User::count())->toBe(2) // staff + parent
        ->and(Student::first()->user_id)->toBeNull();
});

it('rejects a student phone that is already used', function () {
    User::factory()->create(['phone' => '01001111111']);

    $this->postJson('/api/students', ['name' => 'X', 'phone' => '01001111111'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect(Student::count())->toBe(0);
});

it('rejects a parent phone that belongs to a non-parent account and rolls back', function () {
    assistant([], ['phone' => '01202222222']);

    $this->postJson('/api/students', [
        'name' => 'X',
        'phone' => '01001111111',
        'parent_phone' => '01202222222',
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors('parent_phone');

    expect(Student::count())->toBe(0)
        ->and(User::where('phone', '01001111111')->exists())->toBeFalse();
});

it('requires at least one phone and valid phones', function () {
    $this->postJson('/api/students', ['name' => 'X'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'parent_phone']);

    $this->postJson('/api/students', ['name' => 'X', 'parent_phone' => '0223456789'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_phone');
});

it('forbids staff without students.create', function () {
    $assistant = assistant([Permission::StudentsView]);

    forgetAuth();
    $this->postJson('/api/students', ['name' => 'X', 'parent_phone' => '01202222222'], authHeaders($assistant))
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('lets the teacher create students', function () {
    forgetAuth();
    $this->postJson('/api/students', ['name' => 'X', 'parent_phone' => '01202222222'], authHeaders(teacher()))
        ->assertCreated();
});

it('logs who created the student', function () {
    $this->postJson('/api/students', ['name' => 'X', 'parent_phone' => '01202222222'], $this->headers)->assertCreated();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'students',
        'event' => 'created',
        'subject_id' => Student::first()->id,
        'causer_id' => $this->staff->id,
    ]);
});
