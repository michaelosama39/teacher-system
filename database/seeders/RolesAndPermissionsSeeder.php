<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        /*
         * The teacher bypasses checks via Gate::before, but also holds every
         * permission explicitly so data stays consistent for reports/UI.
         * Assistants get permissions per user, granted by the teacher.
         */
        RoleModel::findByName(Role::Teacher->value, 'web')->syncPermissions(Permission::values());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
