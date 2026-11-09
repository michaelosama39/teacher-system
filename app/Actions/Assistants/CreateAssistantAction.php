<?php

namespace App\Actions\Assistants;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Support\Facades\DB;

class CreateAssistantAction
{
    /**
     * @param  list<Permission>  $permissions
     * @return array{user: User, password: string}
     */
    public function handle(string $name, string $phone, array $permissions, ?string $password = null): array
    {
        $password ??= TempPassword::generate();

        $assistant = DB::transaction(function () use ($name, $phone, $permissions, $password): User {
            $assistant = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $assistant->assignRole(Role::Assistant);
            $assistant->syncPermissions($permissions);

            activity('users')
                ->performedOn($assistant)
                ->event('assistant_created')
                ->withProperties(['permissions' => array_column($permissions, 'value')])
                ->log('assistant_created');

            return $assistant;
        });

        return ['user' => $assistant, 'password' => $password];
    }
}
