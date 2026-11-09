<?php

namespace App\Actions\Assistants;

use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Support\Facades\DB;

class ResetAssistantPasswordAction
{
    /**
     * Set a temporary password, force a change on next login and sign out all devices.
     *
     * @return string The new temporary password (show it once).
     */
    public function handle(User $assistant, ?string $password = null): string
    {
        $password ??= TempPassword::generate();

        DB::transaction(function () use ($assistant, $password): void {
            $assistant->forceFill([
                'password' => $password,
                'must_change_password' => true,
            ])->save();

            $assistant->revokeTokens();

            activity('users')
                ->performedOn($assistant)
                ->event('password_reset')
                ->log('password_reset');
        });

        return $password;
    }
}
