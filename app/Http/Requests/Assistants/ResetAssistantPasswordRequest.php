<?php

namespace App\Http\Requests\Assistants;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetAssistantPasswordRequest extends FormRequest
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
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
