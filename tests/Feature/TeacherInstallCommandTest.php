<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the teacher account from config with a temporary password', function () {
    config(['teacher.name' => 'أ. محمد', 'teacher.phone' => '+20 100 123 4567']);

    $this->artisan('teacher:install')
        ->expectsOutputToContain('shown only once')
        ->assertSuccessful();

    $teacher = User::where('phone', '01001234567')->firstOrFail();

    expect($teacher->hasRole(Role::Teacher))->toBeTrue()
        ->and($teacher->name)->toBe('أ. محمد')
        ->and($teacher->must_change_password)->toBeTrue()
        ->and($teacher->is_active)->toBeTrue();
});

it('uses the given --password', function () {
    config(['teacher.phone' => '01001234567']);

    $this->artisan('teacher:install', ['--password' => 'secret-pass-1'])->assertSuccessful();

    $teacher = User::where('phone', '01001234567')->firstOrFail();

    expect(Hash::check('secret-pass-1', $teacher->password))->toBeTrue()
        ->and($teacher->must_change_password)->toBeFalse();
});

it('refuses when a teacher already exists', function () {
    teacher();
    config(['teacher.phone' => '01001234567']);

    $this->artisan('teacher:install')->assertFailed();

    expect(User::role(Role::Teacher)->count())->toBe(1);
});

it('refuses an invalid phone', function () {
    config(['teacher.phone' => '12345']);

    $this->artisan('teacher:install')->assertFailed();

    expect(User::count())->toBe(0);
});

it('refuses a phone that is already used', function () {
    User::factory()->parent()->create(['phone' => '01001234567']);
    config(['teacher.phone' => '01001234567']);

    $this->artisan('teacher:install')->assertFailed();

    expect(User::role(Role::Teacher)->exists())->toBeFalse();
});

it('asks for the phone and name when TEACHER_PHONE is empty', function () {
    config(['teacher.phone' => null, 'teacher.name' => 'Teacher Name']);

    $this->artisan('teacher:install')
        ->expectsQuestion('Teacher mobile number (e.g. 01012345678)', '+20 101 234 5678')
        ->expectsQuestion('Teacher name', 'Mr. Mohamed')
        ->assertSuccessful();

    expect(User::role(Role::Teacher)->sole())
        ->phone->toBe('01012345678')
        ->name->toBe('Mr. Mohamed');
});

it('accepts --phone and --name options', function () {
    config(['teacher.phone' => null]);

    $this->artisan('teacher:install', ['--phone' => '01112345678', '--name' => 'Mr. Ali'])->assertSuccessful();

    expect(User::role(Role::Teacher)->sole())
        ->phone->toBe('01112345678')
        ->name->toBe('Mr. Ali');
});

it('fails with a clear message when the phone is empty and not interactive', function () {
    config(['teacher.phone' => null]);

    $this->artisan('teacher:install', ['--no-interaction' => true])
        ->expectsOutputToContain('TEACHER_PHONE is empty')
        ->assertFailed();
});
