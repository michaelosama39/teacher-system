<?php

namespace App\Actions\Assistants;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetAssistantActiveAction
{
    /**
     * Activate or deactivate an assistant. Deactivation revokes every token.
     */
    public function handle(User $assistant, bool $active): User
    {
        DB::transaction(function () use ($assistant, $active): void {
            $assistant->update(['is_active' => $active]);

            if (! $active) {
                $assistant->revokeTokens();
            }
        });

        return $assistant;
    }
}
