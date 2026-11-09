<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ChangePasswordAction
{
    /**
     * Set the new password, clear the forced-change flag and sign out every other device.
     */
    public function handle(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
            ])->save();

            $current = $user->currentAccessToken();

            $user->revokeTokens($current instanceof PersonalAccessToken ? $current->getKey() : null);

            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event('password_changed')
                ->log('password_changed');
        });
    }
}
