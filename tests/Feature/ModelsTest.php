<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use App\Rules\EgyptianPhone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('seeds roles and permissions idempotently', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::cases()))
        ->and(RoleModel::count())->toBe(count(Role::cases()));
});

it('has an arabic label for every permission', function () {
    foreach (Permission::cases() as $permission) {
        expect($permission->label())->not->toBeEmpty();
    }
});

it('generates the student code from the id after insert', function () {
    config(['teacher.student_code_prefix' => 'MATH']);

    $student = Student::factory()->create();

    expect($student->fresh()->code)->toBe('MATH-'.(1000 + $student->id));
});

it('generates a hidden unique qr token and can regenerate it', function () {
    $student = Student::factory()->create();
    $original = $student->qr_token;

    expect($original)->toHaveLength(48)
        ->and($student->toArray())->not->toHaveKey('qr_token');

    $new = $student->regenerateQrToken();

    expect($new)->toHaveLength(48)->not->toBe($original)
        ->and($student->fresh()->qr_token)->toBe($new);
});

it('normalizes the phone when saving a user', function () {
    $user = User::factory()->create(['phone' => '+20 100 123 4567']);

    expect($user->fresh()->phone)->toBe('01001234567');
});

it('links users, students and parents', function () {
    $parent = User::factory()->parent()->create();
    $first = Student::factory()->withAccount()->create();
    $second = Student::factory()->create();

    $parent->children()->attach([$first->id => ['relation' => 'father'], $second->id => ['relation' => 'father']]);

    expect($parent->children)->toHaveCount(2)
        ->and($first->parents->first()->is($parent))->toBeTrue()
        ->and($first->user->student->is($first))->toBeTrue()
        ->and($parent->isParentOf($second))->toBeTrue();
});

it('logs user changes in the activity log', function () {
    $user = User::factory()->create();
    $user->update(['is_active' => false]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'users',
        'subject_id' => $user->id,
        'event' => 'updated',
    ]);
});

it('validates with the EgyptianPhone rule', function () {
    expect(Validator::make(['phone' => '+20 100 123 4567'], ['phone' => [new EgyptianPhone]])->passes())->toBeTrue()
        ->and(Validator::make(['phone' => '12345'], ['phone' => [new EgyptianPhone]])->passes())->toBeFalse();
});
