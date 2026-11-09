<?php

namespace App\Http\Requests\Assistants;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssistantPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-assistants');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ];
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return array_map(Permission::from(...), $this->validated('permissions'));
    }
}
