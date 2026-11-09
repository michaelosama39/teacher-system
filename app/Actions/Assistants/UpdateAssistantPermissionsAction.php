<?php

namespace App\Actions\Assistants;

use App\Enums\Permission;
use App\Models\User;

class UpdateAssistantPermissionsAction
{
    /**
     * @param  list<Permission>  $permissions
     */
    public function handle(User $assistant, array $permissions): User
    {
        $old = $assistant->getPermissionNames()->sort()->values()->all();

        $assistant->syncPermissions($permissions);

        $new = $assistant->getPermissionNames()->sort()->values()->all();

        activity('users')
            ->performedOn($assistant)
            ->event('permissions_updated')
            ->withProperties(['old' => $old, 'new' => $new])
            ->log('permissions_updated');

        return $assistant;
    }
}
