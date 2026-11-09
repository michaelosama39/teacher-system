<?php

namespace App\Http\Requests\Students;

use App\Enums\StudentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    /**
     * Pending students are activated through the approve endpoint, not here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(StudentStatus::class)->only([StudentStatus::Active, StudentStatus::Suspended])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
