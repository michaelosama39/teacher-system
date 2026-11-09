<?php

namespace App\Http\Controllers\Api;

use App\Actions\Assistants\CreateAssistantAction;
use App\Actions\Assistants\ResetAssistantPasswordAction;
use App\Actions\Assistants\SetAssistantActiveAction;
use App\Actions\Assistants\UpdateAssistantPermissionsAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistants\ResetAssistantPasswordRequest;
use App\Http\Requests\Assistants\StoreAssistantRequest;
use App\Http\Requests\Assistants\UpdateAssistantPermissionsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Teacher-only (route middleware "can:manage-assistants").
 */
class AssistantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $assistants = User::role(Role::Assistant)
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get();

        return UserResource::collection($assistants);
    }

    /**
     * Permission catalogue (value + Arabic label) for the UI.
     */
    public function permissions(): JsonResponse
    {
        return response()->json(['data' => Permission::options()]);
    }

    public function store(StoreAssistantRequest $request, CreateAssistantAction $createAssistant): JsonResponse
    {
        ['user' => $assistant, 'password' => $password] = $createAssistant->handle(
            $request->validated('name'),
            $request->validated('phone'),
            array_map(Permission::from(...), $request->validated('permissions')),
            $request->validated('password'),
        );

        return response()->json([
            'data' => UserResource::make($assistant->load(['roles', 'permissions'])),
            'credentials' => [
                'phone' => $assistant->phone,
                'password' => $password,
            ],
        ], 201);
    }

    public function updatePermissions(
        UpdateAssistantPermissionsRequest $request,
        User $assistant,
        UpdateAssistantPermissionsAction $updatePermissions,
    ): UserResource {
        $updatePermissions->handle($assistant, $request->permissions());

        return UserResource::make($assistant->load(['roles', 'permissions']));
    }

    public function resetPassword(
        ResetAssistantPasswordRequest $request,
        User $assistant,
        ResetAssistantPasswordAction $resetPassword,
    ): JsonResponse {
        $password = $resetPassword->handle($assistant, $request->validated('password'));

        return response()->json([
            'data' => UserResource::make($assistant->load(['roles', 'permissions'])),
            'credentials' => [
                'phone' => $assistant->phone,
                'password' => $password,
            ],
        ]);
    }

    public function deactivate(User $assistant, SetAssistantActiveAction $setActive): UserResource
    {
        return UserResource::make($setActive->handle($assistant, false)->load(['roles', 'permissions']));
    }

    public function activate(User $assistant, SetAssistantActiveAction $setActive): UserResource
    {
        return UserResource::make($setActive->handle($assistant, true)->load(['roles', 'permissions']));
    }
}
