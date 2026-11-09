<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Phone;
use App\Support\TempPassword;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('teacher:install
    {--phone= : Teacher mobile (defaults to TEACHER_PHONE)}
    {--name= : Teacher name (defaults to TEACHER_NAME)}
    {--password= : Use this password instead of generating a temporary one}')]
#[Description('Seed roles/permissions and create the teacher (owner) account from .env')]
class TeacherInstall extends Command
{
    public function handle(): int
    {
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        if (User::withTrashed()->role(Role::Teacher)->exists()) {
            $this->error('A teacher account already exists. Installation aborted.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: config('teacher.name')));
        $rawPhone = trim((string) ($this->option('phone') ?: config('teacher.phone')));

        if ($rawPhone === '' && $this->input->isInteractive()) {
            $this->warn('TEACHER_PHONE is not set in .env.');
            $rawPhone = trim((string) $this->ask('Teacher mobile number (e.g. 01012345678)'));
            $name = trim((string) $this->ask('Teacher name', $name));
        }

        $phone = Phone::normalizeOrNull($rawPhone);

        if ($phone === null) {
            $this->error($rawPhone === ''
                ? 'TEACHER_PHONE is empty. Set it in .env (e.g. TEACHER_PHONE=01012345678) or pass --phone=01012345678.'
                : "\"{$rawPhone}\" is not a valid Egyptian mobile number (010/011/012/015 + 8 digits).");

            return self::FAILURE;
        }

        if ($name === '') {
            $this->error('The teacher name is empty. Set TEACHER_NAME in .env or pass --name.');

            return self::FAILURE;
        }

        if (User::withTrashed()->where('phone', $phone)->exists()) {
            $this->error("The phone {$phone} is already used by another account.");

            return self::FAILURE;
        }

        $givenPassword = $this->option('password');

        if ($givenPassword !== null && strlen($givenPassword) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $password = $givenPassword ?? TempPassword::generate();

        DB::transaction(function () use ($name, $phone, $password, $givenPassword): void {
            $teacher = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => $givenPassword === null,
            ]);

            $teacher->assignRole(Role::Teacher);
        });

        $this->info('Teacher account created. These credentials are shown only once:');
        $this->table(['Name', 'Phone', 'Password'], [[$name, $phone, $password]]);

        if ($givenPassword === null) {
            $this->warn('This is a temporary password; it must be changed on first login.');
        }

        return self::SUCCESS;
    }
}
