<?php

namespace App\Http\Requests\Assistants;

use App\Enums\Permission;
use App\Http\Requests\Concerns\NormalizesPhones;
use App\Rules\EgyptianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistantRequest extends FormRequest
{
    use NormalizesPhones;

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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new EgyptianPhone, Rule::unique('users', 'phone')],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ];
    }

    protected function phoneFields(): array
    {
        return ['phone'];
    }
}
